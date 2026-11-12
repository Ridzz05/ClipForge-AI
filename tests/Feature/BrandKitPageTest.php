<?php

namespace Tests\Feature;

use App\Livewire\BrandKitPage;
use App\Models\BrandKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandKitPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_page_renders(): void
    {
        $this->get('/brand')->assertOk()->assertSee('Brand kit');

        Livewire::test(BrandKitPage::class)
            ->assertOk()
            ->assertSee('Teks CTA default')
            ->assertSee('Logo brand');
    }

    public function test_settings_persist_and_current_reflects_them(): void
    {
        Livewire::test(BrandKitPage::class)
            ->set('name', 'Kopi Nusantara')
            ->set('ctaText', 'BELI SEKARANG')
            ->set('captionStyle', 'karaoke_yellow')
            ->set('captionFont', 'Poppins')
            ->set('headlineStyle', 'highlight')
            ->call('save')
            ->assertHasNoErrors();

        $kit = BrandKit::current();
        $this->assertSame('Kopi Nusantara', $kit->name);
        $this->assertSame('BELI SEKARANG', $kit->cta_text);
        $this->assertSame('karaoke_yellow', $kit->caption_style);
        $this->assertSame('Poppins', $kit->caption_font);
        $this->assertSame('highlight', $kit->headline_style);
    }

    public function test_blank_fields_fall_back_to_system_defaults(): void
    {
        Livewire::test(BrandKitPage::class)
            ->set('captionStyle', 'default')
            ->set('headlineStyle', 'marker')
            ->call('save');

        Livewire::test(BrandKitPage::class)
            ->set('captionStyle', '')
            ->set('headlineStyle', '')
            ->call('save')
            ->assertHasNoErrors();

        $kit = BrandKit::current();
        $this->assertNull($kit->caption_style);
        $this->assertNull($kit->headline_style);
    }

    public function test_cta_over_80_chars_is_rejected(): void
    {
        Livewire::test(BrandKitPage::class)
            ->set('ctaText', str_repeat('a', 81))
            ->call('save')
            ->assertHasErrors(['ctaText' => 'max']);
    }

    public function test_unknown_style_is_rejected(): void
    {
        Livewire::test(BrandKitPage::class)
            ->set('captionStyle', 'not_a_style')
            ->call('save')
            ->assertHasErrors(['captionStyle' => 'in']);
    }

    public function test_logo_upload_stores_file_and_replacing_deletes_old_one(): void
    {
        $component = Livewire::test(BrandKitPage::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 100))
            ->call('save')
            ->assertHasNoErrors();

        $kit = BrandKit::current();
        $this->assertStringStartsWith('brand/', (string) $kit->logo_path);
        Storage::disk('local')->assertExists($kit->logo_path);
        $this->assertSame($kit->logo_path, $component->get('logoPath'));

        $old = $kit->logo_path;

        Livewire::test(BrandKitPage::class)
            ->set('logo', UploadedFile::fake()->image('logo2.webp', 200, 100))
            ->call('save')
            ->assertHasNoErrors();

        $kit = BrandKit::current();
        $this->assertNotSame($old, $kit->logo_path);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($kit->logo_path);
    }

    public function test_logo_with_wrong_mime_is_rejected(): void
    {
        // GIF is previewable by Livewire's temp upload, so the failure comes
        // from our own mimes rule, not from the upload plumbing.
        Livewire::test(BrandKitPage::class)
            ->set('logo', UploadedFile::fake()->image('logo.gif', 100, 100))
            ->call('save')
            ->assertHasErrors(['logo' => 'mimes']);
    }

    public function test_oversize_logo_is_rejected(): void
    {
        // Real bytes: Livewire's temp upload ignores a faked size, and finfo
        // still reports image/png for the padded payload.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $bytes = $png.str_repeat('JUNK', 3 * 1024 * 1024 / 4);

        Livewire::test(BrandKitPage::class)
            ->set('logo', UploadedFile::fake()->createWithContent('big.png', $bytes))
            ->call('save')
            ->assertHasErrors(['logo' => 'max']);

        $this->assertNull(BrandKit::current()->logo_path);
    }

    public function test_remove_logo_deletes_file_and_row_value(): void
    {
        Livewire::test(BrandKitPage::class)
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 100))
            ->call('save');

        $path = BrandKit::current()->logo_path;
        Storage::disk('local')->assertExists($path);

        Livewire::test(BrandKitPage::class)
            ->call('removeLogo')
            ->assertSet('logoPath', null);

        $this->assertNull(BrandKit::current()->logo_path);
        Storage::disk('local')->assertMissing($path);
    }
}
