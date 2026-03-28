<?php

namespace App\Application\Actions\Activity;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityValidator;
use App\Domain\User\UserNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;

class UpdateActivityAction extends ActivityAction {
    public function __construct(LoggerInterface $logger, ActivityRepository $sessionRepository, private ActivityValidator $validator) {
        parent::__construct($logger, $sessionRepository);
    }

    protected function action(): Response {
        // Läs id från URL
        $id = $this->resolveArg('id');
        // Hämta användaren
        $userId = $this->request->getAttribute('userId');
        // Läs formdata och validera
        $data = $this->request->getParsedBody();
        $data = array_change_key_case($data ?? [], CASE_LOWER);
        // Validera
        if (!$this->validator->validateRegister($data)) {
            return $this->respondWithData([
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        // Läs aktivitet
        $activity = $this->sessionRepository->getActivityForUser($id, $userId);
        if (!$activity) {
            throw new HttpNotFoundException($this->request, "Aktiviteten hittades inte");
        }
        $activity->setEmoji($data['emoji'] ?? $activity->getEmoji());
        $activity->setLogDistance($data['log_distance'] ?? $activity->getLogDistance());
        $activity->setLogTime($data['log_time'] ?? $activity->getLogTime());
        $activity->setName($data['name'] ?? $activity->getName());
        $activity->setDistanceUnit($data['distance_unit'] ?? $activity->getDistanceUnit());
        $this->sessionRepository->update($activity);

        // returnerar data
        return $this->respondWithData(["activity" => $activity]);

    }
}