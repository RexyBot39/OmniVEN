<?php

namespace App\Support\Metabase;

use RuntimeException;

/** Someone used today's Metabase allowance. Its message is meant for them, so pages show it to everyone. */
class LimitReached extends RuntimeException
{
}
