<?php

namespace Tests\Feature;

use App\Jobs\ExportDeliverJob;
use App\Jobs\IngestUrlJob;
use App\Jobs\ReframeJob;
use App\Jobs\ScoreHighlightsJob;
use App\Jobs\TranscribeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Tests\TestCase;

/**
 * Guards the two cross-config invariants that silently broke the pipeline:
 *
 * 1. A queued job must carry its own timeout. A class default of null makes the
 *    worker fall back to its 60s default (and pcntl is absent on Windows anyway).
 * 2. The database connection's retry_after must exceed the longest job budget,
 *    otherwise the queue hands the same job to a second worker mid-render.
 * 3. Livewire's temporary upload cap must match the real ingest cap, or a
 *    large video is rejected before any component rule runs.
 */
class QueueConfigTest extends TestCase
{
    use RefreshDatabase;

    private const JOBS = [
        ReframeJob::class,
        TranscribeJob::class,
        ScoreHighlightsJob::class,
        ExportDeliverJob::class,
        IngestUrlJob::class,
    ];

    public function test_every_pipeline_job_publishes_a_positive_timeout_in_its_payload(): void
    {
        foreach (self::JOBS as $class) {
            $job = new $class(1);
            $this->assertGreaterThan(0, $job->timeout, $class.' has no hard timeout.');

            dispatch($job);

            $payload = json_decode((string) \DB::table('jobs')->orderByDesc('id')->value('payload'), true);
            $this->assertSame($job->timeout, $payload['timeout'], $class.' must send its timeout in the payload.');
            $this->assertSame($job->tries, $payload['maxTries'], $class.' maxTries drifted from the property.');

            \DB::table('jobs')->delete();
        }
    }

    public function test_retry_after_outlives_the_longest_job_budget(): void
    {
        $longest = max(array_map(
            fn (string $class): int => (new $class(1))->timeout,
            self::JOBS,
        ));

        $retryAfter = (int) config('queue.connections.database.retry_after');

        $this->assertGreaterThan(
            $longest,
            $retryAfter,
            'retry_after must exceed the longest job timeout or the job gets popped twice.',
        );
    }

    public function test_livewire_temporary_upload_accepts_the_full_ingest_size(): void
    {
        $rules = FileUploadConfiguration::rules();
        $maxKb = (int) str_replace('max:', '', (string) collect($rules)->first(fn ($r) => str_starts_with($r, 'max:')));

        $this->assertGreaterThanOrEqual(
            (int) config('autoclip.ingest.max_size_kb'),
            $maxKb,
            'Livewire rejects the upload before the ingest rule can see it.',
        );
    }
}
