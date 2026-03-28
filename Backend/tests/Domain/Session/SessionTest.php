<?php

declare(strict_types=1);

namespace Tests\Domain\Session;

use App\Domain\Session\Session;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

class SessionTest extends TestCase {

    private function createTestSession(
        ?SessionId $id = null,
        ?UserId $userId = null,
        ?ActivityId $activityId = null,
        string $date = '2024-01-01',
        ?string $duration = '01:00:00',
        ?float $distance = 10.5,
        string $description = 'Test pass',
        int $rpe = 5
    ): Session {
        return new Session(
            $id ?? new SessionId(),
            $userId ?? new UserId(),
            $activityId ?? new ActivityId(),
            new DateTimeImmutable($date),
            $duration,
            $distance,
            $description,
            $rpe
        );
    }

    public function testCanBeCreated(): void {
        $session = $this->createTestSession();

        $this->assertInstanceOf(Session::class, $session);
    }

    public function testGetters(): void {
        $session = $this->createTestSession();

        $this->assertInstanceOf(SessionId::class, $session->getId());
        $this->assertInstanceOf(UserId::class, $session->getUserId());
        $this->assertInstanceOf(ActivityId::class, $session->getActivityId());
        $this->assertEquals('2024-01-01', $session->getDate()->format('Y-m-d'));
        $this->assertEquals('01:00:00', $session->getDuration());
        $this->assertEquals(10.5, $session->getDistance());
        $this->assertEquals('Test pass', $session->getDescription());
        $this->assertEquals(5, $session->getRpe());
    }

    public function testSetters(): void {
        $session = $this->createTestSession();

        $newId = new SessionId();
        $newUserId = new UserId();
        $newActivityId = new ActivityId();

        $session->setId($newId);
        $session->setUserId($newUserId);
        $session->setActivityId($newActivityId);
        $session->setDate(new DateTimeImmutable('2025-01-01'));
        $session->setDuration('02:00:00');
        $session->setDistance(21.0);
        $session->setDescription('Updated');
        $session->setRpe(8);

        $this->assertSame($newId, $session->getId());
        $this->assertSame($newUserId, $session->getUserId());
        $this->assertSame($newActivityId, $session->getActivityId());
        $this->assertEquals('2025-01-01', $session->getDate()->format('Y-m-d'));
        $this->assertEquals('02:00:00', $session->getDuration());
        $this->assertEquals(21.0, $session->getDistance());
        $this->assertEquals('Updated', $session->getDescription());
        $this->assertEquals(8, $session->getRpe());
    }

    public function testFromRow(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $activityId = (new ActivityId())->toString();

        $row = [
            'id' => $sessionId,
            'userid' => $userId,
            'activityid' => $activityId,
            'date' => '2024-01-01',
            'duration' => '01:00:00',
            'distance' => 10.5,
            'description' => 'Test pass',
            'rpe' => 5,
        ];

        $session = Session::fromRow($row);

        $this->assertInstanceOf(Session::class, $session);
        $this->assertEquals($sessionId, $session->getId()->toString());
        $this->assertEquals($userId, $session->getUserId()->toString());
        $this->assertEquals($activityId, $session->getActivityId()->toString());
        $this->assertEquals('2024-01-01', $session->getDate()->format('Y-m-d'));
    }

    public function testState(): void {
        $session = $this->createTestSession();

        $state = $session->state();

        $this->assertIsArray($state);
        $this->assertArrayHasKey('id', $state);
        $this->assertArrayHasKey('userid', $state);
        $this->assertArrayHasKey('activityid', $state);
        $this->assertArrayHasKey('date', $state);
        $this->assertArrayHasKey('duration', $state);
        $this->assertArrayHasKey('distance', $state);
        $this->assertArrayHasKey('description', $state);
        $this->assertArrayHasKey('rpe', $state);
    }

    public function testStateReturnsCorrectValues(): void {
        $session = $this->createTestSession();

        $state = $session->state();

        $this->assertEquals($session->getId()->toString(), $state['id']);
        $this->assertEquals($session->getUserId()->toString(), $state['userid']);
        $this->assertEquals($session->getActivityId()->toString(), $state['activityid']);
        $this->assertEquals('2024-01-01', $state['date']);
        $this->assertEquals('01:00:00', $state['duration']);
        $this->assertEquals(10.5, $state['distance']);
        $this->assertEquals('Test pass', $state['description']);
        $this->assertEquals(5, $state['rpe']);
    }

    public function testJsonSerialize(): void {
        $session = $this->createTestSession();

        $json = $session->jsonSerialize();

        $this->assertInstanceOf(stdClass::class, $json);
        $this->assertEquals($session->getId()->toString(), $json->id);
        $this->assertEquals($session->getUserId()->toString(), $json->userid);
        $this->assertEquals($session->getActivityId()->toString(), $json->activityid);
        $this->assertEquals('2024-01-01', $json->date);
        $this->assertEquals('01:00:00', $json->duration);
        $this->assertEquals(10.5, $json->distance);
        $this->assertEquals('Test pass', $json->description);
        $this->assertEquals(5, $json->rpe);
    }

    public function testJsonSerializeHasAllProperties(): void {
        $json = $this->createTestSession()->jsonSerialize();

        $this->assertObjectHasProperty('id', $json);
        $this->assertObjectHasProperty('userid', $json);
        $this->assertObjectHasProperty('activityid', $json);
        $this->assertObjectHasProperty('date', $json);
        $this->assertObjectHasProperty('duration', $json);
        $this->assertObjectHasProperty('distance', $json);
        $this->assertObjectHasProperty('description', $json);
        $this->assertObjectHasProperty('rpe', $json);
    }

    public function testJsonSerializeCanBeEncoded(): void {
        $json = $this->createTestSession()->jsonSerialize();

        $encoded = json_encode($json);

        $this->assertNotFalse($encoded);
        $this->assertJson($encoded);
    }

    public function testStateAndJsonSerializeHaveSameData(): void {
        $session = $this->createTestSession();

        $state = $session->state();
        $json = $session->jsonSerialize();

        $this->assertEquals($state['id'], $json->id);
        $this->assertEquals($state['userid'], $json->userid);
        $this->assertEquals($state['activityid'], $json->activityid);
        $this->assertEquals($state['date'], $json->date);
        $this->assertEquals($state['duration'], $json->duration);
        $this->assertEquals($state['distance'], $json->distance);
        $this->assertEquals($state['description'], $json->description);
        $this->assertEquals($state['rpe'], $json->rpe);
    }

    /**
     * @dataProvider durationAndDistanceProvider
     */
    public function testHandlesNullableFields(?string $duration, ?float $distance): void {
        $session = $this->createTestSession(
            duration: $duration,
            distance: $distance
        );

        $this->assertSame($duration, $session->getDuration());
        $this->assertSame($distance, $session->getDistance());

        $state = $session->state();
        $this->assertSame($duration, $state['duration']);
        $this->assertSame($distance, $state['distance']);

        $json = $session->jsonSerialize();
        $this->assertSame($duration, $json->duration);
        $this->assertSame($distance, $json->distance);
    }

    public static function durationAndDistanceProvider(): array {
        return [
            'both set' => ['01:00:00', 10.5],
            'no duration' => [null, 10.5],
            'no distance' => ['01:00:00', null],
            'both null' => [null, null],
        ];
    }

    /**
     * @dataProvider rpeProvider
     */
    public function testHandlesDifferentRpeValues(int $rpe): void {
        $session = $this->createTestSession(rpe: $rpe);

        $this->assertEquals($rpe, $session->getRpe());

        $state = $session->state();
        $this->assertEquals($rpe, $state['rpe']);

        $json = $session->jsonSerialize();
        $this->assertEquals($rpe, $json->rpe);
    }

    public static function rpeProvider(): array {
        return [
            'low' => [1],
            'medium' => [5],
            'high' => [10],
        ];
    }
}