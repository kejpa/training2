<?php

namespace App\Application\Actions\Activity;

use Psr\Http\Message\ResponseInterface as Response;

class GetActivityAction extends ActivityAction {
    protected function action(): Response {
        // Läs id från URL
        $id = $this->resolveArg('id');
        // Hämta användaren
        $userId = $this->request->getAttribute('userId');

        // Läs aktivitet
        $activity = $this->activityRepository->getActivityForUser($id, $userId);

        // returnerar data
        return $this->respondWithData(["activity" => $activity]);

    }
}