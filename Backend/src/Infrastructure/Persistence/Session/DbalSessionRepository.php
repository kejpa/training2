<?php

namespace App\Infrastructure\Persistence\Session;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Persistence\AbstractDBRepository;
use Doctrine\DBAL\Exception;

class DbalSessionRepository extends AbstractDBRepository implements SessionRepository {
    private const TABLE = 'sessions';


    /**
     * @inheritDoc
     * @throws Exception
     */
    public function add(Session $session): void {
        $this->connection->insert(self::TABLE, $session->state());
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function update(Session $session): void {
        $this->connection->update(
            self::TABLE,
            $session->state(),
            ['id' => $session->getId(), 'userid' => $session->getUserId()]
        );
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function delete(UserId $userId, SessionId $id): void {
        $this->connection->delete(self::TABLE, ['id' => $id->toString(), 'userid' => $userId->toString()]);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function get(UserId $userId, SessionId $id): ?Session {
        $qb = $this->connection->createQueryBuilder();
        $row = $qb->select('*')
            ->from(self::TABLE)
            ->where('userid = :userid')
            ->andWhere('id = :id')
            ->setParameters(['userid' => $userId->toString(), 'id' => $id->toString()])
            ->executeQuery()
            ->fetchAssociative();

        return $row ? Session::fromRow($row) : null;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getAll(UserId $userId): array {
        $qb = $this->connection->createQueryBuilder();
        $rows = $qb->select('*')
            ->from(self::TABLE)
            ->where('userid = :userid')
            ->setParameter('userid', $userId->toString())
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(fn($row) => Session::fromRow($row), $rows);
    }
}
