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
use React\Cache\CacheInterface;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ScriptFUSION\Byte\ByteFormatter;
use SplFileInfo;

use function current;
use function explode;
use function file_get_contents;
use function filesize;
use function is_string;
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
        $mimeTypeDetector = new ExtensionMimeTypeDetector(
            new OverridingExtensionToMimeTypeMap(
                new GeneratedExtensionToMimeTypeMap(),
                ['ico' => 'image/vnd.microsoft.icon'],
            ),
        );
        $totalSize        = 0;
        $count            = 0;
        $byteFormatter    = new ByteFormatter()->setPrecision(2)->setFormat('%v%u');
        /** @var SplFileInfo[] $directory */
        $directory = [
            ...new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $webroot,
                ),
            ),
        ];
        usort($directory, static fn (SplFileInfo $a, SplFileInfo $b): int => $a->getPathname() <=> $b->getPathname());
        foreach ($directory as $fileinfo) {
            if (! $fileinfo->isFile()) {
                continue;
            }

            $filePath = str_replace([$webroot, DIRECTORY_SEPARATOR, '//'], [DIRECTORY_SEPARATOR, '/', '/'], $fileinfo->getPathname());

            $mime   = $mimeTypeDetector->detectMimeTypeFromFile($fileinfo->getPathname()) ?? 'application/octet-stream';
            [$mime] = explode(';', $mime);
            /** @phpstan-ignore wyrihaximus.reactphp.blocking.function.fileGetContents */
            $contents = file_get_contents($fileinfo->getPathname());
            if (! is_string($contents)) {
                throw new RuntimeException('Failed to read file');
            }

            $item = new Item(
                contents: $contents,
                etag: md5($contents) . '-' . filesize($fileinfo->getPathname()),
                mimeType: str_contains($mime, '/') ? $mime : 'application/octet-stream',
            );

            /** @psalm-suppress TooManyTemplateParams */
            $this->cache->set($filePath, $item);
            $count++;

            $fileSize   = strlen($item->contents);
            $totalSize += $fileSize;
            $logger->debug(
                '{filePath}: {fileSize} ({mimeType})',
                [
                    'filePath' => $filePath,
                    'fileSize' => $byteFormatter->format($fileSize),
                    'mimeType' => $item->mimeType,
                ],
            );
        }

        $logger->info(
            'Preloaded {count} file(s) with a combined size of {totalSize} from "{webroot}" into memory',
            [
                'count' => $count,
                'totalSize' => $byteFormatter->format($totalSize),
                'webroot' => $webroot,
            ],
        );
    }

    /**
     * @param callable(ServerRequestInterface): (PromiseInterface<ResponseInterface>|ResponseInterface) $next
     *
     * @return PromiseInterface<ResponseInterface>
     */
    public function __invoke(ServerRequestInterface $request, callable $next): PromiseInterface
    {
        $path = $request->getUri()->getPath();

        /**
         * @return PromiseInterface<ResponseInterface>
         *
         * @phpstan-ignore ergebnis.noParameterWithNullableTypeDeclaration,argument.type
         */
        return $this->cache->get($path)->then(static function (Item|null $item) use ($next, $request): PromiseInterface|ResponseInterface {
            if (! $item instanceof Item) {
                return $next($request);
            }

            if ($request->hasHeader('If-None-Match')) {
                $etag = current($request->getHeader('If-None-Match'));
                if (is_string($etag)) {
                    $etag = trim($etag, '"');
                    if ($etag === $item->etag) {
                        return new Response(Response::STATUS_NOT_MODIFIED);
                    }
                }
            }

            if ($request->hasHeader('If-Match')) {
                $expectedEtag = current($request->getHeader('If-Match'));
                if (is_string($expectedEtag)) {
                    $expectedEtag = trim($expectedEtag, '"');
                    if ($expectedEtag !== $item->etag) {
                        return new Response(Response::STATUS_PRECONDITION_FAILED);
                    }
                }
            }

            return new Response(
                Response::STATUS_OK,
                [
                    'Content-Type' => $item->mimeType,
                    'ETag' => '"' . $item->etag . '"',
                ],
                new ReadOnlyStringStream($item->contents),
            );
        });
    }
}
