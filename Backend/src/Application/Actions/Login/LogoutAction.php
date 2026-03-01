<?php

namespace App\Application\Actions\Login;

use App\Application\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class LogoutAction extends Action {
    public function __construct(LoggerInterface $logger) {
        parent::__construct($logger);
    }

    protected function action(): Response {
        // Rensa refresh token cookie
        $cookieHeader = sprintf(
            'refresh_token=; Path=/refresh; Expires=%s; HttpOnly; SameSite=Strict',
            gmdate('D, d M Y H:i:s T', -1) // Sätt expires till förflutet
        );

        $response = $this->respondWithData([
            'message' => 'Utloggad'
        ], 200);

        return $response->withAddedHeader('Set-Cookie', $cookieHeader);
    }
}
