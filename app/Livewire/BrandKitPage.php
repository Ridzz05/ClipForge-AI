<?php

namespace App\Livewire;

use App\Models\BrandKit;
use App\Services\Reframe\CaptionRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class BrandKitPage extends Component
{
    use WithFileUploads;

    /** Font names burned into captions; same list the clip editor offers. */
    public const FONTS = ['Outfit', 'Plus Jakarta Sans', 'Poppins', 'Montserrat', 'Inter', 'Fira Sans'];

    /** Human labels for the ASS caption templates (keys come from CaptionRenderer). */
    public const STYLE_LABELS = [
        'none' => 'Tanpa caption',
        'default' => 'Default (putih Arial)',
        'karaoke_yellow' => 'Karaoke kuning',
        'tiktok_green' => 'TikTok hijau',
        'short_bold' => 'Short bold (putih Impact)',
        'hormozi_neon' => 'Hormozi neon',
        'mrbeast_comic' => 'MrBeast komik',
        'minimal_outfit' => 'Minimalis (Trebuchet MS)',
    ];

    public ?string $name = null;

    public ?string $ctaText = null;

    public ?string $captionStyle = null;

    public ?string $captionFont = null;

    public ?string $headlineStyle = null;

    public ?string $logoPath = null;

    public $logo;

    public function mount(): void
    {
        $kit = BrandKit::current();

        $this->name = $kit->name;
        $this->ctaText = $kit->cta_text;
        $this->captionStyle = $kit->caption_style;
        $this->captionFont = $kit->caption_font;
        $this->headlineStyle = $kit->headline_style;
        $this->logoPath = $kit->logo_path;
    }

    public function updatedLogo(): void
    {
        $this->resetValidation('logo');
    }

    public function save(): void
    {
        $this->name = $this->blank($this->name);
        $this->ctaText = $this->blank($this->ctaText);
        $this->captionStyle = $this->blank($this->captionStyle);
        $this->captionFont = $this->blank($this->captionFont);
        $this->headlineStyle = $this->blank($this->headlineStyle);

        $this->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'ctaText' => ['nullable', 'string', 'max:80'],
            'captionStyle' => ['nullable', 'string', Rule::in($this->styleKeys())],
            'captionFont' => ['nullable', 'string', Rule::in(self::FONTS)],
            'headlineStyle' => ['nullable', 'string', Rule::in(array_keys(CaptionRenderer::headlineStyles()))],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [], [
            'ctaText' => 'teks CTA',
            'captionStyle' => 'gaya caption',
            'captionFont' => 'font caption',
            'headlineStyle' => 'gaya headline',
            'logo' => 'logo',
        ]);

        $kit = BrandKit::current();
        $diskName = (string) config('autoclip.assets.disk');
        $updates = [
            'name' => $this->name,
            'cta_text' => $this->ctaText,
            'caption_style' => $this->captionStyle,
            'caption_font' => $this->captionFont,
            'headline_style' => $this->headlineStyle,
        ];

        if ($this->logo) {
            $path = $this->logo->store('brand', $diskName);

            if (is_string($kit->logo_path) && $kit->logo_path !== '' && $kit->logo_path !== $path) {
                Storage::disk($diskName)->delete($kit->logo_path);
            }

            $updates['logo_path'] = $path;
            $this->logoPath = $path;
            $this->reset('logo');
        }

        $kit->update($updates);

        $this->dispatch('toast', message: 'Brand kit tersimpan.', type: 'success');
    }

    public function removeLogo(): void
    {
        $kit = BrandKit::current();

        if (is_string($kit->logo_path) && $kit->logo_path !== '') {
            Storage::disk((string) config('autoclip.assets.disk'))->delete($kit->logo_path);
        }

        $kit->update(['logo_path' => null]);
        $this->logoPath = null;
        $this->reset('logo');

        $this->dispatch('toast', message: 'Logo brand dihapus.', type: 'success');
    }

    public function render()
    {
        $styles = [];

        foreach ($this->styleKeys() as $key) {
            $styles[$key] = self::STYLE_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
        }

        $current = BrandKit::current();

        return view('livewire.brand-kit-page', [
            'styles' => $styles,
            'fonts' => self::FONTS,
            'headlineStyles' => CaptionRenderer::headlineStyles(),
            'logoUrl' => $this->logoPath
                ? route('brand.logo').'?v='.($current->updated_at?->timestamp ?? '0')
                : null,
        ]);
    }

    /** @return list<string> */
    private function styleKeys(): array
    {
        return app(CaptionRenderer::class)->availableStyles();
    }

    private function blank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
