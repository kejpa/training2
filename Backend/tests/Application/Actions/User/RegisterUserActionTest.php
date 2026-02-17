<?php


declare(strict_types=1);

namespace Tests\Application\Actions\User;

use App\Application\Actions\User\RegisterUserAction;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserValidator;
use App\Infrastructure\Email\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class RegisterUserActionTest extends TestCase {
    private UserRepository $userRepository;
    private UserValidator $userValidator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private RegisterUserAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;


    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userValidator = $this->createMock(UserValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
$this->responseFactory = new ResponseFactory();

        $this->action = new RegisterUserAction(
            $this->logger,
            $this->userRepository,
            $this->emailService,
            $this->userValidator
        );

        // Sätt emailService via reflection eftersom det är private
        $reflection = new \ReflectionClass($this->action);
        $property = $reflection->getProperty('emailService');
        $property->setAccessible(true);
        $property->setValue($this->action, $this->emailService);

        // Skapa mock request
        $this->request = $this->createMock(Request::class);

        // Sätt request och response via reflection
        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->action, $this->request);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->action, $this->responseFactory->createResponse());
    }

    public function testSuccessfulRegistration(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->with($data)
            ->willReturn(true);

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        $this->emailService
            ->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with($this->isInstanceOf(User::class));

        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);

        $this->assertEquals(201, $response->getStatusCode());

        $body = (string)$response->getBody();
        $json = json_decode($body, true);

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('user', $json['data']);
        $this->assertArrayHasKey('email', $json['data']['user']);
        $this->assertEquals('test@example.com', $json['data']['user']['email']);
    }

    public function testRegistrationWithValidationErrors(): void {
        $data = [
            'email' => 'invalid-email',
            'firstname' => '',
            'lastname' => 'Andersson',
        ];

        $errors = [
            'email' => 'Ogiltig e-postadress',
            'firstname' => 'Förnamn krävs'
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->with($data)
            ->willReturn(false);

        $this->userValidator
            ->method('getErrors')
            ->willReturn($errors);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->emailService
            ->expects($this->never())
            ->method('sendWelcomeEmail');

        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string)$response->getBody();
        $json = json_decode($body, true);

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('errors', $json['data']);
        $this->assertEquals($errors, $json['data']['errors']);
    }

    public function testRegistrationHandlesCaseInsensitiveData(): void {
        $data = [
            'EMAIL' => 'test@example.com',
            'FIRSTNAME' => 'Anna',
            'LASTNAME' => 'Andersson',
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->with([
                'email' => 'test@example.com',
                'firstname' => 'Anna',
                'lastname' => 'Andersson',
            ])
            ->willReturn(true);

        $this->userRepository
            ->expects($this->once())
            ->method('save');

        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testRegistrationCreatesUserWith2FA(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->willReturn(true);

        $capturedUser = null;
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);

        $this->assertEquals(201, $response->getStatusCode());

        // Verifiera att användaren har 2FA-data
        $this->assertNotNull($capturedUser);
        $this->assertNotEmpty($capturedUser->getSecret());
        $this->assertNotEmpty($capturedUser->getQrUrl());
        $this->assertNotEmpty($capturedUser->getImgData());
        $this->assertNotEmpty($capturedUser->getCode());
        $this->assertInstanceOf(\DateTimeImmutable::class, $capturedUser->getExpires());

        // Verifiera att koden är 6 siffror
        $this->assertMatchesRegularExpression('/^\d{6}$/', $capturedUser->getCode());

        // Verifiera att QR-data är base64
        $this->assertNotFalse(base64_decode($capturedUser->getImgData(), true));
    }

    public function testRegistrationSetsExpirationTo2Hours(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->willReturn(true);

        $capturedUser = null;
        $this->userRepository
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

        $beforeAction = new \DateTimeImmutable();
        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);
        $afterAction = new \DateTimeImmutable();

        $this->assertNotNull($capturedUser);
        $expires = $capturedUser->getExpires();

        // Verifiera att expires är ungefär 2 timmar från nu
        $diff = $expires->getTimestamp() - $beforeAction->getTimestamp();
        $this->assertGreaterThanOrEqual(7200 - 5, $diff); // 2h minus 5 sekunder margin
        $this->assertLessThanOrEqual(7200 + 5, $diff);    // 2h plus 5 sekunder margin
    }

    public function testRegistrationLogsErrorOnException(): void
    {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->willReturn(true);

        $exception = new \Exception('Database connection failed');
        $this->userRepository
            ->method('save')
            ->willThrowException($exception);

        // Använd with() med callback matcher
        $this->logger
            ->expects($this->exactly(2))
            ->method('error')
            ->with($this->callback(function($message) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    return str_contains($message, 'Exception throwed:');
                }
                if ($callCount === 2) {
                    return str_contains($message, 'Parsed body:');
                }

                return false;
            }));

        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string)$response->getBody();
        $json = json_decode($body, true);

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('error', $json['data']);
        $this->assertEquals('Database connection failed', $json['data']['error']);
    }
    public function testEmailServiceIsCalledAfterSuccessfulSave(): void {
        $data = [
            'email' => 'test@example.com',
            'firstname' => 'Anna',
            'lastname' => 'Andersson',
        ];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateRegistration')
            ->willReturn(true);

        // Verifiera ordningen: först save, sedan email
        $callOrder = [];

        $this->userRepository
            ->method('save')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'save';
            });

        $this->emailService
            ->method('sendWelcomeEmail')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'email';
            });

        $response = $this->action->__invoke($this->request, $this->responseFactory->createResponse(), []);

        $this->assertEquals(['save', 'email'], $callOrder);
    }
}