<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stores tech packs, images and attachments on the package's upload disk. */
class FileUploadService
{
    /** Validation for every document upload (tech pack, BOM file, attachments). */
    public const RULES = ['file', 'mimes:pdf,xls,xlsx,doc,docx,zip,jpg,jpeg,png', 'max:20480'];

    public function store(?UploadedFile $file, string $folder, ?string $replacing = null): ?string
    {
        if (! $file) {
            return $replacing;
        }

        $this->delete($replacing);

        return $file->store('merchandising-sfl/' . $folder, config('merchandising-sfl.upload_disk'));
    }

    public function copy(?string $path, string $folder): ?string
    {
        $disk = Storage::disk(config('merchandising-sfl.upload_disk'));
        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $copy = 'merchandising-sfl/' . $folder . '/' . Str::random(40) . '.' . pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $copy);

        return $copy;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(config('merchandising-sfl.upload_disk'))->delete($path);
        }
    }

    /**
     * Several documents kept in one column as a JSON list of {path, name}.
     * A value saved before that (a single path) still reads as one file.
     *
     * @return array<int, array{path: string, name: string}>
     */
    public static function list(?string $value): array
    {
        if (! $value) {
            return [];
        }
        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [['path' => $value, 'name' => 'Document']];
        }

        return array_values(array_filter(array_map(
            fn ($file) => is_array($file) && ! empty($file['path']) ? ['path' => (string) $file['path'], 'name' => (string) ($file['name'] ?? basename($file['path']))] : null,
            $decoded
        )));
    }

    /**
     * The column's new value: the current files minus the ones ticked for removal (deleted from
     * the disk), plus the new uploads. Null when no file is left.
     *
     * @param  array<int, UploadedFile>  $uploads
     * @param  array<int, string>  $remove  paths to drop
     */
    public function storeList(array $uploads, string $folder, ?string $current, array $remove = []): ?string
    {
        $files = [];
        foreach (self::list($current) as $file) {
            if (in_array($file['path'], $remove, true)) {
                $this->delete($file['path']);
            } else {
                $files[] = $file;
            }
        }
        foreach ($uploads as $upload) {
            if ($upload instanceof UploadedFile) {
                $files[] = ['path' => $this->store($upload, $folder), 'name' => $upload->getClientOriginalName()];
            }
        }

        return $files ? json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    }

    public function url(?string $path): ?string
    {
        return self::link($path);
    }

    /** Link that opens an uploaded file through the package (FileController) — works without the storage symlink / APP_URL. */
    public static function link(?string $path): ?string
    {
        return $path ? route('msfl.files.show', ['path' => $path]) : null;
    }
}
