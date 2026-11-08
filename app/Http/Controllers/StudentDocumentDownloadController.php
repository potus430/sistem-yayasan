<?php

namespace App\Http\Controllers;

use App\Models\StudentDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDocumentDownloadController
{
    public function __invoke(StudentDocument $document): StreamedResponse
    {
        $user = auth()->user();
        abort_unless(
            $user->role === 'admin_pusat'
                || ($user->role === 'admin_cabang'
                    && $document->student()->where('branch_id', $user->branch_id)->exists()),
            404
        );
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff']
        );
    }

    public function preview(StudentDocument $document): StreamedResponse
    {
        $user = auth()->user();
        abort_unless(
            $document->type === 'PAS_PHOTO'
                && in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
                && ($user->role === 'admin_pusat'
                    || ($user->role === 'admin_cabang'
                        && $document->student()->where('branch_id', $user->branch_id)->exists())),
            404
        );

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);
        $stream = $disk->readStream($document->path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
