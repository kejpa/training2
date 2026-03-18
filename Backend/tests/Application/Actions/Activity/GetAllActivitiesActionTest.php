<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Activity;

use App\Application\Actions\Activity\GetAllActivitiesAction;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class GetAllActivitiesActionTest extends TestCase {
    private ActivityRepository $activityRepository;
    private LoggerInterface $logger;
    private GetAllActivitiesAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new GetAllActivitiesAction(
            $this->logger,
            $this->activityRepository
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

    private function createTestActivity(
        ?string $activityId = null,
        ?string $userId = null,
        string $emoji = '🏃',
        string $name = 'Löpning'
    ): Activity {
        return new Activity(
            $activityId ? new ActivityId($activityId) : new ActivityId(),
            $userId ? new UserId($userId) : new UserId(),
            $emoji,
            $name,
            true,
            true,
            'km'
        );
    }

    public function testSuccessfullyReturnsActivities(): void {
        $userId = (new UserId())->toString();

        $activities = [
            $this->createTestActivity(null, $userId, '🏃', 'Löpning'),
            $this->createTestActivity(null, $userId, '🚴', 'Cykling'),
            $this->createTestActivity(null, $userId, '🏊', 'Simning'),
        ];

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getAllForUser')
            ->with($userId)
            ->willReturn($activities);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody());
        $this->assertObjectHasProperty('activities', $body->data);
        $this->assertIsArray($body->data->activities);
        $this->assertCount(3, $body->data->activities);
    }

    public function testReturnsEmptyArrayWhenNoActivities(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getAllForUser')
            ->with($userId)
            ->willReturn([]);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody());
        $this->assertObjectHasProperty('activities', $body->data);
        $this->assertIsArray($body->data->activities);
        $this->assertEmpty($body->data->activities);
    }

    public function testReadsUserIdFromJwtAttribute(): void {
        $userId = 'jwt-user-id-123';

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $capturedUserId = null;
        $this->activityRepository
            ->method('getAllForUser')
            ->willReturnCallback(function ($uid) use (&$capturedUserId) {
                $capturedUserId = $uid;
                return [];
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals($userId, $capturedUserId);
    }

    public function testPassesUserIdToRepository(): void {
        $userId = 'test-user-456';

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getAllForUser')
            ->with($this->equalTo($userId))
            ->willReturn([]);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getAllForUser')
            ->willReturn([]);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testResponseContainsActivitiesArray(): void {
        $userId = (new UserId())->toString();
        $activities = [$this->createTestActivity(null, $userId)];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn($activities);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertObjectHasProperty('data', $body);
        $this->assertObjectHasProperty('activities', $body->data);
        $this->assertIsArray($body->data->activities);
    }

    public function testEachActivityContainsAllFields(): void {
        $userId = (new UserId())->toString();
        $activityId = (new ActivityId())->toString();

        $activity = new Activity(
            new ActivityId($activityId),
            new UserId($userId),
            '🏋️',
            'Styrketräning',
            false,
            true,
            'kg'
        );

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn([$activity]);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());
        $activityData = $body->data->activities[0];

        $this->assertEquals($activityId, $activityData->id);
        $this->assertEquals($userId, $activityData->userId);
        $this->assertEquals('🏋️', $activityData->emoji);
        $this->assertEquals('Styrketräning', $activityData->name);
        $this->assertFalse($activityData->log_distance);
        $this->assertTrue($activityData->log_time);
        $this->assertEquals('kg', $activityData->distance_unit);
    }

    public function testReturnsMultipleActivitiesInCorrectFormat(): void {
        $userId = (new UserId())->toString();

        $activities = [
            $this->createTestActivity(null, $userId, '🏃', 'Löpning'),
            $this->createTestActivity(null, $userId, '🚴', 'Cykling'),
            $this->createTestActivity(null, $userId, '🏊', 'Simning'),
            $this->createTestActivity(null, $userId, '🧘', 'Yoga'),
            $this->createTestActivity(null, $userId, '🏋️', 'Styrka'),
        ];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn($activities);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertCount(5, $body->data->activities);

        $emojis = array_map(fn($a) => $a->emoji, $body->data->activities);
        $this->assertEquals(['🏃', '🚴', '🏊', '🧘', '🏋️'], $emojis);
    }

    public function testHandlesLargeNumberOfActivities(): void {
        $userId = (new UserId())->toString();

        $activities = [];
        for ($i = 0; $i < 100; $i++) {
            $activities[] = $this->createTestActivity(null, $userId, '🏃', "Aktivitet $i");
        }

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn($activities);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertCount(100, $body->data->activities);
    }

    public function testResponseIsJson(): void {
        $userId = (new UserId())->toString();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn([]);

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

    public function testHandlesActivitiesWithDifferentEmojis(): void {
        $userId = (new UserId())->toString();
        $emojis = ['🏃', '🚴', '🏊', '🧘', '🏋️', '⚽', '🏀', '💪'];

        $activities = array_map(
            fn($emoji) => $this->createTestActivity(null, $userId, $emoji, 'Test'),
            $emojis
        );

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn($activities);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertCount(count($emojis), $body->data->activities);

        foreach ($body->data->activities as $index => $activity) {
            $this->assertEquals($emojis[$index], $activity->emoji);
        }
    }

    public function testOnlyReturnsActivitiesForSpecificUser(): void {
        $userId = 'specific-user-id';

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        // Repository ska få exakt detta userId
        $this->activityRepository
            ->expects($this->once())
            ->method('getAllForUser')
            ->with($userId)
            ->willReturn([]);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testHandlesActivitiesWithDifferentBooleanFlags(): void {
        $userId = (new UserId())->toString();

        $activities = [
            new Activity(new ActivityId(), new UserId($userId), '🏃', 'Löpning', true, true, 'km'),
            new Activity(new ActivityId(), new UserId($userId), '🧘', 'Yoga', false, true, 'min'),
            new Activity(new ActivityId(), new UserId($userId), '🏋️', 'Styrka', false, false, ''),
        ];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn($activities);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());
        $returnedActivities = $body->data->activities;

        $this->assertTrue($returnedActivities[0]->log_distance);
        $this->assertTrue($returnedActivities[0]->log_time);

        $this->assertFalse($returnedActivities[1]->log_distance);
        $this->assertTrue($returnedActivities[1]->log_time);

        $this->assertFalse($returnedActivities[2]->log_distance);
        $this->assertFalse($returnedActivities[2]->log_time);
    }

    public function testResponseStructureIsCorrect(): void {
        $userId = (new UserId())->toString();
        $activities = [$this->createTestActivity(null, $userId)];

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getAllForUser')
            ->willReturn($activities);

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
        $this->assertArrayHasKey('activities', $body['data']);
        $this->assertIsArray($body['data']['activities']);
    }
}