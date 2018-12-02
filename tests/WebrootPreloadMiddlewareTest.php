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
                'message' => '/favicon.ico: 5.3KiB (image/vnd.microsoft.icon)',
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
        $webroot = __DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR;

        yield [
            'app.css',
            'text/css',
            '66b63ea66df33350dbfc10c06473ad00-' . filesize($webroot . 'app.css'),
        ];

        yield [
            'app.js',
            'application/javascript',
            '5dd2db54ae0f849ac375c43d8926e3b0-' . filesize($webroot . 'app.js'),
        ];

        yield [
            'index.html',
            'text/html',
            'ff17bdff9b7222206667cbda2004efb4-' . filesize($webroot . 'index.html'),
        ];

        yield [
            'robots.txt',
            'text/plain',
            '4900e3b08f7e54d7710f1ce3318f8b8d-' . filesize($webroot . 'robots.txt'),
        ];

        yield [
            'google.png',
            'image/png',
            'b4db2a4b6d91e0df5850a3fdcee9c8df-' . filesize($webroot . 'google.png'),
        ];

        yield [
            'android.jpg',
            'image/jpeg',
            'ce9ad62490af54cf5741f8404e0463e7-' . filesize($webroot . 'android.jpg'),
        ];

        yield [
            'mind-blown.gif',
            'image/gif',
            '0c119d1e5901e83563072eb67774c035-' . filesize($webroot . 'mind-blown.gif'),
        ];

        yield [
            'mind-blown.webp',
            'image/webp',
            '48a97a5b92a1e34df5c7fd42e5ae1db7-' . filesize($webroot . 'mind-blown.webp'),
        ];
    }

    /**
     * @dataProvider provideHits
     */
    public function testHit(string $file, string $contentType, string $etag)
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
            'ETag' => [
                '"' . $etag . '"',
            ],
            'Content-Type' => [
                $contentType,
            ],
        ], $response->getHeaders());
        self::assertSame(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . $file), (string)$response->getBody());
    }

    public function provideEtagIfNoneMatch()
    {
        $webroot = __DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR;

        yield [
            '"123"',
            200,
        ];

        yield [
            '"0c119d1e5901e83563072eb67774c035-' . filesize($webroot . 'mind-blown.gif') . '"',
            304,
        ];

        yield [
            '0c119d1e5901e83563072eb67774c035-' . filesize($webroot . 'mind-blown.gif'),
            304,
        ];
    }

    public function provideEtagIfMatchOK()
    {
        $webroot = __DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR;

        yield [
            '"0c119d1e5901e83563072eb67774c035-' . filesize($webroot . 'mind-blown.gif') . '"',
            200,
        ];

        yield [
            '0c119d1e5901e83563072eb67774c035-' . filesize($webroot . 'mind-blown.gif'),
            200,
        ];
    }

    public function provideEtagIfMatchPreconditionFails()
    {
        yield [
            '"' . md5('FailMe') . '"',
            412,
        ];

        yield [
            '"'. md5('WillNotMatch!').'"',
            412,
        ];
    }

    /**
     * @param string $etag
     * @param int    $statusCode
     * @dataProvider provideEtagIfNoneMatch
     */
    public function testEtagIfNoneMatch(string $etag, int $statusCode)
    {
        $request = new ServerRequest(
            'GET',
            'https://example.com/mind-blown.gif',
            [
                'If-None-Match' => $etag,
            ]
        );
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR);
        $next = function () {
            return new Response(200);
        };
        /** @var ResponseInterface $response */
        $response = $this->await(resolve($middleware($request, $next)));

        self::assertSame($statusCode, $response->getStatusCode());
    }

    /**
     * @param string $etag
     * @param int    $statusCode
     * @dataProvider provideEtagIfMatchOK
     */
    public function testEtagIfMatchOK(string $etag, int $statusCode)
    {
        $request = new ServerRequest(
            'DELETE',
            'https://example.com/mind-blown.gif',
            [
                'If-Match' => $etag,
            ]
        );
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR);
        $next = function () {
            return new Response(200);
        };
        $response = $this->await(resolve($middleware($request, $next)));
        self::assertSame($statusCode, $response->getStatusCode());
    }

    /**
     * @param string $etag
     * @param int    $statusCode
     * @dataProvider provideEtagIfMatchPreconditionFails
     */
    public function testEtagIfMatchPreconditionFails(string $etag, int $statusCode)
    {
        $request = new ServerRequest(
            'DELETE',
            'https://example.com/mind-blown.gif',
            [
                'If-Match' => $etag,
            ]
        );
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR);
        $next = function () {
            return new Response(412);
        };
        $response = $this->await(resolve($middleware($request, $next)));
        self::assertSame($statusCode, $response->getStatusCode());
    }
}
