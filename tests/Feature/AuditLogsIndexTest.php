<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

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

    AuditLog::query()->create([
        'user_id' => $alice->id,
        'action' => 'POST api/products',
        'auditable_type' => 'App\\Http\\Controllers\\Api\\ProductController@store',
        'auditable_id' => 10,
        'old_values' => ['name' => 'Old Product'],
        'new_values' => ['status_code' => 200],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'performed_at' => '2026-03-01 10:00:00',
    ]);

    AuditLog::query()->create([
        'user_id' => $bob->id,
        'action' => 'PUT api/products/10',
        'auditable_type' => 'App\\Http\\Controllers\\Api\\ProductController@update',
        'auditable_id' => 10,
        'old_values' => ['name' => 'Product A'],
        'new_values' => ['status_code' => 200],
        'ip_address' => '127.0.0.2',
        'user_agent' => 'Pest',
        'performed_at' => '2026-03-02 10:00:00',
    ]);

    AuditLog::query()->create([
        'user_id' => $alice->id,
        'action' => 'DELETE api/products/11',
        'auditable_type' => 'App\\Http\\Controllers\\Api\\ProductController@destroy',
        'auditable_id' => 11,
        'old_values' => ['name' => 'Product B'],
        'new_values' => ['status_code' => 200],
        'ip_address' => '127.0.0.3',
        'user_agent' => 'Pest',
        'performed_at' => '2026-03-02 15:00:00',
    ]);

    $response = $this->getJson('/api/audit-logs?per_page=2');
    $response->assertOk();
    $response->assertJsonPath('per_page', 2);
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.action', 'DELETE api/products/11');
    $response->assertJsonPath('data.1.action', 'PUT api/products/10');

    $userFiltered = $this->getJson('/api/audit-logs?user_id='.$alice->id);
    $userFiltered->assertOk();
    $userFiltered->assertJsonPath('total', 2);
    $userFiltered->assertJsonMissing(['action' => 'PUT api/products/10']);

    $searchFiltered = $this->getJson('/api/audit-logs?search=Bob Operator');
    $searchFiltered->assertOk();
    $searchFiltered->assertJsonPath('total', 1);
    $searchFiltered->assertJsonPath('data.0.action', 'PUT api/products/10');

    $dateFiltered = $this->getJson('/api/audit-logs?from=2026-03-02&to=2026-03-02');
    $dateFiltered->assertOk();
    $dateFiltered->assertJsonPath('total', 2);
});
