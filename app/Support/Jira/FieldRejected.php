<?php

namespace App\Support\Jira;

use RuntimeException;

/** Jira turned down optional fields (due date, labels, assignee) the project's screen doesn't have. */
class FieldRejected extends RuntimeException
{
    public function __construct(public array $fields, string $detail)
    {
        parent::__construct('Jira rejected: '.$detail);
    }
}
