<?php

namespace App\Providers;

use App\Contracts\DictionaryProvider;
use App\Models\Admin;
use App\Models\Permission;
use App\Policies\AdminPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Services\Dictionary\FreeDictionaryProvider;
use App\Services\Dictionary\MerriamWebsterProvider;
use App\Support\AdminMutations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DictionaryProvider::class, function () {
            return match (config('services.dictionary.provider', 'free_dictionary')) {
                'merriam_webster' => app(MerriamWebsterProvider::class),
                default => app(FreeDictionaryProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::policy(Admin::class, AdminPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::before(fn ($account) => $account instanceof Admin && AdminMutations::isSuper($account) ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
