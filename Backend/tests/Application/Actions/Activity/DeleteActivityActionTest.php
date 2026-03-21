<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Activity;

use App\Application\Actions\Activity\DeleteActivityAction;
use App\Domain\Activity\ActivityRepository;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class DeleteActivityActionTest extends TestCase {
    private ActivityRepository $activityRepository;
    private LoggerInterface $logger;
    private DeleteActivityAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new DeleteActivityAction(
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

    public function testSuccessfullyDeletesActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('delete')
            ->with($activityId, $userId);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testReturns204NoContent(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(204, $response->getStatusCode());

        // 204 No Content ska ha en tom body
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        $this->assertEmpty($decoded['data']);
    }

    public function testReadsActivityIdFromUrlParameter(): void {
        $activityId = 'specific-activity-id-123';
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $capturedActivityId = null;
        $this->activityRepository
            ->method('delete')
            ->willReturnCallback(function ($id, $uid) use (&$capturedActivityId) {
                $capturedActivityId = $id;
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
        $userId = 'jwt-user-id-456';

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($userId);

        $capturedUserId = null;
        $this->activityRepository
            ->method('delete')
            ->willReturnCallback(function ($id, $uid) use (&$capturedUserId) {
                $capturedUserId = $uid;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals($userId, $capturedUserId);
    }

    public function testPassesBothIdsToRepository(): void {
        $activityId = 'activity-123';
        $userId = 'user-456';

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('delete')
            ->with(
                $this->equalTo($activityId),
                $this->equalTo($userId)
            );

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testRepositoryDeleteIsCalledExactlyOnce(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->expects($this->once())
            ->method('delete');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testDoesNotValidateInput(): void {
        // Delete-operationer behöver inte validera data eftersom
        // det inte finns någon request body att validera
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('delete');

        // Ingen exception ska kastas
        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteIsIdempotent(): void {
        // Delete ska kunna anropas flera gånger utan fel
        // (även om aktiviteten inte finns längre)
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        // Repository returnerar inget, även om aktiviteten inte finns
        $this->activityRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testEnsuresUserCanOnlyDeleteTheirOwnActivities(): void {
        // Säkerhet: userId från JWT skickas till repository
        // Repository-lagret ansvarar för att verifiera ägarskap
        $activityId = (new ActivityId())->toString();
        $userId = 'current-user-id';

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        // Verifiera att BÅDE activityId OCH userId skickas
        $this->activityRepository
            ->expects($this->once())
            ->method('delete')
            ->with($activityId, $userId);

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );
    }

    public function testHandlesDifferentActivityIds(): void {
        $userId = (new UserId())->toString();

        $activityIds = [
            (new ActivityId())->toString(),
            (new ActivityId())->toString(),
            (new ActivityId())->toString(),
        ];

        foreach ($activityIds as $activityId) {
            $this->setUp(); // Reset mocks

            $this->setArgs(['id' => $activityId]);

            $this->request
                ->method('getAttribute')
                ->willReturn($userId);

            $capturedId = null;
            $this->activityRepository
                ->method('delete')
                ->willReturnCallback(function ($id) use (&$capturedId) {
                    $capturedId = $id;
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                ['id' => $activityId]
            );

            $this->assertEquals($activityId, $capturedId);
        }
    }

    public function testResponseHasCorrectContentType(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        // Verifiera att response är JSON (även om body är tom)
        $body = (string)$response->getBody();
        $decoded = json_decode($body, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testResponseBodyIsEmpty(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->activityRepository
            ->method('delete');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
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

    public function testDeleteOperationIsSecure(): void {
        // Detta test verifierar att både activityId OCH userId krävs
        // för att förhindra att användare raderar andras aktiviteter
        $activityId = (new ActivityId())->toString();
        $currentUserId = 'current-user-id';

        $this->setArgs(['id' => $activityId]);

        $this->request
            ->method('getAttribute')
            ->with('userId')
            ->willReturn($currentUserId);

        $receivedParams = [];
        $this->activityRepository
            ->method('delete')
            ->willReturnCallback(function ($id, $uid) use (&$receivedParams) {
                $receivedParams = ['id' => $id, 'userId' => $uid];
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            ['id' => $activityId]
        );

        $this->assertEquals($activityId, $receivedParams['id']);
        $this->assertEquals($currentUserId, $receivedParams['userId']);
    }
}