<?php

namespace App\Providers;

use App\Models\Transfer;
use App\Models\Waste;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
        Relation::morphMap([
            'transfers' => Transfer::class,
            'wastes' => Waste::class,
        ]);
    }
}
