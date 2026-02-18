<?php

declare(strict_types=1);

namespace Tests\Domain\User;

use App\Domain\User\User;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {
    public function testCanCreateUserWithAllFields(): void {
        $userId = new UserId();
        $email = 'test@example.com';
        $firstname = 'Anna';
        $lastname = 'Andersson';
        $secret = 'secret123';
        $qrUrl = 'https://example.com/qr';
        $imgData = 'base64imagedata';
        $code = '123456';
        $expires = new \DateTimeImmutable('2026-12-31 23:59:59');

        $user = new User(
            $userId,
            $email,
            $firstname,
            $lastname,
            $secret,
            $qrUrl,
            $imgData,
            $code,
            $expires
        );

        $this->assertSame($userId->toString(), $user->getId()->toString());
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($firstname, $user->getFirstname());
        $this->assertSame($lastname, $user->getLastname());
        $this->assertSame("$firstname $lastname", $user->getName());
        $this->assertSame($secret, $user->getSecret());
        $this->assertSame($qrUrl, $user->getQrUrl());
        $this->assertSame($imgData, $user->getImgData());
        $this->assertSame($code, $user->getCode());
        $this->assertSame($expires, $user->getExpires());
    }

    public function testCanCreateUserWithNullOptionalFields(): void {
        $email = 'test@example.com';
        $firstname = 'Anna';
        $lastname = 'Andersson';
        $secret = 'secret123';

        $user = new User(
            null,  // id genereras automatiskt
            $email,
            $firstname,
            $lastname,
            $secret,
            null,  // qrUrl
            null,  // imgData
            null,  // code
            null   // expires
        );

        $this->assertNotEmpty($user->getId());
        $this->assertSame($email, $user->getEmail());
        $this->assertNull($user->getQrUrl());
        $this->assertNull($user->getImgData());
        $this->assertNull($user->getCode());
        $this->assertNull($user->getExpires());
    }

    public function testGetNameCombinesFirstAndLastName(): void {
        $user = new User(
            null,
            'test@example.com',
            'Anna',
            'Andersson',
            'secret',
            null,
            null,
            null,
            null
        );

        $this->assertSame('Anna Andersson', $user->getName());
    }

    public function testCanCreateUserFromDatabaseRow(): void {
        $row = [
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'email' => 'db@example.com',
            'firstname' => 'Erik',
            'lastname' => 'Eriksson',
            'secret' => 'dbsecret',
            'qrUrl' => 'https://example.com/qr',
            'imgData' => 'base64data',
            'code' => '654321',
            'expires' => '2026-12-31 23:59:59',
            'created_at' => '2021-01-01 00:00:00'
        ];

        $user = User::fromRow($row);

        $this->assertSame($row['id'], $user->getId()->toString());
        $this->assertSame($row['email'], $user->getEmail());
        $this->assertSame($row['firstname'], $user->getFirstname());
        $this->assertSame($row['lastname'], $user->getLastname());
        $this->assertSame('Erik Eriksson', $user->getName());
        $this->assertSame($row['secret'], $user->getSecret());
        $this->assertSame($row['qrUrl'], $user->getQrUrl());
        $this->assertSame($row['imgData'], $user->getImgData());
        $this->assertSame($row['code'], $user->getCode());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getExpires());
        $this->assertSame('2026-12-31 23:59:59', $user->getExpires()->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertSame('2021-01-01 00:00:00', $user->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testJsonSerializeReturnsCorrectStructure(): void {
        $userId = new UserId();
        $user = new User(
            $userId,
            'json@example.com',
            'Lisa',
            'Larsson',
            'secret',
            'https://qr.com',
            'imgdata',
            '999999',
            new \DateTimeImmutable()
        );

        $json = $user->jsonSerialize();

        $this->assertIsObject($json);
        $this->assertObjectHasProperty('id', $json);
        $this->assertObjectHasProperty('email', $json);
        $this->assertObjectHasProperty('firstname', $json);
        $this->assertObjectHasProperty('lastname', $json);

        $this->assertSame($userId->toString(), $json->id);
        $this->assertSame('json@example.com', $json->email);
        $this->assertSame('Lisa', $json->firstname);
        $this->assertSame('Larsson', $json->lastname);
    }

    public function testJsonSerializeDoesNotExposeSecret(): void {
        $user = new User(
            null,
            'secure@example.com',
            'Kalle',
            'Karlsson',
            'supersecret',
            null,
            null,
            null,
            null
        );

        $json = $user->jsonSerialize();

        $this->assertObjectNotHasProperty('secret', $json);
        $this->assertObjectNotHasProperty('qrUrl', $json);
        $this->assertObjectNotHasProperty('imgData', $json);
        $this->assertObjectNotHasProperty('code', $json);
        $this->assertObjectNotHasProperty('expires', $json);
    }

    public function testCanSerializeToJson(): void {
        $user = new User(
            null,
            'serializable@example.com',
            'Nils',
            'Nilsson',
            'secret',
            null,
            null,
            null,
            null
        );

        $jsonString = json_encode($user);
        $decoded = json_decode($jsonString, true);

        $this->assertIsString($jsonString);
        $this->assertArrayHasKey('email', $decoded);
        $this->assertSame('serializable@example.com', $decoded['email']);
    }

    public function testMultipleUsersHaveUniqueIds(): void {
        $user1 = new User(null, 'user1@example.com', 'User', 'One', 'secret', null, null, null, null);
        $user2 = new User(null, 'user2@example.com', 'User', 'Two', 'secret', null, null, null, null);

        $this->assertNotEquals($user1->getId(), $user2->getId());
    }
}