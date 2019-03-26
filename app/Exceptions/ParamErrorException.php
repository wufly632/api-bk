<?php

namespace App\Exceptions;

use Exception;

class ParamErrorException extends Exception
{
    //
    public function __construct($msg = '')
    {
        parent::__construct($msg);
    }
}
