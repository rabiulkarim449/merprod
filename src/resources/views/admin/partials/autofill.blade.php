{{--
    Fill a form from what an earlier step already knows (Support/Autofill).
    props: source (field name of the picked select, e.g. inquiry_id),
           map (id => [key => value]),
           fields ([target field name => map key]; name may repeat the key)
    On picking the source, each target field in the same form is filled when it
    is empty or still holds the value this same source filled in before — anything
    typed by hand (or filled from another pick) is kept. Nothing changes when the page opens (edits keep their data).
--}}
@push('js')
<script>
(function () {
    const source = @json($source);
    const map = @json($map);
    const fields = @json($fields);

    function fill(src) {
        const row = map[src.value];
        const form = src.closest('form') || document;
        if (! row) return;
        Object.keys(fields).forEach(function (name) {
            const value = row[fields[name]];
            const el = form.querySelector('[name="' + name + '"]');
            if (value === undefined || value === null || ! el || el === src) return;
            // Fill only an empty field, or one this same source filled before (hand-typed values and
            // values filled from another pick are kept).
            const current = String(el.value ?? '');
            if (current !== '' && ! (el.dataset.autofilledBy === source && current === (el.dataset.autofilled ?? ''))) return;
            if (el.tagName === 'SELECT' && ! Array.from(el.options).some(o => o.value === String(value))) return;
            el.value = String(value);
            el.dataset.autofilled = String(value);
            el.dataset.autofilledBy = source;
            el.classList.add('msfl-autofilled');
            setTimeout(() => el.classList.remove('msfl-autofilled'), 1500);
            // Selects: refresh select2 and let chained fills run; inputs: let totals recalc.
            if (el.tagName === 'SELECT' && typeof $ !== 'undefined') { $(el).trigger('change'); }
            else { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
        });
    }

    function bind() {
        document.querySelectorAll('[name="' + source + '"]').forEach(function (src) {
            if (src.dataset.autofillBound?.split(',').includes(source + JSON.stringify(Object.keys(fields)))) return;
            src.dataset.autofillBound = (src.dataset.autofillBound ? src.dataset.autofillBound + ',' : '') + source + JSON.stringify(Object.keys(fields));
            if (typeof $ !== 'undefined') { $(src).on('change', () => fill(src)); } else { src.addEventListener('change', () => fill(src)); }
        });
    }
    document.addEventListener('DOMContentLoaded', bind);
    if (document.readyState !== 'loading') bind();
})();
</script>
@endpush
@once
    @push('css')
    <style>
        .msfl-autofilled, .msfl-autofilled + .select2 .select2-selection { background: #e8f7ee !important; transition: background .3s; }
    </style>
    @endpush
@endonce
