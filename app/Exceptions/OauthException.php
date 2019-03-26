<?php

namespace App\Exceptions;

use Exception;

class OauthException extends Exception
{
    //
    public function __construct($msg = '')
    {
        parent::__construct($msg);
    }
}
