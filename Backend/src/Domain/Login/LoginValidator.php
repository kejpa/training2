<?php

namespace App\Domain\Login;

class LoginValidator {
    private array $errors = [];

    public function validateEmail(array $data): bool {
        $this->errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Ogiltig e-postadress';
        }

        return empty($this->errors);
    }

    public function validateLogin(array $data): bool {
        $this->errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Ogiltig e-postadress';
        }

        if (empty($data['code'])) {
            $this->errors['code'] = 'Kod krävs';
        } elseif (!preg_match('/^\d{6}$/', $data['code'])) {
            $this->errors['code'] = 'Koden måste vara 6 siffror';
        }

        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }
}