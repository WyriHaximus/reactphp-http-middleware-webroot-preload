# A static preloaded webroot middleware for react/http

[![Build Status](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-webroot-preload.svg?branch=master)](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-webroot-preload)
[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/react-http-middleware-webroot-preload/v/stable.png)](https://packagist.org/packages/WyriHaximus/react-http-middleware-webroot-preload)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/react-http-middleware-webroot-preload/downloads.png)](https://packagist.org/packages/WyriHaximus/react-http-middleware-webroot-preload)
[![Code Coverage](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-http-middleware-webroot-preload/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-http-middleware-webroot-preload/?branch=master)
[![License](https://poser.pugx.org/WyriHaximus/react-http-middleware-webroot-preload/license.png)](https://packagist.org/packages/WyriHaximus/react-http-middleware-webroot-preload)
[![PHP 7 ready](http://php7ready.timesplinter.ch/WyriHaximus/reactphp-http-middleware-webroot-preload/badge.svg)](https://travis-ci.org/WyriHaximus/reactphp-http-middleware-webroot-preload)

# Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/react-http-middleware-webroot-preload
```

# Usage

The middleware accepts two parameters, first the webroot directory, and secondly an optional PSR-3 logger.
It is important to note that ALL files in the webroot will be served regardless of file type. So no PHP or
files containing passwords etc etc should be in there.

```php
$webroot = '/var/www/';
$logger = new Psr3Logger(); // Optional, PSR-3 logger for bootstrap logging 
$server = new Server([
    /** Other middleware */
    new WebrootPreloadMiddleware($webroot, $logger),
    /** Other middleware */
]);
```

# License

The MIT License (MIT)

Copyright (c) 2017 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
