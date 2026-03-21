<?php

namespace App\Application\Actions\Activity;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityValidator;
use App\Domain\ValueObject\ActivityId;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class AddActivityAction extends ActivityAction {

    public function __construct(LoggerInterface $logger, ActivityRepository $activityRepository, private ActivityValidator $validator) {
        parent::__construct($logger, $activityRepository);
    }

    protected function action(): Response {
        // Hämta användaren
        $userId = $this->request->getAttribute('userId');

        // Läs request
        $data = $this->request->getParsedBody();
        $data = array_change_key_case($data ?? [], CASE_LOWER);

        // Validera
        if (!$this->validator->validateRegister($data)) {
            return $this->respondWithData([
                'errors' => $this->validator->getErrors()
            ], 400);
        }
        $data['userid'] = $userId;
        $data['id'] = (new ActivityId())->toString();
        $activity = Activity::fromRow($data);

        // Lägg till aktivitet
        $this->activityRepository->add($activity);

        // returnerar data
        return $this->respondWithData($activity);
    }
}