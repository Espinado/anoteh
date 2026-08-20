<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);
        abort_unless(in_array($attachment->disk, config('attachments.private_disks', []), true), 404);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, basename($attachment->original_name), ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
