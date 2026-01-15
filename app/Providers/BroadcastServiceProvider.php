<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
         // Broadcast::routes();
        //Broadcast::routes(['middleware' => ['auth:sanctum'],]);
         Broadcast::routes([
        'middleware' => ['auth:sanctum'],
        'prefix'     => 'api',   // نخليها تحت /api/broadcasting/auth
    ]);
        require base_path('routes/channels.php');
    }
}
