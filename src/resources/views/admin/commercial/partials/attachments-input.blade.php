{{--
    Several document files on one record. props: name (field / column), label, value (column value), col.
    Posts {name}[] (new files) and remove_{name}[] (paths of current files to drop) — see FileUploadService::storeList().
--}}
@php $currentFiles = \ME\MerchandisingSfl\Services\FileUploadService::list($value ?? null); @endphp
<div class="col-md-{{ $col ?? 6 }} mb-3">
    <label class="form-label">{{ $label }}</label>
    <input type="file" name="{{ $name }}[]" class="form-control form-control-sm" multiple>
    <small class="text-muted d-block">Select one or more files (PDF, image, Excel, Word, ZIP — max 20 MB each).</small>
    @if($currentFiles)
        <ul class="list-unstyled mb-0 mt-2 small">
            @foreach($currentFiles as $file)
                <li class="d-flex align-items-center gap-2 mb-1">
                    <a href="{{ \ME\MerchandisingSfl\Services\FileUploadService::link($file['path']) }}" target="_blank" class="text-truncate"><i class="fa-solid fa-paperclip"></i> {{ $file['name'] }}</a>
                    <label class="mb-0 ml-auto text-danger font-weight-normal text-nowrap">
                        <input type="checkbox" name="remove_{{ $name }}[]" value="{{ $file['path'] }}"> Remove
                    </label>
                </li>
            @endforeach
        </ul>
    @endif
    @error($name . '.*')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>
