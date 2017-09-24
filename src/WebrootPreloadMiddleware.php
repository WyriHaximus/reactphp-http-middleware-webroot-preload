<?php

namespace WyriHaximus\React\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Response;

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

            $this->files[$filePath] = file_get_contents($fileinfo->getPathname());
        }
    }

    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $path = $request->getUri()->getPath();
        if (isset($this->files[$path])) {
            return new Response(200, [], $this->files[$path]);
        }

        return $next($request);
    }
}