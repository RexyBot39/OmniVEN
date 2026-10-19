<?php

// The OmniVEN assistant: answers questions about OmniVEN's own data with OpenAI, looking things up through
// read-only tools that respect the asker's permissions. Hidden until OPENAI_API_KEY is set.
return [
    'key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-6.1-sol'),
    // low, medium, high, xhigh or max for gpt-6.1-sol. Higher thinks longer and costs more.
    'effort' => env('OPENAI_REASONING_EFFORT', 'high'),
    'url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'timeout' => (int) env('OPENAI_TIMEOUT', 300),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 16000),
    // Rounds of looking things up before it must answer.
    'max_tool_rounds' => 10,
    // Earlier turns of the same conversation sent along for context.
    'history_turns' => 8,
    // Questions per person per day.
    // TaskBuddy AI task lists per person per day.
    'taskbuddy_daily_limit' => (int) env('OMNIVEN_TASKBUDDY_DAILY_LIMIT', 20),
    'daily_limit' => (int) env('OMNIVEN_ASSISTANT_DAILY_LIMIT', 40),
    // US dollars per million tokens, for the admin cost estimate only.
    'price' => ['input' => (float) env('OPENAI_PRICE_INPUT', 2.00), 'output' => (float) env('OPENAI_PRICE_OUTPUT', 10.00)],
];
