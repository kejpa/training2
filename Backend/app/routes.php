<?php

declare(strict_types=1);

use App\Application\Actions\Activity\AddActivityAction;
use App\Application\Actions\Activity\DeleteActivityAction;
use App\Application\Actions\Activity\GetActivityAction;
use App\Application\Actions\Activity\GetAllActivitiesAction;
use App\Application\Actions\Activity\UpdateActivityAction;
use App\Application\Actions\Login\LogoutAction;
use App\Application\Actions\Login\MailLoginAction;
use App\Application\Actions\Login\NewLoginCodeAction;
use App\Application\Actions\Login\RefreshTokenAction;
use App\Application\Actions\Login\ResendLoginAction;
use App\Application\Actions\Login\TotpLoginAction;
use App\Application\Actions\Session\AddSessionAction;
use App\Application\Actions\Session\DeleteSessionAction;
use App\Application\Actions\Session\GetAllSessionsAction;
use App\Application\Actions\Session\GetSessionAction;
use App\Application\Actions\Session\UpdateSessionAction;
use App\Application\Actions\User\RegisterUserAction;
use App\Application\Actions\User\ViewUserAction;
use App\Application\Middleware\JwtMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    /*    $app->options('/{routes:.*}', function (Request $request, Response $response) {
            // CORS Pre-Flight OPTIONS Request Handler
            return $response;
        });
    */

    $app->group('/api', function (Group $group) {
        $group->post('/register', RegisterUserAction::class);
        $group->post('/resend', ResendLoginAction::class);
        $group->post('/getNewCode', NewLoginCodeAction::class);
        $group->post('/login/mail', MailLoginAction::class);
        $group->post('/login/totp', TotpLoginAction::class);
        $group->get('/refresh', RefreshTokenAction::class);
        $group->delete('/refresh', LogoutAction::class);

        $group->group('', function (Group $protected) {
            // Activities
            $protected->get('/activities', GetAllActivitiesAction::class);
            $protected->get('/activities/{id}', GetActivityAction::class);
            $protected->post('/activities', AddActivityAction::class);
            $protected->put('/activities/{id}', UpdateActivityAction::class);
            $protected->delete('/activities/{id}', DeleteActivityAction::class);
            // Sessions
            $protected->get('/sessions', GetAllSessionsAction::class);
            $protected->get('/sessions/{id}', GetSessionAction::class);
            $protected->post('/sessions', AddSessionAction::class);
            $protected->put('/sessions/{id}', UpdateSessionAction::class);
            $protected->delete('/sessions/{id}', DeleteSessionAction::class);

        })->add(JwtMiddleware::class);
    });


    $app->group('/users', function (Group $group) {
        $group->get('/{id}', ViewUserAction::class);
    })->add(JwtMiddleware::class);
};
