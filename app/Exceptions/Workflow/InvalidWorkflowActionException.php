<?php

namespace App\Exceptions\Workflow;

use Exception;

class InvalidWorkflowActionException extends Exception
{
    public function __construct($message = '無効なワークフロー操作です', $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
