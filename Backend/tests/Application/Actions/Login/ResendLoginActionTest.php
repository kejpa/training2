<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Login;

use App\Application\Actions\Login\ResendLoginAction;
use App\Domain\Login\LoginValidator;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Domain\ValueObject\UserId;
use App\Infrastructure\Email\EmailService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class ResendLoginActionTest extends TestCase {
    private UserRepository $userRepository;
    private LoginValidator $loginValidator;
    private LoggerInterface $logger;
    private EmailService $emailService;
    private ResendLoginAction $action;
    private Request $request;
    private ResponseFactory $responseFactory;

    protected function setUp(): void {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->loginValidator = $this->createMock(LoginValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->responseFactory = new ResponseFactory();

        $this->action = new ResendLoginAction(
            $this->logger,
            $this->userRepository,
            $this->emailService,
            $this->loginValidator
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

    private function createTestUser(): User {
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

    public function testSuccessfulResend(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
            ->method('validateEmail')
            ->with($data)
            ->willReturn(true);

        $this->userRepository
            ->expects($this->once())
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->emailService
            ->expects($this->once())
            ->method('resendEmail')
            ->with($user);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('user', $body['data']);
    }

    public function testResendWithValidationErrors(): void {
        $data = ['email' => 'invalid-email'];
        $errors = ['email' => 'Ogiltig e-postadress'];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
            ->method('validateEmail')
            ->with($data)
            ->willReturn(false);

        $this->loginValidator
            ->method('getErrors')
            ->willReturn($errors);

        $this->userRepository
            ->expects($this->never())
            ->method('getByEmail');

        $this->emailService
            ->expects($this->never())
            ->method('resendEmail');

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

    public function testResendWithNullBody(): void {
        $this->request
            ->method('getParsedBody')
            ->willReturn(null);

        $this->loginValidator
            ->method('validateEmail')
            ->with([])
            ->willReturn(false);

        $this->loginValidator
            ->method('getErrors')
            ->willReturn(['email' => 'E-post krävs']);

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testResendConvertsKeysToLowercase(): void {
        $data = ['EMAIL' => 'test@example.com'];
        $user = $this->createTestUser();

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
            ->method('validateEmail')
            ->with(['email' => 'test@example.com'])
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->emailService
            ->method('resendEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResendLogsErrorWhenRepositoryThrowsException(): void {
        $data = ['email' => 'test@example.com'];
        $exception = new \Exception('User not found');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
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
        $this->assertArrayHasKey('error', $body['data']);
        $this->assertEquals('User not found', $body['data']['error']);

        $this->assertCount(2, $loggedMessages);
        $this->assertStringContainsString('Exception throwed:', $loggedMessages[0]);
        $this->assertStringContainsString('Parsed body:', $loggedMessages[1]);
    }

    public function testResendLogsErrorWhenEmailServiceThrowsException(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();
        $exception = new \Exception('Email service unavailable');

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
            ->method('validateEmail')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturn($user);

        $this->emailService
            ->method('resendEmail')
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

    public function testEmailServiceIsCalledAfterRepositoryFetch(): void {
        $data = ['email' => 'test@example.com'];
        $user = $this->createTestUser();
        $callOrder = [];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
            ->method('validateEmail')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->willReturnCallback(function () use (&$callOrder, $user) {
                $callOrder[] = 'repository';
                return $user;
            });

        $this->emailService
            ->method('resendEmail')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'email';
            });

        $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(['repository', 'email'], $callOrder);
    }
    public function testResendReturnsErrorWhenUserNotFound(): void
    {
        $data = ['email' => 'nonexistent@example.com'];

        $this->request
            ->method('getParsedBody')
            ->willReturn($data);

        $this->loginValidator
            ->method('validateEmail')
            ->willReturn(true);

        $this->userRepository
            ->method('getByEmail')
            ->with('nonexistent@example.com')
            ->willReturn(null);

        $this->emailService
            ->expects($this->never())
            ->method('resendEmail');

        $response = $this->action->__invoke(
            $this->request,
            $this->responseFactory->createResponse(),
            []
        );

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body['data']);
    }
}
