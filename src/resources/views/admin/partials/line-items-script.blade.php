{{--
    Generic repeatable line-items table behavior, reused by every form with
    an items[] array (Cost Sheet, BOM). Same contract as production-trace's
    line-items-script.blade.php.

    Markup contract:
    - A <template id="{prefix}RowTemplate"> containing one <tr> of inputs
      named items[__INDEX__][field].
    - A <tbody id="{prefix}RowsBody"> to append rows into.
    - A button [data-line-items-add="{prefix}"] to add a row.
    - Row remove buttons: [data-line-items-remove] inside each row.
    - Optional reorder buttons: [data-line-items-up] / [data-line-items-down]
      (rows are saved in their on-screen order).
    A "msfl:rows-changed" event fires on the tbody after a row is added/removed.
--}}
@push('js')
<script>
    (function () {
        let lineItemRowIndex = 1000000; // always-increasing, never reused

        function bindRow(row) {
            const removeBtn = row.querySelector('[data-line-items-remove]');
            removeBtn?.addEventListener('click', function () {
                const body = row.closest('tbody');
                if (body.querySelectorAll('tr').length > 1) {
                    row.remove();
                    body.dispatchEvent(new CustomEvent('msfl:rows-changed', { bubbles: true }));
                }
            });
            msflSelect2Init(row);
        }

        document.querySelectorAll('[data-line-items-add]').forEach(function (btn) {
            const prefix = btn.getAttribute('data-line-items-add');
            btn.addEventListener('click', function () {
                const template = document.getElementById(prefix + 'RowTemplate');
                const body = document.getElementById(prefix + 'RowsBody');
                const html = template.innerHTML.replaceAll('__INDEX__', lineItemRowIndex++);
                const tempTable = document.createElement('table');
                tempTable.innerHTML = '<tbody>' + html + '</tbody>';
                const row = tempTable.querySelector('tr');
                body.appendChild(row);
                bindRow(row);
                body.dispatchEvent(new CustomEvent('msfl:rows-changed', { bubbles: true, detail: { row: row } }));
            });
        });

        document.querySelectorAll('tbody[id$="RowsBody"] tr').forEach(bindRow);

        document.addEventListener('click', function (e) {
            const up = e.target.closest('[data-line-items-up]');
            const down = e.target.closest('[data-line-items-down]');
            const row = (up || down)?.closest('tr');
            if (! row) return;
            if (up && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
            if (down && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
            row.closest('tbody').dispatchEvent(new CustomEvent('msfl:rows-changed', { bubbles: true }));
        });
    })();
</script>
@endpush
