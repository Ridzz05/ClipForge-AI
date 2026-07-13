<?php

namespace Tests;

use Illuminate\Http\UploadedFile;

trait CreatesFakeMedia
{
    /**
     * Build an UploadedFile backed by real bytes whose magic number makes
     * finfo report the given MIME. This lets the magic-byte validation in
     * VideoIngestService run for real in tests (a plain UploadedFile::fake()
     * has no content and would be detected as empty/plain text).
     */
    protected function fakeVideoUpload(
        string $clientName = 'clip.mp4',
        string $mime = 'video/mp4',
    ): UploadedFile {
        $bytes = match ($mime) {
            // ISO Base Media (MP4/MOV): size + 'ftyp' + major brand.
            'video/mp4' => "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41",
            'video/quicktime' => "\x00\x00\x00\x14ftypqt  \x00\x00\x00\x00qt  ",
            // Matroska/WebM EBML header.
            'video/webm' => "\x1a\x45\xdf\xa3\x01\x00\x00\x00\x00\x00\x00\x1fwebm",
            // A clearly non-video payload for negative tests.
            default => "This is plain text, not a video at all.\n",
        };

        $path = tempnam(sys_get_temp_dir(), 'autoclip_test_');
        file_put_contents($path, $bytes);

        // test: true bypasses the is_uploaded_file() check so we can seed
        // real content; the file is still moved/copied like a real upload.
        return new UploadedFile($path, $clientName, null, null, true);
    }
}
