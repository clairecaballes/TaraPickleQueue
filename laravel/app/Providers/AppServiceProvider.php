<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Court management (call next-up, confirm calls, complete matches,
        // reorder the queue, add/remove players) is restricted to admins.
        Gate::define('manage-court', fn (User $user) => (bool) $user->is_admin);
    }
}
