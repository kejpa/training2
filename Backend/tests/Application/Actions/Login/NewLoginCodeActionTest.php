<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\NewLoginCodeAction;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\User\UserValidator;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Email\EmailService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;

class NewLoginCodeActionTest extends TestCase
{
    private UserRepository $userRepository;
    private UserValidator $userValidator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private NewLoginCodeAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userValidator = $this->createMock(UserValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new NewLoginCodeAction(
            $this->logger,
            $this->userRepository,
            $this->emailService,
            $this->userValidator
        );

        $this->request = $this->createMock(Request::class);

        $reflection = new \ReflectionClass($this->action);

        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->action, $this->request);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->action, $this->responseFactory->createResponse());
    }

    private function createTestUser(): User
    {
        return new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            '123456',
            new \DateTimeImmutable('+2 hours')
        );
    }

    public function testSuccessfulNewLoginCode(): void
    {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->with($data)
            ->willReturn(true);

        $this->userRepository
            ->expects($this->once())
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $capturedUser = null;
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

        $this->emailService
            ->expects($this->once())
            ->method('sendNewCodeEmail')
            ->with($this->isInstanceOf(User::class));

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('user', $body['data']);

        // Verifiera att användaren uppdaterades
        $this->assertNotNull($capturedUser);
        $this->assertNotNull($capturedUser->getCode());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $capturedUser->getCode());
    }

    public function testGenerates6DigitCode(): void
    {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $capturedUser = null;
        $this->userRepository
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertNotNull($capturedUser);
        $code = $capturedUser->getCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertGreaterThanOrEqual(100000, (int)$code);
        $this->assertLessThanOrEqual(999999, (int)$code);
    }

    public function testSetsExpirationTo1Hour(): void
    {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $capturedUser = null;
        $this->userRepository
            ->method('save')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
            });

        $beforeAction = new \DateTime();

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $afterAction = new \DateTime();

        $this->assertNotNull($capturedUser);
        $expires = $capturedUser->getExpires();

        // Verifiera att expires är ungefär 1 timme från nu
        $diff = $expires->getTimestamp() - $beforeAction->getTimestamp();
        $this->assertGreaterThanOrEqual(3600 - 5, $diff); // 1h minus 5 sekunder margin
        $this->assertLessThanOrEqual(3600 + 5, $diff);    // 1h plus 5 sekunder margin
    }

    public function testReturns404WhenUserNotFound(): void
    {
        $data = ['email' => 'nonexistent@example.com'];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->emailService
            ->expects($this->never())
            ->method('sendNewCodeEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Användaren hittades inte', $body['data']['error']);
    }

    public function testReturnsValidationErrors(): void
    {
        $data = ['email' => 'invalid-email'];
        $errors = ['email' => 'Ogiltig e-postadress'];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->with($data)
            ->willReturn(false);

        $this->userValidator
            ->method('getErrors')
            ->willReturn($errors);

        $this->userRepository
            ->expects($this->never())
            ->method('getByEmail');

        $this->userRepository
            ->expects($this->never())
            ->method('save');

        $this->emailService
            ->expects($this->never())
            ->method('sendNewCodeEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('errors', $body['data']);
        $this->assertEquals($errors, $body['data']['errors']);
    }

    public function testHandlesNullParsedBody(): void
    {
        $this->request
            ->method('getParsedBody')
            ->willReturn(null);

        $this->userValidator
            ->method('validateResend')
            ->with([])
            ->willReturn(false);

        $this->userValidator
            ->method('getErrors')
            ->willReturn(['email' => 'E-post krävs']);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testConvertsKeysToLowercase(): void
    {
        $data = ['EMAIL' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->with(['email' => 'test@example.com'])
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->userRepository
            ->method('save');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLogsErrorWhenRepositoryThrowsException(): void
    {
        $data = ['email' => 'test@example.com'];
        $exception = new \Exception('Database error');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willThrowException($exception);

        $loggedMessages = [];
        $this->logger
            ->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function ($message) use (&$loggedMessages) {
                $loggedMessages[] = $message;
            });

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('Database error', $body['data']['error']);

        $this->assertCount(2, $loggedMessages);
        $this->assertStringContainsString('Exception throwed:', $loggedMessages[0]);
        $this->assertStringContainsString('Parsed body:', $loggedMessages[1]);
    }

    public function testLogsErrorWhenEmailServiceFails(): void
    {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();
        $exception = new \Exception('Email service unavailable');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->userRepository
            ->method('save');

        $this->emailService
            ->method('sendNewCodeEmail')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->exactly(2))
            ->method('error');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertEquals('Email service unavailable', $body['data']['error']);
    }

    public function testExecutesInCorrectOrder(): void
    {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();
        $callOrder = [];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->userValidator
            ->method('validateResend')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturnCallback(function () use (&$callOrder, $user) {
                $callOrder[] = 'getByEmail';
                return $user;
            });

        $this->userRepository
            ->method('save')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'save';
            });

        $this->emailService
            ->method('sendNewCodeEmail')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'sendEmail';
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(['getByEmail', 'save', 'sendEmail'], $callOrder);
    }
}