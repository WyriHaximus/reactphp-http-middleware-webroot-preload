<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use React\Cache\ArrayCache;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use WyriHaximus\React\Http\Middleware\WebrootPreloadMiddleware;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$socket = new SocketServer('127.0.0.1:9001');
$http   = new HttpServer(
    new WebrootPreloadMiddleware(
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR,
        new NullLogger(),
        new ArrayCache(),
    ),
    static fn (ServerRequestInterface $request): ResponseInterface => new Response(Response::STATUS_PROCESSING),
);
$http->listen($socket);
