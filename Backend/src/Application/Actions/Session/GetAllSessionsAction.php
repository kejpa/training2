<?php

namespace App\Application\Actions\Session;

use App\Application\Actions\Session\SessionAction;
use App\Domain\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface as Response;

class GetAllSessionsAction extends SessionAction {
    /**
     * @inheritDoc
     */
    protected function action(): Response {
        $userId = new UserId($this->request->getAttribute('userId'));

        $sessions = $this->sessionRepository->getAll($userId);

        return $this->respondWithData(["sessions" => $sessions]);
    }
}
