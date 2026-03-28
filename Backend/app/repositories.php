<?php

declare(strict_types=1);

use App\Domain\Activity\ActivityRepository;
use App\Domain\Session\SessionRepository;
use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\Activity\DbalActivityRepository;
use App\Infrastructure\Persistence\Session\DbalSessionRepository;
use App\Infrastructure\Persistence\User\DbalUserRepository;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // Repository-mappningar
     $containerBuilder->addDefinitions([
        UserRepository::class => \DI\autowire(DbalUserRepository::class),
        ActivityRepository::class => \DI\autowire(DbalActivityRepository::class),
        SessionRepository::class => \DI\autowire(DbalSessionRepository::class),
    ]);
};
