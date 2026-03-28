<?php

namespace App\Application\Actions\Session;

use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface as Response;

class DeleteSessionAction extends SessionAction {
    /**
     * @inheritDoc
     */
    protected function action(): Response {
        // Läs id från URL
        $id = new SessionId($this->resolveArg('id'));
        // Hämta användaren
        $userId = new UserId($this->request->getAttribute('userId'));

        // Radera aktivitet
        $this->sessionRepository->delete($userId, $id);

        // returnerar data
        return $this->respondWithData([], 204);
    }
}
