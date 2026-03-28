<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Activity;

use App\Application\Actions\Activity\UpdateActivityAction;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityValidator;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ResponseFactory;

class UpdateActivityActionTest extends TestCase {
    private ActivityRepository $activityRepository;
    private ActivityValidator $validator;
    private LoggerInterface $logger;
    private UpdateActivityAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->validator = $this->createMock(ActivityValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new UpdateActivityAction(
            $this->logger,
            $this->activityRepository,
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

    private function createTestActivity(
        ?string $activityId = null,
        ?string $userId = null,
        string $emoji = '🏃',
        string $name = 'Löpning',
        bool $logDistance = true,
        bool $logTime = true,
        string $distanceUnit = 'km'
    ): Activity {
        return new Activity(
            $activityId ? new ActivityId($activityId) : new ActivityId(),
            $userId ? new UserId($userId) : new UserId(),
            $emoji,
            $name,
            $logDistance,
            $logTime,
            $distanceUnit
        );
    }

    public function testSuccessfullyUpdatesActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Löpning');

        $updateData = [
            'emoji' => '🚴',
            'name' => 'Cykling',
            'log_distance' => false,
            'log_duration' => true,
            'distance_unit' => 'mi'
        ];

        $this->setArgs(['id' => $activityId]);

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

        $this->activityRepository
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn($activity);

        $capturedActivity = null;
        $this->activityRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att aktiviteten uppdaterades
        $this->assertEquals('🚴', $capturedActivity->getEmoji());
        $this->assertEquals('Cykling', $capturedActivity->getName());
        $this->assertFalse($capturedActivity->getLogDistance());
        $this->assertTrue($capturedActivity->getLogTime());
        $this->assertEquals('mi', $capturedActivity->getDistanceUnit());
    }

    public function testPartialUpdateOnlyChangesProvidedFields(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Löpning', true, true, 'km');

        // Uppdatera bara namn
        $updateData = [
            'name' => 'Morgonlöpning'
        ];

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $capturedActivity = null;
        $this->activityRepository
            ->method('update')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        // Verifiera att bara name ändrades
        $this->assertEquals('Morgonlöpning', $capturedActivity->getName());
        $this->assertEquals('🏃', $capturedActivity->getEmoji()); // Oförändrad
        $this->assertTrue($capturedActivity->getLogDistance()); // Oförändrad
        $this->assertTrue($capturedActivity->getLogTime()); // Oförändrad
        $this->assertEquals('km', $capturedActivity->getDistanceUnit()); // Oförändrad
    }

    public function testReturnsValidationErrors(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $invalidData = [
            'name' => '',
            'log_distance' => true,
            'distance_unit' => ''
        ];

        $errors = [
            'name' => 'Namn krävs',
            'distance_unit' => 'Enhet för distans krävs'
        ];

        $this->setArgs(['id' => $activityId]);

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

        $this->activityRepository
            ->expects($this->never())
            ->method('getActivityForUser');

        $this->activityRepository
            ->expects($this->never())
            ->method('update');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('errors', $body['data']);
        $this->assertEquals($errors, $body['data']['errors']);
    }

    public function testThrowsNotFoundExceptionWhenActivityDoesNotExist(): void {
        $activityId = 'non-existent-id';
        $userId = (new UserId())->toString();

        $updateData = ['name' => 'Updated'];

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn(null);

        $this->activityRepository
            ->expects($this->never())
            ->method('update');

        $this->expectException(HttpNotFoundException::class);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testThrowsNotFoundExceptionWhenActivityBelongsToOtherUser(): void {
        $activityId = (new ActivityId())->toString();
        $userId = 'current-user-id';

        $updateData = ['name' => 'Updated'];

        $this->setArgs(['id' => $activityId]);

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
        $this->activityRepository
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn(null);

        $this->expectException(HttpNotFoundException::class);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testReadsActivityIdFromUrlParameter(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['name' => 'Updated']);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedId = null;
        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturnCallback(function ($id, $uid) use (&$capturedId, $activity) {
                $capturedId = $id;
                return $activity;
            });

        $this->activityRepository
            ->method('update');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals($activityId, $capturedId);
    }

    public function testReadsUserIdFromJwtAttribute(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['name' => 'Updated']);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $this->activityRepository
            ->method('update');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testHandlesNullParsedBody(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

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
            ->willReturn(['name' => 'Namn krävs']);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testConvertsKeysToLowercase(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $updateData = [
            'NAME' => 'Updated',
            'EMOJI' => '🚴'
        ];

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->with([
                'name' => 'Updated',
                'emoji' => '🚴'
            ])
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $this->activityRepository
            ->method('update');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRepositoryUpdateIsCalledExactlyOnce(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['name' => 'Updated']);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $this->activityRepository
            ->expects($this->once())
            ->method('update');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testResponseContainsUpdatedActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Löpning');

        $updateData = [
            'name' => 'Morgonlöpning',
            'emoji' => '🌅'
        ];

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $this->activityRepository
            ->method('update');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $body = json_decode((string)$response->getBody());

        $this->assertObjectHasProperty('activity', $body->data);
        $this->assertEquals('Morgonlöpning', $body->data->activity->name);
        $this->assertEquals('🌅', $body->data->activity->emoji);
    }

    public function testUpdatesBooleanFlags(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Test', true, true);

        $updateData = [
            'name' => 'Test',
            'log_distance' => false,
            'log_duration' => false,
            'distance_unit' => ''
        ];

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($updateData);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $capturedActivity = null;
        $this->activityRepository
            ->method('update')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertFalse($capturedActivity->getLogDistance());
        $this->assertFalse($capturedActivity->getLogTime());
    }

    public function testValidationIsCalledBeforeRepositoryAccess(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn(['name' => '']);

        $callOrder = [];

        $this->validator
            ->method('validateRegister')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'validate';
                return false;
            });

        $this->validator
            ->method('getErrors')
            ->willReturn(['name' => 'Error']);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'getActivity';
                return null;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        // Validering ska köras först, repository ska inte anropas vid valideringsfel
        $this->assertEquals(['validate'], $callOrder);
    }
}