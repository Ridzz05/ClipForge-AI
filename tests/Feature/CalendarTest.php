<?php

namespace Tests\Feature;

use App\Livewire\Calendar;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\ScheduledPost;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeExport(array $attrs = []): Export
    {
        $video = Video::create([
            'source_type' => 'upload',
            'source_ref' => 'podcast-ep-01.mp4',
            'status' => 'done',
            'duration_seconds' => 600,
            'storage_path' => 'videos/x/source.mp4',
        ]);

        $candidate = ClipCandidate::create([
            'video_id' => $video->id,
            'start_ms' => 0,
            'end_ms' => 30000,
            'hook_score' => 80,
            'score_rationale' => 'x',
            'status' => ClipCandidate::STATUS_EXPORTED,
        ]);

        return Export::create(array_merge([
            'clip_candidate_id' => $candidate->id,
            'aspect_ratio' => '9:16',
            'caption_style' => 'default',
            'status' => Export::STATUS_RENDERED,
            'output_path' => 'exports/x/clip.mp4',
        ], $attrs));
    }

    public function test_calendar_renders_without_exports(): void
    {
        Livewire::test(Calendar::class)
            ->assertOk()
            ->assertSee('Belum ada klip yang selesai dirender');
    }

    public function test_route_returns_200_with_empty_and_seeded_data(): void
    {
        $this->get('/calendar')->assertOk()->assertSee('Kalender Konten');

        $export = $this->makeExport();

        ScheduledPost::create([
            'export_id' => $export->id,
            'platform' => 'TikTok',
            'scheduled_at' => Carbon::now()->startOfMonth()->setTime(8, 15),
            'caption' => 'Uji rute',
            'status' => ScheduledPost::STATUS_PLANNED,
        ]);

        $this->get('/calendar')->assertOk()->assertSee('08:15');
    }

    public function test_calendar_renders_seeded_posts_and_platform_labels(): void
    {
        $export = $this->makeExport();
        $when = Carbon::now()->startOfMonth()->addDays(2)->setTime(14, 30);

        ScheduledPost::create([
            'export_id' => $export->id,
            'platform' => 'TikTok',
            'scheduled_at' => $when,
            'caption' => 'Potongan episode 1',
            'status' => ScheduledPost::STATUS_PLANNED,
        ]);

        Livewire::test(Calendar::class)
            ->assertOk()
            ->assertSee('Kalender Konten')
            ->assertSee('TikTok')
            ->assertSee('14:30')
            ->assertSee('Terjadwal');
    }

    public function test_create_post_persists_parsed_datetime(): void
    {
        $export = $this->makeExport();
        $month = Carbon::now()->format('Y-m');
        $input = $month.'-18T09:45';

        Livewire::test(Calendar::class)
            ->set('exportId', (string) $export->id)
            ->set('platform', 'Instagram Reels')
            ->set('scheduledAt', $input)
            ->set('caption', 'Caption uji coba')
            ->call('save')
            ->assertHasNoErrors();

        $post = ScheduledPost::firstOrFail();

        $this->assertSame($export->id, $post->export_id);
        $this->assertSame('Instagram Reels', $post->platform);
        $this->assertSame('Caption uji coba', $post->caption);
        $this->assertSame(ScheduledPost::STATUS_PLANNED, $post->status);
        $this->assertSame('18/'.$month[5].$month[6].'/'.$month[0].$month[1].$month[2].$month[3], $post->scheduled_at->format('d/m/Y'));
        $this->assertSame('09:45', $post->scheduled_at->format('H:i'));
    }

    public function test_validation_rejects_missing_export_and_invalid_date(): void
    {
        $this->makeExport();

        Livewire::test(Calendar::class)
            ->set('exportId', '')
            ->set('scheduledAt', 'bukan-tanggal')
            ->call('save')
            ->assertHasErrors(['exportId', 'scheduledAt']);

        $this->assertDatabaseCount('scheduled_posts', 0);
    }

    public function test_validation_rejects_unrendered_export(): void
    {
        $queued = $this->makeExport(['status' => Export::STATUS_QUEUED, 'output_path' => null]);

        Livewire::test(Calendar::class)
            ->set('exportId', (string) $queued->id)
            ->set('scheduledAt', Carbon::now()->format('Y-m').'-20T10:00')
            ->call('save')
            ->assertHasErrors('exportId');

        $this->assertDatabaseCount('scheduled_posts', 0);
    }

    public function test_toggle_posted_sets_status_and_posted_at(): void
    {
        $export = $this->makeExport();
        $post = ScheduledPost::create([
            'export_id' => $export->id,
            'platform' => 'TikTok',
            'scheduled_at' => Carbon::now()->subDay(),
            'status' => ScheduledPost::STATUS_PLANNED,
        ]);

        Livewire::test(Calendar::class)
            ->call('selectPost', $post->id)
            ->call('togglePosted')
            ->assertHasNoErrors();

        $post->refresh();
        $this->assertSame(ScheduledPost::STATUS_POSTED, $post->status);
        $this->assertNotNull($post->posted_at);

        Livewire::test(Calendar::class)
            ->call('selectPost', $post->id)
            ->call('togglePosted');

        $this->assertSame(ScheduledPost::STATUS_PLANNED, $post->refresh()->status);
        $this->assertNull($post->posted_at);
    }

    public function test_delete_removes_row(): void
    {
        $export = $this->makeExport();
        $post = ScheduledPost::create([
            'export_id' => $export->id,
            'platform' => 'TikTok',
            'scheduled_at' => Carbon::now(),
            'status' => ScheduledPost::STATUS_PLANNED,
        ]);

        Livewire::test(Calendar::class)
            ->call('selectPost', $post->id)
            ->call('delete');

        $this->assertDatabaseMissing('scheduled_posts', ['id' => $post->id]);
    }

    public function test_month_navigation_changes_visible_posts(): void
    {
        $export = $this->makeExport();
        $now = Carbon::now()->startOfMonth();

        ScheduledPost::create([
            'export_id' => $export->id,
            'platform' => 'TikTok',
            'scheduled_at' => $now->copy()->setTime(12, 0),
            'status' => ScheduledPost::STATUS_PLANNED,
        ]);

        ScheduledPost::create([
            'export_id' => $export->id,
            'platform' => 'YouTube Shorts',
            'scheduled_at' => $now->copy()->addMonth()->setTime(11, 0),
            'status' => ScheduledPost::STATUS_PLANNED,
        ]);

        Livewire::test(Calendar::class)
            ->assertSee('TikTok')
            ->assertDontSee('11:00')
            ->call('nextMonth')
            ->assertSee('11:00')
            ->call('currentMonth')
            ->assertSee('12:00');
    }

    public function test_invalid_month_value_falls_back_to_current_month(): void
    {
        Livewire::test(Calendar::class)
            ->set('month', '2026-13')
            ->assertOk()
            ->assertSet('month', Carbon::now()->format('Y-m'));

        Livewire::test(Calendar::class)
            ->set('month', '../../etc')
            ->assertSet('month', Carbon::now()->format('Y-m'));
    }
}
