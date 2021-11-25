<?php

namespace App\Exceptions;

use Throwable;

class BadMediaFileException extends \Exception
{
    const MSG_PREFIX = "File format not supported";
    public function __construct(String $message = "", $code = 415, Throwable $previous = null)
    {
        parent::__construct(self::MSG_PREFIX." : ".$message, $code, $previous);
    }

}