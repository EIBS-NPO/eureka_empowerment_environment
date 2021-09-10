<?php


namespace App\Exceptions;


use Throwable;

class ViolationException extends \Exception
{
    public function __construct(String $message = "", $code = 400, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
     //   $this->violationsList = $violations;
    }

    /**
     * @return array
     */
    /*public function getViolationsList(): array
    {
        return $this->violationsList;
    }*/
}