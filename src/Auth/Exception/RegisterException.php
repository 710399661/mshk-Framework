<?php


namespace Discuz\Auth\Exception;

use Exception;
use Throwable;

class RegisterException extends Exception
{
    public function __construct($message = '', $code = 400, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
