<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\UserRepository;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Eloquent\PermissionRepository;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Eloquent\RoleRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * This is where we bind interfaces to their implementations.
     * For example, we bind UserRepositoryInterface to UserRepository. 
     * Whenever the UserRepositoryInterface is needed, 
     * an instance of UserRepository will be provided.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
    }
}
