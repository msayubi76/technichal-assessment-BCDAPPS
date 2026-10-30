<?php

use App\Models\User;

it('signs in with the right password and returns a token', function () {
    $user = User::factory()->rider()->create(['email' => 'ayesha@example.com']);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'ayesha@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.role', 'rider');

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects a wrong password with the standard error shape', function () {
    User::factory()->create(['email' => 'ayesha@example.com']);

    $this->postJson('/api/auth/login', [
        'email' => 'ayesha@example.com',
        'password' => 'wrong-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_credentials')
        ->assertJsonStructure(['error' => ['code', 'message', 'request_id']]);
});

it('gives the same answer for an unknown email as for a wrong password', function () {
    $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_credentials')
        ->assertJsonPath('error.message', 'The email or password is incorrect.');
});

it('returns field errors when the login form is incomplete', function () {
    $this->postJson('/api/auth/login', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['details' => ['email', 'password']]]);
});

it('blocks protected routes without a token', function () {
    $this->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('returns the signed in user', function () {
    $user = User::factory()->rider()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);
});

it('signs out by revoking the current token', function () {
    $user = User::factory()->rider()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('slows down repeated failed logins', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'nope']);
    }

    $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'nope'])
        ->assertTooManyRequests()
        ->assertJsonPath('error.code', 'too_many_requests');
});
