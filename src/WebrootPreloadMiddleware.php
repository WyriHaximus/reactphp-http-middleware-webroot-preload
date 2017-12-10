<?php

namespace WyriHaximus\React\Http\Middleware;

use Pekkis\MimeTypes\MimeTypes;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RingCentral\Psr7\Response;
use function RingCentral\Psr7\stream_for;

final class WebrootPreloadMiddleware
{
    /**
     * @var array
     */
    private $files = [];

    public function __construct(string $webroot, LoggerInterface $logger = null)
    {
        $mimeTypes = new MimeTypes();
        $totalSize = 0;
        $directory = new \RecursiveDirectoryIterator($webroot);
        $directory = new \RecursiveIteratorIterator($directory);
        foreach ($directory as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }

            $filePath = str_replace($webroot, DIRECTORY_SEPARATOR, $fileinfo->getPathname());

            $this->files[$filePath] = [
                'contents' => file_get_contents($fileinfo->getPathname()),
            ];

            $mime = $mimeTypes->resolveMimeType($fileinfo->getPathname());
            if (strpos($mime, '/') !== false) {
                $this->files[$filePath]['mime'] = $mime;
            }


            if ($logger instanceof LoggerInterface) {
                $logger->debug($filePath . ': ' . strlen($this->files[$filePath]['contents']) . ' bytes');
                $totalSize += strlen($this->files[$filePath]['contents']);
            }
        }

        if ($logger instanceof LoggerInterface) {
            $logger->info('Preloaded ' . count($this->files) . ' file(s) with a combined size of ' . $totalSize . ' bytes from ' . $webroot . ' into memory');
        }
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $path = $request->getUri()->getPath();
        if (!isset($this->files[$path])) {
            return $next($request);
        }

        $response = (new Response(200))->withBody(stream_for($this->files[$path]['contents']));
        if (!isset($this->files[$path]['mime'])) {
            return $response;
        }

        return $response->withHeader('Content-Type', $this->files[$path]['mime']);
    }
}