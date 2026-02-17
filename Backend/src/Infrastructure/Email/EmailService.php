<?php

namespace App\Infrastructure\Email;

use App\Domain\User\User;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService {
    private PHPMailer $mailer;
    private bool $isDevelopment;
    private string $mailLogPath;

    public function __construct(string $logPath = null) {
        $this->isDevelopment = ($_ENV['APP_ENV'] ?? 'production') === 'development';

        // Använd befintlig logs-mapp och lägg till mail-undermapp
        $baseLogPath = $logPath ?? __DIR__ . '/../../../logs';
        $this->mailLogPath = $baseLogPath . '/mail';

        // Skapa mail-mapp om den inte finns
        if ($this->isDevelopment && !is_dir($this->mailLogPath)) {
            mkdir($this->mailLogPath, 0755, true);
        }

        $this->mailer = new PHPMailer(true);

        // SMTP-konfiguration
        if (!$this->isDevelopment) {
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['SMTP_HOST'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['SMTP_USERNAME'];
            $this->mailer->Password = $_ENV['SMTP_PASSWORD'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $_ENV['SMTP_PORT'];
        }
        $this->mailer->CharSet = 'UTF-8';

// Avsändare
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    }

    public function sendWelcomeEmail(User $user): void {
        try {
            $this->mailer->addAddress($user->getEmail(), $user->getName());
            $this->mailer->Subject = 'Välkommen!';
            $this->mailer->isHTML(true);

            // Avkoda base64 och lägg till som embedded image
            $imageData = base64_decode($user->getImgData());
            $this->mailer->addStringEmbeddedImage(
                $imageData,
                'qrkod',           // CID (Content-ID)
                'qr.png',       // Filnamn
                'base64',
                'image/png'
            );

            $this->mailer->Body = $this->getWelcomeEmailTemplate($user);

            if ($this->isDevelopment) {
                $this->saveToFile();
            } else {
                $this->mailer->send();
            }
        } catch (Exception $e) {
            throw new \RuntimeException("E-post kunde inte skickas: {$this->mailer->ErrorInfo}");
        }
    }

    public function resendEmail(User $user): void {
        try {
            $this->mailer->addAddress($user->getEmail(), $user->getName());
            $this->mailer->Subject = 'Återsändning av inloggnings-qr';
            $this->mailer->isHTML(true);

            // Avkoda base64 och lägg till som embedded image
            $imageData = base64_decode($user->getImgData());
            $this->mailer->addStringEmbeddedImage(
                $imageData,
                'qrkod',           // CID (Content-ID)
                'qr.png',       // Filnamn
                'base64',
                'image/png'
            );

            $this->mailer->Body = $this->getResendEmailTemplate($user);

            if ($this->isDevelopment) {
                $this->saveToFile();
            } else {
                $this->mailer->send();
            }
        } catch (Exception $e) {
            throw new \RuntimeException("E-post kunde inte skickas: {$this->mailer->ErrorInfo}");
        }
    }

    public function sendNewCodeEmail(User $user): void {
        try {
            $this->mailer->addAddress($user->getEmail(), $user->getName());
            $this->mailer->Subject = 'Ny inloggningskod';
            $this->mailer->isHTML(true);

            $this->mailer->Body = "<h1>Hej igen {$user->getFirstname()}!</h1>
<p>Här kommer den nya inloggningskoden.</p>
<p>Följande kod: {$user->getCode()} gäller till {$user->getExpires()->format('H:i e')}</p>
<p>mvh<br>Webbmaster</p>
            ";


            if ($this->isDevelopment) {
                $this->saveToFile();
            } else {
                $this->mailer->send();
            }
        } catch (Exception $e) {
            throw new \RuntimeException("E-post kunde inte skickas: {$this->mailer->ErrorInfo}");
        }
    }

    private function getWelcomeEmailTemplate(User $user): string {
        return "
<h1>Välkommen {$user->getName()}!</h1>
<p>Tack för att du registrerade dig.</p>
<p>Du kan välja att logga in med en engångskod som antingen skickas varje gång du loggar in, eller via valfri authenticator-app.</p>
<p>Scanna nedanstående qr-kod om du vill använda en authenticator-app för inloggning<br>
<img src='cid:qrkod' alt='qrkod' /> </p>
<p>Vill du logga in med en engångskod kan du denna gång använda följande kod: {$user->getCode()}</p>
<p>mvh<br>Webbmaster</p>
";
    }

    private function getResendEmailTemplate(User $user): string {
        return "
<h1>Hej igen {$user->getFirstname()}!</h1>
<p>Här kommer qr-koden igen som du kan använda via valfri authenticator-app.</p>
<p>Scanna nedanstående qr-kod för att använda en authenticator-app för inloggning<br>
<img src='cid:qrkod' alt='qrkod' /> </p>
<p>mvh<br>Webbmaster</p>
";
    }

    private function saveToFile(): void {
        if (!$this->mailer->preSend()) {
            throw new \RuntimeException("Kunde inte förbereda e-post");
        }

        $timestamp = date('Y-m-d_His');
        $recipient = $this->mailer->getToAddresses()[0][0] ?? 'unknown';
        $safeSubject = preg_replace('/[^a-z0-9]/i', '_', $this->mailer->Subject);
        $filename = "{$timestamp}_{$recipient}_{$safeSubject}.eml";
        $filepath = $this->mailLogPath . '/' . $filename;

        file_put_contents($filepath, $this->mailer->getSentMIMEMessage());

        error_log("📧 E-post sparad till: logs/mail/{$filename}");
    }
}