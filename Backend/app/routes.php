<?php

declare(strict_types=1);

use App\Application\Actions\Auth\LogoutAction;
use App\Application\Actions\Login\MailLoginAction;
use App\Application\Actions\Login\NewLoginCodeAction;
use App\Application\Actions\Login\RefreshTokenAction;
use App\Application\Actions\Login\ResendLoginAction;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\RegisterUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->post('/register', RegisterUserAction::class);
    $app->post('/resend', ResendLoginAction::class);
    $app->post('/getNewCode', NewLoginCodeAction::class);
    $app->post('/login/mail', MailLoginAction::class);
    $app->get('/refresh', RefreshTokenAction::class);
    $app->delete('/refresh', LogoutAction::class);


    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });
};
