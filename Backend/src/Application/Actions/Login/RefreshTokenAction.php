<?php

namespace App\Application\Actions\Login;

use App\Application\Actions\Action;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Auth\TokenService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class RefreshTokenAction extends Action {
    public function __construct(LoggerInterface $logger, private UserRepository $userRepository, private TokenService $tokenService) {
        parent::__construct($logger);
    }

    protected function action(): Response {
        // Läs refresh token från cookie
        $cookies = $this->request->getCookieParams();
        $refreshToken = $cookies['refresh_token'] ?? null;

        if (!$refreshToken) {
            return $this->respondWithData([
                'error' => 'Refresh token saknas'
            ], 401);
        }

        try {
            // Verifiera refresh token
            $decoded = $this->tokenService->verifyToken($refreshToken);

            if ($decoded->type !== 'refresh') {
                return $this->respondWithData([
                    'error' => 'Ogiltig token-typ'
                ], 401);
            }

            // Hämta användare
            $user = $this->userRepository->getById(new UserId($decoded->sub));

            if (!$user) {
                return $this->respondWithData([
                    'error' => 'Användaren hittades inte'
                ], 401);
            }

            // Generera nya tokens
            $accessToken = $this->tokenService->generateAccessToken($user);
            $newRefreshToken = $this->tokenService->generateRefreshToken($user);

            // Sätt ny refresh token som cookie
            $cookieValue = urlencode($newRefreshToken);
            $expires = time() + (int)$_ENV['REFRESH_TOKEN_EXPIRATION'];
            $secure = ($_ENV['APP_ENV'] ?? 'production') === 'production';
            $sameSite = 'Strict';

            $cookieHeader = sprintf(
                'refresh_token=%s; Path=/; Expires=%s; HttpOnly; SameSite=%s%s',
                $cookieValue,
                gmdate('D, d M Y H:i:s T', $expires),
                $sameSite,
                $secure ? '; Secure' : ''
            );

            $response = $this->respondWithData([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->tokenService->getExpiresIn()
            ], 200);

            return $response->withAddedHeader('Set-Cookie', $cookieHeader);

        } catch (\Exception $e) {
            $this->logger->error("RefreshTokenAction: " . $e->getMessage());

            return $this->respondWithData([
                'error' => 'Ogiltig eller utgången token'
            ], 401);
        }
    }
}