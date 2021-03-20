<?php


namespace App\Exceptions;


use Throwable;

class ViolationException extends \Exception
{
    private array $violationsList;

    public function __construct(Array $violations, String $message = "", $code = 400, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->violationsList = $violations;
    }

    /**
     * @return array
     */
    public function getViolationsList(): array
    {
        return $this->violationsList;
    }
}