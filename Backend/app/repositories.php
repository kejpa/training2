<?php

declare(strict_types=1);

use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\User\DbalUserRepository;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // Repository-mappningar
     $containerBuilder->addDefinitions([
        UserRepository::class => \DI\autowire(DbalUserRepository::class),
    ]);
};
