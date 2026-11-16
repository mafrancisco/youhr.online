<?php

namespace App\Providers;

use App\Session\TenantAwareDatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SocialiteFactory::class, function ($app) {
            return new SocialiteManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Replace the built-in "database" session driver (SESSION_DRIVER=database) with one
        // that also stores the company id. Same table and connection as Laravel's driver,
        // so existing sessions keep working.
        Session::extend('database', function ($app) {
            return new TenantAwareDatabaseSessionHandler(
                $app['db']->connection($app['config']->get('session.connection')),
                $app['config']->get('session.table'),
                $app['config']->get('session.lifetime'),
                $app
            );
        });
    }
}
