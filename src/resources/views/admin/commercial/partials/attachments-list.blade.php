{{-- A record's document files as links. props: value (column value), label. --}}
@php $listedFiles = \ME\MerchandisingSfl\Services\FileUploadService::list($value ?? null); @endphp
@if($listedFiles)
    <div class="col-md-12 mb-2">
        <strong>{{ $label }} ({{ count($listedFiles) }}):</strong>
        @foreach($listedFiles as $file)
            <a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($file['path']) }}" target="_blank" class="badge badge-light mr-1"><i class="fa-solid fa-paperclip"></i> {{ $file['name'] }}</a>
        @endforeach
    </div>
@endif
