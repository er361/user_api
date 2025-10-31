<?php

namespace App\Exceptions;

use Exception;

class SelfTransferException extends Exception
{
    protected $message = 'Cannot transfer balance to yourself.';
}
