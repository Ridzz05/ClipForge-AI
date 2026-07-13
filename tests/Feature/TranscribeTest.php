<?php

namespace Tests\Feature;

use App\Jobs\ScoreHighlightsJob;
use App\Jobs\TranscribeJob;
use App\Models\PipelineJob;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\Video;
use App\Services\WhisperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TranscribeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Stage 3 handoff is asserted here, not executed (no real Ollama call).
        Queue::fake();
    }

    /** A video row whose source file exists on the faked disk. */
    private function makeVideo(): Video
    {
        Storage::fake('local');
        $path = 'videos/'.Str::uuid().'/source.mp4';
        Storage::disk('local')->put($path, 'fake-media-bytes');

        return Video::create([
            'source_type' => 'upload',
            'source_ref' => 'clip.mp4',
            'status' => 'ingested',
            'duration_seconds' => 60,
            'storage_path' => $path,
        ]);
    }

    /** Canonical normalized whisper result (what WhisperService returns). */
    private function whisperResult(): array
    {
        return [
            'language' => 'en',
            'full_text' => 'hello world this is a test',
            'segments' => [
                [
                    'start_ms' => 0,
                    'end_ms' => 2000,
                    'text' => 'hello world',
                    'words' => [
                        ['word' => 'hello', 'start_ms' => 0, 'end_ms' => 500],
                        ['word' => 'world', 'start_ms' => 500, 'end_ms' => 2000],
                    ],
                ],
                [
                    'start_ms' => 2000,
                    'end_ms' => 4000,
                    'text' => 'this is a test',
                    'words' => [
                        ['word' => 'this', 'start_ms' => 2000, 'end_ms' => 2500],
                        ['word' => 'test', 'start_ms' => 3500, 'end_ms' => 4000],
                    ],
                ],
            ],
        ];
    }

    private function fakeWhisper(array $result): void
    {
        $mock = Mockery::mock(WhisperService::class);
        $mock->shouldReceive('transcribe')->andReturn($result);
        $this->app->instance(WhisperService::class, $mock);
    }

    public function test_transcribe_job_persists_transcript_and_word_level_segments(): void
    {
        $video = $this->makeVideo();
        $this->fakeWhisper($this->whisperResult());

        (new TranscribeJob($video->id))->handle($this->app->make(WhisperService::class));

        $transcript = Transcript::where('video_id', $video->id)->firstOrFail();
        $this->assertSame('en', $transcript->language);
        $this->assertStringContainsString('hello world', $transcript->full_text);

        $segments = TranscriptSegment::where('transcript_id', $transcript->id)
            ->orderBy('start_ms')->get();
        $this->assertCount(2, $segments);

        // Word-level timing survives the round-trip (required for captions).
        $firstWords = $segments->first()->words;
        $this->assertSame('hello', $firstWords[0]['word']);
        $this->assertSame(0, $firstWords[0]['start_ms']);
        $this->assertSame(500, $firstWords[0]['end_ms']);

        $this->assertSame('transcribed', $video->fresh()->status);
        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id,
            'stage' => 'transcribe',
            'status' => 'done',
        ]);

        // Hands off to Stage 3 for the same video.
        Queue::assertPushed(ScoreHighlightsJob::class, fn ($job) => $job->videoId === $video->id);
    }

    public function test_retry_is_idempotent_and_does_not_duplicate_segments(): void
    {
        $video = $this->makeVideo();
        $this->fakeWhisper($this->whisperResult());

        // Simulate a first run followed by a retry (worker crashed after commit).
        (new TranscribeJob($video->id))->handle($this->app->make(WhisperService::class));
        (new TranscribeJob($video->id))->handle($this->app->make(WhisperService::class));

        // Exactly one transcript, two segments — not doubled.
        $this->assertSame(1, Transcript::where('video_id', $video->id)->count());
        $transcript = Transcript::where('video_id', $video->id)->firstOrFail();
        $this->assertSame(2, TranscriptSegment::where('transcript_id', $transcript->id)->count());
    }

    public function test_partial_write_does_not_leave_a_transcript_without_segments(): void
    {
        // A result whose segment insert will blow up (invalid words payload
        // that json_encode handles, but we force a DB error via a huge text?).
        // Simpler: assert transaction atomicity by making the job throw mid-write.
        $video = $this->makeVideo();

        // WhisperService returns a result, but we corrupt segments so the
        // chunked insert throws (non-array segment slips past normalize? No —
        // instead force failure by mocking DB insert). Use a result with a
        // segment count, then wrap: easiest is to trust the transaction and
        // assert no partial state after a thrown insert.
        $bad = $this->whisperResult();
        // Force the insert to fail: start_ms as a non-scalar can't be bound.
        $bad['segments'][1]['start_ms'] = ['not', 'an', 'int'];
        $this->fakeWhisper($bad);

        try {
            (new TranscribeJob($video->id))->handle($this->app->make(WhisperService::class));
            $this->fail('Expected the segment insert to throw.');
        } catch (\Throwable $e) {
            // expected
        }

        // Transaction rolled back: no orphan transcript, no partial segments.
        $this->assertSame(0, Transcript::where('video_id', $video->id)->count());
        $this->assertSame(0, TranscriptSegment::count());
    }

    public function test_failed_hook_marks_video_and_pipeline_job_failed(): void
    {
        $video = $this->makeVideo();

        (new TranscribeJob($video->id))->failed(new RuntimeException('whisper down'));

        $this->assertSame('failed', $video->fresh()->status);
        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id,
            'stage' => 'transcribe',
            'status' => 'failed',
            'last_error' => 'whisper down',
        ]);
    }

    public function test_missing_video_is_a_noop(): void
    {
        $this->fakeWhisper($this->whisperResult());
        // Should not throw for a non-existent video id.
        (new TranscribeJob(999999))->handle($this->app->make(WhisperService::class));
        $this->assertSame(0, Transcript::count());
    }

    public function test_job_is_configured_to_retry_on_crash(): void
    {
        // DoD: "job queue survives a worker crash mid-stage (retry, not data
        // loss)". Retries are what turn a mid-stage crash into a re-run; the
        // idempotent handle() (tested above) makes that re-run safe.
        $job = new TranscribeJob(1);
        $this->assertGreaterThanOrEqual(2, $job->tries);
        $this->assertSame(
            (int) config('autoclip.timeouts.transcribe') + 120,
            $job->timeout(),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
