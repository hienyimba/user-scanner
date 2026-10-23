<?php

declare(strict_types=1);

namespace App\Queue;

use App\Services\Scanner\ScannerDiagnostics;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJob;
use Throwable;

/** Reservation retries never execute a handler or consume a job attempt. */
class ResilientDatabaseQueue extends DatabaseQueue
{
    use DetectsConcurrencyErrors;

    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);
        $record = null;
        try {
            return $this->database->transaction(function () use ($queue, &$record) {
                // A retried SELECT may itself throw: do not fail a stale record
                // left over from the preceding, rolled-back transaction.
                $record = null;
                $record = $this->getNextAvailableJob($queue);

                return $record ? $this->marshalJob($queue, $record) : null;
            }, 5);
        } catch (Throwable $exception) {
            if ($this->causedByConcurrencyError($exception)) {
                // The reservation transaction was rolled back. Leave the job
                // untouched for a later poll; do not call its failed() handler.
                ScannerDiagnostics::exception($exception, 'queue.reserve');
                throw $exception;
            }
            // Preserve the framework's handling of genuinely malformed jobs.
            if ($record !== null) {
                try {
                    (new DatabaseJob($this->container, $this, $record, $this->connectionName, $queue))->fail($exception);
                } catch (Throwable) {
                    // Preserve the original exception, as Laravel does.
                }
            }
            throw $exception;
        }
    }
}
