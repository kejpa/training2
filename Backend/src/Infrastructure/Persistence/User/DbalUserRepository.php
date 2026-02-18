<?php

namespace App\Infrastructure\Persistence\User;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Persistence\AbstractDBRepository;
use Doctrine\DBAL\Exception;

class DbalUserRepository extends AbstractDBRepository implements UserRepository {
    private const TABLE = 'users';

    /**
     * @throws Exception
     */
    public function getAll(): array {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE);

        $result = $qb->executeQuery();
        $rows = $result->fetchAllAssociative();
        $rows = array_map(fn($row) => User::fromRow($row), $rows);

        return $rows;
    }

    /**
     * @throws Exception
     */
    public function getById(UserId $id): ?User {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where('id = :id')
            ->setParameter('id', $id->toString());

        $result = $qb->executeQuery();
        $row = $result->fetchAssociative();
        if ($row) {
            return User::fromRow($row);
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function save(User $user): void {
        if ($user->getUpdatedAt()->getTimestamp() === $user->getCreatedAt()->getTimestamp()) {
            $this->connection->insert(self::TABLE, $user->state());
        } else {
            $this->connection->update(self::TABLE, $user->state(), ['id' => $user->getId()->toString()]);
        }
    }

    /**
     * @throws Exception
     */
    public function getByEmail(string $email): ?User {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(self::TABLE)
            ->where('email = :email')
            ->setParameter('email', $email);

        $result = $qb->executeQuery();
        $row = $result->fetchAssociative();
        if ($row) {
            return User::fromRow($row);
        }
        return null;
    }

    public function delete(UserId $id): void {
        $this->connection->delete(self::TABLE, ['id' => $id->toString()]);
    }
}