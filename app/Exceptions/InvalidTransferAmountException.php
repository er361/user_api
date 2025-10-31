<?php

namespace App\Exceptions;

use Exception;

class InvalidTransferAmountException extends Exception
{
    protected $message = 'Transfer amount must be greater than zero.';
}
