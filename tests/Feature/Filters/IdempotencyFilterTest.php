<?php

declare(strict_types=1);

namespace Tests\Feature\Filters;

use dcardenasl\Ci4ApiCore\Http\Filters\IdempotencyFilter;
use Tests\Support\ApiTestCase;

/**
 * IdempotencyFilter feature tests
 *
 * Audit B7.3 (2026-05-06) / ADR-009: pin the contract for opt-in
 * `Idempotency-Key` semantics on state-changing requests.
 *
 * @internal
 */
final class IdempotencyFilterTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset in-flight state shared between filter phases. The static
        // `$pending` is normally fine in production (single request per
        // PHP-FPM worker) but tests run multiple back-to-back in one process.
        IdempotencyFilter::flushPending();

        // Ensure the cache table starts clean per test.
        \Config\Database::connect()->table('idempotency_keys')->truncate();

        // Define a tiny test route that applies the filter and echoes a
        // counter so we can prove the second call replays instead of
        // re-executing the handler.
        $this->withRoutes([
            ['POST', 'test-idempotent', static function () {
                static $callCount = 0;
                $callCount++;
                return service('response')
                    ->setStatusCode(201)
                    ->setJSON(['call_count' => $callCount, 'echo' => 'created']);
            }, ['filter' => 'idempotency']],
        ]);
    }

    public function testRequestWithoutKeyHeaderPassesThroughUntouched(): void
    {
        $result = $this->withBody('{"foo":"bar"}')->post('test-idempotent');

        $result->assertStatus(201);
        $this->assertSame(0, $this->countKeys(), 'No row should be persisted without the header.');
    }

    public function testFirstCallWithKeyPersistsAndSecondCallReplays(): void
    {
        $body = '{"name":"Acme"}';
        $key = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $first = $this->withBody($body)
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('test-idempotent');
        $first->assertStatus(201);

        $this->assertSame(
            1,
            $this->countKeys(),
            'After a successful 2xx response the row must be persisted.'
        );

        // Reset request state between calls (per ApiTestCase.setUp/tearDown idiom).
        $this->resetRequest();
        IdempotencyFilter::flushPending();

        $second = $this->withBody($body)
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('test-idempotent');

        $second->assertStatus(201);
        $second->assertHeader('Idempotent-Replay', 'true');
        $this->assertSame(
            1,
            $this->countKeys(),
            'Second call must replay the cached row, not insert a new one.'
        );
    }

    public function testSameKeyDifferentBodyReturns409Conflict(): void
    {
        $key = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        // Seed the cache by posting once.
        $this->withBody('{"name":"Acme"}')
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('test-idempotent');

        $this->resetRequest();
        IdempotencyFilter::flushPending();

        // Same key, different body — must return 409 with Idempotency-Mismatch header.
        $second = $this->withBody('{"name":"Different"}')
            ->withHeaders(['Idempotency-Key' => $key])
            ->post('test-idempotent');

        $second->assertStatus(409);
        $second->assertHeader('Idempotency-Mismatch', 'true');
    }

    public function testMalformedKeyIsRejectedWith400(): void
    {
        $result = $this->withBody('{"name":"Acme"}')
            ->withHeaders(['Idempotency-Key' => 'short'])
            ->post('test-idempotent');

        $result->assertStatus(400);
        $this->assertSame(
            0,
            $this->countKeys(),
            'A rejected key must not produce a cache row.'
        );
    }

    public function testNon2xxResponseIsNotPersisted(): void
    {
        // Define a route that returns 500.
        $this->withRoutes([
            ['POST', 'test-idempotent-failing', static function () {
                return service('response')
                    ->setStatusCode(500)
                    ->setJSON(['status' => 'error']);
            }, ['filter' => 'idempotency']],
        ]);

        $this->withBody('{"foo":"bar"}')
            ->withHeaders(['Idempotency-Key' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'])
            ->post('test-idempotent-failing');

        $this->assertSame(
            0,
            $this->countKeys(),
            '5xx responses must NOT persist a replay row — the call may legitimately retry.'
        );
    }

    public function testGetRequestsAreIgnoredEvenWithKeyHeader(): void
    {
        $this->withRoutes([
            ['GET', 'test-idempotent-get', static function () {
                return service('response')->setJSON(['ok' => true]);
            }, ['filter' => 'idempotency']],
        ]);

        $result = $this->withHeaders(['Idempotency-Key' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'])
            ->get('test-idempotent-get');

        $result->assertStatus(200);
        $this->assertSame(
            0,
            $this->countKeys(),
            'GET is read-only — filter must short-circuit before doing any DB work.'
        );
    }

    private function countKeys(): int
    {
        $row = \Config\Database::connect()
            ->table('idempotency_keys')
            ->countAllResults(false);

        return (int) $row;
    }
}
