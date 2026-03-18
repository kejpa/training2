<?php

namespace App\Domain\Activity;

class ActivityValidator {
    /**
     * @var array <string, string>
     */
    private array $errors = [];

    /**
     * @param array<string, string> $data
     * @return bool
     */
    public function validateRegister(array $data): bool {
        $this->errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $this->errors['name'] = 'Namn krävs';
        }

        if (!empty($data['log_distance']) && empty($data['distance_unit'])) {
            $this->errors['distance_unit'] = 'Enhet för distans krävs';
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