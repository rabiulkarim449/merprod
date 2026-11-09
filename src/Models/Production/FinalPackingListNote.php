<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;

/** QC's short / excess note for one style line of a final packing list. */
class FinalPackingListNote extends Model
{
    protected $table = 'msfl_prod_final_packing_list_notes';

    protected $fillable = ['final_packing_list_id', 'order_po_id', 'note'];
}
