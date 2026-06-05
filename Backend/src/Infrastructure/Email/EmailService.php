<?php

namespace App\Infrastructure\Email;

use App\Domain\User\User;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class EmailService {
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
    }

    private function createMailer(): PHPMailer {
        $mailer = new PHPMailer(true);

        // SMTP-konfiguration
        if (!$this->isDevelopment) {
            $mailer->isSMTP();
            $mailer->Host = $_ENV['SMTP_HOST'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $_ENV['SMTP_USERNAME'];
            $mailer->Password = $_ENV['SMTP_PASSWORD'];
            $mailer->SMTPSecure = $_ENV['SMTP_ENCRYPTION'];
            $mailer->Port = $_ENV['SMTP_PORT'];
        }
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);

        return $mailer;
    }

    public function sendWelcomeEmail(User $user): void {
        $mailer = $this->createMailer();
        try {
            $mailer->addAddress($user->getEmail(), $user->getName());
            $mailer->Subject = 'Välkommen!';
            $mailer->isHTML(true);
            $mailer->Body = $this->getWelcomeEmailTemplate($user);

            if ($this->isDevelopment) {
                $this->saveToFile($mailer);
            } else {
                $mailer->send();
            }
        } catch (Exception $e) {
            throw new \RuntimeException("E-post kunde inte skickas: {$mailer->ErrorInfo}");
        }
    }

    public function resendEmail(User $user): void {
        $mailer = $this->createMailer();
        try {
            $mailer->addAddress($user->getEmail(), $user->getName());
            $mailer->Subject = 'Återsändning av inloggnings-qr';
            $mailer->isHTML(true);

            // Avkoda base64 och lägg till som embedded image
            $imageData = base64_decode($user->getImgData());
            $mailer->addStringEmbeddedImage(
                $imageData,
                'qrkod',        // CID (Content-ID)
                'qr.png',       // Filnamn
                'base64',
                'image/png'
            );

            $mailer->Body = $this->getResendEmailTemplate($user);

            if ($this->isDevelopment) {
                $this->saveToFile($mailer);
            } else {
                $mailer->send();
            }
        } catch (Exception $e) {
            throw new \RuntimeException("E-post kunde inte skickas: {$mailer->ErrorInfo}");
        }
    }

    public function sendNewCodeEmail(User $user): void {
        $mailer = $this->createMailer();
        try {
            $mailer->addAddress($user->getEmail(), $user->getName());
            $mailer->Subject = 'Ny inloggningskod';
            $mailer->isHTML(true);

            $mailer->Body = "<h1>Hej igen {$user->getFirstname()}!</h1>
<p>Här kommer den nya inloggningskoden.</p>
<p>Följande kod: {$user->getCode()} gäller till {$user->getExpires()->format('H:i e')}</p>
<p>mvh<br>Webbmaster</p>
            ";

            if ($this->isDevelopment) {
                $this->saveToFile($mailer);
            } else {
                $mailer->send();
            }
        } catch (Exception $e) {
            throw new \RuntimeException("E-post kunde inte skickas: {$mailer->ErrorInfo}");
        }
    }

    private function getWelcomeEmailTemplate(User $user): string {
        return "
<h1>Välkommen {$user->getName()}!</h1>
<p>Tack för att du registrerade dig.</p>
<p>Du loggar in med en engångskoder men inloggningen håller länge så du behöver troligen inte logga in så ofta.</p>
<p>Följande kod: {$user->getCode()} gäller till {$user->getExpires()->format('H:i e')}</p>
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

    private function saveToFile(PHPMailer $mailer): void {
        if (!$mailer->preSend()) {
            throw new \RuntimeException("Kunde inte förbereda e-post");
        }

        $timestamp = date('Y-m-d_His');
        $recipient = $mailer->getToAddresses()[0][0] ?? 'unknown';
        $safeSubject = preg_replace('/[^a-z0-9]/i', '_', $mailer->Subject);
        $filename = "{$timestamp}_{$recipient}_{$safeSubject}.eml";
        $filepath = $this->mailLogPath . '/' . $filename;

        file_put_contents($filepath, $mailer->getSentMIMEMessage());

        error_log("E-post sparad till: logs/mail/{$filename}");
    }
}
