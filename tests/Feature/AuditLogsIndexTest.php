<?php

use App\Models\User;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('lists audit logs with pagination and filters', function () {
    $this->withoutMiddleware();
    Event::fake([RequestHandled::class]);

    $alice = User::factory()->create([
        'name' => 'Alice Auditor',
        'email' => 'alice@example.com',
    ]);
    $bob = User::factory()->create([
        'name' => 'Bob Operator',
        'email' => 'bob@example.com',
    ]);

    createAuditActivity($alice, 'POST api/products', 'App\\Http\\Controllers\\Api\\ProductController@store', 10, [
        'name' => 'Old Product',
    ], '127.0.0.1', '2026-03-01 10:00:00');

    createAuditActivity($bob, 'PUT api/products/10', 'App\\Http\\Controllers\\Api\\ProductController@update', 10, [
        'name' => 'Product A',
    ], '127.0.0.2', '2026-03-02 10:00:00');

    createAuditActivity($alice, 'DELETE api/products/11', 'App\\Http\\Controllers\\Api\\ProductController@destroy', 11, [
        'name' => 'Product B',
    ], '127.0.0.3', '2026-03-02 15:00:00');

    $response = $this->getJson('/api/auditlogs?per_page=10');
    $response->assertOk();
    $response->assertJsonPath('per_page', 10);
    $response->assertJsonCount(3, 'data');
    $response->assertJsonPath('data.0.action', 'DELETE api/products/11');
    $response->assertJsonPath('data.1.action', 'PUT api/products/10');

    $userFiltered = $this->getJson('/api/auditlogs?user_id='.$alice->id);
    $userFiltered->assertOk();
    $userFiltered->assertJsonPath('total', 2);
    $userFiltered->assertJsonMissing(['action' => 'PUT api/products/10']);

    $searchFiltered = $this->getJson('/api/auditlogs?search=Bob Operator');
    $searchFiltered->assertOk();
    $searchFiltered->assertJsonPath('total', 1);
    $searchFiltered->assertJsonPath('data.0.action', 'PUT api/products/10');

    $dateFiltered = $this->getJson('/api/auditlogs?from=2026-03-02&to=2026-03-02');
    $dateFiltered->assertOk();
    $dateFiltered->assertJsonPath('total', 2);
});

function createAuditActivity(
    User $user,
    string $action,
    string $routeAction,
    int $auditableId,
    array $requestPayload,
    string $ipAddress,
    string $performedAt
): void {
    Activity::query()->create([
        'log_name' => 'audit',
        'description' => $action,
        'subject_type' => null,
        'subject_id' => null,
        'event' => 'request',
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'attribute_changes' => null,
        'properties' => [
            'route_action' => $routeAction,
            'auditable_id' => $auditableId,
            'request_payload' => $requestPayload,
            'response' => [
                'status_code' => 200,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => 'Pest',
        ],
        'created_at' => $performedAt,
        'updated_at' => $performedAt,
    ]);
}
