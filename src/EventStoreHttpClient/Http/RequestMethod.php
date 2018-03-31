<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\Http;

class RequestMethod
{
    public const Get = 'GET';
    public const Post = 'POST';
    public const Put = 'PUT';
    public const Delete = 'DELETE';
    public const Options = 'OPTIONS';
    public const Head = 'HEAD';
    public const Patch = 'PATCH';
}
