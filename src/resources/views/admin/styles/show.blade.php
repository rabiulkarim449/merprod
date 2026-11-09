@php($printMode = request()->boolean('print'))
@extends($printMode ? 'printMaster2' : adminTheme() . 'layouts.app')

@section('title')
@if($printMode){{ 'Style ' . $style->style_no }}@else
    <title>{{ websiteTitle('Style ' . $style->style_no) }}</title>
@endif
@endsection

@php($disk = \Illuminate\Support\Facades\Storage::disk(config('merchandising-sfl.upload_disk')))

@section('contents')
<div class="flex-grow-1 msfl-module {{ $printMode ? 'msfl-print' : '' }}">
    @include('merchandising-sfl::admin.partials.page-top', ['printTitle' => 'Tech Pack — ' . $style->style_no, 'printPage' => 'A4 portrait'])

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Style — {{ $style->style_no }} <small class="text-muted">{{ $style->name }}</small></h4>
            <div>
                @can('msfl_cost_sheet.add')
                    <a href="{{ route('msfl.cost-sheets.create', ['style_id' => $style->id]) }}" class="btn btn-sm ui-act ui-act-create" title="Cost Sheet"><i class="fa-solid fa-plus"></i> Cost Sheet</a>
                @endcan
                @can('msfl_bom.add')
                    <a href="{{ route('msfl.boms.create', ['style_id' => $style->id]) }}" class="btn btn-sm ui-act ui-act-create" title="BOM"><i class="fa-solid fa-plus"></i> BOM</a>
                @endcan
                @can('msfl_sample.add')
                    <a href="{{ route('msfl.samples.create', ['style_id' => $style->id]) }}" class="btn btn-sm ui-act ui-act-create" title="Sample"><i class="fa-solid fa-plus"></i> Sample</a>
                @endcan
                @can('msfl_style.edit')
                    <a href="{{ route('msfl.styles.edit', $style) }}" class="btn btn-sm ui-act ui-act-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                @endcan
                @include('merchandising-sfl::admin.partials.print-button')
                <a href="{{ route('msfl.styles.index') }}" class="btn btn-sm ui-act ui-act-back" title="Back"><i class="fa-solid fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-3 mb-2"><strong>Buyer:</strong> {{ $style->buyer->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Season:</strong> {{ $style->season->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Inquiry:</strong>
                    @if($style->inquiry)<a href="{{ route('msfl.inquiries.show', $style->inquiry) }}">{{ $style->inquiry->inquiry_no }}</a>@else - @endif
                </div>
                <div class="col-md-3 mb-2"><strong>Status:</strong> @include('merchandising-sfl::admin.partials.status-badge', ['model' => $style])</div>
                <div class="col-md-3 mb-2"><strong>Product Type:</strong> {{ $style->productType->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Wash Type:</strong> {{ $style->washType->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Merchandiser:</strong> {{ $style->merchandiser->name ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Fabric Sourced By:</strong> {{ $style->fabric_sourced_by === 'buyer' ? 'Buyer' : 'Self' }}</div>
                <div class="col-md-3 mb-2"><strong>SMV:</strong> {{ $style->smv ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Target CM / dz:</strong> {{ $style->target_cm ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Confirm CM / dz:</strong> {{ $style->confirm_cm ?? '-' }}</div>
                <div class="col-md-3 mb-2"><strong>Files:</strong>
                    @foreach(['tech_pack_file' => 'Tech Pack', 'artwork_file' => 'Artwork', 'size_chart_file' => 'Size Chart'] as $file => $fileLabel)
                        @if($style->{$file})
                            <a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($style->{$file}) }}" target="_blank" class="badge badge-light">{{ $fileLabel }}</a>
                        @endif
                    @endforeach
                </div>
                @if($style->fabric_description)
                    <div class="col-md-6 mb-2"><strong>Fabric:</strong> {!! nl2br(e($style->fabric_description)) !!}</div>
                @endif
                @if($style->description)
                    <div class="col-md-6 mb-2"><strong>Description:</strong> {!! nl2br(e($style->description)) !!}</div>
                @endif
            </div>

            {{-- Images --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Images</strong>
                    @can('msfl_style.edit')
                        <form method="POST" action="{{ route('msfl.styles.images.store', $style) }}" enctype="multipart/form-data" class="form-inline">
                            @csrf
                            <select name="type" class="form-control form-control-sm mr-1">
                                @foreach(\ME\MerchandisingSfl\Models\StyleImage::TYPES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="caption" class="form-control form-control-sm mr-1" placeholder="Caption">
                            <input type="file" name="images[]" accept="image/*" multiple required class="form-control form-control-sm mr-1">
                            <button type="submit" class="btn btn-sm ui-act ui-act-import" title="Upload"><i class="fa-solid fa-file-import"></i> Upload</button>
                        </form>
                    @endcan
                </div>
                <div class="card-body">
                    <div class="row">
                        @forelse($style->images as $image)
                            <div class="col-md-2 col-4 mb-3 text-center">
                                <a href="{{ $image->url() }}" target="_blank"><img src="{{ $image->url() }}" class="img-thumbnail" style="height:140px;object-fit:cover;width:100%" alt="{{ $image->caption }}"></a>
                                <div class="small mt-1">
                                    <span class="badge badge-light">{{ \ME\MerchandisingSfl\Models\StyleImage::TYPES[$image->type] ?? $image->type }}</span>
                                    {{ $image->caption }}
                                    @can('msfl_style.edit')
                                        <form method="POST" action="{{ route('msfl.styles.images.destroy', [$style, $image]) }}" class="d-inline" onsubmit="return confirm('Delete this image?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-custom ui-act ui-act-delete" title="Delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-muted">No images uploaded.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <h6>Cost Sheets</h6>
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>No</th><th>Date</th><th>Offer / pc</th><th>Final / pc</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($style->costSheets as $sheet)
                                <tr>
                                    <td><a href="{{ route('msfl.cost-sheets.show', $sheet) }}">{{ $sheet->cost_sheet_no }}</a></td>
                                    <td>{{ $sheet->costing_date->format('d M Y') }}</td>
                                    <td>{{ number_format($sheet->offer_price, 4) }}</td>
                                    <td>{{ $sheet->final_price !== null ? number_format($sheet->final_price, 4) : '-' }}</td>
                                    <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $sheet])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No cost sheet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Samples</h6>
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>No</th><th>Type</th><th>Rev</th><th>Required</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($style->samples as $sample)
                                <tr>
                                    <td><a href="{{ route('msfl.samples.show', $sample) }}">{{ $sample->sample_no }}</a></td>
                                    <td>{{ $sample->sampleType->name ?? '-' }}</td>
                                    <td>{{ $sample->revision_no }}</td>
                                    <td>{{ optional($sample->required_date)->format('d M Y') ?? '-' }}</td>
                                    <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $sample])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No sample.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Orders (PO lines)</h6>
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>Order</th><th>PO No</th><th>Color</th><th>Qty</th><th>Shipment</th></tr></thead>
                        <tbody>
                            @forelse($style->orderPos as $po)
                                <tr>
                                    <td><a href="{{ route('msfl.orders.show', $po->order_id) }}">{{ $po->order->order_no ?? '-' }}</a></td>
                                    <td>{{ $po->po_no }}</td>
                                    <td>{{ $po->color->name ?? '-' }}</td>
                                    <td>{{ number_format($po->po_qty) }}</td>
                                    <td>{{ optional($po->shipment_date)->format('d M Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No order yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>BOMs</h6>
                    <table class="table table-bordered table-sm align-middle">
                        <thead><tr><th>BOM No</th><th>Version</th><th>Order</th><th>Type</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($style->boms as $bom)
                                <tr>
                                    <td><a href="{{ route('msfl.boms.show', $bom) }}">{{ $bom->bom_no }}</a></td>
                                    <td>v{{ $bom->version }}</td>
                                    <td>{{ $bom->order->order_no ?? '-' }}</td>
                                    <td>{{ \ME\MerchandisingSfl\Models\Bom::TYPES[$bom->bom_type] ?? $bom->bom_type }}</td>
                                    <td>@include('merchandising-sfl::admin.partials.status-badge', ['model' => $bom])</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">No BOM.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
