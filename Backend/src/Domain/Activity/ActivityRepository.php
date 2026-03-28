<?php

namespace App\Domain\Activity;

interface ActivityRepository {
    /**
     * @param string $id
     * @return Activity[]
     */
    public function getAllForUser(string $id): array;

    /**
     * @param string $id
     * @param string $userId
     * @return Activity|null
     */
    public function getActivityForUser(string $id, string $userId): ?Activity;

    /**
     * @param Activity $activity
     * @return void
     */
    public function add(Activity $activity): void;

    /**
     * @param Activity $activity
     * @return void
     */
    public function update(Activity $activity): void;

    /**
     * @param string $id
     * @param string $userId
     * @return void
     */
    public function delete(string $id, string $userId): void;
}
