<?php

namespace App\Domain\Session;

use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;

interface SessionRepository {
    /**
     * @param Session $session
     * @return void
     */
    public function add(Session $session): void;

    /**
     * @param UserId $userId
     * @param SessionId $id
     * @return Session|null
     */
    public function get(UserId $userId, SessionId $id): ?Session;

    /**
     * @param UserId $userId
     * @return Session[]
     */
    public function getAll(UserId $userId): array;

    /**
     * @param UserId $userId
     * @param SessionId $id
     * @return void
     */
    public function delete(UserId $userId, SessionId $id): void;

    /**
     * @param Session $session
     * @return void
     */
    public function update(Session $session): void;
}
