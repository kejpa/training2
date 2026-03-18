<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Activity;

use App\Domain\Activity\Activity;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Persistence\Activity\DbalActivityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class DbalActivityRepositoryTest extends TestCase {
    private Connection $connection;
    private DbalActivityRepository $repository;

    protected function setUp(): void {
        // SQLite in-memory databas för tester
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->createSchema();
        $this->repository = new DbalActivityRepository($this->connection);
    }

    protected function tearDown(): void {
        $this->connection->close();
    }

    private function createSchema(): void {
        $schema = <<<SQL
        CREATE TABLE activities (
            id TEXT PRIMARY KEY,
            userid TEXT NOT NULL,
            emoji TEXT NOT NULL,
            name TEXT NOT NULL,
            log_distance INTEGER NOT NULL,
            log_time INTEGER NOT NULL,
            distance_unit TEXT NOT NULL
        )
        SQL;

        $this->connection->executeStatement($schema);
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

    // ========== add() tests ==========

    public function testAddInsertsActivityIntoDatabase(): void {
        $activity = $this->createTestActivity();

        $this->repository->add($activity);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM activities');
        $this->assertEquals(1, $count);
    }

    public function testAddStoresAllActivityFields(): void {
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

        $this->repository->add($activity);

        $row = $this->connection->fetchAssociative('SELECT * FROM activities WHERE id = ?', [$activityId]);

        $this->assertEquals($activityId, $row['id']);
        $this->assertEquals($userId, $row['userid']);
        $this->assertEquals('🏋️', $row['emoji']);
        $this->assertEquals('Styrketräning', $row['name']);
        $this->assertFalse((bool)$row['log_distance']); // Konvertera till bool
        $this->assertTrue((bool)$row['log_time']);      // Konvertera till bool
        $this->assertEquals('kg', $row['distance_unit']);
    }

    public function testAddMultipleActivities(): void {
        $activity1 = $this->createTestActivity(null, null, '🏃', 'Löpning');
        $activity2 = $this->createTestActivity(null, null, '🚴', 'Cykling');
        $activity3 = $this->createTestActivity(null, null, '🏊', 'Simning');

        $this->repository->add($activity1);
        $this->repository->add($activity2);
        $this->repository->add($activity3);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM activities');
        $this->assertEquals(3, $count);
    }

    public function testAddStoresEmoji(): void {
        $activity = $this->createTestActivity(null, null, '🏃', 'Test');

        $this->repository->add($activity);

        $emoji = $this->connection->fetchOne('SELECT emoji FROM activities WHERE name = ?', ['Test']);
        $this->assertEquals('🏃', $emoji);
    }

    // ========== getAllForUser() tests ==========

    public function testGetAllForUserReturnsEmptyArrayWhenNoActivities(): void {
        $userId = (new UserId())->toString();

        $activities = $this->repository->getAllForUser($userId);

        $this->assertIsArray($activities);
        $this->assertEmpty($activities);
    }

    public function testGetAllForUserReturnsUserActivities(): void {
        $userId = (new UserId())->toString();

        $activity1 = $this->createTestActivity(null, $userId, '🏃', 'Löpning');
        $activity2 = $this->createTestActivity(null, $userId, '🚴', 'Cykling');

        $this->repository->add($activity1);
        $this->repository->add($activity2);

        $activities = $this->repository->getAllForUser($userId);

        $this->assertCount(2, $activities);
        $this->assertContainsOnlyInstancesOf(Activity::class, $activities);
    }

    public function testGetAllForUserOnlyReturnsActivitiesForSpecificUser(): void {
        $user1 = new UserId();
        $user2 = new UserId();

        $activity1 = new Activity(new ActivityId(), $user1, '🏃', 'User1 Activity', true, true, 'km');
        $activity2 = new Activity(new ActivityId(), $user2, '🚴', 'User2 Activity', true, true, 'km');
        $activity3 = new Activity(new ActivityId(), $user1, '🏊', 'User1 Activity 2', true, true, 'km');

        $this->repository->add($activity1);
        $this->repository->add($activity2);
        $this->repository->add($activity3);

        $user1Activities = $this->repository->getAllForUser($user1->toString());

        $this->assertCount(2, $user1Activities);

        foreach ($user1Activities as $activity) {
            $this->assertEquals($user1->toString(), $activity->getUserId()->toString());
        }
    }

    public function testGetAllForUserReturnsActivitiesWithAllFields(): void {
        $userId = (new UserId())->toString();
        $activityId = (new ActivityId())->toString();

        $originalActivity = new Activity(
            new ActivityId($activityId),
            new UserId($userId),
            '🏋️',
            'Styrketräning',
            false,
            true,
            'kg'
        );

        $this->repository->add($originalActivity);

        $activities = $this->repository->getAllForUser($userId);

        $this->assertCount(1, $activities);

        $activity = $activities[0];
        $this->assertEquals($activityId, $activity->getId()->toString());
        $this->assertEquals($userId, $activity->getUserId()->toString());
        $this->assertEquals('🏋️', $activity->getEmoji());
        $this->assertEquals('Styrketräning', $activity->getName());
        $this->assertFalse($activity->getLogDistance());
        $this->assertTrue($activity->getLogTime());
        $this->assertEquals('kg', $activity->getDistanceUnit());
    }

    // ========== getActivityForUser() tests ==========

    public function testGetActivityForUserReturnsActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Löpning');
        $this->repository->add($activity);

        $result = $this->repository->getActivityForUser($activityId, $userId);

        $this->assertInstanceOf(Activity::class, $result);
        $this->assertEquals($activityId, $result->getId()->toString());
        $this->assertEquals($userId, $result->getUserId()->toString());
    }

    public function testGetActivityForUserReturnsNullWhenNotFound(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $result = $this->repository->getActivityForUser($activityId, $userId);

        $this->assertNull($result);
    }

    public function testGetActivityForUserReturnsNullForWrongUser(): void {
        $activityId = (new ActivityId())->toString();
        $owner = (new UserId())->toString();
        $otherUser = (new UserId())->toString();

        $activity = $this->createTestActivity($activityId, $owner);
        $this->repository->add($activity);

        // Försök hämta med fel userId
        $result = $this->repository->getActivityForUser($activityId, $otherUser);

        $this->assertNull($result);
    }

    public function testGetActivityForUserReturnsActivityWithAllFields(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $originalActivity = new Activity(
            new ActivityId($activityId),
            new UserId($userId),
            '🧘',
            'Yoga',
            false,
            true,
            'min'
        );

        $this->repository->add($originalActivity);

        $result = $this->repository->getActivityForUser($activityId, $userId);

        $this->assertEquals('🧘', $result->getEmoji());
        $this->assertEquals('Yoga', $result->getName());
        $this->assertFalse($result->getLogDistance());
        $this->assertTrue($result->getLogTime());
        $this->assertEquals('min', $result->getDistanceUnit());
    }

    // ========== update() tests ==========

    public function testUpdateModifiesActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Löpning');
        $this->repository->add($activity);

        // Uppdatera aktivitet
        $activity->setName('Morgonlöpning');
        $activity->setEmoji('🌅');
        $this->repository->update($activity);

        // Hämta från databas
        $row = $this->connection->fetchAssociative('SELECT * FROM activities WHERE id = ?', [$activityId]);

        $this->assertEquals('Morgonlöpning', $row['name']);
        $this->assertEquals('🌅', $row['emoji']);
    }

    public function testUpdateOnlyModifiesSpecificActivity(): void {
        $userId = (new UserId())->toString();

        $activity1 = $this->createTestActivity(null, $userId, '🏃', 'Löpning');
        $activity2 = $this->createTestActivity(null, $userId, '🚴', 'Cykling');

        $this->repository->add($activity1);
        $this->repository->add($activity2);

        // Uppdatera bara activity1
        $activity1->setName('Uppdaterad Löpning');
        $this->repository->update($activity1);

        $activities = $this->repository->getAllForUser($userId);

        $names = array_map(fn($a) => $a->getName(), $activities);
        $this->assertContains('Uppdaterad Löpning', $names);
        $this->assertContains('Cykling', $names);
    }

    public function testUpdateChangesBooleanFlags(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $activity = $this->createTestActivity($activityId, $userId, '🏃', 'Test', true, true);
        $this->repository->add($activity);

        $activity->setLogDistance(false);
        $activity->setLogTime(false);
        $this->repository->update($activity);

        $result = $this->repository->getActivityForUser($activityId, $userId);

        $this->assertFalse($result->getLogDistance());
        $this->assertFalse($result->getLogTime());
    }

    // ========== delete() tests ==========

    public function testDeleteRemovesActivity(): void {
        $activityId = (new ActivityId())->toString();
        $userId = (new UserId())->toString();

        $activity = $this->createTestActivity($activityId, $userId);
        $this->repository->add($activity);

        $this->repository->delete($activityId, $userId);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM activities WHERE id = ?', [$activityId]);
        $this->assertEquals(0, $count);
    }

    public function testDeleteOnlyRemovesSpecificActivity(): void {
        $userId = (new UserId())->toString();

        $activity1 = $this->createTestActivity(null, $userId, '🏃', 'Löpning');
        $activity2 = $this->createTestActivity(null, $userId, '🚴', 'Cykling');

        $this->repository->add($activity1);
        $this->repository->add($activity2);

        $this->repository->delete($activity1->getId()->toString(), $userId);

        $activities = $this->repository->getAllForUser($userId);
        $this->assertCount(1, $activities);
        $this->assertEquals('Cykling', $activities[0]->getName());
    }

    public function testDeleteRequiresBothIdAndUserId(): void {
        $activityId = (new ActivityId())->toString();
        $owner = (new UserId())->toString();
        $otherUser = (new UserId())->toString();

        $activity = $this->createTestActivity($activityId, $owner);
        $this->repository->add($activity);

        // Försök ta bort med fel userId
        $this->repository->delete($activityId, $otherUser);

        // Aktiviteten ska fortfarande finnas
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM activities WHERE id = ?', [$activityId]);
        $this->assertEquals(1, $count);
    }

    // ========== Integration tests ==========

    public function testCompleteWorkflow(): void {
        $userId = (new UserId())->toString();

        // 1. Tom till början
        $this->assertEmpty($this->repository->getAllForUser($userId));

        // 2. Lägg till aktivitet
        $activity = $this->createTestActivity(null, $userId, '🏃', 'Löpning');
        $activityId = $activity->getId()->toString();
        $this->repository->add($activity);

        // 3. Hämta alla
        $activities = $this->repository->getAllForUser($userId);
        $this->assertCount(1, $activities);

        // 4. Hämta specifik
        $fetched = $this->repository->getActivityForUser($activityId, $userId);
        $this->assertEquals('Löpning', $fetched->getName());

        // 5. Uppdatera
        $fetched->setName('Morgonlöpning');
        $this->repository->update($fetched);

        // 6. Verifiera uppdatering
        $updated = $this->repository->getActivityForUser($activityId, $userId);
        $this->assertEquals('Morgonlöpning', $updated->getName());

        // 7. Ta bort
        $this->repository->delete($activityId, $userId);

        // 8. Verifiera borttagning
        $this->assertEmpty($this->repository->getAllForUser($userId));
    }

    public function testHandlesEmojisCorrectly(): void {
        $userId = (new UserId())->toString();
        $emojis = ['🏃', '🚴', '🏊', '🧘', '🏋️', '⚽', '🏀', '💪'];

        foreach ($emojis as $emoji) {
            $activity = $this->createTestActivity(null, $userId, $emoji, "Test $emoji");
            $this->repository->add($activity);
        }

        $activities = $this->repository->getAllForUser($userId);

        $this->assertCount(count($emojis), $activities);

        $retrievedEmojis = array_map(fn($a) => $a->getEmoji(), $activities);
        foreach ($emojis as $emoji) {
            $this->assertContains($emoji, $retrievedEmojis);
        }
    }

    public function testHandlesSpecialCharactersInName(): void {
        $userId = (new UserId())->toString();
        $specialName = "Löpning & Styrka™ (Test) [2024] <Special> 'quoted' \"double\"";

        $activity = $this->createTestActivity(null, $userId, '🏃', $specialName);
        $this->repository->add($activity);

        $activities = $this->repository->getAllForUser($userId);

        $this->assertEquals($specialName, $activities[0]->getName());
    }
    }