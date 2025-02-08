<?php

namespace sdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Cache;
use Exception;
use think\facade\Env;

class QueryTimeStamp
{

    protected static float $timestamp;


    public static function start(): void
    {
        self::$timestamp = microtime(true);
    }

    public static function end(): float
    {
        return round((microtime(true) - self::$timestamp) * 1000, 2);
    }

}