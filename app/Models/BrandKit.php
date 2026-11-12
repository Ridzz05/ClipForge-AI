<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row brand defaults (logo, CTA, caption look, headline look) applied
 * to new exports. Use BrandKit::current(); there is never more than one row.
 */
class BrandKit extends Model
{
    protected $fillable = [
        'name',
        'cta_text',
        'caption_style',
        'caption_font',
        'headline_style',
        'logo_path',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }
}
