<?php

namespace ME\MerchandisingSfl\Models\Commercial;

use Illuminate\Database\Eloquent\Model;

/** Pcs of one size in each carton of a carton range. */
class InvoiceCartonSize extends Model
{
    public $timestamps = false;

    protected $table = 'msfl_com_invoice_carton_sizes';

    protected $fillable = ['invoice_carton_id', 'size_id', 'qty'];
}
