<?php

namespace ME\MerchandisingSfl\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use ME\MerchandisingSfl\Models\Concerns\IsMaster;

/** Extra days a wash/finish step adds after sewing ends — used by the Shipment learning curve's ready-date projection. */
class LeadTime extends Model
{
    use HasAudit;
    use IsMaster;
    use SoftDeletes;

    protected $table = 'msfl_lead_times';

    protected $fillable = ['category', 'name', 'days', 'sequence', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
