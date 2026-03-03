<?php

namespace App\Domain\Login;

class LoginValidator {
    /**
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * @param string[] $data
     * @return bool
     */
    public function validateEmail(array $data): bool {
        $this->errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = "Ogiltig e-postadress";
        }

        return empty($this->errors);
    }

    /**
     * @param string[] $data
     * @return bool
     */
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

    /**
     * @return string[]
     */
    public function getErrors(): array {
        return $this->errors;
    }
}
