<?php

namespace App\Application\Actions\Session;

use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpNotFoundException;

class GetSessionAction extends SessionAction {
    /**
     * @inheritDoc
     */
    protected function action(): Response {
        $userId = new UserId($this->request->getAttribute('userId'));
        $sessionId = new SessionId($this->resolveArg('id'));

        $session = $this->sessionRepository->get($userId, $sessionId);
        if (!$session) {
            throw new HttpNotFoundException($this->request, "Session not found");
        }
        return $this->respondWithData(["session" => $session]);
    }
}
