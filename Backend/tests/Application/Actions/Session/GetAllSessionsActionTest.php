<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Session;

use App\Application\Actions\Session\GetAllSessionsAction;
use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\User\User;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\ActivityId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use DateTimeImmutable;

class GetAllSessionsActionTest extends TestCase {

    private SessionRepository $sessionRepository;
    private LoggerInterface $logger;
    private GetAllSessionsAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new GetAllSessionsAction(
            $this->logger,
            $this->sessionRepository
        );

        $this->request = $this->createMock(Request::class);

        // Inject request & response (samma som din fungerande testklass)
        $reflection = new \ReflectionClass($this->action);

        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->action, $this->request);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->action, $this->responseFactory->createResponse());
    }

    private function createTestSession(
        ?string $sessionId = null,
        ?string $userId = null,
        string $date = '2024-01-01',
        ?string $duration = '01:00:00',
        ?float $distance = 10.5,
        string $description = 'Test pass',
        int $rpe = 5
    ): Session {
        return new Session(
            $sessionId ? new SessionId($sessionId) : new SessionId(),
            $userId ? new UserId($userId) : new UserId(),
            new ActivityId(),
            new DateTimeImmutable($date),
            $duration,
            $distance,
            $description,
            $rpe
        );
    }

    // ========== tests ==========

    public function testSuccessfullyReturnsSessions(): void {
        $userId = (new UserId())->toString();

        $sessions = [
            $this->createTestSession(null, $userId),
            $this->createTestSession(null, $userId),
            $this->createTestSession(null, $userId),
        ];

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->sessionRepository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn($sessions);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody());
        $this->assertObjectHasProperty('sessions', $body->data);
        $this->assertIsArray($body->data->sessions);
        $this->assertCount(3, $body->data->sessions);
    }

    public function testReturnsEmptyArrayWhenNoSessions(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->sessionRepository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertObjectHasProperty('sessions', $body->data);
        $this->assertIsArray($body->data->sessions);
        $this->assertEmpty($body->data->sessions);
    }

    public function testReadsUserIdFromRequest(): void {
        $userId = new UserId();

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $capturedUserId = null;

        $this->sessionRepository
            ->method('getAll')
            ->willReturnCallback(function ($uid) use (&$capturedUserId) {
                $capturedUserId = $uid;
                return [];
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        // Viktigt: här är det ett ValueObject
        $this->assertInstanceOf(UserId::class, $capturedUserId);
        $this->assertEquals($userId, $capturedUserId->toString());
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->expects($this->once())
            ->method('getAll')
            ->willReturn([]);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testResponseContainsSessionsArray(): void {
        $userId = (new UserId())->toString();
        $sessions = [$this->createTestSession(null, $userId)];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('getAll')
            ->willReturn($sessions);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertObjectHasProperty('data', $body);
        $this->assertObjectHasProperty('sessions', $body->data);
        $this->assertIsArray($body->data->sessions);
    }

    public function testEachSessionContainsBasicFields(): void {
        $userId = (new UserId())->toString();
        $sessionId = (new SessionId())->toString();

        $session = new Session(
            new SessionId($sessionId),
            new UserId($userId),
            new ActivityId(),
            new DateTimeImmutable('2024-01-01'),
            '01:00:00',
            10.5,
            'Test pass',
            5
        );

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('getAll')
            ->willReturn([$session]);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());
        $sessionData = $body->data->sessions[0];

        $this->assertEquals($sessionId, $sessionData->id);
        $this->assertEquals($userId, $sessionData->userid);
        $this->assertEquals('Test pass', $sessionData->description);
        $this->assertEquals(10.5, $sessionData->distance);
        $this->assertEquals(5, $sessionData->rpe);
    }

    public function testResponseStructureIsCorrect(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('getAll')
            ->willReturn([]);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('sessions', $body['data']);
        $this->assertIsArray($body['data']['sessions']);
    }
}