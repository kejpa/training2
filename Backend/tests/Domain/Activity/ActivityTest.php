<?php

declare(strict_types=1);

namespace Tests\Domain\Activity;

use App\Domain\Activity\Activity;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

class ActivityTest extends TestCase {
    private function createTestActivity(
        ?ActivityId $id = null,
        ?UserId $userId = null,
        string $emoji = '🏃',
        string $name = 'Löpning',
        bool $logDistance = true,
        bool $logTime = true,
        string $distanceUnit = 'km'
    ): Activity {
        return new Activity(
            $id,
            $userId ?? new UserId(),
            $emoji,
            $name,
            $logDistance,
            $logTime,
            $distanceUnit
        );
    }

    public function testCanBeCreated(): void {
        $userId = new UserId();
        $activity = new Activity(
            null,
            $userId,
            '🏃',
            'Löpning',
            true,
            true,
            'km'
        );

        $this->assertInstanceOf(Activity::class, $activity);
    }

    public function testGeneratesIdWhenNull(): void {
        $activity = $this->createTestActivity(null);

        $this->assertNotNull($activity->getId());
        $this->assertInstanceOf(ActivityId::class, $activity->getId());
    }

    public function testUsesProvidedId(): void {
        $id = new ActivityId();
        $activity = $this->createTestActivity($id);

        $this->assertSame($id, $activity->getId());
    }

    public function testGetUserId(): void {
        $userId = new UserId();
        $activity = $this->createTestActivity(null, $userId);

        $this->assertSame($userId, $activity->getUserId());
    }

    public function testSetUserId(): void {
        $activity = $this->createTestActivity();
        $newUserId = new UserId();

        $activity->setUserId($newUserId);

        $this->assertSame($newUserId, $activity->getUserId());
    }

    public function testGetEmoji(): void {
        $activity = $this->createTestActivity(null, null, '🚴');

        $this->assertEquals('🚴', $activity->getEmoji());
    }

    public function testSetEmoji(): void {
        $activity = $this->createTestActivity();

        $activity->setEmoji('🏊');

        $this->assertEquals('🏊', $activity->getEmoji());
    }

    public function testGetName(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Morgonlöpning');

        $this->assertEquals('Morgonlöpning', $activity->getName());
    }

    public function testSetName(): void {
        $activity = $this->createTestActivity();

        $activity->setName('Kvällslöpning');

        $this->assertEquals('Kvällslöpning', $activity->getName());
    }

    public function testGetLogDistance(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', true);

        $this->assertTrue($activity->getLogDistance());
    }

    public function testSetLogDistance(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', true);

        $activity->setLogDistance(false);

        $this->assertFalse($activity->getLogDistance());
    }

    public function testGetLogTime(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', true, false);

        $this->assertFalse($activity->getLogTime());
    }

    public function testSetLogTime(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', true, false);

        $activity->setLogTime(true);

        $this->assertTrue($activity->getLogTime());
    }

    public function testGetDistanceUnit(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', true, true, 'mi');

        $this->assertEquals('mi', $activity->getDistanceUnit());
    }

    public function testSetDistanceUnit(): void {
        $activity = $this->createTestActivity();

        $activity->setDistanceUnit('mi');

        $this->assertEquals('mi', $activity->getDistanceUnit());
    }

    public function testFromRow(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $row = [
            'id' => $activityId,
            'userid' => $userId,
            'emoji' => '🏃',
            'name' => 'Löpning',
            'log_distance' => true,
            'log_time' => false,
            'distance_unit' => 'km'
        ];

        $activity = Activity::fromRow($row);

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertEquals($activityId, $activity->getId()->toString());
        $this->assertEquals($userId, $activity->getUserId()->toString());
        $this->assertEquals('🏃', $activity->getEmoji());
        $this->assertEquals('Löpning', $activity->getName());
        $this->assertTrue($activity->getLogDistance());
        $this->assertFalse($activity->getLogTime());
        $this->assertEquals('km', $activity->getDistanceUnit());
    }

    public function testFromRowWithDifferentData(): void {
        $row = [
            'id' => (new ActivityId())->toString(),
            'userid' => (new UserId())->toString(),
            'emoji' => '🚴',
            'name' => 'Cykling',
            'log_distance' => false,
            'log_time' => true,
            'distance_unit' => 'mi'
        ];

        $activity = Activity::fromRow($row);

        $this->assertEquals('🚴', $activity->getEmoji());
        $this->assertEquals('Cykling', $activity->getName());
        $this->assertFalse($activity->getLogDistance());
        $this->assertTrue($activity->getLogTime());
        $this->assertEquals('mi', $activity->getDistanceUnit());
    }

    public function testState(): void {
        $activityId = new ActivityId();
        $userId = new UserId();

        $activity = new Activity(
            $activityId,
            $userId,
            '🏃',
            'Löpning',
            true,
            false,
            'km'
        );

        $state = $activity->state();

        $this->assertIsArray($state);
        $this->assertArrayHasKey('id', $state);
        $this->assertArrayHasKey('userid', $state);
        $this->assertArrayHasKey('emoji', $state);
        $this->assertArrayHasKey('name', $state);
        $this->assertArrayHasKey('log_distance', $state);
        $this->assertArrayHasKey('log_time', $state);
        $this->assertArrayHasKey('distance_unit', $state);

        $this->assertEquals($activityId->toString(), $state['id']);
        $this->assertEquals($userId->toString(), $state['userid']);
        $this->assertEquals('🏃', $state['emoji']);
        $this->assertEquals('Löpning', $state['name']);
        $this->assertTrue($state['log_distance']);
        $this->assertFalse($state['log_time']);
        $this->assertEquals('km', $state['distance_unit']);
    }

    public function testStateReturnsCorrectTypes(): void {
        $activity = $this->createTestActivity();
        $state = $activity->state();

        $this->assertIsString($state['id']);
        $this->assertIsString($state['userid']);
        $this->assertIsString($state['emoji']);
        $this->assertIsString($state['name']);
        $this->assertIsBool($state['log_distance']);
        $this->assertIsBool($state['log_time']);
        $this->assertIsString($state['distance_unit']);
    }

    public function testJsonSerialize(): void {
        $activityId = new ActivityId();
        $userId = new UserId();

        $activity = new Activity(
            $activityId,
            $userId,
            '🏋️',
            'Styrketräning',
            false,
            true,
            'kg'
        );

        $json = $activity->jsonSerialize();

        $this->assertInstanceOf(\stdClass::class, $json);
        $this->assertEquals($activityId->toString(), $json->id);
        $this->assertEquals($userId->toString(), $json->userId);
        $this->assertEquals('🏋️', $json->emoji);
        $this->assertEquals('Styrketräning', $json->name);
        $this->assertFalse($json->log_distance);
        $this->assertTrue($json->log_time);
        $this->assertEquals('kg', $json->distance_unit);
    }

    public function testJsonSerializeHasAllProperties(): void {
        $activity = $this->createTestActivity();
        $json = $activity->jsonSerialize();

        $this->assertObjectHasProperty('id', $json);
        $this->assertObjectHasProperty('userId', $json);
        $this->assertObjectHasProperty('emoji', $json);
        $this->assertObjectHasProperty('name', $json);
        $this->assertObjectHasProperty('log_distance', $json);
        $this->assertObjectHasProperty('log_time', $json);
        $this->assertObjectHasProperty('distance_unit', $json);
    }

    public function testJsonSerializeCanBeEncoded(): void {
        $activity = $this->createTestActivity();
        $json = $activity->jsonSerialize();

        $encoded = json_encode($json);

        $this->assertNotFalse($encoded);
        $this->assertJson($encoded);
    }

    public function testJsonSerializeWithEmoji(): void {
        $emojis = ['🏃', '🚴', '🏊', '🧘', '🏋️', '⚽', '🏀', '💪'];

        foreach ($emojis as $emoji) {
            $activity = $this->createTestActivity(null, null, $emoji);
            $json = $activity->jsonSerialize();

            $this->assertEquals($emoji, $json->emoji);

            // Verifiera att emoji kan JSON-encodas
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString($emoji, $encoded);
        }
    }

    public function testStateAndJsonSerializeHaveSameData(): void {
        $activity = $this->createTestActivity();

        $state = $activity->state();
        $json = $activity->jsonSerialize();

        $this->assertEquals($state['id'], $json->id);
        $this->assertEquals($state['userid'], $json->userId);
        $this->assertEquals($state['emoji'], $json->emoji);
        $this->assertEquals($state['name'], $json->name);
        $this->assertEquals($state['log_distance'], $json->log_distance);
        $this->assertEquals($state['log_time'], $json->log_time);
        $this->assertEquals($state['distance_unit'], $json->distance_unit);
    }

    /**
     * @dataProvider booleanCombinationsProvider
     */
    public function testHandlesDifferentBooleanCombinations(bool $logDistance, bool $logTime): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', $logDistance, $logTime);

        $this->assertEquals($logDistance, $activity->getLogDistance());
        $this->assertEquals($logTime, $activity->getLogTime());

        // Verifiera i state
        $state = $activity->state();
        $this->assertEquals($logDistance, $state['log_distance']);
        $this->assertEquals($logTime, $state['log_time']);

        // Verifiera i JSON
        $json = $activity->jsonSerialize();
        $this->assertEquals($logDistance, $json->log_distance);
        $this->assertEquals($logTime, $json->log_time);
    }

    public static function booleanCombinationsProvider(): array {
        return [
            'both true' => [true, true],
            'distance only' => [true, false],
            'time only' => [false, true],
            'both false' => [false, false],
        ];
    }

    /**
     * @dataProvider distanceUnitProvider
     */
    public function testHandlesDifferentDistanceUnits(string $unit): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test', true, true, $unit);

        $this->assertEquals($unit, $activity->getDistanceUnit());

        $state = $activity->state();
        $this->assertEquals($unit, $state['distance_unit']);

        $json = $activity->jsonSerialize();
        $this->assertEquals($unit, $json->distance_unit);
    }

    public static function distanceUnitProvider(): array {
        return [
            'kilometers' => ['km'],
            'miles' => ['mi'],
            'meters' => ['m'],
            'yards' => ['yd'],
            'feet' => ['ft'],
        ];
    }

    public function testSetIdWithNull(): void {
        $activity = $this->createTestActivity(new ActivityId());
        $originalId = $activity->getId();

        $activity->setId(null);

        $this->assertNull($activity->getId());
        $this->assertNotSame($originalId, $activity->getId());
    }

    public function testSetIdWithNewId(): void {
        $activity = $this->createTestActivity();
        $newId = new ActivityId();

        $activity->setId($newId);

        $this->assertSame($newId, $activity->getId());
    }

    public function testActivityWithLongName(): void {
        $longName = str_repeat('Löpning ', 50);
        $activity = $this->createTestActivity(null, null, '🏃', $longName);

        $this->assertEquals($longName, $activity->getName());

        $state = $activity->state();
        $this->assertEquals($longName, $state['name']);
    }

    public function testActivityWithSpecialCharactersInName(): void {
        $specialName = 'Löpning & Styrka™ (Test) [2024] <Special>';
        $activity = $this->createTestActivity(null, null, '🏃', $specialName);

        $this->assertEquals($specialName, $activity->getName());

        $json = json_encode($activity->jsonSerialize());
        $this->assertNotFalse($json);
    }
}