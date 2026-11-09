<?php

namespace ME\MerchandisingSfl\Models\Concerns;

/**
 * For models that declare `const STATUSES = ['key' => ['Label', 'badge-color'], ...]`.
 */
trait HasStatus
{
    public static function statusOptions(): array
    {
        return array_map(fn ($status) => $status[0], static::STATUSES);
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status][0] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function statusBadge(): string
    {
        return static::STATUSES[$this->status][1] ?? 'secondary';
    }
}
