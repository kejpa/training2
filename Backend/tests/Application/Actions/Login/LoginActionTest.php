<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\LoginAction;
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

class LoginActionTest extends TestCase {
    private UserRepository $userRepository;
    private LoginValidator $validator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private TokenService $tokenService;
    private LoginAction $action;
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

        $this->action = new LoginAction(
            $this->logger,
            $this->userRepository,
            $this->emailService,
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

    public function testLoginReturns401WithExpiredCode(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456',
            'loginalternative' => 'mail'
        ];

        // Skapa user med utgången kod
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
        $this->assertEquals('Koden har gått ut', $body['data']['error']); // Uppdaterat meddelande
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

    public function testLoginReturns404WhenUserNotFound(): void {
        $data = [
            'email' => 'nonexistent@example.com',
            'code' => '123456',
            'loginalternative' => 'mail'
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

    public function testLoginReturns401WithInvalidCode(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '999999',
            'loginalternative' => 'mail'
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

    public function testLoginSkipsCodeCheckForNonMailAlternative(): void {
        $data = [
            'email' => 'test@example.com',
            'loginalternative' => 'totp' // Inte 'mail'
        ];
        $user = $this->createTestUser();

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
            ->willReturn('mock-access-token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('mock-refresh-token');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLoginHandlesNullParsedBody(): void {
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

    public function testLoginConvertsKeysToLowercase(): void {
        $data = [
            'EMAIL' => 'test@example.com',
            'CODE' => '123456',
            'LOGINALTERNATIVE' => 'mail'
        ];
        $user = $this->createTestUser('123456');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateLogin')
            ->with([
                'email' => 'test@example.com',
                'code' => '123456',
                'loginalternative' => 'mail'
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

    public function testLoginLogsErrorOnException(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456',
            'loginalternative' => 'mail'
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

        $data = [
            'email' => 'test@example.com',
            'code' => '123456',
            'loginalternative' => 'mail'
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
            'code' => '123456',
            'loginalternative' => 'mail'
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