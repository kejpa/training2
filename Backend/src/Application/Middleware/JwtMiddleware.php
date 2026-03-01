<?php

namespace App\Application\Middleware;

use App\Infrastructure\Auth\TokenService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

class JwtMiddleware implements MiddlewareInterface {
    public function __construct(private TokenService $tokenService) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Token saknas']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = trim($matches[1]);

        try {
            $decoded = $this->tokenService->verifyToken($token);

            // Lägg till user info i request för användning i actions
            $request = $request->withAttribute('userId', $decoded->sub);
            $request = $request->withAttribute('userEmail', $decoded->email);

            return $handler->handle($request);

        } catch (\Exception $e) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Ogiltig eller utgången token']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }
}