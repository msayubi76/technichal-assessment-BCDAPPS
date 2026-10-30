<?php

use App\Models\IdempotencyKey;
use App\Models\Ride;
use App\Models\User;

beforeEach(function () {
    seedLahore();
    $this->rider = User::factory()->rider()->create();
    $this->body = [
        'category' => 'economy',
        'pickup' => place('Liberty Market'),
        'dropoff' => place('Packages Mall'),
    ];
});

it('returns the same ride when a request is retried with the same key', function () {
    driverAt('MM Alam Road');
    $key = idempotencyKey();

    $first = $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body);

    $retry = $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body);

    $first->assertCreated();
    $retry->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($retry->json('data.id'))->toBe($first->json('data.id'))
        ->and(Ride::count())->toBe(1);
});

it('requires an idempotency key on ride requests', function () {
    driverAt('MM Alam Road');

    $this->actingAs($this->rider, 'sanctum')
        ->postJson('/api/rides', $this->body)
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'idempotency_key_required');

    expect(Ride::count())->toBe(0);
});

it('rejects a key reused for a different request', function () {
    driverAt('MM Alam Road');
    $key = idempotencyKey();

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body)
        ->assertCreated();

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', ['category' => 'comfort'] + $this->body)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'idempotency_key_reused');
});

it('replays a business error too, and a new key starts fresh', function () {
    $key = idempotencyKey();

    // No drivers yet, so the request is refused.
    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body)
        ->assertConflict();

    driverAt('MM Alam Road');

    // Same key: the saved answer comes back, nothing is redone.
    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body)
        ->assertConflict()
        ->assertHeader('Idempotent-Replayed', 'true');

    // A new tap on "Request ride" sends a new key and works.
    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', idempotencyKey())
        ->postJson('/api/rides', $this->body)
        ->assertCreated();
});

it('tells a duplicate to wait while the first request is still running', function () {
    driverAt('MM Alam Road');
    $key = idempotencyKey();

    // The first request has claimed the key but not finished yet.
    IdempotencyKey::create([
        'user_id' => $this->rider->id,
        'key' => $key,
        'method' => 'POST',
        'path' => 'api/rides',
        'request_hash' => hash('sha256', 'POST|api/rides|'.json_encode($this->body)),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body)
        ->assertConflict()
        ->assertJsonPath('error.code', 'request_in_progress');

    expect(Ride::count())->toBe(0);
});

it('keeps keys separate per user', function () {
    driverAt('MM Alam Road');
    driverAt('Liberty Market');
    $key = idempotencyKey();
    $otherRider = User::factory()->rider()->create();

    $mine = $this->actingAs($this->rider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body);

    $theirs = $this->actingAs($otherRider, 'sanctum')
        ->withHeader('Idempotency-Key', $key)
        ->postJson('/api/rides', $this->body);

    $mine->assertCreated();
    $theirs->assertCreated();

    expect($theirs->json('data.id'))->not->toBe($mine->json('data.id'))
        ->and(Ride::count())->toBe(2);
});
