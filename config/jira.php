<?php

// Jira: send a task from the board to the company Jira board as a ticket, show its column on the task, and keep
// "done" in step both ways. Uses an Atlassian API token (id.atlassian.com → Security → API tokens) for an account
// that can create issues in the project. Hidden until all four settings are set.
return [
    'base_url' => rtrim((string) env('JIRA_BASE_URL', ''), '/'),   // https://yourcompany.atlassian.net
    'email' => env('JIRA_EMAIL'),
    'api_token' => env('JIRA_API_TOKEN'),
    'project' => env('JIRA_PROJECT'),                              // the project key on the board, e.g. MKT
    'issue_type' => env('JIRA_ISSUE_TYPE', 'Task'),
    'label' => 'omniven',
    // How often ticket columns are checked when someone opens the board.
    'sync_minutes' => 10,
    'timeout' => 20,
];
