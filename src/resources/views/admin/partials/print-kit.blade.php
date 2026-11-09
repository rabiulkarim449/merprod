{{--
    Print styles for v2 pages on printMaster2 (which has no Bootstrap): page size, compact tables,
    the grid / badges / text helpers the screens use, and nothing interactive (buttons, forms' controls, modals).
    props: page (@page size)
--}}
@once
@push('css')
<style>
    @page { size: {{ $page ?? 'A4 portrait' }}; margin: 8mm; }
    .container { max-width: none; }
    .msfl-print-title { font-weight: 700; text-transform: uppercase; font-size: 15px; margin-top: 4px; }
    .msfl-print-title small { font-size: 11px; text-transform: none; font-weight: 400; }
    .msfl-print, .msfl-print table { font-size: 11px; }
    .msfl-print table { margin-bottom: 8px; }
    .msfl-print th, .msfl-print td { padding: 3px 5px; font-size: 11px; vertical-align: middle; }
    .msfl-print .table-responsive { overflow: visible; }
    .msfl-print a { color: inherit; text-decoration: none; }
    .msfl-print .row { display: flex; flex-wrap: wrap; margin: 0 -4px 6px; }
    .msfl-print [class*="col-"] { padding: 0 4px; flex: 0 0 100%; max-width: 100%; }
    .msfl-print .col-md-2 { flex-basis: 16.66%; max-width: 16.66%; } .msfl-print .col-md-3 { flex-basis: 25%; max-width: 25%; }
    .msfl-print .col-md-4 { flex-basis: 33.33%; max-width: 33.33%; } .msfl-print .col-md-6 { flex-basis: 50%; max-width: 50%; }
    .msfl-print .col-md-8 { flex-basis: 66.66%; max-width: 66.66%; } .msfl-print .col-md-9 { flex-basis: 75%; max-width: 75%; }
    .msfl-print .mb-2, .msfl-print .mb-3 { margin-bottom: 3px; } .msfl-print .mt-3 { margin-top: 8px; } .msfl-print .mb-0 { margin-bottom: 0; }
    .msfl-print .card { border: 0; } .msfl-print .card-body { padding: 0; }
    .msfl-print .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #999; margin: 8px 0 4px; }
    .msfl-module.msfl-print > .card:first-of-type > .card-header { display: none; }
    .msfl-print h4, .msfl-print h5, .msfl-print h6 { margin: 8px 0 4px; font-size: 13px; }
    .msfl-print .d-flex { display: flex; } .msfl-print .justify-content-between { justify-content: space-between; } .msfl-print .align-items-center { align-items: center; }
    .msfl-print .d-block { display: block; } .msfl-print .text-nowrap { white-space: nowrap; }
    .msfl-print .font-weight-bold, .msfl-print strong { font-weight: 700; }
    .msfl-print .small, .msfl-print small { font-size: 9.5px; }
    .msfl-print .text-muted { color: #666; } .msfl-print .text-danger { color: #b91c1c; } .msfl-print .text-success { color: #15803d; }
    .msfl-print .badge { display: inline-block; border: 1px solid #555; border-radius: 3px; padding: 0 4px; font-size: 9.5px; font-weight: 600; }
    .msfl-print .alert { border: 1px solid #bbb; padding: 4px 6px; margin: 4px 0; font-size: 10.5px; }
    .msfl-print .table-secondary td, .msfl-print .table-secondary th { background: #e5e7eb; }
    .msfl-print .table-success td, .msfl-print .table-success th { background: #dcfce7; }
    .msfl-print .table-danger td { background: #fee2e2; } .msfl-print .table-warning td { background: #fef3c7; }
    .msfl-print img { max-width: 100%; }
    /* Nothing to click on paper. */
    .msfl-print .btn, .msfl-print .btn-custom, .msfl-print button, .msfl-print .modal, .msfl-print form.d-inline, .msfl-print form.form-inline,
    .msfl-print input[type="file"], .msfl-print .msfl-no-print, .msfl-print form[method="GET"], .msfl-print .pagination, .msfl-print nav[role="navigation"] { display: none !important; }
    @media print { .msfl-print th, .msfl-print .badge, .msfl-print tr[class^="table-"] td { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
@endpush
@push('js')
<script>
    // Lists: drop the action column (its buttons are hidden on paper).
    document.querySelectorAll('.msfl-print table').forEach(function (table) {
        const head = table.querySelector('thead tr:last-child');
        if (! head) return;
        Array.from(head.children).forEach(function (th, i) {
            if (! ['actions', 'action', 'entry'].includes(th.textContent.trim().toLowerCase())) return;
            table.querySelectorAll('tr').forEach(function (tr) {
                const cell = tr.children[i];
                if (cell && tr.children.length === head.children.length) cell.style.display = 'none';
            });
        });
    });
</script>
@endpush
@endonce
