<?php

namespace App\Application\Actions\Login;

use App\Application\Actions\User\UserAction;
use App\Domain\User\UserRepository;
use App\Domain\User\UserValidator;
use App\Infrastructure\Email\EmailService;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class NewLoginCodeAction extends UserAction {
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

            // Skapa en slumpmässig 6 siffrig kod
            $randomCode = (string)random_int(100000, 999999);
            $user->setCode($randomCode);
            $user->setExpires(new DateTimeImmutable('+1 hour'));

            // Spara uppdaterad användare
            $this->userRepository->save($user);

            // Maila användaren ny inloggningskod
            $this->emailService->sendNewCodeEmail($user);

            return $this->respondWithData([
                'user' => $user
            ], 200);
        } catch (Exception $e) {
            $this->logger->error("ResendLoginAction: Exception throwed:" . $e->getMessage());
            $this->logger->error("ResendLoginAction: Parsed body:" . print_r($data, true));

            return $this->respondWithData([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}