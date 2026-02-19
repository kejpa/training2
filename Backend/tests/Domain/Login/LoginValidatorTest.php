<?php

declare(strict_types=1);

namespace Tests\Domain\Login;

use App\Domain\Login\LoginValidator;
use PHPUnit\Framework\TestCase;

class LoginValidatorTest extends TestCase {
    private LoginValidator $validator;

    protected function setUp(): void {
        $this->validator = new LoginValidator();
    }

    // ========== validateEmail tests ==========

    public function testValidateEmailWithValidEmail(): void {
        $data = ['email' => 'test@example.com'];

        $result = $this->validator->validateEmail($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateEmailWithInvalidEmail(): void {
        $data = ['email' => 'invalid-email'];

        $result = $this->validator->validateEmail($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
        $this->assertEquals('Ogiltig e-postadress', $this->validator->getErrors()['email']);
    }

    public function testValidateEmailWithEmptyEmail(): void {
        $data = ['email' => ''];

        $result = $this->validator->validateEmail($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testValidateEmailWithMissingEmail(): void {
        $data = [];

        $result = $this->validator->validateEmail($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testValidateEmailWithEmailWithoutDomain(): void {
        $data = ['email' => 'test@'];

        $result = $this->validator->validateEmail($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testValidateEmailWithEmailWithoutAt(): void {
        $data = ['email' => 'testexample.com'];

        $result = $this->validator->validateEmail($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
    }

    public function testValidateEmailAcceptsVariousValidFormats(): void {
        $validEmails = [
            'simple@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'test_123@sub.example.com'
        ];

        foreach ($validEmails as $email) {
            $result = $this->validator->validateEmail(['email' => $email]);
            $this->assertTrue($result, "Email '$email' should be valid");
        }
    }

    // ========== validateLogin tests ==========

    public function testValidateLoginWithValidData(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123456'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateLoginWithInvalidEmail(): void {
        $data = [
            'email' => 'invalid-email',
            'code' => '123456'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('email', $this->validator->getErrors());
        $this->assertEquals('Ogiltig e-postadress', $this->validator->getErrors()['email']);
    }

    public function testValidateLoginWithEmptyCode(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => ''
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
        $this->assertEquals('Kod krävs', $this->validator->getErrors()['code']);
    }

    public function testValidateLoginWithMissingCode(): void {
        $data = ['email' => 'test@example.com'];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
        $this->assertEquals('Kod krävs', $this->validator->getErrors()['code']);
    }

    public function testValidateLoginWithCodeTooShort(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '12345'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
        $this->assertEquals('Koden måste vara 6 siffror', $this->validator->getErrors()['code']);
    }

    public function testValidateLoginWithCodeTooLong(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '1234567'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
        $this->assertEquals('Koden måste vara 6 siffror', $this->validator->getErrors()['code']);
    }

    public function testValidateLoginWithNonNumericCode(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => 'abc123'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
        $this->assertEquals('Koden måste vara 6 siffror', $this->validator->getErrors()['code']);
    }

    public function testValidateLoginWithCodeContainingSpaces(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '123 456'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
    }

    public function testValidateLoginWithCodeContainingLetters(): void {
        $data = [
            'email' => 'test@example.com',
            'code' => '12A456'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('code', $this->validator->getErrors());
    }

    public function testValidateLoginWithMultipleErrors(): void {
        $data = [
            'email' => 'invalid-email',
            'code' => '123'
        ];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('code', $errors);
        $this->assertCount(2, $errors);
    }

    public function testValidateLoginWithAllFieldsMissing(): void {
        $data = [];

        $result = $this->validator->validateLogin($data);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('code', $errors);
    }

    public function testValidateLoginAcceptsAllNumericCodes(): void {
        $validCodes = [
            '000000',
            '123456',
            '999999',
            '100000',
            '000001'
        ];

        foreach ($validCodes as $code) {
            $result = $this->validator->validateLogin([
                'email' => 'test@example.com',
                'code' => $code
            ]);
            $this->assertTrue($result, "Code '$code' should be valid");
        }
    }

    // ========== Error state management tests ==========

    public function testErrorsAreResetBetweenValidations(): void {
        // Första validering med fel
        $invalidData = ['email' => 'invalid-email'];
        $this->validator->validateEmail($invalidData);
        $this->assertNotEmpty($this->validator->getErrors());

        // Andra validering med giltig data
        $validData = ['email' => 'valid@example.com'];
        $this->validator->validateEmail($validData);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateLoginResetsErrors(): void {
        // Första validering
        $this->validator->validateEmail(['email' => 'invalid']);
        $this->assertNotEmpty($this->validator->getErrors());

        // Andra validering med validateLogin ska rensa fel
        $this->validator->validateLogin([
            'email' => 'valid@example.com',
            'code' => '123456'
        ]);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateEmailResetsErrors(): void {
        // Första validering
        $this->validator->validateLogin([
            'email' => 'invalid',
            'code' => '123'
        ]);
        $this->assertNotEmpty($this->validator->getErrors());

        // Andra validering med validateEmail ska rensa fel
        $this->validator->validateEmail(['email' => 'valid@example.com']);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void {
        $errors = $this->validator->getErrors();

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testGetErrorsReturnsCorrectStructure(): void {
        $this->validator->validateLogin([
            'email' => 'invalid',
            'code' => '123'
        ]);

        $errors = $this->validator->getErrors();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('code', $errors);
        $this->assertIsString($errors['email']);
        $this->assertIsString($errors['code']);
    }

    public function testValidatorCanBeReused(): void {
        // Validering 1
        $result1 = $this->validator->validateEmail(['email' => 'test1@example.com']);
        $this->assertTrue($result1);

        // Validering 2
        $result2 = $this->validator->validateLogin([
            'email' => 'test2@example.com',
            'code' => '654321'
        ]);
        $this->assertTrue($result2);

        // Validering 3
        $result3 = $this->validator->validateEmail(['email' => 'invalid']);
        $this->assertFalse($result3);
    }
}