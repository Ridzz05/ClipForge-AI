<?php

namespace Tests\Feature;

use App\Livewire\AssetLibrary;
use App\Models\Asset;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use App\Services\FfprobeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AssetLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fakeFfprobe(?int $duration, bool $throw = false): void
    {
        $ffprobe = Mockery::mock(FfprobeService::class);

        if ($throw) {
            $ffprobe->shouldReceive('durationSeconds')->andThrow(new RuntimeException('no video stream'));
        } else {
            $ffprobe->shouldReceive('durationSeconds')->andReturn($duration);
        }

        $this->app->instance(FfprobeService::class, $ffprobe);
    }

    public function test_empty_page_renders_with_both_empty_states(): void
    {
        $this->get('/assets')->assertOk()->assertSee('Aset');

        Livewire::test(AssetLibrary::class)
            ->assertOk()
            ->assertSee('Belum ada musik di perpustakaan')
            ->assertSee('Belum ada gambar');
    }

    public function test_music_upload_stores_file_row_and_duration(): void
    {
        $this->fakeFfprobe(93);

        Livewire::test(AssetLibrary::class)
            ->set('music', UploadedFile::fake()->create('My Track.mp3', 512, 'audio/mpeg'))
            ->call('uploadMusic')
            ->assertHasNoErrors()
            ->assertSee('My Track');

        $asset = Asset::firstOrFail();
        $this->assertSame(Asset::KIND_MUSIC, $asset->kind);
        $this->assertSame('My Track', $asset->name);
        $this->assertSame(93, $asset->duration_seconds);
        $this->assertStringStartsWith('assets/music/', $asset->path);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_music_duration_stays_null_when_probe_fails(): void
    {
        $this->fakeFfprobe(null, throw: true);

        Livewire::test(AssetLibrary::class)
            ->set('music', UploadedFile::fake()->create('weird.mp3', 300, 'audio/mpeg'))
            ->call('uploadMusic')
            ->assertHasNoErrors();

        $this->assertNull(Asset::firstOrFail()->duration_seconds);
    }

    public function test_name_sanitised_from_client_path(): void
    {
        $this->fakeFfprobe(30);

        Livewire::test(AssetLibrary::class)
            ->set('music', UploadedFile::fake()->create('../../C:/Users/muhri/My Song.mp3', 200, 'audio/mpeg'))
            ->call('uploadMusic')
            ->assertHasNoErrors();

        $this->assertSame('My Song', Asset::firstOrFail()->name);
    }

    public function test_wrong_music_mime_is_rejected(): void
    {
        Livewire::test(AssetLibrary::class)
            ->set('music', UploadedFile::fake()->create('video.mp4', 100, 'video/mp4'))
            ->call('uploadMusic')
            ->assertHasErrors(['music' => 'mimes']);
    }

    public function test_oversize_music_is_rejected(): void
    {
        // Livewire's temp upload drops a faked size, so the file carries real
        // bytes and the cap comes from config (validation must read the file).
        config(['autoclip.assets.max_size_kb' => 2]);
        $bytes = "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64", 1000);

        Livewire::test(AssetLibrary::class)
            ->set('music', UploadedFile::fake()->createWithContent('huge.mp3', $bytes))
            ->call('uploadMusic')
            ->assertHasErrors(['music' => 'max']);

        $this->assertSame(0, Asset::query()->count());
    }

    public function test_image_upload_stores_thumbnail_source(): void
    {
        Livewire::test(AssetLibrary::class)
            ->set('image', UploadedFile::fake()->image('bumper.png', 400, 400))
            ->call('uploadImage')
            ->assertHasNoErrors()
            ->assertSee('bumper');

        $asset = Asset::firstOrFail();
        $this->assertSame(Asset::KIND_IMAGE, $asset->kind);
        $this->assertSame('bumper', $asset->name);
        $this->assertStringStartsWith('assets/images/', $asset->path);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_wrong_image_mime_is_rejected(): void
    {
        Livewire::test(AssetLibrary::class)
            ->set('image', UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg'))
            ->call('uploadImage')
            ->assertHasErrors(['image' => 'mimes']);
    }

    public function test_delete_removes_file_and_row(): void
    {
        $asset = Asset::create([
            'kind' => Asset::KIND_MUSIC,
            'name' => 'track',
            'path' => 'assets/music/track.mp3',
            'mime' => 'audio/mpeg',
            'size_bytes' => 100,
        ]);
        Storage::disk('local')->put($asset->path, 'audio');

        Livewire::test(AssetLibrary::class)
            ->call('deleteAsset', $asset->id);

        $this->assertNull(Asset::find($asset->id));
        Storage::disk('local')->assertMissing('assets/music/track.mp3');
    }

    public function test_deleting_music_clears_exports_referencing_it(): void
    {
        $asset = Asset::create([
            'kind' => Asset::KIND_MUSIC,
            'name' => 'track',
            'path' => 'assets/music/track.mp3',
            'mime' => 'audio/mpeg',
            'size_bytes' => 100,
        ]);

        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'done',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'x', 'status' => ClipCandidate::STATUS_EXPORTED,
        ]);
        $export = Export::create([
            'clip_candidate_id' => $candidate->id, 'aspect_ratio' => '9:16',
            'caption_style' => 'default', 'status' => Export::STATUS_RENDERED,
            'music_asset_id' => $asset->id,
        ]);

        Livewire::test(AssetLibrary::class)->call('deleteAsset', $asset->id);

        $this->assertNull($export->fresh()->music_asset_id);
    }
}
