<?php
// tests/Infrastructure/Persistence/User/DbalUserRepositoryTest.php

namespace Tests\Infrastructure\Persistence\User;

use App\Domain\User\User;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Persistence\User\DbalUserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class DbalUserRepositoryTest extends TestCase {
    private Connection $connection;
    private DbalUserRepository $repository;

    protected function setUp(): void {
        // Skapa test-databas i minnet (SQLite)
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        // Skapa tabell
        $this->connection->executeStatement('
            CREATE TABLE users (
                id TEXT PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                firstname TEXT NOT NULL,
                lastname TEXT NOT NULL,
                secret TEXT NOT NULL,
                qrUrl TEXT,
                imgData TEXT,
                code TEXT,
                expires TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )
        ');

        $this->repository = new DbalUserRepository($this->connection);
    }

    protected function tearDown(): void {
        $this->connection->close();
    }

    public function testCanSaveNewUser(): void {
        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64data',
            '123456',
            new \DateTimeImmutable('+2 hours'),
        );

        $this->repository->save($user);

        $found = $this->repository->getById($user->getId()->toString());

        $this->assertNotNull($found);
        $this->assertEquals($user->getId(), $found->getId());
        $this->assertEquals($user->getEmail(), $found->getEmail());
    }

    public function testCanUpdateExistingUser(): void {
        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64data',
            '123456',
            new \DateTimeImmutable('+2 hours'),
        );

        $this->repository->save($user);

        // Skapa uppdaterad version (samma ID)
        $updatedUser = new User(
            new UserId($user->getId()),
            'updated@example.com',
            'Anna',
            'Nilsson',
            'secret123',
            'https://qr.url',
            'base64data',
            '123456',
            new \DateTimeImmutable('+2 hours'),
            new \DateTimeImmutable('yesterday'),
        );

        $this->repository->save($updatedUser);

        $found = $this->repository->getById($user->getId()->toString());

        $this->assertEquals('updated@example.com', $found->getEmail());
        $this->assertEquals('Nilsson', $found->getLastname());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void {
        $result = $this->repository->getById('Invalid');

        $this->assertNull($result);
    }

    public function testCanFindByEmail(): void {
        $user = new User(
            new UserId(),
            'findme@example.com',
            'Erik',
            'Eriksson',
            'secret123',
            'https://qr.url',
            'base64data',
            '123456',
            new \DateTimeImmutable('+2 hours'),
        );

        $this->repository->save($user);

        $found = $this->repository->getByEmail('findme@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($user->getId(), $found->getId());
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void {
        $result = $this->repository->getByEmail('notfound@example.com');

        $this->assertNull($result);
    }

    public function testCanFindAllUsers(): void {
        $user1 = new User(new UserId(), 'user1@example.com', 'User', 'One', 'secret', 'null', 'null', 'null', new \DateTimeImmutable('+2 hours'));
        $user2 = new User(new UserId(), 'user2@example.com', 'User', 'Two', 'secret', 'null', 'null', 'null', new \DateTimeImmutable('+2 hours'));
        $user3 = new User(new UserId(), 'user3@example.com', 'User', 'Three', 'secret', 'null', 'null', 'null', new \DateTimeImmutable('+2 hours'));

        $this->repository->save($user1);
        $this->repository->save($user2);
        $this->repository->save($user3);

        $all = $this->repository->getAll();

        $this->assertCount(3, $all);
        $this->assertContainsOnlyInstancesOf(User::class, $all);
    }

    public function testFindAllReturnsEmptyArrayWhenNoUsers(): void {
        $all = $this->repository->getAll();

        $this->assertIsArray($all);
        $this->assertEmpty($all);
    }

    public function testCanDeleteUser(): void {
        $user = new User(
            new UserId(),
            'delete@example.com',
            'Delete',
            'Me',
            'secret',
             'https://qr.url',
            'base64data',
            '123456',
            new \DateTimeImmutable('+2 hours'),
        );

        $this->repository->save($user);
        $this->assertNotNull($this->repository->getById($user->getId()->toString()));

        $this->repository->delete($user->getId());

        $this->assertNull($this->repository->getById($user->getId()->toString()));
    }

    public function testDeleteNonExistentUserDoesNotThrow(): void {
        $this->repository->delete('Invalid');

        $this->assertTrue(true); // Om vi kommer hit har inget exception kastats
    }
}