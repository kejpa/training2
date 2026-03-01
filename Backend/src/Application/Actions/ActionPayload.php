<?php

declare(strict_types=1);

namespace App\Application\Actions;

use JsonSerializable;

class ActionPayload implements JsonSerializable {
    /**
     * @param int $statusCode
     * @param mixed|null $data
     * @param ActionError|null $error
     */
    public function __construct(
        private int $statusCode = 200,
        private mixed $data = null,
        private ?ActionError $error = null
    ) {}

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>|object|null
     */
    public function getData(): mixed {
        return $this->data;
    }

    public function getError(): ?ActionError {
        return $this->error;
    }

    /**
     * @return array{
     *     statusCode: int,
     *     data?: mixed,
     *     error?: mixed
     * }
     */
    public function jsonSerialize(): array {
        $payload = [
           'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
