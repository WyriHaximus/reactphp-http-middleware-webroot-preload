<?php declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

use Narrowspark\Mimetypes\MimeTypeByExtensionGuesser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use RingCentral\Psr7\Response;
use ScriptFUSION\Byte\ByteFormatter;
use function RingCentral\Psr7\stream_for;

final class WebrootPreloadMiddleware
{
    /** @var CacheInterface */
    private $cache;

    public function __construct(string $webroot, LoggerInterface $logger = null, CacheInterface $cache = null)
    {
        $this->cache = $cache ?? new ArrayCache();

        $totalSize = 0;
        $count = 0;
        $byteFormatter = (new ByteFormatter())->setPrecision(2)->setFormat('%v%u');
        $directory = new \RecursiveDirectoryIterator($webroot);
        $directory = new \RecursiveIteratorIterator($directory);
        $directory = iterator_to_array($directory);
        usort($directory, function ($a, $b) {
            return $a->getPathname() <=> $b->getPathname();
        });
        foreach ($directory as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }

            $filePath = str_replace(
                [
                    $webroot,
                    DIRECTORY_SEPARATOR,
                    '//',
                ],
                [
                    DIRECTORY_SEPARATOR,
                    '/',
                    '/',
                ],
                $fileinfo->getPathname()
            );

            $item = [
                'contents' => file_get_contents($fileinfo->getPathname()),
            ];
            $item['etag'] = md5($item['contents']) . '-' . filemtime($fileinfo->getPathname());

            $mime = MimeTypeByExtensionGuesser::guess($fileinfo->getExtension());
            if (is_null($mime)) {
                $mime = 'application/octet-stream';
            }
            list($mime) = explode(';', $mime);
            if (strpos($mime, '/') !== false) {
                $item['mime'] = $mime;
            }

            $this->cache->set($filePath, $item);
            $count++;
            if ($logger instanceof LoggerInterface) {
                $fileSize = strlen($item['contents']);
                $totalSize += $fileSize;
                $logger->debug($filePath . ': ' . $byteFormatter->format($fileSize) . ' (' . $item['mime'] . ')');
            }
        }

        if ($logger instanceof LoggerInterface) {
            $logger->info('Preloaded ' . $count . ' file(s) with a combined size of ' . $byteFormatter->format($totalSize) . ' from "' . $webroot . '" into memory');
        }
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $path = $request->getUri()->getPath();

        return $this->cache->get($path)->then(function ($item) use ($next, $request) {
            if ($item === null) {
                return $next($request);
            }

            if ($request->hasHeader('If-None-Match')) {
                $etag = current($request->getHeader('If-None-Match'));
                $etag = trim($etag, '"');
                if ($etag === $item['etag']) {
                    return new Response(304);
                }
            }

            $response = (new Response(200))->
                withBody(stream_for($item['contents']))->
                withHeader('ETag', $item['etag'])
            ;
            if (!isset($item['mime'])) {
                return $response;
            }

            return $response->withHeader('Content-Type', $item['mime']);
        });
    }
}
