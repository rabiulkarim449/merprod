{{-- PO lines of an order (read-only; they are entered on the order form). props: order (pos.style/color/shipMode/sizes loaded), sizeColumns --}}
@php $canEditPos = $order->isEditable() && auth()->user()->can('msfl_order.edit'); @endphp
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">PO Lines</h6>
                @if($canEditPos)
                    <a href="{{ route('msfl.orders.edit', $order) }}" class="btn btn-sm ui-act ui-act-edit" title="Add / Edit PO Lines"><i class="fa-solid fa-pen-to-square"></i> Add / Edit PO Lines</a>
                @endif
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                        <tr>
                            <th>PO No</th><th>Style</th><th>Color</th>
                            @foreach($sizeColumns as $size)<th class="text-center">{{ $size->name }}</th>@endforeach
                            <th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Value</th><th>PCD</th><th>Shipment</th><th>Ship Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->pos as $po)
                            @php $qtyBySize = $po->sizes->pluck('qty', 'size_id'); @endphp
                            <tr>
                                <td>{{ $po->po_no }}</td>
                                <td><a href="{{ route('msfl.styles.show', $po->style_id) }}">{{ $po->style->style_no ?? '-' }}</a></td>
                                <td>{{ $po->color->name ?? '-' }}</td>
                                @foreach($sizeColumns as $size)<td class="text-center">{{ $qtyBySize->get($size->id, '') }}</td>@endforeach
                                <td class="text-right font-weight-bold">{{ number_format($po->po_qty) }}</td>
                                <td class="text-right">{{ number_format($po->unit_price, 4) }}</td>
                                <td class="text-right">{{ number_format($po->total_value, 2) }}</td>
                                <td>{{ optional($po->pcd_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ optional($po->shipment_date)->format('d M Y') ?? '-' }}</td>
                                <td>{{ $po->shipMode->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 10 + $sizeColumns->count() }}" class="text-center text-muted">No PO lines yet.</td></tr>
                        @endforelse
                    </tbody>
                    @if($order->pos->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Total</th>
                                @foreach($sizeColumns as $size)
                                    <th class="text-center">{{ $order->pos->sum(fn ($po) => $po->sizes->where('size_id', $size->id)->sum('qty')) }}</th>
                                @endforeach
                                <th class="text-right">{{ number_format($order->total_qty) }}</th><th></th><th class="text-right">{{ number_format($order->total_value, 2) }}</th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

