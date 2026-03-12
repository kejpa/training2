<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserRepository {
    /**
     * @return User[]
     */
    public function getAll(): array;

    /**
     * @param string $id
     * @return User
     * @throws UserNotFoundException
     */
    public function getById(string $id): ?User;

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
     * @param string $id
     * @return void
     */
    public function delete(string $id): void;
}
