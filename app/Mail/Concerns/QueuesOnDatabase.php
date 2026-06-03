<?php

namespace App\Mail\Concerns;

/**
 * Hostinger shared hosting: no Redis — use database queue (see QUEUE_CONNECTION).
 */
trait QueuesOnDatabase
{
    public string $connection = 'database';

    public string $queue = 'default';
}
