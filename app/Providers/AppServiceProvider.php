<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Services\BarcodeService;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BarcodeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            $request = $event->request;
            $response = $event->response;

            if (! $request->is('api/*')) {
                return;
            }

            $route = $request->route();

            $payload = collect($request->except(['password', 'password_confirmation', 'otp']))
                ->map(function (mixed $value): mixed {
                    if (is_scalar($value) || $value === null) {
                        return $value;
                    }

                    return is_array($value) ? $value : (string) $value;
                })
                ->all();

            $auditableId = 0;
            foreach (($route?->parameters() ?? []) as $parameter) {
                if (is_object($parameter) && method_exists($parameter, 'getKey')) {
                    $auditableId = (int) $parameter->getKey();
                    break;
                }

                if (is_numeric($parameter)) {
                    $auditableId = (int) $parameter;
                    break;
                }
            }

            try {
                AuditLog::query()->create([
                    'user_id' => Auth::id(),
                    'action' => $request->method().' '.$request->path(),
                    'auditable_type' => $route?->getActionName() ?? 'unknown',
                    'auditable_id' => $auditableId,
                    'old_values' => $payload,
                    'new_values' => [
                        'status_code' => $response->getStatusCode(),
                        'route_name' => $route?->getName(),
                    ],
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'performed_at' => now(),
                ]);
            } catch (Throwable) {
            }
        });
    }
}
