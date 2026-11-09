{{--
    Buttons, badges, tables, forms, select2, filter bars and pagination are styled once for the
    whole admin in the host's public/admin/assets/css/ui.css — only module-specific bits live here.
    Shared UI for Merchandising (SFL) pages — matches the HR module on the host's
    Bootstrap 4 theme: plain .card, .table-bordered.table-sm, small buttons,
    and icon-only row actions as .btn-custom (yellow = edit, danger = delete,
    success = view/approve, primary = print/label).
--}}
<style>
    .form-label { margin-bottom: .25rem; }
    .msfl-module .table td, .msfl-module .table th { vertical-align: middle; }
    /* Row-action cells stay on one line. */
    .msfl-module .table td.text-right:last-child { white-space: nowrap; }
    .msfl-module .table td:last-child > form { display: inline-block; }
    /* Popups: medium (Bootstrap md, 500px) everywhere so they fit small monitors, above the host sidebar;
       two fields per row inside, full-width fields stay full, wide tables scroll sideways. */
    .modal { z-index: 1100; } .modal-backdrop { z-index: 1090; }
    .modal .modal-body { overflow-x: auto; }
    .modal .modal-body .row > [class*="col-"] { flex: 0 0 50% !important; max-width: 50% !important; }
    .modal .modal-body .row > .col-12, .modal .modal-body .row > .col-md-12, .modal .modal-body .row > .col-lg-12 { flex: 0 0 100% !important; max-width: 100% !important; }
</style>

@include('merchandising-sfl::admin.partials.page-help')
