<?php

namespace App\Services\Scoring;

/**
 * Validates and sanitises the LLM's highlight output against a strict schema
 * before any of it reaches the database (spec section 6: prompt-injection
 * surface). The LLM's response is treated purely as untrusted DATA:
 *
 *  - Only the four expected fields are read; everything else is dropped.
 *  - Numeric bounds are clamped/validated, never used to build paths or shell.
 *  - Rationale text is length-capped and kept as an inert string.
 *  - Ranges outside the video, or inverted, are rejected.
 *
 * Anything that can't be coerced into a clean candidate is discarded; if the
 * whole payload is unusable, an InvalidHighlightSchemaException is thrown so
 * the job can retry rather than persist garbage.
 */
class HighlightSchema
{
    /**
     * Default clip-length bounds. The effective bounds are configurable
     * (config/autoclip.php > clips) so a campaign with a hard minimum length
     * (e.g. "clips must be >= 10s") is enforced at validation time, not left to
     * the LLM. Constants remain the fallback when config is absent.
     */
    public const MIN_CLIP_MS = 3_000;      // 3s floor (default)

    public const MAX_CLIP_MS = 180_000;    // 3min ceiling (default)

    public const MAX_RATIONALE_LEN = 1_000;

    private int $minClipMs;

    private int $maxClipMs;

    public function __construct(?int $minClipMs = null, ?int $maxClipMs = null)
    {
        // Precedence: explicit arg > config > constant default.
        $this->minClipMs = $minClipMs
            ?? (int) config('autoclip.clips.min_ms', self::MIN_CLIP_MS);
        $this->maxClipMs = $maxClipMs
            ?? (int) config('autoclip.clips.max_ms', self::MAX_CLIP_MS);
    }

    public function minClipMs(): int
    {
        return $this->minClipMs;
    }

    public function maxClipMs(): int
    {
        return $this->maxClipMs;
    }

    /**
     * @param  mixed  $raw  decoded JSON from the LLM (expected: array of objects)
     * @param  int  $videoDurationMs  used to reject out-of-range ranges
     * @return array<int, array{start_ms:int, end_ms:int, hook_score:int, rationale:string}>
     *
     * @throws InvalidHighlightSchemaException when nothing valid can be salvaged
     */
    public function validate(mixed $raw, int $videoDurationMs): array
    {
        // The model may wrap the array in a key (e.g. {"highlights":[...]}).
        $items = $this->extractArray($raw);

        if ($items === null) {
            throw new InvalidHighlightSchemaException(
                'LLM output was not a JSON array of highlight objects.'
            );
        }

        $clean = [];
        foreach ($items as $item) {
            $candidate = $this->coerceItem($item, $videoDurationMs);
            if ($candidate !== null) {
                $clean[] = $candidate;
            }
        }

        if ($clean === []) {
            throw new InvalidHighlightSchemaException(
                'LLM output contained no valid highlight objects after validation.'
            );
        }

        return $clean;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function extractArray(mixed $raw): ?array
    {
        if (is_array($raw) && array_is_list($raw)) {
            return $raw;
        }

        // Accept a single wrapper key whose value is the list.
        if (is_array($raw)) {
            foreach (['highlights', 'clips', 'candidates', 'segments', 'data'] as $key) {
                if (isset($raw[$key]) && is_array($raw[$key]) && array_is_list($raw[$key])) {
                    return $raw[$key];
                }
            }
        }

        return null;
    }

    /**
     * @return array{start_ms:int, end_ms:int, hook_score:int, rationale:string}|null
     */
    private function coerceItem(mixed $item, int $videoDurationMs): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        // Required numeric fields must be present and numeric.
        if (! $this->isNumeric($item['start_ms'] ?? null)
            || ! $this->isNumeric($item['end_ms'] ?? null)
            || ! $this->isNumeric($item['hook_score'] ?? null)) {
            return null;
        }

        $start = (int) round((float) $item['start_ms']);
        $end = (int) round((float) $item['end_ms']);
        $score = (int) round((float) $item['hook_score']);

        // Bounds: non-negative, ordered, within the video, sane duration.
        if ($start < 0 || $end <= $start) {
            return null;
        }
        if ($videoDurationMs > 0 && $start >= $videoDurationMs) {
            return null;
        }
        // Snap the end into the video rather than dropping a good hook.
        if ($videoDurationMs > 0 && $end > $videoDurationMs) {
            $end = $videoDurationMs;
        }

        $duration = $end - $start;
        if ($duration < $this->minClipMs || $duration > $this->maxClipMs) {
            return null;
        }

        $score = max(0, min(100, $score));

        $rationale = $item['rationale'] ?? $item['reason'] ?? '';
        if (! is_string($rationale)) {
            $rationale = '';
        }
        // Inert string: trimmed and length-capped. Never interpreted.
        $rationale = mb_substr(trim($rationale), 0, self::MAX_RATIONALE_LEN);

        return [
            'start_ms' => $start,
            'end_ms' => $end,
            'hook_score' => $score,
            'rationale' => $rationale,
        ];
    }

    private function isNumeric(mixed $v): bool
    {
        return is_int($v) || is_float($v) || (is_string($v) && is_numeric($v));
    }
}
