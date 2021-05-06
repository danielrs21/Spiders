<?php

namespace Spiders\Helpers;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
* Helper de funciones para registrar logs
*
* @author Daniel Rodríguez [drs]
*
* @package Spiders
*/
class Log {

    const LOGPATH = __DIR__.'/../Logs/';

    public static function create( $filename ){

        $filename.= '-'.date('Ymd-His').'.log';
        $log = new Logger('Spiders');
        $log->pushHandler(new StreamHandler(self::LOGPATH.$filename, Logger::INFO));

        return $log;

    }

}