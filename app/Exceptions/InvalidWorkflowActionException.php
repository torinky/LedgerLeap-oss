<?php

namespace App\Exceptions;

class InvalidWorkflowActionException extends \Exception
{
    public function __construct(string $message = '無効なワークフロー操作です', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
