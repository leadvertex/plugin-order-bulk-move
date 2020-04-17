<?php

use Lcobucci\JWT\Parser;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Components\Handshake\Registration;
use Leadvertex\Plugin\Core\Macros\Controllers\PluginController;
use Leadvertex\Plugin\Core\Macros\Factories\AppFactory;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;
use Slim\Routing\RouteCollector;

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../medoo.php';

$factory = new AppFactory();
$application = $factory->web();


/** @var RouteCollector $router */
$router = $application->getRouteCollector();
foreach ($router->getRoutes() as $route) {
    if ($route->getPattern() == "/registration") {
        $route->setCallable(function (Request $request, Response $response) {
            $parser = new Parser();
            $token = $parser->parse($request->getParsedBodyParam('registration'));
            Connector::setCompanyId($token->getClaim('cid'));

            $registration = Registration::findById(
                $token->getClaim('plugin')->id,
                $token->getClaim('plugin')->model
            );

            if ($registration) {
                $registration->delete();
            }

            $controller = new PluginController($request, $response);
            return $controller->registration();
        });
        break;
    }
}

$application->run();