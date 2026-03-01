<?php

declare(strict_types=1);

namespace Tests\Application\Actions\User;

use App\Application\Actions\User\ViewUserAction;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class ViewUserActionTest extends TestCase {
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private ViewUserAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new ViewUserAction(
            $this->logger,
            $this->userRepository
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
            null,
            null
        );
    }

    public function testSuccessfullyReturnsUser(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->expects($this->once())
            ->method('getById')
            ->with($this->callback(function ($id) use ($userId) {
                return $id instanceof UserId && $id->toString() === $userId;
            }))
            ->willReturn($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('user', $body['data']);
        $this->assertEquals($userId, $body['data']['user']['id']);
        $this->assertEquals('test@example.com', $body['data']['user']['email']);
        $this->assertEquals('Anna', $body['data']['user']['firstname']);
        $this->assertEquals('Andersson', $body['data']['user']['lastname']);
    }

    public function testReturns404WhenUserNotFound(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->expects($this->once())
            ->method('getById')
            ->with($this->callback(function ($id) use ($userId) {
                return $id instanceof UserId && $id->toString() === $userId;
            }))
            ->willReturn(null);

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

    public function testReadsUserIdFromRequestAttribute(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testUsesCorrectUserId(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $capturedUserId = null;
        $this->userRepository
            ->method('getById')
            ->willReturnCallback(function ($id) use (&$capturedUserId, $user) {
                $capturedUserId = $id->toString();
                return $user;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals($userId, $capturedUserId);
    }

    public function testResponseContainsSerializedUser(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody(), true);

        // Verifiera att det är serialized data (via jsonSerialize)
        $this->assertIsArray($body['data']['user']);

        // Verifiera att känslig data INTE finns med
        $this->assertArrayNotHasKey('secret', $body['data']['user']);
        $this->assertArrayNotHasKey('code', $body['data']['user']);
    }

    public function testResponseContainsAllPublicUserFields(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody(), true);
        $userData = $body['data']['user'];

        // Verifiera att alla publika fält finns
        $this->assertArrayHasKey('id', $userData);
        $this->assertArrayHasKey('email', $userData);
        $this->assertArrayHasKey('firstname', $userData);
        $this->assertArrayHasKey('lastname', $userData);
    }

    public function testResponseDoesNotContainSensitiveData(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody(), true);
        $userData = $body['data']['user'];

        // Verifiera att känslig data INTE finns
        $this->assertArrayNotHasKey('secret', $userData, 'Secret should not be exposed');
        $this->assertArrayNotHasKey('code', $userData, 'Code should not be exposed');
        $this->assertArrayNotHasKey('qrUrl', $userData, 'QR URL should not be exposed');
        $this->assertArrayNotHasKey('imgData', $userData, 'Image data should not be exposed');
        $this->assertArrayNotHasKey('expires', $userData, 'Expires should not be exposed');
    }

    public function testResponseIsJson(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = (string)$response->getBody();

        // Verifiera att det är giltig JSON
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testResponseHasCorrectStructure(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->method('getById')
            ->willReturn($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody(), true);

        // Verifiera struktur
        $this->assertIsArray($body);
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertArrayHasKey('user', $body['data']);
        $this->assertIsArray($body['data']['user']);
    }

    public function testHandlesDifferentUserIds(): void {
        $userIds = [
            (new UserId())->toString(),
            (new UserId())->toString(),
            (new UserId())->toString(),
            (new UserId())->toString()
        ];

        foreach ($userIds as $userId) {
            $user = $this->createTestUser($userId);

            $this->request
                ->method('getAttribute')
                ->with('userId')
                ->willReturn($userId);

            $this->userRepository
                ->method('getById')
                ->willReturn($user);

            $response = $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                []
            );

            $this->assertEquals(200, $response->getStatusCode(), "Failed for userId: $userId");
        }
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $userId = (new UserId())->toString();
        $user = $this->createTestUser($userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->userRepository
            ->expects($this->once())
            ->method('getById')
            ->willReturn($user);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

}