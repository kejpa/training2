<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Activity;

use App\Application\Actions\Activity\AddActivityAction;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityValidator;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class AddActivityActionTest extends TestCase {
    private ActivityRepository $activityRepository;
    private ActivityValidator $validator;
    private LoggerInterface $logger;
    private AddActivityAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->validator = $this->createMock(ActivityValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new AddActivityAction(
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
    }

    private function createTestActivityData(): array {
        return [
            'emoji' => '🏃',
            'name' => 'Löpning',
            'log_distance' => true,
            'log_duration' => true,
            'distance_unit' => 'km'
        ];
    }

    public function testSuccessfullyAddsActivity(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestActivityData();

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

        $capturedActivity = null;
        $this->activityRepository
            ->expects($this->once())
            ->method('add')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att activity skapades med rätt data
        $this->assertNotNull($capturedActivity);
        $this->assertInstanceOf(Activity::class, $capturedActivity);
        $this->assertEquals($userId, $capturedActivity->getUserId()->toString());
        $this->assertEquals('🏃', $capturedActivity->getEmoji());
        $this->assertEquals('Löpning', $capturedActivity->getName());
        $this->assertTrue($capturedActivity->getLogDistance());
        $this->assertTrue($capturedActivity->getLogTime());
        $this->assertEquals('km', $capturedActivity->getDistanceUnit());
    }

    public function testAddsUserIdToActivity(): void {
        $userId = (new UserId())->toString();
        $data = [
            'emoji' => '🚴',
            'name' => 'Cykling',
            'log_distance' => true,
            'log_duration' => false,
            'distance_unit' => 'km'
        ];

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

        $capturedActivity = null;
        $this->activityRepository
            ->method('add')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals($userId, $capturedActivity->getUserId()->toString());
    }

    public function testGeneratesActivityId(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestActivityData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $capturedActivity = null;
        $this->activityRepository
            ->method('add')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        // Verifiera att ett ID genererades
        $this->assertNotNull($capturedActivity->getId());
        $this->assertInstanceOf(ActivityId::class, $capturedActivity->getId());

        // Verifiera UUID-format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $capturedActivity->getId()->toString()
        );
    }

    public function testReturnsValidationErrors(): void {
        $userId = (new UserId())->toString();
        $data = [
            'emoji' => '',
            'name' => '',
            'log_distance' => true,
            'log_duration' => true,
            'distance_unit' => ''
        ];

        $errors = [
            'emoji' => 'Emoji krävs',
            'name' => 'Namn krävs',
            'distance_unit' => 'Avståndsenhet krävs'
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

        $this->activityRepository
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
            'EMOJI' => '🏃',
            'NAME' => 'Löpning',
            'LOG_DISTANCE' => true,
            'LOG_DURATION' => true,
            'DISTANCE_UNIT' => 'km'
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
                'emoji' => '🏃',
                'name' => 'Löpning',
                'log_distance' => true,
                'log_duration' => true,
                'distance_unit' => 'km'
            ])
            ->willReturn(true);

        $this->activityRepository
            ->method('add');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReturnsActivityInResponse(): void {
        $userId = (new UserId())->toString();
        $data = [
            'emoji' => '🏋️',
            'name' => 'Styrketräning',
            'log_distance' => false,
            'log_duration' => true,
            'distance_unit' => 'km'
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

        $this->activityRepository
            ->method('add');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $body = json_decode((string)$response->getBody());

        $this->assertIsObject($body->data);

        // Verifiera att activity-data finns i response (jsonSerialize returnerar stdClass)
        $this->assertObjectHasProperty('id', $body->data);
        $this->assertObjectHasProperty('userId', $body->data);
        $this->assertObjectHasProperty('emoji', $body->data);
        $this->assertObjectHasProperty('name', $body->data);
        $this->assertEquals('🏋️', $body->data->emoji);
        $this->assertEquals('Styrketräning', $body->data->name);
    }

    public function testRepositoryIsCalledExactlyOnce(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestActivityData();

        $this->request
            ->method('getAttribute')
            ->willReturn($userId);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateRegister')
            ->willReturn(true);

        $this->activityRepository
            ->expects($this->once())
            ->method('add');

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );
    }

    public function testHandlesEmojiCorrectly(): void
    {
        $emojis = ['🏃', '🚴', '🏊', '🧘', '🏋️', '⚽', '🏀', '💪'];

        foreach ($emojis as $emoji) {
            // Återskapa action och mocks för varje iteration
            $this->setUp();

            $userId = (new UserId())->toString();
            $data = [
                'emoji' => $emoji,
                'name' => 'Test',
                'log_distance' => true,
                'log_duration' => true,
                'distance_unit' => 'km'
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

            $capturedActivity = null;
            $this->activityRepository
                ->method('add')
                ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                    $capturedActivity = $activity;
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                []
            );

            $this->assertEquals($emoji, $capturedActivity->getEmoji(), "Failed for emoji: $emoji");
        }
    }
    public function testActivityCreatedWithAllFields(): void {
        $userId = (new UserId())->toString();
        $data = [
            'emoji' => '🏃',
            'name' => 'Löpning',
            'log_distance' => true,
            'log_duration' => false,
            'distance_unit' => 'km'
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

        $capturedActivity = null;
        $this->activityRepository
            ->method('add')
            ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                $capturedActivity = $activity;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertNotNull($capturedActivity->getId());
        $this->assertEquals($userId, $capturedActivity->getUserId()->toString());
        $this->assertEquals('🏃', $capturedActivity->getEmoji());
        $this->assertEquals('Löpning', $capturedActivity->getName());
        $this->assertTrue($capturedActivity->getLogDistance());
        $this->assertFalse($capturedActivity->getLogTime());
        $this->assertEquals('km', $capturedActivity->getDistanceUnit());
    }

    public function testHandlesBooleanFlags(): void
    {
        $testCases = [
            ['log_distance' => true, 'log_duration' => true],
            ['log_distance' => true, 'log_duration' => false],
            ['log_distance' => false, 'log_duration' => true],
            ['log_distance' => false, 'log_duration' => false],
        ];

        foreach ($testCases as $flags) {
            // Återskapa action och mocks för varje iteration
            $this->setUp();

            $userId = (new UserId())->toString();
            $data = [
                'emoji' => '🏃',
                'name' => 'Test',
                'log_distance' => $flags['log_distance'],
                'log_duration' => $flags['log_duration'],
                'distance_unit' => 'km'
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

            $capturedActivity = null;
            $this->activityRepository
                ->method('add')
                ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                    $capturedActivity = $activity;
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                []
            );

            $this->assertEquals($flags['log_distance'], $capturedActivity->getLogDistance());
            $this->assertEquals($flags['log_duration'], $capturedActivity->getLogTime());
        }
    }
    public function testHandlesDifferentDistanceUnits(): void
    {
        $units = ['km', 'mi', 'm', 'yd'];

        foreach ($units as $unit) {
            // Återskapa action och mocks för varje iteration
            $this->setUp();

            $userId = (new UserId())->toString();
            $data = [
                'emoji' => '🏃',
                'name' => 'Test',
                'log_distance' => true,
                'log_duration' => true,
                'distance_unit' => $unit
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

            $capturedActivity = null;
            $this->activityRepository
                ->method('add')
                ->willReturnCallback(function ($activity) use (&$capturedActivity) {
                    $capturedActivity = $activity;
                });

            $this->action->__invoke(
                $this->request,
                $this->responseFactory->createResponse(),
                []
            );

            $this->assertEquals($unit, $capturedActivity->getDistanceUnit(), "Failed for unit: $unit");
        }
    }
    public function testValidationIsCalledBeforeRepositoryAdd(): void {
        $userId = (new UserId())->toString();
        $data = $this->createTestActivityData();

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

        $this->activityRepository
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
}