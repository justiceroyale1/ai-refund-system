<?php

namespace App\Providers;

use App\Http\CurrentCustomer;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentCustomer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::wrap('data');

        Gate::define('access-admin', fn (User $user): bool => $user->is_admin);

        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = Str::transliterate(Str::lower($request->string('email')->toString()));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
