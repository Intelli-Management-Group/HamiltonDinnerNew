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

use App\Repositories\Contracts\CategoryDetailRepositoryInterface;
use App\Repositories\Eloquent\CategoryDetailRepository;

use App\Repositories\Contracts\ItemDetailRepositoryInterface;
use App\Repositories\Eloquent\ItemDetailRepository;

use App\Repositories\Contracts\ItemOptionRepositoryInterface;
use App\Repositories\Eloquent\ItemOptionRepository;

use App\Repositories\Contracts\ItemPreferenceRepositoryInterface;
use App\Repositories\Eloquent\ItemPreferenceRepository;

use App\Repositories\Contracts\MenuDetailRepositoryInterface;
use App\Repositories\Eloquent\MenuDetailRepository;

use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use App\Repositories\Eloquent\RoomDetailRepository;

use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Eloquent\SettingRepository;

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
        $this->app->bind(CategoryDetailRepositoryInterface::class, CategoryDetailRepository::class);
        $this->app->bind(ItemDetailRepositoryInterface::class, ItemDetailRepository::class);
        $this->app->bind(ItemOptionRepositoryInterface::class, ItemOptionRepository::class);
        $this->app->bind(ItemPreferenceRepositoryInterface::class, ItemPreferenceRepository::class);
        $this->app->bind(MenuDetailRepositoryInterface::class, MenuDetailRepository::class);
        $this->app->bind(RoomDetailRepositoryInterface::class, RoomDetailRepository::class);
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);
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
