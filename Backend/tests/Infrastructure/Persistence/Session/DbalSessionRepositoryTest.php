<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Session;

use App\Domain\Session\Session;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\ActivityId;
use App\Infrastructure\Persistence\Session\DbalSessionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class DbalSessionRepositoryTest extends TestCase {

    private Connection $connection;
    private DbalSessionRepository $repository;

    protected function setUp(): void {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->createSchema();
        $this->repository = new DbalSessionRepository($this->connection);
    }

    protected function tearDown(): void {
        $this->connection->close();
    }

    private function createSchema(): void {
        $schema = <<<SQL
        CREATE TABLE sessions (
            id TEXT PRIMARY KEY,
            userid TEXT NOT NULL,
            activityid TEXT NOT NULL,
            date TEXT NOT NULL,
            duration TEXT DEFAULT NULL,
            distance REAL DEFAULT NULL,
            description TEXT,
            rpe INTEGER
        )
        SQL;

        $this->connection->executeStatement($schema);
    }

    private function createTestSession(
        ?string $sessionId = null,
        ?string $userId = null,
        ?string $activityId = null,
        string $date = '2024-01-01',
        ?string $duration = '01:00:00',
        ?float $distance = 10.5,
        string $description = 'Test pass',
        int $rpe = 5
    ): Session {
        return new Session(
            $sessionId ? new SessionId($sessionId) : new SessionId(),
            $userId ? new UserId($userId) : new UserId(),
            $activityId ? new ActivityId($activityId) : new ActivityId(),
            new DateTimeImmutable($date),
            $duration,
            $distance,
            $description,
            $rpe
        );
    }

    // ========== add() tests ==========

    public function testAddInsertsSessionIntoDatabase(): void {
        $session = $this->createTestSession();

        $this->repository->add($session);

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions');
        $this->assertEquals(1, $count);
    }

    public function testAddStoresAllSessionFields(): void {
        $sessionId = (new SessionId())->toString();
        $userId = (new UserId())->toString();
        $activityId = (new ActivityId())->toString();

        $session = new Session(
            new SessionId($sessionId),
            new UserId($userId),
            new ActivityId($activityId),
            new DateTimeImmutable('2024-01-01'),
            '01:00:00',
            10.5,
            'Test pass',
            5
        );

        $this->repository->add($session);

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM sessions WHERE id = ?',
            [$sessionId]
        );

        $this->assertEquals($sessionId, $row['id']);
        $this->assertEquals($userId, $row['userid']);
        $this->assertEquals($activityId, $row['activityid']);
        $this->assertEquals('2024-01-01', $row['date']);
        $this->assertEquals('01:00:00', $row['duration']);
        $this->assertEquals(10.5, (float)$row['distance']);
        $this->assertEquals('Test pass', $row['description']);
        $this->assertEquals(5, (int)$row['rpe']);
    }

    public function testAddMultipleSessions(): void {
        $this->repository->add($this->createTestSession());
        $this->repository->add($this->createTestSession());
        $this->repository->add($this->createTestSession());

        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM sessions');
        $this->assertEquals(3, $count);
    }

    // ========== get() tests ==========

    public function testGetReturnsSession(): void {
        $session = $this->createTestSession();
        $this->repository->add($session);

        $result = $this->repository->get(
            $session->getUserId(),
            $session->getId()
        );

        $this->assertInstanceOf(Session::class, $result);
        $this->assertEquals($session->getId()->toString(), $result->getId()->toString());
    }

    public function testGetReturnsNullWhenNotFound(): void {
        $result = $this->repository->get(new UserId(), new SessionId());

        $this->assertNull($result);
    }

    public function testGetReturnsNullForWrongUser(): void {
        $session = $this->createTestSession();
        $this->repository->add($session);

        $result = $this->repository->get(new UserId(), $session->getId());

        $this->assertNull($result);
    }

    // ========== getAll() tests ==========

    public function testGetAllReturnsEmptyArray(): void {
        $sessions = $this->repository->getAll(new UserId());

        $this->assertIsArray($sessions);
        $this->assertEmpty($sessions);
    }

    public function testGetAllReturnsUserSessions(): void {
        $userId = new UserId();

        $session1 = $this->createTestSession(null, $userId->toString());
        $session2 = $this->createTestSession(null, $userId->toString());

        $this->repository->add($session1);
        $this->repository->add($session2);

        $sessions = $this->repository->getAll($userId);

        $this->assertCount(2, $sessions);
        $this->assertContainsOnlyInstancesOf(Session::class, $sessions);
    }

    public function testGetAllOnlyReturnsSpecificUser(): void {
        $user1 = new UserId();
        $user2 = new UserId();

        $this->repository->add($this->createTestSession(null, $user1->toString()));
        $this->repository->add($this->createTestSession(null, $user2->toString()));
        $this->repository->add($this->createTestSession(null, $user1->toString()));

        $sessions = $this->repository->getAll($user1);

        $this->assertCount(2, $sessions);

        foreach ($sessions as $session) {
            $this->assertEquals($user1->toString(), $session->getUserId()->toString());
        }
    }

    // ========== update() tests ==========

    public function testUpdateModifiesSession(): void {
        $session = $this->createTestSession();
        $this->repository->add($session);

        $session->setDescription('Updated');
        $session->setDistance(42.0);

        $this->repository->update($session);

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM sessions WHERE id = ?',
            [$session->getId()->toString()]
        );

        $this->assertEquals('Updated', $row['description']);
        $this->assertEquals(42.0, (float)$row['distance']);
    }

    public function testUpdateOnlyAffectsSpecificSession(): void {
        $userId = new UserId();

        $session1 = $this->createTestSession(null, $userId->toString(), null, '2024-01-01', null, null, 'A');
        $session2 = $this->createTestSession(null, $userId->toString(), null, '2024-01-02', null, null, 'B');

        $this->repository->add($session1);
        $this->repository->add($session2);

        $session1->setDescription('Updated A');
        $this->repository->update($session1);

        $sessions = $this->repository->getAll($userId);
        $descriptions = array_map(fn($s) => $s->getDescription(), $sessions);

        $this->assertContains('Updated A', $descriptions);
        $this->assertContains('B', $descriptions);
    }

    // ========== delete() tests ==========

    public function testDeleteRemovesSession(): void {
        $session = $this->createTestSession();
        $this->repository->add($session);

        $this->repository->delete($session->getUserId(), $session->getId());

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sessions WHERE id = ?',
            [$session->getId()->toString()]
        );

        $this->assertEquals(0, $count);
    }

    public function testDeleteRequiresCorrectUser(): void {
        $session = $this->createTestSession();
        $this->repository->add($session);

        $this->repository->delete(new UserId(), $session->getId());

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM sessions WHERE id = ?',
            [$session->getId()->toString()]
        );

        $this->assertEquals(1, $count);
    }

    // ========== Integration test ==========

    public function testCompleteWorkflow(): void {
        $userId = new UserId();

        // 1. Tomt
        $this->assertEmpty($this->repository->getAll($userId));

        // 2. Skapa
        $session = $this->createTestSession(null, $userId->toString());
        $this->repository->add($session);

        // 3. Hämta
        $sessions = $this->repository->getAll($userId);
        $this->assertCount(1, $sessions);

        // 4. Get
        $fetched = $this->repository->get($userId, $session->getId());
        $this->assertEquals($session->getId()->toString(), $fetched->getId()->toString());

        // 5. Update
        $fetched->setDescription('Updated');
        $this->repository->update($fetched);

        $updated = $this->repository->get($userId, $session->getId());
        $this->assertEquals('Updated', $updated->getDescription());

        // 6. Delete
        $this->repository->delete($userId, $session->getId());

        $this->assertEmpty($this->repository->getAll($userId));
    }
}