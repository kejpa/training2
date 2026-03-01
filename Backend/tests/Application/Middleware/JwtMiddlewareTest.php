<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Middleware\JwtMiddleware;
use App\Infrastructure\Auth\TokenService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class JwtMiddlewareTest extends TestCase {
    private TokenService $tokenService;
    private JwtMiddleware $middleware;
    private RequestHandlerInterface $handler;
    private ServerRequestFactory $requestFactory;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->tokenService = $this->createMock(TokenService::class);
        $this->middleware = new JwtMiddleware($this->tokenService);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->requestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
    }

    public function testAllowsRequestWithValidToken(): void {
        $token = 'valid-jwt-token';
        $decodedToken = (object)[
            'sub' => 'user-123',
            'email' => 'test@example.com',
            'exp' => time() + 3600
        ];

        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->with($token)
            ->willReturn($decodedToken);

        $expectedResponse = $this->responseFactory->createResponse(200);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($req) {
                return $req->getAttribute('userId') === 'user-123' &&
                    $req->getAttribute('userEmail') === 'test@example.com';
            }))
            ->willReturn($expectedResponse);

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddsUserIdToRequest(): void {
        $token = 'valid-token';
        $userId = 'test-user-id-456';
        $decodedToken = (object)[
            'sub' => $userId,
            'email' => 'user@example.com'
        ];

        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $capturedRequest = null;
        $this->handler
            ->method('handle')
            ->willReturnCallback(function ($req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return $this->responseFactory->createResponse(200);
            });

        $this->middleware->process($request, $this->handler);

        $this->assertNotNull($capturedRequest);
        $this->assertEquals($userId, $capturedRequest->getAttribute('userId'));
    }

    public function testAddsUserEmailToRequest(): void {
        $token = 'valid-token';
        $email = 'test@example.com';
        $decodedToken = (object)[
            'sub' => 'user-123',
            'email' => $email
        ];

        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $capturedRequest = null;
        $this->handler
            ->method('handle')
            ->willReturnCallback(function ($req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return $this->responseFactory->createResponse(200);
            });

        $this->middleware->process($request, $this->handler);

        $this->assertNotNull($capturedRequest);
        $this->assertEquals($email, $capturedRequest->getAttribute('userEmail'));
    }

    public function testRejects401WhenAuthorizationHeaderMissing(): void {
        $request = $this->requestFactory->createServerRequest('GET', '/api/user');

        $this->handler
            ->expects($this->never())
            ->method('handle');

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertEquals('Token saknas', $body['error']);
    }

    public function testRejects401WhenAuthorizationHeaderEmpty(): void {
        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', '');

        $this->handler
            ->expects($this->never())
            ->method('handle');

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Token saknas', $body['error']);
    }

    public function testRejects401WhenBearerPrefixMissing(): void {
        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', 'just-a-token');

        $this->handler
            ->expects($this->never())
            ->method('handle');

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Token saknas', $body['error']);
    }

    public function testAcceptsBearerWithDifferentCasing(): void {
        $token = 'valid-token';
        $decodedToken = (object)[
            'sub' => 'user-123',
            'email' => 'test@example.com'
        ];

        // Test med lowercase 'bearer'
        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->with($token)
            ->willReturn($decodedToken);

        $this->handler
            ->method('handle')
            ->willReturn($this->responseFactory->createResponse(200));

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRejects401WithInvalidToken(): void {
        $token = 'invalid-token';
        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->with($token)
            ->willThrowException(new \Exception('Invalid token'));

        $this->handler
            ->expects($this->never())
            ->method('handle');

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertEquals('Ogiltig eller utgången token', $body['error']);
    }

    public function testRejects401WithExpiredToken(): void {
        $token = 'expired-token';
        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->with($token)
            ->willThrowException(new \Exception('Token expired'));

        $this->handler
            ->expects($this->never())
            ->method('handle');

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Ogiltig eller utgången token', $body['error']);
    }

    public function testExtractsTokenCorrectly(): void {
        $expectedToken = 'my-secret-jwt-token-123';
        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $expectedToken");

        $decodedToken = (object)[
            'sub' => 'user-123',
            'email' => 'test@example.com'
        ];

        $capturedToken = null;
        $this->tokenService
            ->method('verifyToken')
            ->willReturnCallback(function ($token) use (&$capturedToken, $decodedToken) {
                $capturedToken = $token;
                return $decodedToken;
            });

        $this->handler
            ->method('handle')
            ->willReturn($this->responseFactory->createResponse(200));

        $this->middleware->process($request, $this->handler);

        $this->assertEquals($expectedToken, $capturedToken);
    }

    public function testReturnsJsonResponse(): void {
        $request = $this->requestFactory->createServerRequest('GET', '/api/user');

        $response = $this->middleware->process($request, $this->handler);

        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testDoesNotModifyOriginalRequestOnFailure(): void {
        $request = $this->requestFactory->createServerRequest('GET', '/api/user');

        $this->middleware->process($request, $this->handler);

        // Original request borde inte ha attribut om det misslyckades
        $this->assertNull($request->getAttribute('userId'));
        $this->assertNull($request->getAttribute('userEmail'));
    }

    public function testPassesModifiedRequestToHandler(): void {
        $token = 'valid-token';
        $decodedToken = (object)[
            'sub' => 'user-456',
            'email' => 'modified@example.com'
        ];

        $originalRequest = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token");

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $handlerReceivedRequest = null;
        $this->handler
            ->method('handle')
            ->willReturnCallback(function ($req) use (&$handlerReceivedRequest) {
                $handlerReceivedRequest = $req;
                return $this->responseFactory->createResponse(200);
            });

        $this->middleware->process($originalRequest, $this->handler);

        $this->assertNotNull($handlerReceivedRequest);
        $this->assertNotSame($originalRequest, $handlerReceivedRequest);
        $this->assertEquals('user-456', $handlerReceivedRequest->getAttribute('userId'));
        $this->assertEquals('modified@example.com', $handlerReceivedRequest->getAttribute('userEmail'));
    }

    public function testHandlerOnlyCalledWithValidToken(): void {
        // Test 1: Invalid token - handler inte kallad
        $invalidRequest = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', 'Bearer invalid-token');

        $this->tokenService
            ->method('verifyToken')
            ->willThrowException(new \Exception('Invalid'));

        $this->handler
            ->expects($this->never())
            ->method('handle');

        $this->middleware->process($invalidRequest, $this->handler);
    }

    public function testPreservesOtherRequestAttributes(): void {
        $token = 'valid-token';
        $decodedToken = (object)[
            'sub' => 'user-123',
            'email' => 'test@example.com'
        ];

        $request = $this->requestFactory
            ->createServerRequest('GET', '/api/user')
            ->withHeader('Authorization', "Bearer $token")
            ->withAttribute('customAttribute', 'customValue');

        $this->tokenService
            ->method('verifyToken')
            ->willReturn($decodedToken);

        $capturedRequest = null;
        $this->handler
            ->method('handle')
            ->willReturnCallback(function ($req) use (&$capturedRequest) {
                $capturedRequest = $req;
                return $this->responseFactory->createResponse(200);
            });

        $this->middleware->process($request, $this->handler);

        $this->assertEquals('customValue', $capturedRequest->getAttribute('customAttribute'));
        $this->assertEquals('user-123', $capturedRequest->getAttribute('userId'));
    }
}
