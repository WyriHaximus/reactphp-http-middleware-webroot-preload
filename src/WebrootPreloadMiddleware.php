<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Ancarda\Psr7\StringStream\ReadOnlyStringStream;
use Narrowspark\MimeType\MimeTypeExtensionGuesser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Cache\CacheInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ScriptFUSION\Byte\ByteFormatter;
use SplFileInfo;

use function current;
use function explode;
use function is_string;
use function iterator_to_array;
use function md5;
use function Safe\file_get_contents;
use function Safe\filesize;
use function Safe\usort;
use function str_replace;
use function strlen;
use function strpos;
use function trim;

use const DIRECTORY_SEPARATOR;
use const WyriHaximus\Constants\Boolean\FALSE_;
use const WyriHaximus\Constants\HTTPStatusCodes\NOT_MODIFIED;
use const WyriHaximus\Constants\HTTPStatusCodes\OK;
use const WyriHaximus\Constants\HTTPStatusCodes\PRECONDITION_FAILED;
use const WyriHaximus\Constants\Numeric\TWO;
use const WyriHaximus\Constants\Numeric\ZERO;

final class WebrootPreloadMiddleware
{
    private CacheInterface $cache;

    public function __construct(string $webroot, LoggerInterface $logger, CacheInterface $cache)
    {
        $this->cache = $cache;

        $totalSize     = ZERO;
        $count         = ZERO;
        $byteFormatter = (new ByteFormatter())->setPrecision(TWO)->setFormat('%v%u');
        $directory     = new RecursiveDirectoryIterator($webroot);
        $directory     = new RecursiveIteratorIterator($directory);
        $directory     = iterator_to_array($directory);
        usort($directory, static fn (SplFileInfo $a, SplFileInfo $b): int => $a->getPathname() <=> $b->getPathname());
        foreach ($directory as $fileinfo) {
            if (! $fileinfo->isFile()) {
                continue;
            }

            $filePath = str_replace([$webroot, DIRECTORY_SEPARATOR, '//'], [DIRECTORY_SEPARATOR, '/', '/'], $fileinfo->getPathname());

            $item         = [
                'contents' => file_get_contents($fileinfo->getPathname()),
            ];
            $item['etag'] = md5($item['contents']) . '-' . filesize($fileinfo->getPathname());

            $mime = MimeTypeExtensionGuesser::guess($fileinfo->getExtension());
            if ($mime === null) {
                $mime = 'application/octet-stream';
            }

            $item['mime'] = 'application/octet-stream';
            [$mime]       = explode(';', $mime);
            if (strpos($mime, '/') !== FALSE_) {
                $item['mime'] = $mime;
            }

            $this->cache->set($filePath, $item);
            $count++;
            if ($logger instanceof NullLogger) {
                continue;
            }

            $fileSize   = strlen($item['contents']);
            $totalSize += $fileSize;
            $logger->debug($filePath . ': ' . $byteFormatter->format($fileSize) . ' (' . $item['mime'] . ')');
        }

        if ($logger instanceof NullLogger) {
            return;
        }

        $logger->info('Preloaded ' . $count . ' file(s) with a combined size of ' . $byteFormatter->format($totalSize) . ' from "' . $webroot . '" into memory');
    }

    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $path = $request->getUri()->getPath();

        /**
         * @psalm-suppress MissingClosureReturnType
         * @psalm-suppress MissingClosureParamType
         */
        return $this->cache->get($path)->then(static function ($item) use ($next, $request) {
            if ($item === null) {
                return $next($request);
            }

            if ($request->hasHeader('If-None-Match')) {
                $etag = current($request->getHeader('If-None-Match'));
                if (is_string($etag)) {
                    $etag = trim($etag, '"');
                    if ($etag === $item['etag']) {
                        return new Response(NOT_MODIFIED);
                    }
                }
            }

            if ($request->hasHeader('If-Match')) {
                $expectedEtag = current($request->getHeader('If-Match'));
                if (is_string($expectedEtag)) {
                    $expectedEtag = trim($expectedEtag, '"');
                    if ($expectedEtag !== $item['etag']) {
                        return new Response(PRECONDITION_FAILED);
                    }
                }
            }

            $response = (new Response(OK))->
                withBody(new ReadOnlyStringStream($item['contents']))->
                withHeader('ETag', '"' . $item['etag'] . '"');

            return $response->withHeader('Content-Type', $item['mime']);
        });
    }
}
