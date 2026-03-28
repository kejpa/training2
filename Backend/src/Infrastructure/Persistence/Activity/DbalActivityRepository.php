<?php

namespace App\Infrastructure\Persistence\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Infrastructure\Persistence\AbstractDBRepository;
use Doctrine\DBAL\Exception;

class DbalActivityRepository extends AbstractDBRepository implements ActivityRepository {
    private const TABLE = 'activities';

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getAllForUser(string $id): array {
        $qb = $this->connection->createQueryBuilder();
        $rows = $qb->select('*')
            ->from(self::TABLE)
            ->where('userid = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn($row) => Activity::fromRow($row), $rows);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function add(Activity $activity): void {
        $this->connection->insert(self::TABLE, $activity->state());
    }

    public function update(Activity $activity): void {
        $this->connection->update(
            self::TABLE,
            $activity->state(),
            ['id' => $activity->getId(), 'userid' => $activity->getUserId()]
        );
    }

    public function delete(string $id, string $userId): void {
        $this->connection->delete(self::TABLE, ['id' => $id, 'userid' => $userId]);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getActivityForUser(string $id, string $userId): ?Activity {
        $qb = $this->connection->createQueryBuilder();
        $result = $qb->select('*')
            ->from(self::TABLE)
            ->where('userid = :userid')
            ->andWhere('id = :id')
            ->setParameter('id', $id)
            ->setParameter('userid', $userId)
            ->executeQuery()
            ->fetchAssociative();

        return $result ? Activity::fromRow($result) : null;
    }
}
