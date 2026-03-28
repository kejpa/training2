<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Session;

use App\Application\Actions\Session\GetSessionAction;
use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\ActivityId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Exception\HttpNotFoundException;
use DateTimeImmutable;

class GetSessionActionTest extends TestCase {

    private SessionRepository $sessionRepository;
    private LoggerInterface $logger;
    private GetSessionAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new GetSessionAction(
            $this->logger,
            $this->sessionRepository
        );

        $this->request = $this->createMock(Request::class);

        // Inject request & response
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
        ?string $userId = null
    ): Session {
        return new Session(
            $sessionId ? new SessionId($sessionId) : new SessionId(),
            $userId ? new UserId($userId) : new UserId(),
            new ActivityId(),
            new DateTimeImmutable('2024-01-01'),
            '01:00:00',
            10.5,
            'Test pass',
            5
        );
    }

    // ========== success tests ==========

    public function testSuccessfullyReturnsSession(): void {
        $userId = (new UserId())->toString();
        $sessionId = (new SessionId())->toString();

        $session = $this->createTestSession($sessionId, $userId);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        // resolveArg('id') använder route args → mockas via __invoke args
        $this->sessionRepository
            ->expects($this->once())
            ->method('get')
            ->willReturn($session);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody());
        $this->assertObjectHasProperty('session', $body->data);
        $this->assertEquals($sessionId, $body->data->session->id);
    }

    // ========== not found tests ==========

    public function testThrowsNotFoundWhenSessionDoesNotExist(): void {
        $userId = (new UserId())->toString();
        $sessionId = (new SessionId())->toString();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('get')
            ->willReturn(null);

        $this->expectException(HttpNotFoundException::class);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );
    }

    // ========== repository interaction ==========

    public function testPassesCorrectValueObjectsToRepository(): void {
        $userId = new UserId();
        $sessionId = new SessionId();

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $capturedUserId = null;
        $capturedSessionId = null;

        $this->sessionRepository
            ->method('get')
            ->willReturnCallback(function ($uid, $sid) use (&$capturedUserId, &$capturedSessionId) {
                $capturedUserId = $uid;
                $capturedSessionId = $sid;
                return null;
            });

        try {
            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                ['id' => $sessionId->toString()]
            );
        } catch (HttpNotFoundException) {
            // expected
        }

        $this->assertInstanceOf(UserId::class, $capturedUserId);
        $this->assertInstanceOf(SessionId::class, $capturedSessionId);

        $this->assertEquals($userId, $capturedUserId->toString());
        $this->assertEquals($sessionId, $capturedSessionId->toString());
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        try {
            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                ['id' => (new SessionId())->toString()]
            );
        } catch (HttpNotFoundException) {
            // expected
        }
    }

    // ========== response structure ==========

    public function testResponseStructureIsCorrect(): void {
        $userId = (new UserId())->toString();
        $sessionId = (new SessionId())->toString();

        $session = $this->createTestSession($sessionId, $userId);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $body = json_decode((string)$response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('session', $body['data']);
    }

    public function testReturnedSessionContainsExpectedFields(): void {
        $userId = (new UserId())->toString();
        $sessionId = (new SessionId())->toString();

        $session = new Session(
            new SessionId($sessionId),
            new UserId($userId),
            new ActivityId(),
            new DateTimeImmutable('2024-01-01'),
            '01:00:00',
            12.3,
            'My session',
            7
        );

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $body = json_decode((string)$response->getBody());
        $data = $body->data->session;

        $this->assertEquals($sessionId, $data->id);
        $this->assertEquals($userId, $data->userid);
        $this->assertEquals('My session', $data->description);
        $this->assertEquals(12.3, $data->distance);
        $this->assertEquals(7, $data->rpe);
    }
}