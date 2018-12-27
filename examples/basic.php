<?php declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Response;
use React\Http\Server as HttpServer;
use React\Socket\Server as SocketServer;
use WyriHaximus\React\Http\Middleware\WebrootPreloadMiddleware;

require \dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';

$loop = Factory::create();
$socket = new SocketServer('127.0.0.1:9001', $loop);
$http = new HttpServer([
    new WebrootPreloadMiddleware(\dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'tests' . \DIRECTORY_SEPARATOR . 'webroot' . \DIRECTORY_SEPARATOR),
    function (ServerRequestInterface $request) {
        return new Response(102);
    },
]);
$http->listen($socket);
$loop->run();
