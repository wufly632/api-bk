<?php


namespace App\Exceptions;

class CustomException extends \Exception
{
    public function __construct($msg = '')
    {
        parent::__construct($msg);
    }
}