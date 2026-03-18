<?php

declare(strict_types=1);

namespace Tests\Domain\Activity;

use App\Domain\Activity\ActivityValidator;
use PHPUnit\Framework\TestCase;

class ActivityValidatorTest extends TestCase {
    private ActivityValidator $validator;

    protected function setUp(): void {
        $this->validator = new ActivityValidator();
    }

    // ========== validateRegister tests ==========

    public function testValidateRegisterWithValidData(): void {
        $data = [
            'name' => 'Löpning',
            'emoji' => '🏃',
            'log_distance' => true,
            'log_time' => true,
            'distance_unit' => 'km'
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateRegisterWithMinimalValidData(): void {
        $data = [
            'name' => 'Löpning'
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testReturnsErrorWhenNameIsEmpty(): void {
        $data = [
            'name' => ''
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->validator->getErrors());
        $this->assertEquals('Namn krävs', $this->validator->getErrors()['name']);
    }

    public function testReturnsErrorWhenNameIsMissing(): void {
        $data = [
            'emoji' => '🏃',
            'log_distance' => true
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->validator->getErrors());
    }

    public function testReturnsErrorWhenLogDistanceIsTrueButDistanceUnitIsMissing(): void {
        $data = [
            'name' => 'Löpning',
            'log_distance' => true
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('distance_unit', $this->validator->getErrors());
        $this->assertEquals('Enhet för distans krävs', $this->validator->getErrors()['distance_unit']);
    }

    public function testReturnsErrorWhenLogDistanceIsTrueButDistanceUnitIsEmpty(): void {
        $data = [
            'name' => 'Löpning',
            'log_distance' => true,
            'distance_unit' => ''
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('distance_unit', $this->validator->getErrors());
    }

    public function testDoesNotRequireDistanceUnitWhenLogDistanceIsFalse(): void {
        $data = [
            'name' => 'Yoga',
            'log_distance' => false
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testDoesNotRequireDistanceUnitWhenLogDistanceIsMissing(): void {
        $data = [
            'name' => 'Yoga'
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testReturnsMultipleErrors(): void {
        $data = [
            'name' => '',
            'log_distance' => true,
            'distance_unit' => ''
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
        $errors = $this->validator->getErrors();

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('distance_unit', $errors);
    }

    public function testAcceptsNameWithWhitespace(): void {
        $data = [
            'name' => '   Löpning   '
        ];

        $result = $this->validator->validateRegister($data);

        // Om du vill att whitespace ska vara OK
        $this->assertTrue($result);
    }

    public function testRejectsNameWithOnlyWhitespace(): void {
        $data = [
            'name' => '   '
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
    }

    public function testAcceptsVariousDistanceUnits(): void {
        $units = ['km', 'mi', 'm', 'yd', 'ft'];

        foreach ($units as $unit) {
            $data = [
                'name' => 'Test',
                'log_distance' => true,
                'distance_unit' => $unit
            ];

            $result = $this->validator->validateRegister($data);
            $this->assertTrue($result, "Failed for unit: $unit");
        }
    }

    public function testHandlesLogDistanceAsString(): void {
        // Frontend kan skicka '1' eller 'true' som string
        $data = [
            'name' => 'Löpning',
            'log_distance' => '1',
            'distance_unit' => 'km'
        ];

        $result = $this->validator->validateRegister($data);

        // !empty('1') är true
        $this->assertTrue($result);
    }

    public function testHandlesLogDistanceAsZero(): void {
        $data = [
            'name' => 'Yoga',
            'log_distance' => 0
        ];

        $result = $this->validator->validateRegister($data);

        // empty(0) är true, så ingen validering av distance_unit
        $this->assertTrue($result);
    }

    public function testErrorsAreResetBetweenValidations(): void {
        // Första validering med fel
        $invalidData = ['name' => ''];
        $this->validator->validateRegister($invalidData);
        $this->assertNotEmpty($this->validator->getErrors());

        // Andra validering med giltig data
        $validData = ['name' => 'Löpning'];
        $this->validator->validateRegister($validData);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void {
        $errors = $this->validator->getErrors();

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    public function testGetErrorsReturnsCorrectStructure(): void {
        $data = [
            'name' => '',
            'log_distance' => true
        ];

        $this->validator->validateRegister($data);
        $errors = $this->validator->getErrors();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('distance_unit', $errors);
        $this->assertIsString($errors['name']);
        $this->assertIsString($errors['distance_unit']);
    }

    public function testValidatorCanBeReused(): void {
        // Validering 1
        $result1 = $this->validator->validateRegister(['name' => 'Test1']);
        $this->assertTrue($result1);

        // Validering 2
        $result2 = $this->validator->validateRegister(['name' => '']);
        $this->assertFalse($result2);

        // Validering 3
        $result3 = $this->validator->validateRegister(['name' => 'Test3']);
        $this->assertTrue($result3);
    }

    public function testAcceptsLongActivityName(): void {
        $data = [
            'name' => str_repeat('Löpning ', 100)
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result);
    }

    public function testAcceptsSpecialCharactersInName(): void {
        $specialNames = [
            'Löpning & Styrka',
            'Cykling (MTB)',
            'Simning - 100m',
            'Yoga™',
            '🏃 Löpning',
            'Test/Training'
        ];

        foreach ($specialNames as $name) {
            $data = ['name' => $name];
            $result = $this->validator->validateRegister($data);
            $this->assertTrue($result, "Failed for name: $name");
        }
    }

    public function testAcceptsEmptyArrayAsInput(): void {
        $data = [];

        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $this->validator->getErrors());
    }

    public function testValidatesWithAllPossibleFields(): void {
        $data = [
            'name' => 'Löpning',
            'emoji' => '🏃',
            'log_distance' => true,
            'log_time' => true,
            'distance_unit' => 'km',
            'extra_field' => 'ignored'
        ];

        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result);
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testValidatesVariousValidData(array $data): void {
        $result = $this->validator->validateRegister($data);

        $this->assertTrue($result, 'Expected validation to pass for: ' . json_encode($data));
        $this->assertEmpty($this->validator->getErrors());
    }

    public static function validDataProvider(): array {
        return [
            'minimal' => [
                ['name' => 'Löpning']
            ],
            'with distance' => [
                ['name' => 'Löpning', 'log_distance' => true, 'distance_unit' => 'km']
            ],
            'without distance' => [
                ['name' => 'Yoga', 'log_distance' => false]
            ],
            'full data' => [
                [
                    'name' => 'Cykling',
                    'emoji' => '🚴',
                    'log_distance' => true,
                    'log_time' => true,
                    'distance_unit' => 'km'
                ]
            ],
        ];
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testValidatesVariousInvalidData(array $data, array $expectedErrors): void {
        $result = $this->validator->validateRegister($data);

        $this->assertFalse($result, 'Expected validation to fail for: ' . json_encode($data));

        foreach ($expectedErrors as $field) {
            $this->assertArrayHasKey($field, $this->validator->getErrors(), "Expected error for field: $field");
        }
    }

    public static function invalidDataProvider(): array {
        return [
            'missing name' => [
                ['emoji' => '🏃'],
                ['name']
            ],
            'empty name' => [
                ['name' => ''],
                ['name']
            ],
            'distance without unit' => [
                ['name' => 'Löpning', 'log_distance' => true],
                ['distance_unit']
            ],
            'distance with empty unit' => [
                ['name' => 'Löpning', 'log_distance' => true, 'distance_unit' => ''],
                ['distance_unit']
            ],
            'multiple errors' => [
                ['name' => '', 'log_distance' => true, 'distance_unit' => ''],
                ['name', 'distance_unit']
            ],
        ];
    }
}