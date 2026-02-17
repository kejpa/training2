<?php

namespace App\Application\Actions\User;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserValidator;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Email\EmailService;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class RegisterUserAction extends UserAction {
    public function __construct(LoggerInterface $logger, UserRepository $userRepository, EmailService $emailService, private UserValidator $userValidator) {
        parent::__construct($logger, $userRepository, $emailService);
    }

    /**
     * @inheritDoc
     */
    protected function action(): Response {
        $data = $this->request->getParsedBody();
        $data = array_change_key_case($data ?? [], CASE_LOWER);

        // Validera
        if (!$this->userValidator->validateRegistration($data)) {
            return $this->respondWithData([
                'errors' => $this->userValidator->getErrors()
            ], 400);
        }

        try {
            // Skapa hemlig nyckel för 2-faktorsautentisering
            $g2fa = new Google2FA();
            $secret = $g2fa->generateSecretKey();

            // Skapa en url för att kunna skapa en QR-kod
            $qrUrl = $g2fa->getQRCodeUrl("Träningslogg", $data['email'], $secret);

            // Skapa en qr-ko mha BaconQR-biblioteket
            $renderer = new GDLibRenderer(250);
            $writer = new Writer($renderer);

            // Skapa en base64-sträng med informationen från den skapade qr-koden
            $b64_data = base64_encode($writer->writeString($qrUrl));

            // Skapa en slumpmässig 6 siffrig kod
            $randomCode = (string)random_int(100000, 999999);

            // Skapa user
            $user = new User(
                new UserId(),
                $data['email'],
                $data['firstname'],
                $data['lastname'],
                $secret,
                $qrUrl,
                $b64_data,
                $randomCode,
                new \DateTimeImmutable('+2 hours'),
            );

            // Spara
            $this->userRepository->save($user);

            // Maila användaren info om hur man loggar in
            $this->emailService->sendWelcomeEmail($user);

            return $this->respondWithData([
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            $this->logger->error("RegisterUserAction: Exception throwed:" . $e->getMessage());
            $this->logger->error("RegisterUserAction: Parsed body:" . print_r($data, true));

            return $this->respondWithData([
                'error' => $e->getMessage()
            ], 400);
        }
    }
}