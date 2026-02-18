<?php
// src/Infrastructure/Auth/TokenService.php

namespace App\Infrastructure\Auth;

use App\Domain\User\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenService {
    private string $secret;
    private int $jwtExpiration;
    private int $refreshExpiration;

    public function __construct() {
        $this->secret = $_ENV['JWT_SECRET'];
        $this->jwtExpiration = (int)$_ENV['JWT_EXPIRATION'];
        $this->refreshExpiration = (int)$_ENV['REFRESH_TOKEN_EXPIRATION'];
    }

    public function generateAccessToken(User $user): string {
        $issuedAt = time();
        $expire = $issuedAt + $this->jwtExpiration;

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $user->getId()->toString(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function generateRefreshToken(User $user): string {
        $issuedAt = time();
        $expire = $issuedAt + $this->refreshExpiration;

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $user->getId()->toString(),
            'type' => 'refresh',
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function verifyToken(string $token): object {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }

    public function getExpiresIn(): int {
        return $this->jwtExpiration;
    }
}