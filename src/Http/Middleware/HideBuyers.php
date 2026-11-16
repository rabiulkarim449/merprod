<?php

namespace ME\MerchandisingSfl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ME\MerchandisingSfl\Support\BuyerVisibility;

/** Report and dashboard pages: apply the role's "Hide Export / Local Buyer" ticks. */
class HideBuyers
{
    public function handle(Request $request, Closure $next)
    {
        BuyerVisibility::enable();

        return $next($request);
    }

    public function terminate(): void
    {
        BuyerVisibility::disable();
    }
}
