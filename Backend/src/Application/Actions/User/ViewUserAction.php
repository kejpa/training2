<?php

namespace App\Application\Actions\User;

use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Email\EmailService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ViewUserAction extends UserAction {
    public function __construct(LoggerInterface $logger, UserRepository $userRepository) {
        parent::__construct($logger, $userRepository);
    }

    protected function action(): Response {
        // Hämta userId som middleware lade till
        $userId = $this->request->getAttribute('userId');

        // Hämta användare
        $user = $this->userRepository->getById($userId);

        if (!$user) {
            return $this->respondWithData([
                'error' => 'Användaren hittades inte'
            ], 404);
        }

        return $this->respondWithData([
            'user' => $user->jsonSerialize()
        ], 200);
    }
}
