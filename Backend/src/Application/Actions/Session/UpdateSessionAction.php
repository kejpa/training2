<?php

namespace App\Application\Actions\Session;

use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionValidator;
use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;

class UpdateSessionAction extends SessionAction {
    public function __construct(
        LoggerInterface $logger,
        SessionRepository $sessionRepository,
        private SessionValidator $validator
    ) {
        parent::__construct($logger, $sessionRepository);
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response {
        $userId = new UserId($this->request->getAttribute('userId'));
        $sessionId = new SessionId($this->resolveArg('id'));

        $data = $this->request->getParsedBody();
        $data = array_change_key_case($data ?? [], CASE_LOWER);
        if (!$this->validator->validateRegister($data)) {
            return $this->respondWithData([
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        // Läs post
        $record = $this->sessionRepository->get($userId, $sessionId);
        if (is_null($record)) {
            throw new HttpNotFoundException($this->request, "Passet hittades inte");
        }
        // Uppdatera fälten
        $record->setActivityId(
            isset($data['activityid']) ? new ActivityId($data['activityid']) : $record->getActivityId()
        );
        $record->setDate(isset($data['date']) ? new DateTimeImmutable($data['date']) : $record->getDate());
        $record->setDistance($data['distance'] ?? $record->getDistance());
        $record->setDuration($data['duration'] ?? $record->getDuration());
        $record->setDescription($data['description'] ?? $record->getDescription());
        $record->setRpe($data['rpe'] ?? $record->getRpe());
        $this->sessionRepository->update($record);

        return $this->respondWithData($record);
    }
}
