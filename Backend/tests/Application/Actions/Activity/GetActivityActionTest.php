<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Activity;

use App\Application\Actions\Activity\GetActivityAction;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Psr7\Factory\ResponseFactory;

class GetActivityActionTest extends TestCase {
    private ActivityRepository $activityRepository;
    private LoggerInterface $logger;
    private GetActivityAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new GetActivityAction(
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

        // Set args property for resolveArg to work
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

    private function createTestActivity(?string $activityId = null, ?string $userId = null): Activity {
        return new Activity(
            $activityId ? new ActivityId($activityId) : new ActivityId(),
            $userId ? new UserId($userId) : new UserId(),
            '🏃',
            'Löpning',
            true,
            true,
            'km'
        );
    }

    public function testSuccessfullyReturnsActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn($activity);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody());
        $this->assertObjectHasProperty('activity', $body->data);
        $this->assertEquals($activityId, $body->data->activity->id);
        $this->assertEquals($userId, $body->data->activity->userId);
    }

    public function testThrowsNotFoundExceptionWhenActivityNotFound(): void {
        $activityId = 'non-existent-activity-id';
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn(null);

        $this->expectException(\Slim\Exception\HttpNotFoundException::class);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testThrowsNotFoundExceptionForWrongUser(): void {
        $activityId = (new ActivityId())->toString();
        $userId = 'current-user-id';

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        // Repository returnerar null eftersom userId inte matchar
        $this->activityRepository
            ->expects($this->once())
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn(null);

        $this->expectException(\Slim\Exception\HttpNotFoundException::class);

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

        $capturedActivityId = null;
        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturnCallback(function ($id, $uid) use (&$capturedActivityId, $activity) {
                $capturedActivityId = $id;
                return $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals($activityId, $capturedActivityId);
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

        $capturedUserId = null;
        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturnCallback(function ($id, $uid) use (&$capturedUserId, $activity) {
                $capturedUserId = $uid;
                return $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals($userId, $capturedUserId);
    }

    public function testPassesBothIdsToRepository(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getActivityForUser')
            ->with(
                $this->equalTo($activityId),
                $this->equalTo($userId)
            )
            ->willReturn($activity);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('getActivityForUser')
            ->willReturn($activity);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testResponseContainsActivityObject(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $body = json_decode((string)$response->getBody());

        $this->assertObjectHasProperty('data', $body);
        $this->assertObjectHasProperty('activity', $body->data);
        $this->assertIsObject($body->data->activity);
    }

    public function testResponseContainsAllActivityFields(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = new Activity(
            new ActivityId($activityId),
            new UserId($userId),
            '🏋️',
            'Styrketräning',
            false,
            true,
            'kg'
        );

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $body = json_decode((string)$response->getBody());
        $activityData = $body->data->activity;

        $this->assertEquals($activityId, $activityData->id);
        $this->assertEquals($userId, $activityData->userId);
        $this->assertEquals('🏋️', $activityData->emoji);
        $this->assertEquals('Styrketräning', $activityData->name);
        $this->assertFalse($activityData->log_distance);
        $this->assertTrue($activityData->log_duration);
        $this->assertEquals('kg', $activityData->distance_unit);
    }


    public function testThrowsExceptionWhenIdParameterMissing(): void {
        $userId = (new UserId())->toString();

        // Args saknar 'id'
        $this->setArgs([]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->expectException(HttpBadRequestException::class);
        $this->expectExceptionMessage('Could not resolve argument `id`');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testResponseIsJson(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('getActivityForUser')
            ->willReturn($activity);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $body = (string)$response->getBody();

        $decoded = json_decode($body);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testHandlesActivityWithEmoji(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $emojis = ['🏃', '🚴', '🏊', '🧘', '🏋️'];

        foreach ($emojis as $emoji) {
            $this->setUp(); // Reset mocks

            $activity = new Activity(
                new ActivityId($activityId),
                new UserId($userId),
                $emoji,
                'Test',
                true,
                true,
                'km'
            );

            $this->setArgs(['id' => $activityId]);

            $this->request
                ->method('getAttribute')
                ->willReturn($userId);

            $this->activityRepository
                ->method('getActivityForUser')
                ->willReturn($activity);

            $response = $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                ['id' => $activityId]
            );

            $body = json_decode((string)$response->getBody());
            $this->assertEquals($emoji, $body->data->activity->emoji, "Failed for emoji: $emoji");
        }
    }

    public function testEnsuresUserCanOnlyAccessTheirOwnActivities(): void {
        // Detta är implicit genom att skicka userId till repository
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();
        $activity = $this->createTestActivity($activityId, $userId);

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        // Repository får BÅDE activityId OCH userId
        $this->activityRepository
            ->expects($this->once())
            ->method('getActivityForUser')
            ->with($activityId, $userId)
            ->willReturn($activity);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }
}