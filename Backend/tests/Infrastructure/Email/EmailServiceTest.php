<?php

namespace Tests\Infrastructure\Email;

use App\Domain\User\User;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Email\EmailService;
use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase {
    private string $testLogPath;
    private EmailService $emailService;

    protected function setUp(): void {
        $this->testLogPath = sys_get_temp_dir() . '/test-logs';

        if (!is_dir($this->testLogPath)) {
            mkdir($this->testLogPath, 0755, true);
        }

        // Sätt development mode
        $_ENV['APP_ENV'] = 'development';
        $_ENV['MAIL_FROM_ADDRESS'] = 'test@example.com';
        $_ENV['MAIL_FROM_NAME'] = 'Test App';

        $this->emailService = new EmailService($this->testLogPath);
    }

    protected function tearDown(): void {
        // Rensa test-filer
        $files = glob($this->testLogPath . '/mail/*.eml');
        foreach ($files as $file) {
            unlink($file);
        }

        if (is_dir($this->testLogPath . '/mail')) {
            rmdir($this->testLogPath . '/mail');
        }

        if (is_dir($this->testLogPath)) {
            rmdir($this->testLogPath);
        }
    }

    public function testSendsWelcomeEmailInDevelopmentMode(): void {
        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret',
            'https://qr.url',
            'base64data',
            '123456',
            new \DateTimeImmutable('+2 hours')
        );

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('test@example.com', $files[0]);
    }

    public function testEmailContainsUserName(): void {
        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret',
            'null',
            'null',
            '123456',
            new \DateTimeImmutable('+2 hours')
        );

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('Anna Andersson', $content);
    }

    public function testEmailContainsVerificationCode(): void {
        $user = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret',
            'null',
            'null',
            '987654',
            new \DateTimeImmutable('+2 hours')
        );

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('987654', $content);
    }
}