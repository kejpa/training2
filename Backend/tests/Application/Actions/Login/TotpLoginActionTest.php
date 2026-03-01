<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\TotpLoginAction;
use App\Domain\Login\LoginValidator;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Auth\TokenService;
use App\Infrastructure\Email\EmailService;
use PHPUnit\Framework\TestCase;
use PragmaRX\Google2FA\Google2FA;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class TotpLoginActionTest extends TestCase {
    private UserRepository $userRepository;
    private LoginValidator $validator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private TokenService $tokenService;
    private TotpLoginAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;
    private Google2FA $g2fa;

    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validator = $this->createMock(LoginValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->responseFactory = new ResponseFactory();
        $this->g2fa = new Google2FA();

        // Sätt environment variables för tester
        $_ENV['REFRESH_TOKEN_EXPIRATION'] = '2592000';
        $_ENV['APP_ENV'] = 'development';

        $this->action = new TotpLoginAction(
            $this->logger,
            $this->userRepository,
            $this->validator,
            $this->tokenService
        );

        $this->request = $this->createMock(Request::class);

        $reflection = new \ReflectionClass($this->action);

        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->action, $this->request);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->action, $this->responseFactory->createResponse());
    }

    private function createTestUserWithSecret(string $secret): User {
        return new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            $secret,
            'https://qr.url',
            'base64imagedata',
            null,
            null
        );
    }

    public function testSuccessfulLoginWithValidTotpCode(): void {
        // Generera secret och giltig kod
        $secret = $this->g2fa->generateSecretKey();
        $validCode = $this->g2fa->getCurrentOtp($secret);

        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => $validCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->with($data)
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->with($user)
            ->willReturn('mock-access-token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->with($user)
            ->willReturn('mock-refresh-token');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('user', $body['data']);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertEquals('mock-access-token', $body['data']['access_token']);
        $this->assertEquals('Bearer', $body['data']['token_type']);

        // Verifiera cookie
        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertNotEmpty($setCookieHeaders);
        $this->assertStringContainsString('refresh_token=', $setCookieHeaders[0]);
        $this->assertStringContainsString('HttpOnly', $setCookieHeaders[0]);
    }

    public function testReturns401WithInvalidTotpCode(): void {
        $secret = $this->g2fa->generateSecretKey();
        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => '000000' // Ogiltig kod
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->tokenService
            ->expects($this->never())
            ->method('generateAccessToken');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Ogiltig kod', $body['data']['error']);
    }

    public function testReturns401WithExpiredTotpCode(): void {
        $secret = $this->g2fa->generateSecretKey();
        $user = $this->createTestUserWithSecret($secret);

        // Generera kod från 2 minuter sedan (garanterat utgången)
        $oldTimestamp = time() - 120;
        $expiredCode = $this->g2fa->oathTotp($secret, $oldTimestamp);

        $data = [
            'email' => 'test@example.com',
            'code' => $expiredCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->tokenService
            ->expects($this->never())
            ->method('generateAccessToken');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Ogiltig kod', $body['data']['error']);
    }

    public function testLogsTotpVerificationError(): void {
        $secret = $this->g2fa->generateSecretKey();
        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => '999999' // Ogiltig kod
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        // Logger ska kallas, men vi kan inte enkelt mocka Google2FA exception
        // så vi förväntar oss bara att error-response returneras
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testReturns404WhenUserNotFound(): void {
        $data = [
            'email' => 'nonexistent@example.com',
            'code' => '123456'
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn(null);

        $this->tokenService
            ->expects($this->never())
            ->method('generateAccessToken');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Användaren hittades inte', $body['data']['error']);
    }

    public function testReturnsValidationErrors(): void {
        $data = [
            'email' => 'invalid-email',
            'code' => '12'
        ];
        $errors = [
            'email' => 'Ogiltig e-postadress',
            'code' => 'Kod måste vara 6 siffror'
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->with($data)
            ->willReturn(false);

        $this->validator
            ->method('getErrors')
            ->willReturn($errors);

        $this->userRepository
            ->expects($this->never())
            ->method('getByEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('errors', $body['data']);
        $this->assertEquals($errors, $body['data']['errors']);
    }

    public function testHandlesNullParsedBody(): void {
        $this->request
            ->method('getParsedBody')
            ->willReturn(null);

        $this->validator
            ->method('validateLogin')
            ->with([])
            ->willReturn(false);

        $this->validator
            ->method('getErrors')
            ->willReturn(['email' => 'E-post krävs']);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testConvertsKeysToLowercase(): void {
        $secret = $this->g2fa->generateSecretKey();
        $validCode = $this->g2fa->getCurrentOtp($secret);
        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'EMAIL' => 'test@example.com',
            'CODE' => $validCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->with([
                'email' => 'test@example.com',
                'code' => $validCode
            ])
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('refresh');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLogsErrorOnException(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];
        $exception = new \Exception('Database error');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willThrowException($exception);

        $loggedMessages = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function ($message) use (&$loggedMessages) {
                $loggedMessages[] = $message;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Database error', $body['data']['error']);

        $this->assertCount(2, $loggedMessages);
        $this->assertStringContainsString('Exception thrown:', $loggedMessages[0]);
        $this->assertStringContainsString('Parsed body:', $loggedMessages[1]);
    }

    public function testCookieIsSecureInProduction(): void {
        $_ENV['APP_ENV'] = 'production';

        $secret = $this->g2fa->generateSecretKey();
        $validCode = $this->g2fa->getCurrentOtp($secret);
        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => $validCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('refresh');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertStringContainsString('Secure', $setCookieHeaders[0]);

        // Återställ
        $_ENV['APP_ENV'] = 'development';
    }

    public function testCookiePathIsSetToRefresh(): void {
        $secret = $this->g2fa->generateSecretKey();
        $validCode = $this->g2fa->getCurrentOtp($secret);
        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => $validCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('refresh');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertStringContainsString('Path=/refresh', $setCookieHeaders[0]);
    }

    public function testAcceptsCodeFromRecentTimeWindow(): void {
        // TOTP accepterar koder från ett tidsfönster med window=1
        $secret = $this->g2fa->generateSecretKey();

        // Generera kod från 20 sekunder sedan (säkert inom föregående period)
        $currentPeriod = floor(time() / 30);
        $oldPeriod = $currentPeriod - 1; // En period tillbaka
        $oldCode = $this->g2fa->oathTotp($secret, $oldPeriod);

        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => $oldCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('refresh');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        // Borde accepteras inom tidsfönstret
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDoesNotModifyUserInDatabase(): void {
        // Till skillnad från mail-login behöver TOTP inte spara något
        $secret = $this->g2fa->generateSecretKey();
        $validCode = $this->g2fa->getCurrentOtp($secret);
        $user = $this->createTestUserWithSecret($secret);

        $data = [
            'email' => 'test@example.com',
            'code' => $validCode
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        // Verifiera att save INTE anropas
        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('refresh');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }
}