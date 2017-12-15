<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use React\Http\Io\ServerRequest;
use React\Http\Response;
use ScriptFUSION\Byte\ByteFormatter;
use WyriHaximus\React\Http\Middleware\WebrootPreloadMiddleware;

final class WebrootPreloadMiddlewareTest extends TestCase
{
    public function testLogger()
    {
        $byteFormatter = (new ByteFormatter())->setPrecision(2)->setFormat('%v%u');
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
                'message' => '/style.css: ' . $byteFormatter->format(filesize(__FILE__)),
            ],
            [
                'level' => 'info',
                'message' => 'Preloaded 1 file(s) with a combined size of ' . $byteFormatter->format(filesize(__FILE__)) . ' from "' . __DIR__ . '" into memory',
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
        $response = $middleware($request, $next);

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
            'text/javascript',
        ];

        yield [
            'index.html',
            'text/html',
        ];

        yield [
            'index.html',
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
        $response = $middleware($request, $next);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'Content-Type' => [
                $contentType,
            ],
        ], $response->getHeaders());
        self::assertSame(file_get_contents(__FILE__), (string)$response->getBody());
    }
}
