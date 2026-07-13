<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Phase 1 is single-operator; no auth gate yet (spec: no multi-tenant).
        return true;
    }

    /**
     * First-line validation. The authoritative type/duration checks happen in
     * VideoIngestService (magic bytes + ffprobe); this just rejects the
     * obviously-wrong before we touch the file.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('autoclip.ingest.max_size_kb');

        return [
            'video' => [
                'required',
                File::default()->max($maxKb),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'video.required' => 'A video file is required under the "video" field.',
        ];
    }
}
