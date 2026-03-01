<?php

namespace Tests\Domain\ValueObject;

use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

class UserIdTest extends TestCase {
    public function testGeneratesValidUuid(): void {
        $userId = new UserId();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $userId->toString()
        );
    }

    public function testCanCreateFromExistingUuid(): void {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $userId = new UserId($uuid);

        $this->assertEquals($uuid, $userId->toString());
    }

    public function testTwoGeneratedIdsAreDifferent(): void {
        $id1 = new UserId();
        $id2 = new UserId();

        $this->assertNotEquals($id1->toString(), $id2->toString());
    }

    public function testEqualsReturnsTrueForSameId(): void {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id1 = new UserId($uuid);
        $id2 = new UserId($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentIds(): void {
        $id1 = new UserId();
        $id2 = new UserId();

        $this->assertFalse($id1->equals($id2));
    }

    public function testCanConvertToString(): void {
        $userId = new UserId();

        $this->assertIsString((string)$userId);
        $this->assertEquals($userId->toString(), (string)$userId);
    }
}
