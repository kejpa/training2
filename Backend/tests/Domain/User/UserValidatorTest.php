<?php
// tests/Domain/User/UserValidatorTest.php

namespace Tests\Domain\User;

use App\Domain\User\UserValidator;
use PHPUnit\Framework\TestCase;

class UserValidatorTest extends TestCase {
    private UserValidator $validator;

    protected function setUp(): void {
        $this->validator = new UserValidator();
    }

    public function testValidRegistrationData(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testInvalidEmail(): void {
        $data = [
            'email' => 'invalid-email',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testEmptyEmail(): void {
        $data = [
            'email' => '',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testMissingEmail(): void {
        $data = [
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testEmptyFirstname(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => '',
            'lastname' => 'Andersson',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('firstname', $this->validator->getErrors());
    }

    public function testEmptyLastname(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => '',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('lastname', $this->validator->getErrors());
    }

    public function testMultipleErrors(): void {
        $data = [
            'email' => 'bad-email',
            'firstname' => '',
            'lastname' => '',
        ];

        $result = $this->validator->validateRegistration($data);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('firstname', $errors);
        $this->assertArrayHasKey('lastname', $errors);
        $this->assertCount(3, $errors);
    }

    public function testValidatorResetsErrorsBetweenValidations(): void {
        $invalidData = [
            'email' => 'bad-email',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->validator->validateRegistration($invalidData);
        $this->assertNotEmpty($this->validator->getErrors());

        $validData = [
            'email' => 'good@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->validator->validateRegistration($validData);
        $this->assertEmpty($this->validator->getErrors());
    }
}