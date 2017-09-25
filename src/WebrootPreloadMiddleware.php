<?php

namespace WyriHaximus\React\Http\Middleware;

use Defr\PhpMimeType\MimeType;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Response;
use function RingCentral\Psr7\stream_for;

final class WebrootPreloadMiddleware
{
    /**
     * @var array
     */
    private $files = [];

    public function __construct(string $webroot)
    {
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

            $mime = MimeType::get($fileinfo->getPathname());
            if (strpos($mime, '/') !== false) {
                $this->files[$filePath]['mime'] = $mime;
            }
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