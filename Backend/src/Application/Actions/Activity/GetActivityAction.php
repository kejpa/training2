<?php

namespace App\Application\Actions\Activity;

use App\Domain\User\UserNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpNotFoundException;

class GetActivityAction extends ActivityAction {
    protected function action(): Response {
        // Läs id från URL
        $id = $this->resolveArg('id');
        // Hämta användaren
        $userId = $this->request->getAttribute('userId');

        // Läs aktivitet
        $activity = $this->sessionRepository->getActivityForUser($id, $userId);
        if (!$activity) {
            throw new HttpNotFoundException ($this->request, "Activity not found");
        }

        // returnerar data
        return $this->respondWithData(["activity" => $activity]);

    }
}