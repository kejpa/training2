<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\NewLoginCodeAction;
use App\Domain\Login\LoginValidator;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Email\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class NewLoginCodeActionTest extends TestCase {
    private UserRepository $userRepository;
    private LoginValidator $validator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private NewLoginCodeAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->validator = $this->createMock(LoginValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new NewLoginCodeAction(
            $this->logger,
            $this->userRepository,
            $this->emailService,
            $this->validator
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

    private function createTestUser(string $code = null, \DateTimeImmutable $expires = null): User {
        return new User(
            new UserId(),
            'test@example.com',
            'Anna',
            'Andersson',
            'secret123',
            'https://qr.url',
            'base64imagedata',
            $code,
            $expires
        );
    }

    public function testGeneratesNewCodeWhenExpiresIsNull(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser(null, null);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
            ->with($data)
            ->willReturn(true);

        $this->userRepository
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

        // Verifiera att ny kod skapades
        $this->assertNotNull($capturedUser);
        $this->assertNotNull($capturedUser->getCode());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $capturedUser->getCode());
        $this->assertNotNull($capturedUser->getExpires());
    }

    public function testGeneratesNewCodeWhenExpiresHasPassed(): void {
        $data = ['email' => 'test@example.com'];
        $oldCode = '111111';
        $user = $this->createTestUser($oldCode, new \DateTimeImmutable('-1 hour')); // Utgången

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

        $this->emailService
            ->method('sendNewCodeEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att ny kod skapades (inte samma som gamla)
        $this->assertNotNull($capturedUser);
        $this->assertNotNull($capturedUser->getCode());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $capturedUser->getCode());
        // Koden kan vara samma eller olika pga randomisering, men den ska finnas
    }

    public function testKeepsExistingCodeWhenExpiresIsValid(): void {
        $data = ['email' => 'test@example.com'];
        $existingCode = '123456';
        $user = $this->createTestUser($existingCode, new \DateTimeImmutable('+30 minutes')); // Giltig

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

        $this->emailService
            ->method('sendNewCodeEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att samma kod behölls
        $this->assertNotNull($capturedUser);
        $this->assertEquals($existingCode, $capturedUser->getCode());
        $this->assertNotNull($capturedUser->getExpires());
    }

    public function testExtendsExpirationTimeWhenCodeIsValid(): void {
        $data = ['email' => 'test@example.com'];
        $existingCode = '123456';
        $oldExpires = new \DateTimeImmutable('+30 minutes');
        $user = $this->createTestUser($existingCode, $oldExpires);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

        $this->emailService
            ->method('sendNewCodeEmail');

        $beforeAction = new \DateTimeImmutable();

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $afterAction = new \DateTimeImmutable();

        $this->assertEquals(200, $response->getStatusCode());

        // Verifiera att expires förlängdes till +1 timme från nu
        $this->assertNotNull($capturedUser);
        $newExpires = $capturedUser->getExpires();
        $this->assertNotNull($newExpires);

        // Nya expires borde vara ~1 timme från nu (inte från gamla expires)
        $diff = $newExpires->getTimestamp() - $beforeAction->getTimestamp();
        $this->assertGreaterThanOrEqual(3600 - 5, $diff); // 1h minus margin
        $this->assertLessThanOrEqual(3600 + 5, $diff);    // 1h plus margin
    }

    public function testSetsExpirationTo1Hour(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser(null, null);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

        $this->emailService
            ->method('sendNewCodeEmail');

        $beforeAction = new \DateTimeImmutable();

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $afterAction = new \DateTimeImmutable();

        $this->assertNotNull($capturedUser);
        $expires = $capturedUser->getExpires();

        $diff = $expires->getTimestamp() - $beforeAction->getTimestamp();
        $this->assertGreaterThanOrEqual(3600 - 5, $diff);
        $this->assertLessThanOrEqual(3600 + 5, $diff);
    }

    public function testReturns404WhenUserNotFound(): void {
        $data = ['email' => 'nonexistent@example.com'];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

    public function testReturnsValidationErrors(): void {
        $data = ['email' => 'invalid-email'];
        $errors = ['email' => 'Ogiltig e-postadress'];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
            ->with($data)
            ->willReturn(false);

        $this->validator
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

    public function testHandlesNullParsedBody(): void {
        $this->request
            ->method('getParsedBody')
            ->willReturn(null);

        $this->validator
            ->method('validateEmail')
            ->with([])
            ->willReturn(false);

        $this->validator
            ->method('getErrors')
            ->willReturn(['email' => 'E-post krävs']);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testConvertsKeysToLowercase(): void {
        $data = ['EMAIL' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
            ->with(['email' => 'test@example.com'])
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->userRepository
            ->method('save');

        $this->emailService
            ->method('sendNewCodeEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLogsErrorOnException(): void {
        $data = ['email' => 'test@example.com'];
        $exception = new \Exception('Database error');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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
        $this->assertEquals('Database error', $body['data']['error']);

        $this->assertCount(2, $loggedMessages);
        $this->assertStringContainsString('Exception thrown:', $loggedMessages[0]);
        $this->assertStringContainsString('Parsed body:', $loggedMessages[1]);
    }

    public function testLogsErrorWhenEmailServiceFails(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();
        $exception = new \Exception('Email service unavailable');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

    public function testExecutesInCorrectOrder(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();
        $callOrder = [];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

    public function testCodeIs6Digits(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser(null, null);

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->validator
            ->method('validateEmail')
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

        $this->emailService
            ->method('sendNewCodeEmail');

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
}