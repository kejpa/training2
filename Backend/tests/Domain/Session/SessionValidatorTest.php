<?php

declare(strict_types=1);

namespace Tests\Domain\Session;

use App\Domain\Session\SessionValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class SessionValidatorTest extends TestCase {

    private function createValidData(
        ?string $distance = '10.5',
        ?string $duration = '01:00',
        ?string $date = null,
        int|string $rpe = 5
    ): array {
        return [
            'distance' => $distance,
            'duration' => $duration,
            'date' => $date ?? (new DateTimeImmutable('yesterday'))->format('Y-m-d'),
            'rpe' => $rpe,
        ];
    }

    public function testValidDataPassesValidation(): void {
        $validator = new SessionValidator();
        $data = $this->createValidData();

        $result = $validator->validateRegister($data);

        $this->assertTrue($result);
        $this->assertEmpty($validator->getErrors());
    }

    public function testInvalidDistance(): void {
        $validator = new SessionValidator();
        $data = $this->createValidData(distance: 'invalid');

        $result = $validator->validateRegister($data);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('distance', $errors);
    }

    public function testInvalidTime(): void {
        $validator = new SessionValidator();
        $data = $this->createValidData(duration: '99:99');

        $result = $validator->validateRegister($data);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('duration', $errors);
    }

    public function testInvalidDate(): void {
        $validator = new SessionValidator();
        $data = $this->createValidData(date: 'invalid-date');

        $result = $validator->validateRegister($data);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('date', $errors);
    }

    public function testFutureDate(): void {
        $validator = new SessionValidator();
        $futureDate = (new DateTimeImmutable('+1 day'))->format('Y-m-d');

        $data = $this->createValidData(date: $futureDate);

        $result = $validator->validateRegister($data);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('date', $errors);
        $this->assertEquals('Datum får inte vara i framtiden', $errors['date']);
    }

    /**
     * @dataProvider invalidRpeProvider
     */
    public function testInvalidRpe(int|string $rpe): void {
        $validator = new SessionValidator();
        $data = $this->createValidData(rpe: $rpe);

        $result = $validator->validateRegister($data);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $this->assertArrayHasKey('rpe', $errors);
    }

    public static function invalidRpeProvider(): array {
        return [
            'too low' => [0],
            'too high' => [11],
            'negative' => [-1],
            'string' => ['invalid'],
        ];
    }

    /**
     * @dataProvider validRpeProvider
     */
    public function testValidRpe(int $rpe): void {
        $validator = new SessionValidator();
        $data = $this->createValidData(rpe: $rpe);

        $result = $validator->validateRegister($data);

        $this->assertTrue($result);
    }

    public static function validRpeProvider(): array {
        return [
            'min' => [1],
            'mid' => [5],
            'max' => [10],
        ];
    }

    #[DataProvider('nullableFieldsProvider')]
    public function testNullableDistanceAndTime(?string $distance, ?string $time): void {
        $validator = new SessionValidator();

        $data = $this->createValidData(
            distance: $distance,
            duration: $time
        );

        $result = $validator->validateRegister($data);
if ($result === false) {
    var_dump($validator->getErrors());
}
        $this->assertTrue($result);
    }

    public static function nullableFieldsProvider(): array {
        return [
            'both set' => ['10.5', '01:00'],
            'no distance' => [null, '01:00'],
            'no duration' => ['10.5', null],
            'both null' => [null, null],
        ];
    }

    public function testMultipleErrorsReturned(): void {
        $validator = new SessionValidator();

        $data = [
            'distance' => 'invalid',
            'duration' => 'invalid',
            'date' => 'invalid',
            'rpe' => 999,
        ];

        $result = $validator->validateRegister($data);

        $this->assertFalse($result);

        $errors = $validator->getErrors();

        $this->assertCount(4, $errors);
        $this->assertArrayHasKey('distance', $errors);
        $this->assertArrayHasKey('duration', $errors);
        $this->assertArrayHasKey('date', $errors);
        $this->assertArrayHasKey('rpe', $errors);
    }

    public function testErrorsResetBetweenValidations(): void {
        $validator = new SessionValidator();

        // Först: invalid
        $validator->validateRegister($this->createValidData(distance: 'invalid'));
        $this->assertNotEmpty($validator->getErrors());

        // Sen: valid
        $validator->validateRegister($this->createValidData());
        $this->assertEmpty($validator->getErrors());
    }
}