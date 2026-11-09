<?php

namespace ME\MerchandisingSfl;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class MerchandisingSflServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'merchandising-sfl');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([Console\Commands\Overview::class]);
        }

        $this->mergeSidebar();
        $this->mergePermissions();
        $this->registerApprovalModules();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'merchandising-sfl');
    }

    private function mergeSidebar(): void
    {
        if (! file_exists($sidebar = __DIR__ . '/Config/sidebar.php')) {
            return;
        }

        // Sidebar entries are a numeric array — must array_merge, not mergeConfigFrom.
        Config::set('sidebar', array_merge(
            config('sidebar', []),
            require $sidebar
        ));
    }

    /** Buyer and sample decisions on the host's central Approvals page. */
    private function registerApprovalModules(): void
    {
        if (! interface_exists(\App\Contracts\ApprovalHandlerInterface::class)) {
            return;
        }

        Config::set('approval.modules', array_merge([
            Models\Buyer::APPROVAL_MODULE => Approvals\BuyerApprovalHandler::class,
            Services\SampleDecision::MODULE => Approvals\SampleApprovalHandler::class,
        ], config('approval.modules', [])));
    }

    private function mergePermissions(): void
    {
        if (! file_exists($file = __DIR__ . '/Config/permission.php')) {
            return;
        }

        $permissions = require $file;
        $main = config('permission', []);
        $main['modules'] = $main['modules'] ?? [];

        // The host's config/permission.php nests every group under a top-level
        // 'modules' key — both the Roles Setup screen and hasPermission() read
        // it from there (same as ProductionTraceServiceProvider).
        foreach ($permissions as $group => $modules) {
            $main['modules'][$group] = $modules;
        }

        Config::set('permission', $main);
    }
}
