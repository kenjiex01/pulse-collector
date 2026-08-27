<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class CollectRunTracker
{
    public const STATE_IDLE = 'idle';

    public const STATE_RUNNING = 'running';

    public const STATE_FINISHED = 'finished';

    public const STATE_FAILED = 'failed';

    public const STALE_AFTER_SECONDS = 900;

    /**
     * @return array{
     *     state: string,
     *     message: string,
     *     started_at: ?string,
     *     finished_at: ?string,
     *     summary: array<string, mixed>|null,
     * }
     */
    public function current(): array
    {
        $payload = $this->read();

        if (
            ($payload['state'] ?? self::STATE_IDLE) === self::STATE_RUNNING
            && $this->isStale($payload['started_at'] ?? null)
        ) {
            $payload['state'] = self::STATE_FAILED;
            $payload['finished_at'] = now()->toIso8601String();
            $payload['message'] = 'Collection did not finish (timed out). Close and reopen the app, then try Collect now again.';
            $this->write($payload);
        }

        return $payload;
    }

    public function isRunning(): bool
    {
        return $this->current()['state'] === self::STATE_RUNNING;
    }

    public function markRunning(string $message = 'Collecting from devices…'): void
    {
        $this->write([
            'state' => self::STATE_RUNNING,
            'message' => $message,
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'summary' => null,
        ]);
    }

    /**
     * @param  array{
     *     devices_processed?: int,
     *     logs_inserted?: int,
     *     batches_uploaded?: int,
     *     errors?: list<string>,
     * }  $summary
     */
    public function markFinished(array $summary): void
    {
        $errors = $summary['errors'] ?? [];
        $message = sprintf(
            'Collection finished: %d new log(s), %d S3 upload(s).',
            (int) ($summary['logs_inserted'] ?? 0),
            (int) ($summary['batches_uploaded'] ?? 0),
        );

        if ($errors !== []) {
            $message .= ' Some devices failed — see the Last collect error column.';
        }

        $this->write([
            'state' => self::STATE_FINISHED,
            'message' => $message,
            'started_at' => $this->read()['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'summary' => $summary,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->write([
            'state' => self::STATE_FAILED,
            'message' => $message !== '' ? $message : 'Collection failed.',
            'started_at' => $this->read()['started_at'] ?? now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'summary' => null,
        ]);
    }

    private function isStale(?string $startedAt): bool
    {
        if ($startedAt === null || $startedAt === '') {
            return true;
        }

        try {
            return \Illuminate\Support\Carbon::parse($startedAt)
                ->lt(now()->subSeconds(self::STALE_AFTER_SECONDS));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array{
     *     state: string,
     *     message: string,
     *     started_at: ?string,
     *     finished_at: ?string,
     *     summary: array<string, mixed>|null,
     * }
     */
    private function read(): array
    {
        $defaults = [
            'state' => self::STATE_IDLE,
            'message' => '',
            'started_at' => null,
            'finished_at' => null,
            'summary' => null,
        ];

        if (! File::exists($this->path())) {
            return $defaults;
        }

        try {
            $decoded = json_decode((string) File::get($this->path()), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $defaults;
        }

        return array_merge($defaults, is_array($decoded) ? $decoded : []);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(array $payload): void
    {
        File::ensureDirectoryExists(dirname($this->path()));
        File::put(
            $this->path(),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    private function path(): string
    {
        return storage_path('app/collect-run.json');
    }
}
