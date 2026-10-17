<?php

// AI answer tracking: the assistants we check each month, and how to open each with a question.
// "ask" gets the question URL-encoded on the end; null means paste it in yourself.
return [
    'engines' => [
        'chatgpt' => ['label' => 'ChatGPT', 'ask' => 'https://chatgpt.com/?q=', 'icon' => null],
        'google_ai' => ['label' => 'Google AI Overview', 'ask' => 'https://www.google.com/search?q=', 'icon' => 'google'],
        'perplexity' => ['label' => 'Perplexity', 'ask' => 'https://www.perplexity.ai/search?q=', 'icon' => null],
        'gemini' => ['label' => 'Gemini', 'ask' => null, 'home' => 'https://gemini.google.com/app', 'icon' => null],
        'copilot' => ['label' => 'Copilot', 'ask' => 'https://copilot.microsoft.com/?q=', 'icon' => null],
    ],

    // What the answer said about our brand.
    'results' => [
        'recommended' => 'Recommended us',
        'mentioned' => 'Mentioned us',
        'absent' => 'Not mentioned',
    ],
];
