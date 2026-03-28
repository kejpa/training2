<?php

declare(strict_types=1);

namespace App\Application\Actions\Activity;

use App\Application\Actions\Action;
use App\Domain\Activity\ActivityRepository;
use Psr\Log\LoggerInterface;

abstract class ActivityAction extends Action {
    public function __construct(LoggerInterface $logger, protected ActivityRepository $sessionRepository) {
        parent::__construct($logger);
    }
}
