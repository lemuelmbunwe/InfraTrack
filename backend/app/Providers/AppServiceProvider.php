<?php

namespace App\Providers;

use App\Models\Issue;
use App\Models\Assignment;
use App\Policies\AssignmentPolicy;
use App\Policies\IssuePolicy;
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
        Gate::policy(Assignment::class, AssignmentPolicy::class);
        Gate::policy(Issue::class, IssuePolicy::class);
    }
}
