<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /** Rows per page of a list — all of them when the list is opened for printing (?print=1). */
    protected function perPage(int $default = 20): int
    {
        return request()->boolean('print') ? 10000 : $default;
    }
}
