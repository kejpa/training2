<?php

namespace App\Domain\User;

class UserValidator {
    private array $errors = [];

    public function validateRegistration(array $data): bool {
        $this->errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Ogiltig e-postadress';
        }

        if (empty($data['firstname'])) {
            $this->errors['firstname'] = 'Förnamn krävs';
        }

        if (empty($data['lastname'])) {
            $this->errors['lastname'] = 'Efternamn krävs';
        }

        return empty($this->errors);
    }
    public function validateResend(array $data): bool {
        $this->errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Ogiltig e-postadress';
        }

        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }
}