<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Model::preventLazyLoading(! app()->isProduction());

        // Audit-row actors point into either the consumers or the officials
        // table; the morph map keeps those type strings stable and readable.
        Relation::enforceMorphMap([
            'consumer' => Consumer::class,
            'official' => User::class,
        ]);

        View::composer('components.layouts.portal', function (ViewContract $view): void {
            $official = auth()->user();
            $headerNotifications = collect();
            $unreadNotificationCount = 0;

            if ($official instanceof User && in_array($official->role, [UserRole::RegionalManager, UserRole::CompetentAuthority], true)) {
                $unreadNotificationCount = $official->unreadNotifications()->count();
                $headerNotifications = $official->unreadNotifications()
                    ->latest()
                    ->limit(5)
                    ->get();
            } elseif ($consumer = auth('consumer')->user()) {
                $unreadNotificationCount = $consumer->unreadNotifications()->count();
                $headerNotifications = $consumer->unreadNotifications()
                    ->latest()
                    ->limit(5)
                    ->get();
            }

            $view->with('headerNotifications', $headerNotifications);
            $view->with('unreadNotificationCount', $unreadNotificationCount);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip(),
            ));
        });

        RateLimiter::for('otp-request', function (Request $request) {
            return Limit::perMinute(2)->by(Str::transliterate(
                Str::lower((string) $request->input('consumer_number')).'|'.$request->ip(),
            ));
        });
    }
}
