<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

class TnaTemplate extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_tna_templates';

    protected $fillable = ['name', 'is_default', 'ship_to_ex_factory_days', 'ex_factory_to_sewing_end_days', 'pcd_to_sewing_start_days', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean', 'is_default' => 'boolean'];

    public function tasks(): HasMany
    {
        return $this->hasMany(TnaTemplateTask::class)->orderBy('sequence');
    }

    public static function default(): ?self
    {
        return static::query()->active()->orderByDesc('is_default')->orderBy('id')->first();
    }
}
