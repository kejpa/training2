<?php

namespace App\Domain\Activity;

interface ActivityRepository {
    /**
     * @param string $id
     * @return Activity[]
     */
    function getAllForUser(string $id): array;

    /**
     * @param string $id
     * @param string $userId
     * @return Activity|null
     */
    function getActivityForUser(string $id, string $userId): ?Activity;

    /**
     * @param Activity $activity
     * @return void
     */
    function add(Activity $activity): void;

    /**
     * @param Activity $activity
     * @return void
     */
    function update(Activity $activity): void;

    /**
     * @param string $id
     * @param string $userId
     * @return void
     */
    function delete(string $id, string $userId): void;

}