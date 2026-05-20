<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;
    public bool $deleteWhenMissingModels = true;
    public int $maxExceptions = 3;

    public function backoff(): array
    {
        return [
            $this->backoff,          // 1st retry: 60s
            $this->backoff * 5,      // 2nd retry: 300s
            $this->backoff * 15,     // 3rd retry: 900s
        ];
    }

    public function tags(): array
    {
        return [static::class];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Job failed permanently', [
            'job'      => static::class,
            'queue'    => $this->queue ?? 'default',
            'attempts' => $this->attempts(),
            'error'    => $exception->getMessage(),
        ]);
    }
}