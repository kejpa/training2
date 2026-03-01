<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\MailLoginAction;
use App\Domain\Login\LoginValidator;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Auth\TokenService;
use App\Infrastructure\Email\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class MailLoginActionTest extends TestCase {
    private UserRepository $userRepository;
    private LoginValidator $validator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private TokenService $tokenService;
    private MailLoginAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validator = $this->createMock(LoginValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->responseFactory = new ResponseFactory();

        // Sätt environment variables för tester
        $_ENV['REFRESH_TOKEN_EXPIRATION'] = '2592000';
        $_ENV['APP_ENV'] = 'development';

        $this->action = new MailLoginAction(
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

    private function createTestUser(string $code = '123456'): User {
        return new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            $code,
            new \DateTimeImmutable('+1 hour')
        );
    }

    public function testSuccessfulLogin(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];
        $user = $this->createTestUser('123456');

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

        $capturedUser = null;
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

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

        // Verifiera att kod och expires nollställdes
        $this->assertNotNull($capturedUser);
        $this->assertNull($capturedUser->getCode());
        $this->assertNull($capturedUser->getExpires());

        // Verifiera cookie
        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertNotEmpty($setCookieHeaders);
        $this->assertStringContainsString('refresh_token=', $setCookieHeaders[0]);
        $this->assertStringContainsString('HttpOnly', $setCookieHeaders[0]);
    }

    public function testClearsCodeAndExpiresOnSuccessfulLogin(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];
        $user = $this->createTestUser('123456');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $capturedUser = null;
        $this->userRepository
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

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

        // Verifiera att kod och expires verkligen nollställdes
        $this->assertNotNull($capturedUser);
        $this->assertNull($capturedUser->getCode(), 'Code should be null after successful login');
        $this->assertNull($capturedUser->getExpires(), 'Expires should be null after successful login');
    }

    public function testLoginWithValidationErrors(): void {
        $data = [
            'email' => 'invalid-email',
            'code' => '123'
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

        $this->userRepository
            ->expects($this->never())
            ->method('save');

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

        $this->userRepository
            ->expects($this->never())
            ->method('save');

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

    public function testReturns401WithInvalidCode(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '999999'
        ];
        $user = $this->createTestUser('123456');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

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

    public function testReturns401WithExpiredCode(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];

        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            '123456',
            new \DateTimeImmutable('-1 hour') // Utgången
        );

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

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
        $this->assertEquals('Koden har gått ut', $body['data']['error']);
    }

    public function testReturns401WithNullExpires(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];

        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            '123456',
            null // Ingen expires
        );

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Koden har gått ut', $body['data']['error']);
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
        $data = [
            'EMAIL' => 'test@example.com',
            'CODE' => '123456'
        ];
        $user = $this->createTestUser('123456');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->with([
                'email' => 'test@example.com',
                'code' => '123456'
            ])
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->userRepository
            ->method('save');

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

    public function testSavesUserBeforeGeneratingTokens(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];
        $user = $this->createTestUser('123456');
        $callOrder = [];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->method('save')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'save';
            });

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'accessToken';
                return 'token';
            });

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'refreshToken';
                return 'refresh';
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        // Verifiera ordningen: save först, sedan tokens
        $this->assertEquals(['save', 'accessToken', 'refreshToken'], $callOrder);
    }

    public function testCookieIsSecureInProduction(): void {
        $_ENV['APP_ENV'] = 'production';

        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];
        $user = $this->createTestUser('123456');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->method('save');

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
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];
        $user = $this->createTestUser('123456');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->method('save');

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
}
