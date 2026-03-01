<?php

namespace App\Application\Actions\Login;

use App\Application\Actions\User\UserAction;
use App\Domain\Login\LoginValidator;
use App\Domain\User\UserRepository;
use App\Domain\User\UserValidator;
use App\Infrastructure\Email\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ResendLoginAction extends UserAction {
    public function __construct(
        LoggerInterface $logger,
        UserRepository $userRepository,
        private EmailService $emailService,
        private LoginValidator $validator
    ) {
        parent::__construct($logger, $userRepository);
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response {
        $data = $this->request->getParsedBody();
        $data = array_change_key_case($data ?? [], CASE_LOWER);

        // Validera
        if (!$this->validator->validateEmail($data)) {
            return $this->respondWithData([
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        try {
            // Hämta användare
            $user = $this->userRepository->getByEmail($data['email']);

            if (!$user) {
                return $this->respondWithData([
                    'error' => 'Användaren hittades inte'
                ], 404);
            }


            // Maila användaren ny inloggningskod
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
