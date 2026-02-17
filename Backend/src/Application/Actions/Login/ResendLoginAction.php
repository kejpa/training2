<?php

namespace App\Application\Actions\Login;

use App\Application\Actions\User\UserAction;
use App\Domain\User\UserRepository;
use App\Domain\User\UserValidator;
use App\Infrastructure\Email\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ResendLoginAction extends UserAction {
    public function __construct(LoggerInterface $logger, UserRepository $userRepository, EmailService $emailService, private UserValidator $userValidator) {
        parent::__construct($logger, $userRepository, $emailService);
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response {
        $data = $this->request->getParsedBody();
        $data = array_change_key_case($data ?? [], CASE_LOWER);

        // Validera
        if (!$this->userValidator->validateResend($data)) {
            return $this->respondWithData([
                'errors' => $this->userValidator->getErrors()
            ], 400);
        }

        try {
            // Skapa user
            $user = $this->userRepository->getByEmail($data['email']);

            if (!$user) {
                return $this->respondWithData([
                    'error' => 'Användaren hittades inte'
                ], 404);
            }

            // Maila användaren info om hur man loggar in
            $this->emailService->resendEmail($user);

            return $this->respondWithData([
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            $this->logger->error("ResendLoginAction: Exception throwed:" . $e->getMessage());
            $this->logger->error("ResendLoginAction: Parsed body:" . print_r($data, true));

            return $this->respondWithData([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}