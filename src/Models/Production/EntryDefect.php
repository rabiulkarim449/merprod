<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;

class EntryDefect extends Model
{
    public $timestamps = false;

    protected $table = 'msfl_prod_entry_defects';

    public const SEVERITIES = ['critical' => 'Critical', 'major' => 'Major', 'minor' => 'Minor'];

    protected $fillable = ['entry_id', 'type', 'severity', 'part_name', 'machine_id', 'defect', 'qty'];

    /**
     * Every reject / rework row that carries pieces must say how serious it is — the DHU report
     * counts Critical / Major / Minor from these. Returns an error message, or null.
     */
    public static function missingSeverity(iterable $rows): ?string
    {
        foreach ($rows as $d) {
            if ((int) ($d['qty'] ?? 0) > 0 && ! array_key_exists($d['severity'] ?? '', self::SEVERITIES)) {
                return 'Pick Critical / Major / Minor for every reject / rework row.';
            }
        }

        return null;
    }

    /** Inventory machine name/code (machines are entered once, in Inventory). */
    public function machineLabel(): ?string
    {
        if (! $this->machine_id) {
            return null;
        }
        $m = \Illuminate\Support\Facades\DB::table('inv_machines')->where('id', $this->machine_id)->first(['name', 'code']);

        return $m ? trim($m->code . ' — ' . $m->name) : null;
    }
}
