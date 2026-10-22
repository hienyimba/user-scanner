<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scanner\AsyncUsernamePilotWorker;
use Illuminate\Console\Command;
use Throwable;

final class WorkUsernameAsyncPilot extends Command
{
    protected $signature = 'scanner:work-username-pilot
        {--concurrency=40} {--max-jobs=300} {--max-time=3600}
        {--memory=256} {--stop-when-empty}';

    protected $description = 'Experimental bounded async worker for opt-in Discourse/Gitea/Lemmy jobs (non-production only)';

    public function handle(AsyncUsernamePilotWorker $worker): int
    {
        try {
            $stats = $worker->work(
                (int) $this->option('concurrency'), (int) $this->option('max-jobs'),
                (int) $this->option('max-time'), (bool) $this->option('stop-when-empty'),
                (int) $this->option('memory'),
            );
            $this->line(json_encode($stats, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
