{{--
    Size rows of a sewing modal, built from the picked PO.
    props: prefix, sizeRows (po => size => figures), sizeNames, fields ([name => label]), info (JS returning the balance text), mine (bool)
--}}
@push('js')
<script>
(function () {
    const prefix = @json($prefix);
    const sizeRows = @json($sizeRows);
    const sizeNames = @json($sizeNames);
    const fields = @json($fields);
    const oldSizes = @json($mine ? old('sizes', []) : []);
    const errors = @json($mine ? collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'sizes.'))->map(fn ($m) => $m[0]) : []);
    const poSelect = document.getElementById(prefix + 'Po');
    const body = document.getElementById(prefix + 'SizeRows');
    const form = document.getElementById(prefix + 'Form');
    const cols = 2 + Object.keys(fields).length;
    const info = function (r) { {!! $info !!} };

    function build() {
        const rows = sizeRows[poSelect.value] || {};
        body.innerHTML = '';
        if (! Object.keys(rows).length) {
            body.innerHTML = '<tr><td colspan="' + cols + '" class="text-center text-muted">Pick a style · color to see its sizes.</td></tr>';
            return sums();
        }
        Object.keys(rows).forEach(function (id) {
            const tr = document.createElement('tr');
            let html = '<td><strong>' + (sizeNames[id] || id) + '</strong></td><td class="small">' + info(rows[id]) + '</td>';
            Object.keys(fields).forEach(function (f) {
                html += '<td><input type="number" min="0" step="1" name="sizes[' + id + '][' + f + ']" value="' + ((oldSizes[id] || {})[f] || '') + '" class="form-control form-control-sm" data-sum="' + f + '"></td>';
            });
            tr.innerHTML = html;
            body.appendChild(tr);
            if (errors['sizes.' + id]) {
                const er = document.createElement('tr');
                er.innerHTML = '<td colspan="' + cols + '" class="text-danger small py-1">' + errors['sizes.' + id] + '</td>';
                body.appendChild(er);
            }
        });
        sums();
    }

    function sums() {
        Object.keys(fields).forEach(function (f) {
            let t = 0;
            body.querySelectorAll('[data-sum="' + f + '"]').forEach(i => t += parseInt(i.value || 0, 10));
            const el = form.querySelector('[data-total="' + f + '"]');
            if (el) el.textContent = t;
        });
    }

    body.addEventListener('input', sums);
    $(poSelect).on('change', build);
    build();
})();
</script>
@endpush
