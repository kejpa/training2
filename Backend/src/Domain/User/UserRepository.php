<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\ValueObject\UserId;

interface UserRepository {
    /**
     * @return User[]
     */
    public function getAll(): array;

    /**
     * @param UserId $id
     * @return User
     * @throws UserNotFoundException
     */
    public function getById(UserId $id): ?User;

    /**
     * @param string $email
     * @return User
     */
    public function getByEmail(string $email): ?User;

    /**
     * @param User $user
     * @return void
     */
    public function save(User $user): void;

    /**
     * @param UserId $id
     * @return void
     */
    public function delete(UserId $id): void;
}
