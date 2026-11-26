<?php

return [
    App\Providers\AppServiceProvider::class,
];

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.env') !== 'local' || getenv('VERCEL')) {
            URL::forceScheme('https');
        }
    }
}