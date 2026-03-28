<?php

namespace App\Application\Actions\Activity;

use Psr\Http\Message\ResponseInterface as Response;

class  DeleteActivityAction extends ActivityAction {
    protected function action(): Response {
        // Läs id från URL
        $id = $this->resolveArg('id');
        // Hämta användaren
        $userId = $this->request->getAttribute('userId');

        // Radera aktivitet
        $this->sessionRepository->delete($id, $userId);

        // returnerar data
        return $this->respondWithData([],204);

    }
}