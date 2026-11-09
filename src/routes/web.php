<?php

use Illuminate\Support\Facades\Route;
use ME\MerchandisingSfl\Http\Controllers\BomController;
use ME\MerchandisingSfl\Http\Controllers\BulletinController;
use ME\MerchandisingSfl\Http\Controllers\Commercial;
use ME\MerchandisingSfl\Http\Controllers\CostSheetController;
use ME\MerchandisingSfl\Http\Controllers\DashboardController;
use ME\MerchandisingSfl\Http\Controllers\InquiryController;
use ME\MerchandisingSfl\Http\Controllers\InventorySyncController;
use ME\MerchandisingSfl\Http\Controllers\LearningCurveController;
use ME\MerchandisingSfl\Http\Controllers\LineController;
use ME\MerchandisingSfl\Http\Controllers\MasterController;
use ME\MerchandisingSfl\Http\Controllers\OrderController;
use ME\MerchandisingSfl\Http\Controllers\OrderPoController;
use ME\MerchandisingSfl\Http\Controllers\PostCostingController;
use ME\MerchandisingSfl\Http\Controllers\ReportController;
use ME\MerchandisingSfl\Services\Reports;
use ME\MerchandisingSfl\Http\Controllers\Production\CuttingController;
use ME\MerchandisingSfl\Http\Controllers\Production\EntryController;
use ME\MerchandisingSfl\Http\Controllers\Production\FabricRequisitionController;
use ME\MerchandisingSfl\Http\Controllers\Production\QcReworkController;
use ME\MerchandisingSfl\Http\Controllers\Production\SewingController;
use ME\MerchandisingSfl\Http\Controllers\Production\StatusController;
use ME\MerchandisingSfl\Services\ProductionFlow;
use ME\MerchandisingSfl\Http\Controllers\SampleController;
use ME\MerchandisingSfl\Http\Controllers\StyleController;
use ME\MerchandisingSfl\Http\Controllers\TnaPlanController;
use ME\MerchandisingSfl\Http\Controllers\TnaSheetController;
use ME\MerchandisingSfl\Http\Controllers\TnaTemplateController;
use ME\MerchandisingSfl\Support\MasterRegistry;

$route = config('merchandising-sfl.route');

Route::middleware($route['middleware'] ?? ['web', 'auth'])
    ->prefix($route['prefix'] ?? 'admin/merchandising-sfl')
    ->name($route['as'] ?? 'msfl.')
    ->group(function () {
        // Master Data — every screen in MasterRegistry (buyers, seasons, colors, ...).
        Route::prefix('masters/{master}')
            ->whereIn('master', array_keys(MasterRegistry::all()))
            ->name('masters.')
            ->group(function () {
                Route::get('/', [MasterController::class, 'index'])->name('index');
                Route::post('/', [MasterController::class, 'store'])->name('store');
                Route::put('{id}', [MasterController::class, 'update'])->whereNumber('id')->name('update');
                Route::delete('{id}', [MasterController::class, 'destroy'])->whereNumber('id')->name('destroy');
            });

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Pull existing Inventory Buyers / Styles into Merchandising's own masters.
        Route::get('inventory-sync', [InventorySyncController::class, 'index'])->name('inventory-sync.index');
        Route::post('inventory-sync', [InventorySyncController::class, 'sync'])->name('inventory-sync.sync');

        // Uploaded files (tech packs, BOM files, attachments, images) — served from the upload disk.
        Route::get('files/{path}', [\ME\MerchandisingSfl\Http\Controllers\FileController::class, 'show'])->where('path', '.*')->name('files.show');

        // Dev (R&D)
        Route::resource('inquiries', InquiryController::class);

        Route::resource('styles', StyleController::class);
        Route::post('styles/{style}/images', [StyleController::class, 'storeImage'])->name('styles.images.store');
        Route::delete('styles/{style}/images/{image}', [StyleController::class, 'destroyImage'])->name('styles.images.destroy');

        Route::resource('cost-sheets', CostSheetController::class);
        Route::post('cost-sheets/{cost_sheet}/approve', [CostSheetController::class, 'approve'])->name('cost-sheets.approve');

        // Order + BOM
        Route::resource('orders', OrderController::class);
        Route::post('orders/{order}/status', [OrderController::class, 'changeStatus'])->name('orders.status');
        Route::post('orders/{order}/pos', [OrderPoController::class, 'store'])->name('orders.pos.store');
        Route::put('orders/{order}/pos/{po}', [OrderPoController::class, 'update'])->name('orders.pos.update');
        Route::delete('orders/{order}/pos/{po}', [OrderPoController::class, 'destroy'])->name('orders.pos.destroy');

        Route::get('post-costing', [PostCostingController::class, 'index'])->name('post-costing.index');
        Route::get('post-costing/{po}', [PostCostingController::class, 'show'])->name('post-costing.show');
        Route::get('post-costing/{po}/print', [PostCostingController::class, 'print'])->name('post-costing.print');

        Route::resource('boms', BomController::class);
        Route::post('boms/{bom}/approve', [BomController::class, 'approve'])->name('boms.approve');
        Route::post('boms/{bom}/revise', [BomController::class, 'revise'])->name('boms.revise');

        // Sample Stages
        Route::resource('samples', SampleController::class);
        Route::post('samples/{sample}/status', [SampleController::class, 'changeStatus'])->name('samples.status');
        Route::post('samples/{sample}/submit', [SampleController::class, 'submit'])->name('samples.submit');
        Route::post('samples/{sample}/decide', [SampleController::class, 'decide'])->name('samples.decide');
        Route::post('samples/{sample}/comments', [SampleController::class, 'storeComment'])->name('samples.comments.store');

        // Planning — setup (machine types, operations, holidays are in MasterRegistry)
        Route::resource('lines', LineController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('tna-templates', TnaTemplateController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);

        // Planning — bulletin
        Route::resource('bulletins', BulletinController::class);
        Route::get('bulletins/{bulletin}/print', [BulletinController::class, 'print'])->name('bulletins.print');
        Route::post('bulletins/{bulletin}/approve', [BulletinController::class, 'approve'])->name('bulletins.approve');
        Route::post('bulletins/{bulletin}/revise', [BulletinController::class, 'revise'])->name('bulletins.revise');

        // Planning — Learning Curve
        Route::get('learning-curve', [LearningCurveController::class, 'index'])->name('learning-curve.index');
        Route::put('learning-curve/{learning_curve_band}', [LearningCurveController::class, 'update'])->name('learning-curve.update');
        Route::put('learning-curve/lead-times/{lead_time}', [LearningCurveController::class, 'leadTimeUpdate'])->name('learning-curve.lead-times.update');
        Route::get('learning-curve/calculate', [LearningCurveController::class, 'calculate'])->name('learning-curve.calculate');

        // Planning — T&A
        Route::resource('tna', TnaPlanController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::put('tna/{tna}/tasks', [TnaPlanController::class, 'updateTasks'])->name('tna.tasks.update');
        Route::post('tna/{tna}/recalculate', [TnaPlanController::class, 'recalculate'])->name('tna.recalculate');
        Route::post('tna/{tna}/status', [TnaPlanController::class, 'changeStatus'])->name('tna.status');
        Route::get('tna-sheet', [TnaSheetController::class, 'index'])->name('tna-sheet.index');
        Route::get('tna-sheet/print', [TnaSheetController::class, 'print'])->name('tna-sheet.print');

        // Reports
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->whereIn('report', array_keys(Reports::LIST))->name('reports.show');
        Route::get('reports/{report}/print', [ReportController::class, 'print'])->whereIn('report', array_keys(Reports::LIST))->name('reports.print');

        // Operations reports (Excel-template layouts with typed cells)
        $opsKeys = array_keys(\ME\MerchandisingSfl\Support\OpsReports\OpsReports::LIST);
        Route::get('ops-reports', [\ME\MerchandisingSfl\Http\Controllers\OpsReportController::class, 'index'])->name('ops-reports.index');
        Route::get('ops-reports/{report}', [\ME\MerchandisingSfl\Http\Controllers\OpsReportController::class, 'show'])->whereIn('report', $opsKeys)->name('ops-reports.show');
        Route::post('ops-reports/{report}', [\ME\MerchandisingSfl\Http\Controllers\OpsReportController::class, 'save'])->whereIn('report', $opsKeys)->name('ops-reports.save');
        Route::get('ops-reports/{report}/print', [\ME\MerchandisingSfl\Http\Controllers\OpsReportController::class, 'print'])->whereIn('report', $opsKeys)->name('ops-reports.print');

        // Commercial — Export LC / SC → Commercial Invoice (+ packing list)
        Route::prefix('commercial')->name('commercial.')->group(function () {
            Route::get('dashboard', [Commercial\DashboardController::class, 'index'])->name('dashboard');
            Route::resource('export-lcs', Commercial\ExportLcController::class);
            Route::post('export-lcs/{export_lc}/status', [Commercial\ExportLcController::class, 'changeStatus'])->name('export-lcs.status');
            Route::resource('invoices', Commercial\InvoiceController::class);
            Route::get('invoices/{invoice}/print/{doc}', [Commercial\InvoiceController::class, 'print'])->whereIn('doc', ['invoice', 'packing'])->name('invoices.print');

            // Record-keeping screens (CommercialRecordRegistry): Supplier PI, BTB LC, Import Shipment,
            // Export Documents, Bank Realization — each with its own route names.
            foreach (array_keys(\ME\MerchandisingSfl\Support\CommercialRecordRegistry::all()) as $record) {
                Route::prefix($record)->name("$record.")->group(function () use ($record) {
                    Route::get('/', [Commercial\RecordController::class, 'index'])->defaults('record', $record)->name('index');
                    Route::get('create', [Commercial\RecordController::class, 'create'])->defaults('record', $record)->name('create');
                    Route::post('/', [Commercial\RecordController::class, 'store'])->defaults('record', $record)->name('store');
                    Route::get('{id}', [Commercial\RecordController::class, 'show'])->defaults('record', $record)->whereNumber('id')->name('show');
                    Route::get('{id}/edit', [Commercial\RecordController::class, 'edit'])->defaults('record', $record)->whereNumber('id')->name('edit');
                    Route::put('{id}', [Commercial\RecordController::class, 'update'])->defaults('record', $record)->whereNumber('id')->name('update');
                    Route::delete('{id}', [Commercial\RecordController::class, 'destroy'])->defaults('record', $record)->whereNumber('id')->name('destroy');
                });
            }
        });

        // Production — fabric requisition → cutting → stage entries (pcs + QC)
        Route::prefix('production')->name('production.')->group(function () {
            Route::get('status', [StatusController::class, 'index'])->name('status.index');
            Route::get('status/{po}', [StatusController::class, 'show'])->name('status.show');

            Route::resource('requisitions', FabricRequisitionController::class)->only(['index', 'create', 'store', 'show']);
            Route::resource('cuttings', CuttingController::class)->only(['index', 'create', 'store', 'show', 'destroy']);

            // Final Packing List (buyer PO, after floor packing → buyer approval)
            Route::resource('final-packing-lists', \ME\MerchandisingSfl\Http\Controllers\Production\FinalPackingListController::class);
            Route::post('final-packing-lists/{final_packing_list}/approve', [\ME\MerchandisingSfl\Http\Controllers\Production\FinalPackingListController::class, 'approve'])->name('final-packing-lists.approve');
            Route::post('final-packing-lists/{final_packing_list}/reopen', [\ME\MerchandisingSfl\Http\Controllers\Production\FinalPackingListController::class, 'reopen'])->name('final-packing-lists.reopen');

            // QC (step-wise, not Buyer QC) and Rework — a card per step, then that step's form.
            foreach (['qc', 'rework'] as $kind) {
                Route::prefix($kind)->name("$kind.")->group(function () use ($kind) {
                    Route::get('/', [QcReworkController::class, 'index'])->defaults('kind', $kind)->name('index');
                    Route::get('create', [QcReworkController::class, 'create'])->defaults('kind', $kind)->name('create');
                    Route::post('/', [QcReworkController::class, 'store'])->defaults('kind', $kind)->name('store');
                    Route::delete('{entry}', [QcReworkController::class, 'destroy'])->defaults('kind', $kind)->whereNumber('entry')->name('destroy');
                });
            }

            // Daily Hourly Production Report (from the sewing board's figures).
            Route::get('sewing/hourly-report', [SewingController::class, 'hourlyReport'])->name('hourly-report.index');
            Route::get('sewing/hourly-report/print', [SewingController::class, 'hourlyReport'])->name('hourly-report.print');

            // Sewing, line by line: board (Daily Production), line input, hourly output.
            // Registered before {stage} so production/sewing opens the board.
            Route::prefix('sewing')->name('sewing.')->group(function () {
                Route::get('/', [SewingController::class, 'index'])->name('index');
                Route::get('print', [SewingController::class, 'print'])->name('print');
                Route::get('input', [SewingController::class, 'inputForm'])->name('input');
                Route::post('input', [SewingController::class, 'storeInput'])->name('input.store');
                Route::get('hourly', [SewingController::class, 'hourlyForm'])->name('hourly');
                Route::post('hourly', [SewingController::class, 'storeHourly'])->name('hourly.store');
                Route::get('entries', [EntryController::class, 'index'])->defaults('stage', 'sewing')->name('entries');
            });

            Route::prefix('{stage}')->whereIn('stage', array_keys(ProductionFlow::STAGES))->name('entries.')->group(function () {
                Route::get('/', [EntryController::class, 'index'])->name('index');
                Route::get('create', [EntryController::class, 'create'])->name('create');
                Route::post('/', [EntryController::class, 'store'])->name('store');
                Route::get('{entry}/edit', [EntryController::class, 'edit'])->whereNumber('entry')->name('edit');
                Route::put('{entry}', [EntryController::class, 'update'])->whereNumber('entry')->name('update');
                Route::delete('{entry}', [EntryController::class, 'destroy'])->whereNumber('entry')->name('destroy');
            });
        });
    });
