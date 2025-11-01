<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use React\Cache\ArrayCache;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server as HttpServer;
use React\Socket\Server as SocketServer;
use WyriHaximus\React\Http\Middleware\WebrootPreloadMiddleware;

use const WyriHaximus\Constants\HTTPStatusCodes\PROCESSING;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$loop   = Factory::create();
$socket = new SocketServer('127.0.0.1:9001', $loop);
$http   = new HttpServer(
    $loop,
    new WebrootPreloadMiddleware(
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR,
        new NullLogger(),
        new ArrayCache(),
    ),
    static fn (ServerRequestInterface $request): ResponseInterface => new Response(PROCESSING),
);
$http->listen($socket);
$loop->run();
