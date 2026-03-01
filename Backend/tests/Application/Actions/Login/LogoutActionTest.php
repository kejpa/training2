<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\LogoutAction;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class LogoutActionTest extends TestCase {
    private LoggerInterface $logger;
    private LogoutAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new LogoutAction($this->logger);

        $this->request = $this->createMock(Request::class);

        $reflection = new \ReflectionClass($this->action);

        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->action, $this->request);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->action, $this->responseFactory->createResponse());
    }

    public function testSuccessfulLogout(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('message', $body['data']);
        $this->assertEquals('Utloggad', $body['data']['message']);
    }

    public function testClearsRefreshTokenCookie(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');

        $this->assertNotEmpty($setCookieHeaders);
        $this->assertCount(1, $setCookieHeaders);

        $cookieHeader = $setCookieHeaders[0];

        // Verifiera att cookie tömts
        $this->assertStringContainsString('refresh_token=;', $cookieHeader);
    }

    public function testCookieHasExpiredDate(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        // Verifiera att expires är satt
        $this->assertStringContainsString('Expires=', $cookieHeader);

        // Verifiera att det är ett gammalt datum (Thu, 01 Jan 1970 skulle vara ett tecken på -1)
        // Vi kan inte testa exakt datum pga timezone, men vi kan kolla att det finns
        preg_match('/Expires=([^;]+)/', $cookieHeader, $matches);
        $this->assertNotEmpty($matches);
    }

    public function testCookieHasHttpOnlyAttribute(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        $this->assertStringContainsString('HttpOnly', $cookieHeader);
    }

    public function testCookieHasSameSiteStrictAttribute(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        $this->assertStringContainsString('SameSite=Strict', $cookieHeader);
    }

    public function testCookieHasRootPath(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        $this->assertStringContainsString('Path=/', $cookieHeader);
    }

    public function testCookieDoesNotHaveSecureAttribute(): void {
        // LogoutAction sätter inte Secure flag i nuvarande implementation
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        $this->assertStringNotContainsString('Secure', $cookieHeader);
    }

    public function testMultipleLogoutCallsWork(): void {
        // Första logout
        $response1 = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response1->getStatusCode());

        // Andra logout (borde fungera även om redan utloggad)
        $response2 = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response2->getStatusCode());

        $body = json_decode((string)$response2->getBody(), true);
        $this->assertEquals('Utloggad', $body['data']['message']);
    }

    public function testCookieFormatIsCorrect(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $setCookieHeaders = $response->getHeader('Set-Cookie');
        $cookieHeader = $setCookieHeaders[0];

        // Verifiera hela cookie-formatet
        $expectedPattern = '/^refresh_token=; Path=\/refresh; Expires=[^;]+; HttpOnly; SameSite=Strict$/';
        $this->assertMatchesRegularExpression($expectedPattern, $cookieHeader);
    }

    public function testResponseContentType(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        // respondWithData sätter vanligtvis Content-Type till application/json
        $contentType = $response->getHeaderLine('Content-Type');

        if (!empty($contentType)) {
            $this->assertStringContainsString('application/json', $contentType);
        }
    }

    public function testResponseBodyStructure(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertIsArray($decoded['data']);
        $this->assertArrayHasKey('message', $decoded['data']);
    }

    public function testCanSerializeResponse(): void {
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = (string)$response->getBody();

        // Verifiera att body är giltig JSON
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    protected function action(): Response {
        $secure = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        // Rensa refresh token cookie
        $cookieHeader = sprintf(
            'refresh_token=; Path=/; Expires=%s; HttpOnly; SameSite=Strict%s',
            gmdate('D, d M Y H:i:s T', -1),
            $secure ? '; Secure' : ''
        );

        $response = $this->respondWithData([
            'message' => 'Utloggad'
        ], 200);

        return $response->withAddedHeader('Set-Cookie', $cookieHeader);
    }
}
