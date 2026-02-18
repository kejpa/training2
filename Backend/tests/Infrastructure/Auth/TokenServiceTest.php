<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Auth;

use App\Domain\User\User;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Auth\TokenService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase {
    private TokenService $tokenService;

    protected function setUp(): void {
        // Sätt test environment variables
        $_ENV['JWT_SECRET'] = 'test-secret-key-at-least-32-characters-long';
        $_ENV['JWT_EXPIRATION'] = '3600'; // 1 timme
        $_ENV['REFRESH_TOKEN_EXPIRATION'] = '2592000'; // 30 dagar

        $this->tokenService = new TokenService();
    }

    private function createTestUser(): User {
        return new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            '123456',
            new \DateTimeImmutable('+1 hour')
        );
    }

    public function testGeneratesAccessToken(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateAccessToken($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testAccessTokenContainsUserData(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateAccessToken($user);

        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        $this->assertEquals($user->getId()->toString(), $decoded->sub);
        $this->assertEquals($user->getEmail(), $decoded->email);
        $this->assertEquals($user->getName(), $decoded->name);
    }

    public function testAccessTokenHasCorrectExpiration(): void {
        $user = $this->createTestUser();

        $beforeGeneration = time();
        $token = $this->tokenService->generateAccessToken($user);
        $afterGeneration = time();

        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        $this->assertObjectHasProperty('exp', $decoded);
        $this->assertObjectHasProperty('iat', $decoded);

        // Verifiera att expiration är ungefär 1 timme från nu
        $expectedExpiration = $beforeGeneration + 3600;
        $this->assertGreaterThanOrEqual($expectedExpiration - 2, $decoded->exp);
        $this->assertLessThanOrEqual($afterGeneration + 3600 + 2, $decoded->exp);

        // Verifiera att issued at är nu
        $this->assertGreaterThanOrEqual($beforeGeneration, $decoded->iat);
        $this->assertLessThanOrEqual($afterGeneration, $decoded->iat);
    }

    public function testGeneratesRefreshToken(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateRefreshToken($user);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testRefreshTokenContainsUserIdAndType(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateRefreshToken($user);

        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        $this->assertEquals($user->getId(), $decoded->sub);
        $this->assertEquals('refresh', $decoded->type);
    }

    public function testRefreshTokenDoesNotContainSensitiveData(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateRefreshToken($user);

        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        // Refresh token ska INTE innehålla email eller name
        $this->assertObjectNotHasProperty('email', $decoded);
        $this->assertObjectNotHasProperty('name', $decoded);
    }

    public function testRefreshTokenHasCorrectExpiration(): void {
        $user = $this->createTestUser();

        $beforeGeneration = time();
        $token = $this->tokenService->generateRefreshToken($user);
        $afterGeneration = time();

        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        // Verifiera att expiration är ungefär 30 dagar från nu
        $expectedExpiration = $beforeGeneration + 2592000;
        $this->assertGreaterThanOrEqual($expectedExpiration - 2, $decoded->exp);
        $this->assertLessThanOrEqual($afterGeneration + 2592000 + 2, $decoded->exp);
    }

    public function testCanVerifyValidToken(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateAccessToken($user);
        $decoded = $this->tokenService->verifyToken($token);

        $this->assertIsObject($decoded);
        $this->assertEquals($user->getId(), $decoded->sub);
    }

    public function testVerifyTokenThrowsExceptionForInvalidToken(): void {
        $this->expectException(\Exception::class);

        $this->tokenService->verifyToken('invalid.token.here');
    }

    public function testVerifyTokenThrowsExceptionForExpiredToken(): void {
        $user = $this->createTestUser();

        // Generera token med utgången expiration
        $issuedAt = time() - 7200; // 2 timmar sedan
        $expire = $issuedAt + 3600; // Utgick för 1 timme sedan

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $user->getId(),
        ];

        $expiredToken = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

        $this->expectException(\Exception::class);

        $this->tokenService->verifyToken($expiredToken);
    }

    public function testVerifyTokenThrowsExceptionForWrongSecret(): void {
        $user = $this->createTestUser();

        // Generera token med annat secret
        $wrongSecret = 'wrong-secret-key-for-testing-purposes';
        $payload = [
            'iat' => time(),
            'exp' => time() + 3600,
            'sub' => $user->getId(),
        ];

        $tokenWithWrongSecret = JWT::encode($payload, $wrongSecret, 'HS256');

        $this->expectException(\Exception::class);

        $this->tokenService->verifyToken($tokenWithWrongSecret);
    }

    public function testGetExpiresInReturnsCorrectValue(): void {
        $expiresIn = $this->tokenService->getExpiresIn();

        $this->assertEquals(3600, $expiresIn);
    }

    public function testAccessTokenAndRefreshTokenAreDifferent(): void {
        $user = $this->createTestUser();

        $accessToken = $this->tokenService->generateAccessToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken($user);

        $this->assertNotEquals($accessToken, $refreshToken);
    }

    public function testMultipleAccessTokensForSameUserAreDifferent(): void {
        $user = $this->createTestUser();

        $token1 = $this->tokenService->generateAccessToken($user);

        // Vänta 1 sekund så iat blir annorlunda
        sleep(1);

        $token2 = $this->tokenService->generateAccessToken($user);

        $this->assertNotEquals($token1, $token2);
    }

    public function testTokensUsesHS256Algorithm(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateAccessToken($user);

        // Decode header för att verifiera algoritm
        $parts = explode('.', $token);
        $header = json_decode(base64_decode($parts[0]), true);

        $this->assertEquals('HS256', $header['alg']);
    }

    public function testAccessTokenHasRequiredClaims(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateAccessToken($user);
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        // Verifiera att alla required claims finns
        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
        $this->assertObjectHasProperty('sub', $decoded);
        $this->assertObjectHasProperty('email', $decoded);
        $this->assertObjectHasProperty('name', $decoded);
    }

    public function testRefreshTokenHasRequiredClaims(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateRefreshToken($user);
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        // Verifiera att alla required claims finns
        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
        $this->assertObjectHasProperty('sub', $decoded);
        $this->assertObjectHasProperty('type', $decoded);
    }

    public function testCanVerifyBothAccessAndRefreshTokens(): void {
        $user = $this->createTestUser();

        $accessToken = $this->tokenService->generateAccessToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken($user);

        $decodedAccess = $this->tokenService->verifyToken($accessToken);
        $decodedRefresh = $this->tokenService->verifyToken($refreshToken);

        $this->assertEquals($user->getId(), $decodedAccess->sub);
        $this->assertEquals($user->getId(), $decodedRefresh->sub);
        $this->assertEquals('refresh', $decodedRefresh->type);
    }

    public function testTokenServiceUsesEnvironmentVariables(): void {
        // Ändra environment variables
        $_ENV['JWT_EXPIRATION'] = '7200'; // 2 timmar
        $_ENV['REFRESH_TOKEN_EXPIRATION'] = '5184000'; // 60 dagar

        $newTokenService = new TokenService();

        $this->assertEquals(7200, $newTokenService->getExpiresIn());

        // Återställ
        $_ENV['JWT_EXPIRATION'] = '3600';
        $_ENV['REFRESH_TOKEN_EXPIRATION'] = '2592000';
    }

    public function testAccessTokenExpirationMatchesConfiguration(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateAccessToken($user);
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        $actualExpiration = $decoded->exp - $decoded->iat;
        $expectedExpiration = (int)$_ENV['JWT_EXPIRATION'];

        $this->assertEquals($expectedExpiration, $actualExpiration);
    }

    public function testRefreshTokenExpirationMatchesConfiguration(): void {
        $user = $this->createTestUser();

        $token = $this->tokenService->generateRefreshToken($user);
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

        $actualExpiration = $decoded->exp - $decoded->iat;
        $expectedExpiration = (int)$_ENV['REFRESH_TOKEN_EXPIRATION'];

        $this->assertEquals($expectedExpiration, $actualExpiration);
    }
}