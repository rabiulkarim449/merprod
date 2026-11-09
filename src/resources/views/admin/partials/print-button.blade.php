{{-- "Print" — opens the same page in printMaster2 (?print=1) in a new tab. --}}
<a href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm btn-icon" title="Print"><i class="fa-solid fa-print"></i></a>
