<?php

namespace App\Providers;

use App\Http\Interfaces\Admin\CodeInterface;
use App\Http\Interfaces\Admin\DueInterface;
use App\Http\Interfaces\Admin\PackageInterface;
use App\Http\Interfaces\Admin\SubscribeInterface;
use App\Http\Repositories\Admin\CodeRepository;
use App\Http\Repositories\Admin\DueRepository;
use App\Http\Repositories\Admin\PackageRepository;
use App\Http\Repositories\Admin\SubscribeRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CodeInterface::class, CodeRepository::class);
        $this->app->bind(DueInterface::class, DueRepository::class);
        $this->app->bind(PackageInterface::class, PackageRepository::class);
        $this->app->bind(SubscribeInterface::class, SubscribeRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
