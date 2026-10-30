<?php

it('reports healthy when the database is reachable', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertHeader('X-Request-Id');
});

it('returns a JSON 404 for unknown API routes', function () {
    $this->getJson('/api/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('passes a valid incoming request id through to the response', function () {
    $this->withHeader('X-Request-Id', 'abc12345-trace')
        ->getJson('/api/does-not-exist')
        ->assertHeader('X-Request-Id', 'abc12345-trace')
        ->assertJsonPath('error.request_id', 'abc12345-trace');
});
