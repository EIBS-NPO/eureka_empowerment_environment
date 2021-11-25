<?php

namespace App\Exceptions;

use Throwable;

class NoFoundException extends \Exception
{
    const MSG_PREFIX = "Data not found";
    public function __construct(String $message = "", $code = 404, Throwable $previous = null)
    {
        parent::__construct(self::MSG_PREFIX." : ".$message, $code, $previous);
    }

}