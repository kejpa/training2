<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Session;

use App\Application\Actions\Session\AddSessionAction;
use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionValidator;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class AddSessionActionTest extends TestCase {
    private SessionRepository $sessionRepository;
    private SessionValidator $validator;
    private LoggerInterface $logger;
    private AddSessionAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->sessionRepository = $this->createMock(SessionRepository::class);
        $this->validator = $this->createMock(SessionValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new AddSessionAction(
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
    }

    private function createTestSessionData(): array {
        return [
            'activityid' => (new ActivityId())->toString(),
            'date' => '2024-03-18',
            'duration' => '00:30',
            'distance' => 5.5,
            'rpe' => 5,
            'description' => 'Bra träningspass'
        ];
    }

    public function testSuccessfullyAddsSession(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->with($data)
            ->willReturn(true);

        $capturedSession = null;
        $this->sessionRepository
            ->expects($this->once())
            ->method('add')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att session skapades med rätt data
        $this->assertNotNull($capturedSession);
        $this->assertInstanceOf(Session::class, $capturedSession);
        $this->assertEquals($userId, $capturedSession->getUserId()->toString());
        $this->assertEquals('00:30', $capturedSession->getDuration());
        $this->assertEquals(5.5, $capturedSession->getDistance());
    }

    public function testAddsUserIdToSession(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedSession = null;
        $this->sessionRepository
            ->method('add')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals($userId, $capturedSession->getUserId()->toString());
    }

    public function testGeneratesSessionId(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedSession = null;
        $this->sessionRepository
            ->method('add')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        // Verifiera att ett ID genererades
        $this->assertNotNull($capturedSession->getId());
        $this->assertInstanceOf(SessionId::class, $capturedSession->getId());

        // Verifiera UUID-format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $capturedSession->getId()->toString()
        );
    }

    public function testReturnsValidationErrors(): void {
        $userId = (new UserId())->toString();
        $data = [
            'activityid' => '',  // Ogiltig
            'date' => '',        // Ogiltig
            'duration' => -5     // Ogiltig
        ];

        $errors = [
            'activityid' => 'Aktivitet krävs',
            'date' => 'Datum krävs',
            'duration' => 'Duration måste vara positiv'
        ];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->with($data)
            ->willReturn(false);

        $this->validator
            ->method('getErrors')
            ->willReturn($errors);

        $this->sessionRepository
            ->expects($this->never())
            ->method('add');

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

    public function testHandlesNullParsedBody(): void {
        $userId = (new UserId())->toString();

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
            []
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testConvertsKeysToLowercase(): void {
        $userId = (new UserId())->toString();
        $data = [
            'ACTIVITYID' => (new ActivityId())->toString(),
            'DATE' => '2024-03-18',
            'DURATION' => '00:30',
            'DISTANCE' => 5.5,
            'RPE' => 5,
            'DESCRIPTION' => 'Bra träningspass'
        ];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->with([
                'activityid' => $data['ACTIVITYID'],
                'date' => '2024-03-18',
                'duration' => '00:30',
                'distance' => 5.5,
                'rpe' => 5,
                'description' => 'Bra träningspass'
            ])
            ->willReturn(true);

        $this->sessionRepository
            ->method('add');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReturnsSessionInResponse(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('add');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertIsObject($body->data);

        // Verifiera att session-data finns i response
        $this->assertObjectHasProperty('id', $body->data);
        $this->assertObjectHasProperty('activityid', $body->data);
        $this->assertObjectHasProperty('duration', $body->data);
        $this->assertEquals('00:30', $body->data->duration);
        $this->assertEquals(5.5, $body->data->distance);
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->expects($this->once())
            ->method('add');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testHandlesDifferentDurationValues(): void {
        $userId = (new UserId())->toString();
        $durations = [5, 30, 60, 90, 120];

        foreach ($durations as $duration) {
            $this->setUp(); // Reset mocks

            $data = $this->createTestSessionData();
            $data['duration'] = $duration;

            $this->request
                ->method('getAttribute')
                ->willReturn($userId);

            $this->request
                ->method('getParsedBody')
                ->willReturn($data);

            $this->validator
                ->method('validateRegister')
                ->willReturn(true);

            $capturedSession = null;
            $this->sessionRepository
                ->method('add')
                ->willReturnCallback(function ($session) use (&$capturedSession) {
                    $capturedSession = $session;
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                []
            );

            $this->assertEquals($duration, $capturedSession->getDuration(), "Failed for duration: $duration");
        }
    }

    public function testHandlesDifferentDistanceValues(): void {
        $userId = (new UserId())->toString();
        $distances = [0.0, 1.5, 5.0, 10.5, 21.1];

        foreach ($distances as $distance) {
            $this->setUp(); // Reset mocks

            $data = $this->createTestSessionData();
            $data['distance'] = $distance;

            $this->request
                ->method('getAttribute')
                ->willReturn($userId);

            $this->request
                ->method('getParsedBody')
                ->willReturn($data);

            $this->validator
                ->method('validateRegister')
                ->willReturn(true);

            $capturedSession = null;
            $this->sessionRepository
                ->method('add')
                ->willReturnCallback(function ($session) use (&$capturedSession) {
                    $capturedSession = $session;
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                []
            );

            $this->assertEquals($distance, $capturedSession->getDistance(), "Failed for distance: $distance");
        }
    }

    public function testSessionCreatedWithAllFields(): void {
        $userId = (new UserId())->toString();
        $activityId = (new ActivityId())->toString();

        $data = [
            'activityid' => $activityId,
            'date' => '2024-03-18',
            'duration' => '00:45',
            'distance' => 7.5,
            'rpe' => 7,
            'description' => 'Mycket bra pass'
        ];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedSession = null;
        $this->sessionRepository
            ->method('add')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertNotNull($capturedSession->getId());
        $this->assertEquals($userId, $capturedSession->getUserId()->toString());
        $this->assertEquals($activityId, $capturedSession->getActivityId()->toString());
        $this->assertEquals('2024-03-18', $capturedSession->getDate()->format('Y-m-d'));
        $this->assertEquals('00:45', $capturedSession->getDuration());
        $this->assertEquals(7.5, $capturedSession->getDistance());
        $this->assertEquals('Mycket bra pass', $capturedSession->getDescription());
    }

    public function testValidationIsCalledBeforeRepositoryAdd(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $callOrder = [];

        $this->validator
            ->method('validateRegister')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'validate';
                return true;
            });

        $this->sessionRepository
            ->method('add')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'add';
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(['validate', 'add'], $callOrder);
    }

    public function testHandlesOptionalNotes(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();
        unset($data['notes']); // Ta bort notes

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedSession = null;
        $this->sessionRepository
            ->method('add')
            ->willReturnCallback(function ($session) use (&$capturedSession) {
                $capturedSession = $session;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($capturedSession);
    }

    public function testResponseIsJson(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestSessionData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->sessionRepository
            ->method('add');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = (string)$response->getBody();

        $decoded = json_decode($body);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }
}