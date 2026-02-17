<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use JsonSerializable;
use stdClass;

class User implements JsonSerializable {

    public function __construct(private ?UserId $id, private string $email, private string $firstname, private string $lastname,
        private string $secret, private ?string $qrUrl, private ?string $imgData, private ?string $code, private ?DateTimeImmutable $expires,
        private ?DateTimeImmutable $created_at = new DateTimeImmutable(), private ?DateTimeImmutable $updated_at = new DateTimeImmutable()) {
        if (!$this->id) {
            $this->id = new UserId();
        }

        $this->updated_at = new DateTimeImmutable();
    }

    public static function fromRow(array $row): self {
        return new self(new UserId($row['id']), $row['email'], $row['firstname'], $row['lastname'], $row['secret'], $row['qrUrl'], $row['imgData'],
            $row['code'], DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['expires']));
    }

    public function getId(): UserId {
        return $this->id;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function getFirstname(): string {
        return $this->firstname;
    }

    public function getLastname(): string {
        return $this->lastname;
    }

    public function getName(): string {
        return "$this->firstname $this->lastname";
    }

    public function getSecret(): string {
        return $this->secret;
    }

    public function getQrUrl(): ?string {
        return $this->qrUrl;
    }

    public function getImgData(): ?string {
        return $this->imgData;
    }

    public function getCode(): ?string {
        return $this->code;
    }

    public function getExpires(): ?DateTimeImmutable {
        return $this->expires;
    }

    public function getCreatedAt(): ?DateTimeImmutable {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?DateTimeImmutable {
        return $this->updated_at;
    }

    public function state(): array {
        return [
            'id' => $this->id->toString(),
            'email' => $this->email,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'secret' => $this->secret,
            'qrUrl' => $this->qrUrl,
            'imgData' => $this->imgData,
            'code' => $this->code,
            'expires' => $this->expires->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public function jsonSerialize(): stdClass {
        $me = new stdClass();
        $me->id = $this->id->toString();
        $me->email = $this->email;
        $me->firstname = $this->firstname;
        $me->lastname = $this->lastname;

        return $me;
    }
}
