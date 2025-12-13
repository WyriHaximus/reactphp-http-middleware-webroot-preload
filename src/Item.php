<?php

declare(strict_types=1);

namespace WyriHaximus\React\Http\Middleware;

final readonly class Item
{
    public function __construct(
        public string $contents,
        public string $etag,
        public string $mimeType,
    ) {
    }
}
