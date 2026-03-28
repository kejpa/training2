<?php

namespace App\Domain\Session;

use DateTimeImmutable;

class SessionValidator {
    /**
     * @var array <string, string>
     */
    private array $errors = [];

    /**
     * @param array<string, string|null> $data
     * @return bool
     */
    public function validateRegister(array $data): bool {
        $this->errors = [];

        if (!is_null($data['distance']) && filter_var($data['distance'], FILTER_VALIDATE_FLOAT) === false) {
            $this->errors['distance'] = 'Distans ska anges som ett tal';
        }
        if (!is_null($data['duration']) && DateTimeImmutable::createFromFormat('H:i', $data['duration']) === false) {
            $this->errors['duration'] = 'Ogiltig tidsangivelse';
        } elseif (
            !is_null($data['duration']) &&
            $data['duration'] !== (DateTimeImmutable::createFromFormat('H:i', $data['duration']))->format('H:i')
        ) {
            $this->errors['duration'] = 'Felaktig tidsangivelse';
        }
        if (DateTimeImmutable::createFromFormat('Y-m-d', $data['date']) === false) {
            $this->errors['date'] = 'Ogiltigt datum';
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $data['date']);

        if ($date !== false && $date > new DatetimeImmutable()) {
            $this->errors['date'] = "Datum får inte vara i framtiden";
        }
        if (filter_var($data['rpe'], FILTER_VALIDATE_INT) === false || $data['rpe'] > 10 || $data['rpe'] < 1) {
            $this->errors['rpe'] = "Ogiltig rpe (1-10)";
        }

        return empty($this->errors);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array {
        return $this->errors;
    }
}
