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
    private function createTestUser(): User
    {
        return new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret',
            'https://qr.url',
            base64_encode('fake-qr-image-data'),
            '123456',
            new \DateTimeImmutable('+2 hours')
        );
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

    public function testSendsWelcomeEmailInDevelopmentMode(): void {
        $user = $this->createTestUser();

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('test@example.com', $files[0]);
    }

    public function testWelcomeEmailContainsUserName(): void {
        $user = $this->createTestUser();

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('Anna Andersson', $content);
    }

    public function testWelcomeEmailContainsVerificationCode(): void {
        $user = $this->createTestUser();

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('123456', $content);
    }

    public function testSendsResendEmailInDevelopmentMode(): void {
        $user = $this->createTestUser();

        $this->emailService->resendEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('test@example.com', $files[0]);
    }

    public function testResendEmailHasCorrectSubject(): void
    {
        $user = $this->createTestUser();

        $this->emailService->resendEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        // Testa bara att Subject: innehåller nyckelord
        $this->assertMatchesRegularExpression('/Subject:.*inloggnings-qr/i', $content);
    }
    public function testResendEmailContainsUserName(): void {
        $user = $this->createTestUser();

        $this->emailService->resendEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('Anna', $content);
    }

    public function testResendEmailContainsEmbeddedImage(): void {
        $user = $this->createTestUser();

        $this->emailService->resendEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        // Verifiera att embedded image finns med CID
        $this->assertStringContainsString('Content-ID: <qrkod>', $content);
        $this->assertStringContainsString('Content-Type: image/png', $content);
    }

    public function testResendEmailUsesEmbeddedImageInHtml(): void {
        $user = $this->createTestUser();

        $this->emailService->resendEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        // HTML ska referera till cid:qrkod
        $this->assertStringContainsString('cid:qrkod', $content);
    }

    public function testSendsNewCodeEmailInDevelopmentMode(): void {
        $user = $this->createTestUser();

        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');

        $this->assertCount(1, $files);
        $this->assertStringContainsString('test@example.com', $files[0]);
    }

    public function testNewCodeEmailHasCorrectSubject(): void {
        $user = $this->createTestUser();

        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        // Testa bara att Subject: innehåller nyckelord
        $this->assertMatchesRegularExpression('/Subject:.*ny inloggningskod/i', $content);
    }

    public function testNewCodeEmailContainsFirstName(): void {
        $user = $this->createTestUser();

        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('Hej igen Anna!', $content);
    }

    public function testNewCodeEmailContainsCode(): void {
        $user = $this->createTestUser();

        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('123456', $content);
    }

    public function testNewCodeEmailContainsExpirationTime(): void {
        $user = $this->createTestUser();

        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        // Verifiera att expires-tid finns i formatet HH:MM
        $expectedTime = $user->getExpires()->format('H:i');
        $this->assertStringContainsString($expectedTime, $content);
    }

    public function testNewCodeEmailContainsTimezone(): void {
        $user = $this->createTestUser();

        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $content = file_get_contents($files[0]);

        // Verifiera att timezone finns
        $expectedTimezone = $user->getExpires()->format('e');
        $this->assertStringContainsString($expectedTimezone, $content);
    }

    public function testMultipleEmailsCreateSeparateFiles(): void {
        $user1 = new User(
            new UserId(),
            'user1@example.com',
            'User',
            'One',
            'secret',
            'https://qr.url',
            base64_encode('qr1'),
            '111111',
            new \DateTimeImmutable('+1 hour')
        );

        $user2 = new User(
            new UserId(),
            'user2@example.com',
            'User',
            'Two',
            'secret',
            'https://qr.url',
            base64_encode('qr2'),
            '222222',
            new \DateTimeImmutable('+2 hours')
        );

        $this->emailService->sendWelcomeEmail($user1);
        $this->emailService->sendNewCodeEmail($user2);
        $this->emailService->resendEmail($user1);

        $files = glob($this->testLogPath . '/mail/*.eml');

        $this->assertCount(3, $files);
    }

    public function testEmailFilenamesContainRecipient(): void {
        $user = $this->createTestUser();

        $this->emailService->sendWelcomeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $filename = basename($files[0]);

        $this->assertStringContainsString('test@example.com', $filename);
    }

    public function testEmailFilenamesContainTimestamp(): void {
        $user = $this->createTestUser();

        $beforeSend = date('Y-m-d');
        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $filename = basename($files[0]);

        $this->assertStringContainsString($beforeSend, $filename);
    }

    public function testThrowsExceptionWhenMailerFails(): void {
        // Detta test är svårt att genomföra utan att mocka PHPMailer
        // eftersom EmailService skapar PHPMailer internt
        // Men vi kan testa med ogiltig data

        $this->markTestSkipped('Requires mocking PHPMailer which is created internally');
    }

    public function testAllEmailMethodsWorkWithSameUser(): void {
        $user = $this->createTestUser();

        // Alla tre metoder ska fungera
        $this->emailService->sendWelcomeEmail($user);
        $this->emailService->resendEmail($user);
        $this->emailService->sendNewCodeEmail($user);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $this->assertCount(3, $files);
    }

    public function testResendEmailWithoutQrImageStillWorks(): void {
        $userWithoutQr = new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret',
            null,
            null, // Ingen QR-bild
            '123456',
            new \DateTimeImmutable('+1 hour')
        );

        // Borde inte kasta exception även utan QR-bild
        $this->emailService->resendEmail($userWithoutQr);

        $files = glob($this->testLogPath . '/mail/*.eml');
        $this->assertCount(1, $files);
    }
}