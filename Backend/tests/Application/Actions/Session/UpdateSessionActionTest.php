<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Session;

use App\Application\Actions\Session\UpdateSessionAction;
use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionValidator;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;

class UpdateSessionActionTest extends TestCase {
    private SessionRepository $sessionRepository;
    private SessionValidator $validator;
    private LoggerInterface $logger;
    private UpdateSessionAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->validator = $this->createMock(SessionValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new UpdateSessionAction(
            $this->logger,
            $this->sessionRepository,
            $this->validator
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

    private function createTestSession(
        ?string $sessionId = null,
        ?string $userId = null,
        ?string $activityId = null,
        string $date = '2024-03-18',
        string $duration = '00:30',
        float $distance = 5.0,
        string $description = 'Test pass',
        ?int $rpe = 7
    ): Session {
        return new Session(
            $sessionId ? new SessionId($sessionId) : new SessionId(),
            $userId ? new UserId($userId) : new UserId(),
            $activityId ? new ActivityId($activityId) : new ActivityId(),
            new \DateTimeImmutable($date),
            $duration,
            $distance,
            $description,
            $rpe
        );
    }

    public function testSuccessfullyUpdatesSession(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId);

        $updateData = [
            'duration' => '00:45',
            'distance' => 7.5,
            'description' => 'Uppdaterat pass',
            'rpe' => 8
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->with($updateData)
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->with(
                $this->isInstanceOf(UserId::class),
                $this->isInstanceOf(SessionId::class)
            )
            ->willReturn($session);

        $capturedSession = null;
        $this->sessionRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att session uppdaterades
        $this->assertEquals('00:45', $capturedSession->getDuration());
        $this->assertEquals(7.5, $capturedSession->getDistance());
        $this->assertEquals('Uppdaterat pass', $capturedSession->getDescription());
        $this->assertEquals(8, $capturedSession->getRpe());
    }

    public function testPartialUpdateOnlyChangesProvidedFields(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId, null, '2024-03-18 10:00:00', '00:30', 5.0, 'Original', 7);

        // Uppdatera bara duration
        $updateData = [
            'duration' => 60
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $capturedSession = null;
        $this->sessionRepository
            ->method('update')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        // Verifiera att bara duration ändrades
        $this->assertEquals(60, $capturedSession->getDuration());
        $this->assertEquals(5.0, $capturedSession->getDistance()); // Oförändrad
        $this->assertEquals('Original', $capturedSession->getDescription()); // Oförändrad
        $this->assertEquals(7, $capturedSession->getRpe()); // Oförändrad
    }

    public function testUpdatesActivityId(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $oldActivityId = (new ActivityId())->toString();
        $newActivityId = (new ActivityId())->toString();
        $session = $this->createTestSession($sessionId, $userId, $oldActivityId);

        $updateData = [
            'activityid' => $newActivityId
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $capturedSession = null;
        $this->sessionRepository
            ->method('update')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals($newActivityId, $capturedSession->getActivityId()->toString());
    }

    public function testUpdatesDate(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId, null, '2024-03-18');

        $updateData = [
            'date' => '2024-03-19'
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $capturedSession = null;
        $this->sessionRepository
            ->method('update')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals('2024-03-19', $capturedSession->getDate()->format('Y-m-d'));
    }

    public function testReturnsValidationErrors(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $invalidData = [
            'duration' => -5,
            'distance' => -10
        ];

        $errors = [
            'duration' => 'Duration måste vara positiv',
            'distance' => 'Distance måste vara positiv'
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($invalidData);

        $this->validator
            ->method('validateRegister')
            ->with($invalidData)
            ->willReturn(false);

        $this->validator
            ->method('getErrors')
            ->willReturn($errors);

        $this->sessionRepository
            ->expects($this->never())
            ->method('get');

        $this->sessionRepository
            ->expects($this->never())
            ->method('update');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('errors', $body['data']);
        $this->assertEquals($errors, $body['data']['errors']);
    }

    public function testThrowsNotFoundExceptionWhenSessionDoesNotExist(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $updateData = ['duration' => 60];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn(null);

        $this->sessionRepository
            ->expects($this->never())
            ->method('update');

        $this->expectException(HttpNotFoundException::class);
        $this->expectExceptionMessage('Passet hittades inte');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );
    }

    public function testThrowsNotFoundExceptionWhenSessionBelongsToOtherUser(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $updateData = ['duration' => 60];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        // Repository returnerar null eftersom userId inte matchar
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

    public function testReadsSessionIdFromUrlParameter(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId);

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['duration' => 60]);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedSessionId = null;
        $this->sessionRepository
            ->method('get')
            ->willReturnCallback(function ($uid, $sid) use (&$capturedSessionId, $session) {
                $capturedSessionId = $sid->toString();
                return $session;
            });

        $this->sessionRepository
            ->method('update');

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
        $session = $this->createTestSession($sessionId, $userId);

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['duration' => 60]);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $this->sessionRepository
            ->method('update');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );
    }

    public function testHandlesNullParsedBody(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(null);

        $this->validator
            ->method('validateRegister')
            ->with([])
            ->willReturn(false);

        $this->validator
            ->method('getErrors')
            ->willReturn(['data' => 'Data krävs']);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testConvertsKeysToLowercase(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId);

        $updateData = [
            'DURATION' => 60,
            'DISTANCE' => 10.5
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->with([
                'duration' => 60,
                'distance' => 10.5
            ])
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $this->sessionRepository
            ->method('update');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRepositoryUpdateIsCalledExactlyOnce(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId);

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['duration' => 60]);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $this->sessionRepository
            ->expects($this->once())
            ->method('update');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );
    }

    public function testResponseContainsUpdatedSession(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId);

        $updateData = [
            'duration' => '00:45',
            'distance' => 12.5
        ];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $this->sessionRepository
            ->method('update');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $body = json_decode((string)$response->getBody());

        $this->assertIsObject($body->data);
        $this->assertEquals('00:45', $body->data->duration);
        $this->assertEquals(12.5, $body->data->distance);
    }

    public function testUpdatesRpeValue(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId, null, '2024-03-18', '00:30', 5.0, 'Test', 5);

        $updateData = ['rpe' => 9];

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('get')
            ->willReturn($session);

        $capturedSession = null;
        $this->sessionRepository
            ->method('update')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertEquals(9, $capturedSession->getRpe());
    }

    public function testValidationIsCalledBeforeRepositoryAccess(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['duration' => -5]);

        $callOrder = [];

        $this->validator
            ->method('validateRegister')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'validate';
                return false;
            });

        $this->validator
            ->method('getErrors')
            ->willReturn(['duration' => 'Error']);

        $this->sessionRepository
            ->method('get')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'get';
                return null;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        // Validering ska köras först, repository ska inte anropas vid valideringsfel
        $this->assertEquals(['validate'], $callOrder);
    }

    public function testPassesValueObjectsToRepository(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $session = $this->createTestSession($sessionId, $userId);

        $this->setArgs(['id' => $sessionId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['duration' => 60]);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $receivedParams = [];
        $this->sessionRepository
            ->method('get')
            ->willReturnCallback(function ($uid, $sid) use (&$receivedParams, $session) {
                $receivedParams = [
                    'userId' => $uid,
                    'sessionId' => $sid
                ];
                return $session;
            });

        $this->sessionRepository
            ->method('update');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $sessionId]
        );

        $this->assertInstanceOf(UserId::class, $receivedParams['userId']);
        $this->assertInstanceOf(SessionId::class, $receivedParams['sessionId']);
        $this->assertEquals($userId, $receivedParams['userId']->toString());
        $this->assertEquals($sessionId, $receivedParams['sessionId']->toString());
    }
}