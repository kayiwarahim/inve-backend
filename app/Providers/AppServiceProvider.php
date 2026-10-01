<?php

namespace App\Providers;

use App\Services\BarcodeService;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    private const MAX_ACTIVITY_LOG_STRING_LENGTH = 2000;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->singleton(BarcodeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login-account', function (Request $request): Limit {
            $email = $request->input('email');

            return Limit::perMinute(5)->by(hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : 'invalid'));
        });
        RateLimiter::for('mfa-management', fn (Request $request): array => [
            Limit::perMinute(5)->by('user:'.$request->user()->id),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);

        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            $request = $event->request;
            $response = $event->response;

            if (! $request->is('api/*')) {
                return;
            }

            $route = $request->route();

            $payload = $this->sanitizeForActivityLog($request->all());

            $auditable = null;
            $auditableId = null;

            foreach (($route?->parameters() ?? []) as $parameter) {
                if ($parameter instanceof Model) {
                    $auditable = $parameter;
                    $auditableId = $parameter->getKey();

                    break;
                }

                if (is_numeric($parameter)) {
                    $auditableId = (int) $parameter;
                    break;
                }
            }

            try {
                $causer = $request->user();

                $logger = activity('audit')
                    ->event('request')
                    ->createdAt(now())
                    ->withProperties($this->activityProperties($request, $response, $route, $auditable, $auditableId, $causer, $payload));

                if ($auditable instanceof Model) {
                    $logger->performedOn($auditable);
                }

                if ($causer instanceof Model) {
                    $logger->causedBy($causer);
                }

                $logger->log($request->method().' '.$request->path());
            } catch (Throwable) {
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function activityProperties(
        Request $request,
        Response $response,
        ?Route $route,
        ?Model $auditable,
        mixed $auditableId,
        mixed $causer,
        array $payload
    ): array {
        $userAgent = Str::limit((string) $request->userAgent(), 255, '');
        $responseDetails = $this->responseDetails($response);

        return [
            'route_action' => $route?->getActionName() ?? 'unknown',
            'route_name' => $route?->getName(),
            'auditable_id' => $auditableId,
            'request_payload' => $payload,
            'response' => $responseDetails,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'request' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'url' => $request->url(),
                'query' => $this->sanitizeForActivityLog($request->query()),
                'payload' => $payload,
                'headers' => $this->sanitizedHeaders($request),
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent,
            ],
            'route' => [
                'name' => $route?->getName(),
                'uri' => $route?->uri(),
                'action' => $route?->getActionName(),
                'methods' => $route?->methods() ?? [],
                'middleware' => $route?->gatherMiddleware() ?? [],
                'parameters' => $this->sanitizeForActivityLog($route?->parameters() ?? []),
            ],
            'subject' => $auditable instanceof Model ? $this->modelDetails($auditable) : null,
            'authenticated_user' => $causer instanceof Model ? $this->modelDetails($causer) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseDetails(Response $response): array
    {
        $details = [
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->headers->get('content-type'),
        ];

        if ($response instanceof JsonResponse) {
            $details['payload'] = $this->sanitizeForActivityLog($response->getData(true));

            return $details;
        }

        if (Str::contains((string) $details['content_type'], 'json', true)) {
            $decodedPayload = json_decode($response->getContent(), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $details['payload'] = $this->sanitizeForActivityLog($decodedPayload);
            }
        }

        return $details;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizedHeaders(Request $request): array
    {
        return $this->sanitizeForActivityLog($request->headers->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function modelDetails(Model $model): array
    {
        return [
            'type' => $model::class,
            'id' => $model->getKey(),
            'name' => $model->getAttribute('name'),
            'email' => $model->getAttribute('email'),
        ];
    }

    private function sanitizeForActivityLog(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $this->modelDetails($value);
        }

        if ($value instanceof UploadedFile) {
            return [
                'original_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
                'error' => $value->getError(),
            ];
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->isSensitiveActivityLogKey($key)
                    ? '[redacted]'
                    : $this->sanitizeForActivityLog($item);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return Str::limit($value, self::MAX_ACTIVITY_LOG_STRING_LENGTH);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return $value instanceof Stringable ? (string) $value : $value::class;
    }

    private function isSensitiveActivityLogKey(string|int $key): bool
    {
        $normalizedKey = Str::of((string) $key)
            ->lower()
            ->replace('-', '_')
            ->toString();

        return Str::contains($normalizedKey, [
            'recovery_code',
            'challenge',
            'provisioning',
            'qr_code',
            'api_key',
            'authorization',
            'cookie',
            'otp',
            'password',
            'secret',
            'token',
            'x_csrf_token',
            'x_xsrf_token',
        ]) || $normalizedKey === 'code';
    }
}
