<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Session;

use App\Application\Actions\Session\DeleteSessionAction;
use App\Domain\Session\SessionRepository;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class DeleteSessionActionTest extends TestCase {
    private SessionRepository $sessionRepository;
    private LoggerInterface $logger;
    private DeleteSessionAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new DeleteSessionAction(
            $this->logger,
            $this->sessionRepository
        );

        $this->request = $this->createMock(Request::class);

        $reflection = new \ReflectionClass($this->action);

        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->action, $this->request);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->action, $this->responseFactory->createResponse());

        $argsProperty = $reflection->getProperty('args');
        $argsProperty->setAccessible(true);
        $argsProperty->setValue($this->action, []);
    }

    private function setArgs(array $args): void {
        $reflection = new \ReflectionClass($this->action);
        $argsProperty = $reflection->getProperty('args');
        $argsProperty->setAccessible(true);
        $argsProperty->setValue($this->action, $args);
    }

    public function testSuccessfullyDeletesSession(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->sessionRepository
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->callback(fn($uid) => $uid instanceof UserId && $uid->toString() === $userId),
                $this->callback(fn($sid) => $sid instanceof SessionId && $sid->toString() === $sessionId)
            );

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testReturns204NoContent(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(204, $response->getStatusCode());

        // 204 No Content ska ha en tom body
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        $this->assertEmpty($decoded['data']);
    }

    public function testReadsSessionIdFromUrlParameter(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $capturedSessionId = null;
        $this->sessionRepository
            ->method('delete')
            ->willReturnCallback(function ($uid, $sid) use (&$capturedSessionId) {
                $capturedSessionId = $sid->toString();
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals($sessionId, $capturedSessionId);
    }

    public function testReadsUserIdFromJwtAttribute(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $capturedUserId = null;
        $this->sessionRepository
            ->method('delete')
            ->willReturnCallback(function ($uid, $sid) use (&$capturedUserId) {
                $capturedUserId = $uid->toString();
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals($userId, $capturedUserId);
    }

    public function testPassesBothIdsAsValueObjects(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $receivedUserId = null;
        $receivedSessionId = null;

        $this->sessionRepository
            ->expects($this->once())
            ->method('delete')
            ->willReturnCallback(function ($uid, $sid) use (&$receivedUserId, &$receivedSessionId) {
                $receivedUserId = $uid;
                $receivedSessionId = $sid;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertInstanceOf(UserId::class, $receivedUserId);
        $this->assertInstanceOf(SessionId::class, $receivedSessionId);
        $this->assertEquals($userId, $receivedUserId->toString());
        $this->assertEquals($sessionId, $receivedSessionId->toString());
    }

    public function testRepositoryDeleteIsCalledExactlyOnce(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->expects($this->once())
            ->method('delete');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );
    }

    public function testDeleteParameterOrder(): void {
        // Viktigt: delete() tar userId FÖRST, sedan sessionId
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $parameterOrder = [];
        $this->sessionRepository
            ->method('delete')
            ->willReturnCallback(function ($param1, $param2) use (&$parameterOrder) {
                $parameterOrder = [
                    'first' => get_class($param1),
                    'second' => get_class($param2)
                ];
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertStringContainsString('UserId', $parameterOrder['first']);
        $this->assertStringContainsString('SessionId', $parameterOrder['second']);
    }

    public function testDeleteIsIdempotent(): void {
        // Delete ska kunna anropas flera gånger utan fel
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testEnsuresUserCanOnlyDeleteTheirOwnSessions(): void {
        // Säkerhet: userId från JWT skickas till repository
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        // Verifiera att BÅDE userId OCH sessionId skickas
        $this->sessionRepository
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->isInstanceOf(UserId::class),
                $this->isInstanceOf(SessionId::class)
            );

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );
    }

    public function testResponseHasCorrectContentType(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        // Verifiera att response är JSON
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testResponseBodyIsEmpty(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $body = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('data', $body);
        $this->assertEmpty($body['data']);
    }

    public function testThrowsExceptionWhenIdParameterMissing(): void {
        $userId = (new UserId())->toString();

        // Args saknar 'id'
        $this->setArgs([]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->expectException(\Slim\Exception\HttpBadRequestException::class);
        $this->expectExceptionMessage('Could not resolve argument `id`');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testCreatesValueObjectsFromStrings(): void {
        // Viktigt: Action konverterar strings till ValueObjects
        $sessionIdString = (new SessionId())->toString();
        $userIdString = (new UserId())->toString();

        $this->setArgs(['id' => $sessionIdString]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userIdString);

        $receivedTypes = [];
        $this->sessionRepository
            ->method('delete')
            ->willReturnCallback(function ($uid, $sid) use (&$receivedTypes) {
                $receivedTypes = [
                    'userId' => get_class($uid),
                    'sessionId' => get_class($sid)
                ];
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionIdString]
        );

        $this->assertStringContainsString('UserId', $receivedTypes['userId']);
        $this->assertStringContainsString('SessionId', $receivedTypes['sessionId']);
    }

    public function testDeleteOperationIsSecure(): void {
        // Verifierar att både userId och sessionId krävs
        $sessionId = (new SessionId())->toString();
        $currentUserId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($currentUserId);

        $receivedParams = [];
        $this->sessionRepository
            ->method('delete')
            ->willReturnCallback(function ($uid, $sid) use (&$receivedParams) {
                $receivedParams = [
                    'userId' => $uid->toString(),
                    'sessionId' => $sid->toString()
                ];
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals($currentUserId, $receivedParams['userId']);
        $this->assertEquals($sessionId, $receivedParams['sessionId']);
    }

    public function testHandlesDifferentSessionIds(): void {
        $userId = (new UserId())->toString();

        $sessionIds = [
            (new SessionId())->toString(),
            (new SessionId())->toString(),
            (new SessionId())->toString(),
        ];

        foreach ($sessionIds as $sessionId) {
            $this->setUp(); // Reset mocks

            $this->setArgs(['id' => $sessionId]);

            $this->request
                ->method('getAttribute')
                ->willReturn($userId);

            $capturedId = null;
            $this->sessionRepository
                ->method('delete')
                ->willReturnCallback(function ($uid, $sid) use (&$capturedId) {
                    $capturedId = $sid->toString();
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                ['id' => $sessionId]
            );

            $this->assertEquals($sessionId, $capturedId);
        }
    }
}