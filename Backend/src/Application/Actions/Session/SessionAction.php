<?php

declare(strict_types=1);

namespace App\Application\Actions\Session;

use App\Application\Actions\Action;
use App\Domain\Session\SessionRepository;
use Psr\Log\LoggerInterface;

abstract class SessionAction extends Action {
    public function __construct(LoggerInterface $logger, protected SessionRepository $sessionRepository) {
        parent::__construct($logger);
    }
}
