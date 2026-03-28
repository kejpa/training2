<?php

namespace App\Application\Actions\Session;

use App\Domain\Session\Session;
use App\Domain\Session\SessionRepository;
use App\Domain\Session\SessionValidator;
use App\Domain\ValueObject\SessionId;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class AddSessionAction extends SessionAction {
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
        $data['id'] = (new SessionId())->toString();
        $session = Session::fromRow($data);

        // Lägg till aktivitet
        $this->sessionRepository->add($session);

        // returnerar data
        return $this->respondWithData($session);
    }
}
