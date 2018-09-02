<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ApiClients\Tools\TestUtilities\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use React\Http\Io\ServerRequest;
use React\Http\Response;
use WyriHaximus\React\Http\Middleware\WebrootPreloadMiddleware;
use function React\Promise\resolve;

final class WebrootPreloadMiddlewareTest extends TestCase
{
    public function testLogger()
    {
        $webroot = __DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR;
        $logger = new class() extends AbstractLogger {
            private $messages = [];

            public function log($level, $message, array $context = [])
            {
                $this->messages[] = [
                    'level' => $level,
                    'message' => $message,
                ];
            }

            public function getMessages(): array
            {
                return $this->messages;
            }
        };

        new WebrootPreloadMiddleware($webroot, $logger);

        self::assertSame([
            [
                'level' => 'debug',
                'message' => '/android.jpg: 15.61KiB (image/jpeg)',
            ],
            [
                'level' => 'debug',
                'message' => '/app.css: 18B (text/css)',
            ],
            [
                'level' => 'debug',
                'message' => '/app.js: 27B (application/javascript)',
            ],
            [
                'level' => 'debug',
                'message' => '/favicon.ico: 5.3KiB (image/x-icon)',
            ],
            [
                'level' => 'debug',
                'message' => '/google.png: 594B (image/png)',
            ],
            [
                'level' => 'debug',
                'message' => '/index.html: 453B (text/html)',
            ],
            [
                'level' => 'debug',
                'message' => '/mind-blown.gif: 4.37MiB (image/gif)',
            ],
            [
                'level' => 'debug',
                'message' => '/mind-blown.webp: 2.28MiB (image/webp)',
            ],
            [
                'level' => 'debug',
                'message' => '/robots.txt: 68B (text/plain)',
            ],
            [
                'level' => 'debug',
                'message' => '/unknown.unknown_extension: 73B (application/octet-stream)',
            ],
            [
                'level' => 'info',
                'message' => 'Preloaded 10 file(s) with a combined size of 6.67MiB from "' . $webroot . '" into memory',
            ],
        ], $logger->getMessages());
    }

    public function testMiss()
    {
        $request = new ServerRequest('GET', 'https://example.com/');
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR);
        $next = function () {
            return new Response(200);
        };
        /** @var ResponseInterface $response */
        $response = $this->await(resolve($middleware($request, $next)));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    public function provideHits()
    {
        yield [
            'app.css',
            'text/css',
        ];

        yield [
            'app.js',
            'application/javascript',
        ];

        yield [
            'index.html',
            'text/html',
        ];

        yield [
            'robots.txt',
            'text/plain',
        ];

        yield [
            'google.png',
            'image/png',
        ];

        yield [
            'android.jpg',
            'image/jpeg',
        ];

        yield [
            'mind-blown.gif',
            'image/gif',
        ];

        yield [
            'mind-blown.webp',
            'image/webp',
        ];
    }

    /**
     * @dataProvider provideHits
     */
    public function testHit(string $file, string $contentType)
    {
        $request = new ServerRequest('GET', 'https://example.com/' . $file);
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR);
        $next = function () {
            return new Response(200);
        };
        /** @var ResponseInterface $response */
        $response = $this->await(resolve($middleware($request, $next)));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'Content-Type' => [
                $contentType,
            ],
        ], $response->getHeaders());
        self::assertSame(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . $file), (string)$response->getBody());
    }
}
