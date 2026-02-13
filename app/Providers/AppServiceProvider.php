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

use App\Repositories\Contracts\DateWiseOccupancyRepositoryInterface;
use App\Repositories\Eloquent\DateWiseOccupancyRepository;

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

use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Eloquent\OrderDetailRepository;

use App\Repositories\Contracts\Forms\FormMediaAttachmentsRepositoryInterface;
use App\Repositories\Eloquent\Forms\FormMediaAttachmentsRepository;

use App\Repositories\Contracts\Forms\FormResponseRepositoryInterface;
use App\Repositories\Eloquent\Forms\FormResponseRepository;

use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use App\Repositories\Eloquent\Forms\FormTypeRepository;

use App\Repositories\Contracts\Forms\MoveInSummaryValuesRepositoryInterface;
use App\Repositories\Eloquent\Forms\MoveInSummaryValuesRepository;

use App\Repositories\Contracts\Reports\ChargeReportRepositoryInterface;
use App\Repositories\Eloquent\Reports\ChargeReportRepository;

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
    $this->app->bind(DateWiseOccupancyRepositoryInterface::class, DateWiseOccupancyRepository::class);
        $this->app->bind(ItemDetailRepositoryInterface::class, ItemDetailRepository::class);
        $this->app->bind(ItemOptionRepositoryInterface::class, ItemOptionRepository::class);
        $this->app->bind(ItemPreferenceRepositoryInterface::class, ItemPreferenceRepository::class);
        $this->app->bind(MenuDetailRepositoryInterface::class, MenuDetailRepository::class);
        $this->app->bind(RoomDetailRepositoryInterface::class, RoomDetailRepository::class);
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);
        $this->app->bind(OrderDetailRepositoryInterface::class, OrderDetailRepository::class);

        // Forms Repositories
        $this->app->bind(FormMediaAttachmentsRepositoryInterface::class, FormMediaAttachmentsRepository::class);
        $this->app->bind(FormResponseRepositoryInterface::class, FormResponseRepository::class);
        $this->app->bind(FormTypeRepositoryInterface::class, FormTypeRepository::class);
        $this->app->bind(MoveInSummaryValuesRepositoryInterface::class, MoveInSummaryValuesRepository::class);

        // Report Repositories
        $this->app->bind(ChargeReportRepositoryInterface::class, ChargeReportRepository::class);
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
