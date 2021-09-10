<?php

namespace App\Exceptions;

use Throwable;

class PartialContentException extends \Exception
{
    const MSG_PREFIX = "Partial Data";

    private array $data;


    public function __construct(array $data, String $message = "", $code = 206, Throwable $previous = null)
    {
        parent::__construct(self::MSG_PREFIX." : ".$message, $code, $previous);
           $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

}