<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Http\Middleware;

use ColinODell\PsrTestLogger\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use React\Cache\ArrayCache;
use React\Http\Message\Response;
use React\Http\Message\ServerRequest;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Http\Middleware\WebrootPreloadMiddleware;

use function md5;
use function React\Async\await;
use function React\Promise\resolve;
use function Safe\file_get_contents;
use function Safe\filesize;

use const DIRECTORY_SEPARATOR;

final class WebrootPreloadMiddlewareTest extends AsyncTestCase
{
    #[Test]
    public function logger(): void
    {
        $webroot = __DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR;
        $logger  = new TestLogger();
        new WebrootPreloadMiddleware($webroot, $logger, new ArrayCache());

        foreach (
            [
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/android.jpg',
                        'fileSize' => '15.61KiB',
                        'mimeType' => 'image/jpeg',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/app.css',
                        'fileSize' => '18B',
                        'mimeType' => 'text/css',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/app.js',
                        'fileSize' => '27B',
                        'mimeType' => 'application/javascript',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/favicon.ico',
                        'fileSize' => '5.3KiB',
                        'mimeType' => 'image/vnd.microsoft.icon',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/google.png',
                        'fileSize' => '594B',
                        'mimeType' => 'image/png',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/index.html',
                        'fileSize' => '453B',
                        'mimeType' => 'text/html',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/mind-blown.gif',
                        'fileSize' => '4.37MiB',
                        'mimeType' => 'image/gif',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/mind-blown.webp',
                        'fileSize' => '2.28MiB',
                        'mimeType' => 'image/webp',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/robots.txt',
                        'fileSize' => '68B',
                        'mimeType' => 'text/plain',
                    ],
                ],
                [
                    'level' => 'debug',
                    'message' => '{filePath}: {fileSize} ({mimeType})',
                    'context' => [
                        'filePath' => '/unknown.unknown_extension',
                        'fileSize' => '73B',
                        'mimeType' => 'application/octet-stream',
                    ],
                ],
                [
                    'level' => 'info',
                    'message' => 'Preloaded {count} file(s) with a combined size of {totalSize} from "{webroot}" into memory',
                    'context' => [
                        'count' => 10,
                        'totalSize' => '6.67MiB',
                        'webroot' => $webroot,
                    ],
                ],
            ] as $item
        ) {
            self::assertTrue($logger->hasRecord($item), $item['level'] . ': ' . $item['message']);
        }
    }

    #[Test]
    public function miss(): void
    {
        $request    = new ServerRequest('GET', 'https://example.com/');
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR, new NullLogger(), new ArrayCache());
        /** @phpstan-ignore shipmonk.unusedParameter */
        $next     = static fn (ServerRequestInterface $request): ResponseInterface => new Response(200);
        $response = await(resolve($middleware($request, $next)));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->getHeaders());
        self::assertSame('', (string) $response->getBody());
    }

    /** @return iterable<array<string>> */
    public static function provideHits(): iterable
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

    #[DataProvider('provideHits')]
    #[Test]
    public function hit(string $file, string $contentType, string $etag): void
    {
        $request    = new ServerRequest('GET', 'https://example.com/' . $file);
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR, new NullLogger(), new ArrayCache());
        /** @phpstan-ignore shipmonk.unusedParameter */
        $next     = static fn (ServerRequestInterface $request): ResponseInterface => new Response(200);
        $response = await(resolve($middleware($request, $next)));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'Content-Type' => [$contentType],
            'ETag' => [
                '"' . $etag . '"',
            ],
        ], $response->getHeaders());
        self::assertSame(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . $file), (string) $response->getBody());
    }

    /** @return iterable<array<string|int>> */
    public static function provideEtagIfNoneMatch(): iterable
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

    /** @return iterable<array<string|int>> */
    public static function provideEtagIfMatchOK(): iterable
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

    /** @return iterable<array<string|int>> */
    public static function provideEtagIfMatchPreconditionFails(): iterable
    {
        yield [
            '"' . md5('FailMe') . '"',
            412,
        ];

        yield [
            '"' . md5('WillNotMatch!') . '"',
            412,
        ];
    }

    #[DataProvider('provideEtagIfNoneMatch')]
    #[Test]
    public function etagIfNoneMatch(string $etag, int $statusCode): void
    {
        $request    = new ServerRequest(
            'GET',
            'https://example.com/mind-blown.gif',
            ['If-None-Match' => $etag],
        );
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR, new NullLogger(), new ArrayCache());
        /** @phpstan-ignore shipmonk.unusedParameter */
        $next     = static fn (ServerRequestInterface $request): ResponseInterface => new Response(200);
        $response = await(resolve($middleware($request, $next)));

        self::assertSame($statusCode, $response->getStatusCode());
    }

    #[DataProvider('provideEtagIfMatchOK')]
    #[Test]
    public function etagIfMatchOK(string $etag, int $statusCode): void
    {
        $request    = new ServerRequest(
            'DELETE',
            'https://example.com/mind-blown.gif',
            ['If-Match' => $etag],
        );
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR, new NullLogger(), new ArrayCache());
        /** @phpstan-ignore shipmonk.unusedParameter */
        $next     = static fn (ServerRequestInterface $request): ResponseInterface => new Response(200);
        $response = await(resolve($middleware($request, $next)));
        self::assertSame($statusCode, $response->getStatusCode());
    }

    #[DataProvider('provideEtagIfMatchPreconditionFails')]
    #[Test]
    public function etagIfMatchPreconditionFails(string $etag, int $statusCode): void
    {
        $request    = new ServerRequest(
            'DELETE',
            'https://example.com/mind-blown.gif',
            ['If-Match' => $etag],
        );
        $middleware = new WebrootPreloadMiddleware(__DIR__ . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR, new NullLogger(), new ArrayCache());
        /** @phpstan-ignore shipmonk.unusedParameter */
        $next     = static fn (ServerRequestInterface $request): ResponseInterface => new Response(200);
        $response = await(resolve($middleware($request, $next)));
        self::assertSame($statusCode, $response->getStatusCode());
    }
}
