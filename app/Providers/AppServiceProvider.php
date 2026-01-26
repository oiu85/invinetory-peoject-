<?php

namespace App\Providers;

use App\Events\SaleCreated;
use App\Events\StockOrderCreated;
use App\Events\StockOrderStatusChanged;
use App\Listeners\NotifyAdminOnSale;
use App\Listeners\NotifyAdminOnOrderCreated;
use App\Listeners\NotifyDriverOnOrderStatusChange;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        SaleCreated::class => [
            NotifyAdminOnSale::class,
        ],
        StockOrderCreated::class => [
            NotifyAdminOnOrderCreated::class,
        ],
        StockOrderStatusChanged::class => [
            NotifyDriverOnOrderStatusChange::class,
        ],
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
