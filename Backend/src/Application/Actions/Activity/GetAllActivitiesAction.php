<?php

namespace App\Application\Actions\Activity;

use App\Domain\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface as Response;

class GetAllActivitiesAction extends ActivityAction {
    protected function action(): Response {
        // Hämta användaren
        $userId = $this->request->getAttribute('userId');

        // Läs alla aktiviteter
        $activities = $this->activityRepository->getAllForUser($userId);

        // returnerar data
        return $this->respondWithData(["activities" => $activities]);

    }
}