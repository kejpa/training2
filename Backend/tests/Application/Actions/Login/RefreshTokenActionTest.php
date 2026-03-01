<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\RefreshTokenAction;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Auth\TokenService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class RefreshTokenActionTest extends TestCase {
    private UserRepository $userRepository;
    private TokenService $tokenService;
    private LoggerInterface $logger;
    private RefreshTokenAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->tokenService = $this->createMock(TokenService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        // Sätt environment variables för tester
        $_ENV['REFRESH_TOKEN_EXPIRATION'] = '2592000';
        $_ENV['APP_ENV'] = 'development';

        $this->action = new RefreshTokenAction(
            $this->logger,
            $this->userRepository,
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

    private function createTestUser(string $userId = null): User {
        return new User(
            $userId ? new UserId($userId) : new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            '123456',
            new \DateTimeImmutable('+1 hour')
        );
    }

    public function testSuccessfulTokenRefresh(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);
        $refreshToken = 'valid-refresh-token';

        $decodedToken = (object)[
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->with($refreshToken)
            ->willReturn($decodedToken);

        $this->userRepository
            ->method('getById')
            ->with($this->callback(function ($id) use ($userId) {
                return $id instanceof UserId && $id->toString() === $userId;
            }))
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->with($user)
            ->willReturn('new-access-token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->with($user)
            ->willReturn('new-refresh-token');

        $this->tokenService
            ->method('getExpiresIn')
            ->willReturn(3600);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertEquals('new-access-token', $body['data']['access_token']);
        $this->assertEquals('Bearer', $body['data']['token_type']);
        $this->assertEquals(3600, $body['data']['expires_in']);

        // Verifiera cookie header
        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertNotEmpty($setCookieHeaders);
        $this->assertStringContainsString('refresh_token=', $setCookieHeaders[0]);
        $this->assertStringContainsString('HttpOnly', $setCookieHeaders[0]);
        $this->assertStringContainsString('SameSite=Strict', $setCookieHeaders[0]);
    }

    public function testReturns401WhenRefreshTokenMissing(): void {
        $this->request
            ->method('getCookieParams')
            ->willReturn([]);

        $this->tokenService
            ->expects($this->never())
            ->method('verifyToken');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Refresh token saknas', $body['data']['error']);
    }

    public function testReturns401WithInvalidTokenType(): void {
        $refreshToken = 'access-token-not-refresh';

        $decodedToken = (object)[
            'sub' => 'user-123',
            'type' => 'access', // Fel typ
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->with($refreshToken)
            ->willReturn($decodedToken);

        $this->userRepository
            ->expects($this->never())
            ->method('getById');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Ogiltig token-typ', $body['data']['error']);
    }

    public function testReturns401WhenUserNotFound(): void {
        $userId = (new UserId())->toString();
        $refreshToken = 'valid-refresh-token';

        $decodedToken = (object)[
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->with($refreshToken)
            ->willReturn($decodedToken);

        $this->userRepository
            ->method('getById')
            ->willReturn(null);

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
        $this->assertEquals('Användaren hittades inte', $body['data']['error']);
    }

    public function testReturns401WithExpiredToken(): void {
        $refreshToken = 'expired-refresh-token';
        $exception = new \Exception('Token expired');

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->with($refreshToken)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('RefreshTokenAction:'));

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Ogiltig eller utgången token', $body['data']['error']);
    }

    public function testReturns401WithInvalidToken(): void {
        $refreshToken = 'invalid-token-format';
        $exception = new \Exception('Invalid token format');

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->with($refreshToken)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Ogiltig eller utgången token', $body['data']['error']);
    }

    public function testLogsErrorOnException(): void {
        $refreshToken = 'valid-token';
        $exception = new \Exception('Database connection failed');

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('RefreshTokenAction: Database connection failed');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testCookieIsSecureInProduction(): void {
        $_ENV['APP_ENV'] = 'production';

        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);
        $refreshToken = 'valid-refresh-token';

        $decodedToken = (object)[
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('access-token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('new-refresh-token');

        $this->tokenService
            ->method('getExpiresIn')
            ->willReturn(3600);

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

    public function testCookieIsNotSecureInDevelopment(): void {
        $_ENV['APP_ENV'] = 'development';

        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);
        $refreshToken = 'valid-refresh-token';

        $decodedToken = (object)[
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('access-token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('new-refresh-token');

        $this->tokenService
            ->method('getExpiresIn')
            ->willReturn(3600);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertStringNotContainsString('Secure', $setCookieHeaders[0]);
    }

    public function testCookieHasCorrectAttributes(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);
        $refreshToken = 'valid-refresh-token';

        $decodedToken = (object)[
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $refreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('access-token');

        $this->tokenService
            ->method('generateRefreshToken')
            ->willReturn('new-refresh-token');

        $this->tokenService
            ->method('getExpiresIn')
            ->willReturn(3600);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        $this->assertStringContainsString('refresh_token=', $cookieHeader);
        $this->assertStringContainsString('Path=/', $cookieHeader);
        $this->assertStringContainsString('HttpOnly', $cookieHeader);
        $this->assertStringContainsString('SameSite=Strict', $cookieHeader);
        $this->assertStringContainsString('Expires=', $cookieHeader);
    }

    public function testGeneratesNewRefreshToken(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);
        $oldRefreshToken = 'old-refresh-token';

        $decodedToken = (object)[
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + 3600
        ];

        $this->request
            ->method('getCookieParams')
            ->willReturn(['refresh_token' => $oldRefreshToken]);

        $this->tokenService
            ->method('verifyToken')
            ->with($oldRefreshToken)
            ->willReturn($decodedToken);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $this->tokenService
            ->method('generateAccessToken')
            ->willReturn('access-token');

        $this->tokenService
            ->expects($this->once())
            ->method('generateRefreshToken')
            ->with($user)
            ->willReturn('new-refresh-token');

        $this->tokenService
            ->method('getExpiresIn')
            ->willReturn(3600);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att ny refresh token finns i cookie
        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $this->assertStringContainsString('new-refresh-token', urldecode($setCookieHeaders[0]));
    }
}
