<?php

namespace App\Domain\ValueObject;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class AbstractId {
    protected UuidInterface $value;

    public function __construct(string $id = null) {
        $this->value = $id ? Uuid::fromString($id) : Uuid::uuid4();
    }

    public function toString(): string {
        return $this->value->toString();
    }

    public function equals(self $other): bool {
        return $this->value->equals($other->value);
    }

    public function __toString(): string {
        return $this->toString();
    }
}
