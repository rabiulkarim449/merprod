<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens an uploaded file (tech pack, BOM file, attachments, images) straight from the
 * upload disk — no public/storage symlink or APP_URL needed, and only for logged-in users
 * (route group middleware). Only files under merchandising-sfl/ are served.
 */
class FileController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $disk = Storage::disk(config('merchandising-sfl.upload_disk'));
        abort_unless(str_starts_with($path, 'merchandising-sfl/') && ! str_contains($path, '..') && $disk->exists($path), 404);

        return $disk->response($path, basename($path), [], 'inline');
    }
}
