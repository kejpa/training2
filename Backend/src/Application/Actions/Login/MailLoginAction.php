<?php

namespace App\Application\Actions\Login;

use App\Application\Actions\User\UserAction;
use App\Domain\Login\LoginValidator;
use App\Domain\User\UserRepository;
use App\Infrastructure\Auth\TokenService;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class MailLoginAction extends UserAction {
    public function __construct(
        LoggerInterface $logger,
        UserRepository $userRepository,
        private LoginValidator $validator,
        private TokenService $tokenService
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
        if (!$this->validator->validateLogin($data)) {
            return $this->respondWithData([
                'errors' => $this->validator->getErrors()
            ], 400);
        }

        try {
            // Hämta användaren
            $user = $this->userRepository->getByEmail($data['email']);

            if (!$user) {
                return $this->respondWithData([
                    'error' => 'Användaren hittades inte'
                ], 404);
            }

            $now = new \DateTimeImmutable();
            $expires = $user->getExpires();

            if ($data['code'] !== $user->getCode()) {
                return $this->respondWithData([
                    'error' => 'Ogiltig kod'
                ], 401);
            }
            if (!$expires || $expires < $now) {
                return $this->respondWithData([
                    'error' => 'Koden har gått ut'
                ], 401);
            }

            //Nollställ kod och utgångstid
            $user->setCode(null);
            $user->setExpires(null);
            $this->userRepository->save($user);

            // Generera tokens
            $accessToken = $this->tokenService->generateAccessToken($user);
            $refreshToken = $this->tokenService->generateRefreshToken($user);

            // Sätt refresh token som httpOnly cookie
            $cookieValue = urlencode($refreshToken);
            $expires = time() + (int)$_ENV['REFRESH_TOKEN_EXPIRATION'];
            $secure = ($_ENV['APP_ENV'] ?? 'production') === 'production'; // true i produktion
            $sameSite = 'Strict';

            $cookieHeader = sprintf(
                'refresh_token=%s; Path=/api/refresh; Expires=%s; HttpOnly; SameSite=%s%s',
                $cookieValue,
                gmdate('D, d M Y H:i:s T', $expires),
                $sameSite,
                $secure ? '; Secure' : ''
            );

            // Returnera access token i payload
            $response = $this->respondWithData([
                'user' => $user,
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
            ], 200);

            // Lägg till cookie header
            return $response->withAddedHeader('Set-Cookie', $cookieHeader);
        } catch (Exception $e) {
            $this->logger->error("MailLoginAction: Exception thrown:" . $e->getMessage());
            $this->logger->error("MailLoginAction: Parsed body:" . print_r($data, true));

            return $this->respondWithData([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
