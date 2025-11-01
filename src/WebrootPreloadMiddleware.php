<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Ancarda\Psr7\StringStream\ReadOnlyStringStream;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;
use League\MimeTypeDetection\OverridingExtensionToMimeTypeMap;
use Psr\Http\Message\ResponseInterface;
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
use function file_get_contents;
use function filesize;
use function is_string;
use function iterator_to_array;
use function md5;
use function str_contains;
use function str_replace;
use function strlen;
use function trim;
use function usort;

use const DIRECTORY_SEPARATOR;

final readonly class WebrootPreloadMiddleware
{
    public function __construct(string $webroot, LoggerInterface $logger, private CacheInterface $cache)
    {
        $mimeTypeDetector = new ExtensionMimeTypeDetector(new OverridingExtensionToMimeTypeMap(new GeneratedExtensionToMimeTypeMap(), ['ico' => 'image/vnd.microsoft.icon']));
        $totalSize        = 0;
        $count            = 0;
        $byteFormatter    = new ByteFormatter()->setPrecision(2)->setFormat('%v%u');
        $directory        = new RecursiveDirectoryIterator($webroot);
        $directory        = new RecursiveIteratorIterator($directory);
        $directory        = iterator_to_array($directory);
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

            $mime = $mimeTypeDetector->detectMimeTypeFromFile($fileinfo->getPathname()) ?? 'application/octet-stream';

            $item['mime'] = 'application/octet-stream';
            [$mime]       = explode(';', $mime);
            if (str_contains($mime, '/')) {
                $item['mime'] = $mime;
            }

            /** @psalm-suppress TooManyTemplateParams */
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

    /** @return PromiseInterface<ResponseInterface> */
    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $path = $request->getUri()->getPath();

        /**
         * @psalm-suppress MissingClosureReturnType
         * @psalm-suppress MissingClosureParamType
         * @psalm-suppress TooManyTemplateParams
         */
        return $this->cache->get($path)->then(static function (array $item) use ($next, $request) {
            if ($item === null) {
                return $next($request);
            }

            if ($request->hasHeader('If-None-Match')) {
                $etag = current($request->getHeader('If-None-Match'));
                if (is_string($etag)) {
                    $etag = trim($etag, '"');
                    if ($etag === $item['etag']) {
                        return new Response(Response::STATUS_NOT_MODIFIED);
                    }
                }
            }

            if ($request->hasHeader('If-Match')) {
                $expectedEtag = current($request->getHeader('If-Match'));
                if (is_string($expectedEtag)) {
                    $expectedEtag = trim($expectedEtag, '"');
                    if ($expectedEtag !== $item['etag']) {
                        return new Response(Response::STATUS_PRECONDITION_FAILED);
                    }
                }
            }

            return new Response(
                Response::STATUS_OK,
                [
                    'Content-Type' => $item['mime'],
                    'ETag' => '"' . $item['etag'] . '"',
                ],
                new ReadOnlyStringStream($item['contents']),
            );
        });
    }
}
