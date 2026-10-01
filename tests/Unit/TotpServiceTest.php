<?php

use App\Services\TotpService;
use Illuminate\Support\Carbon;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->app->bind(Google2FA::class, fn (): Google2FA => new class extends Google2FA
    {
        public function getTimestamp(): int
        {
            return intdiv(now()->timestamp, 30);
        }
    });
});

it('matches the six-digit RFC 4226 HOTP vectors', function (int $counter, string $expected) {
    $this->travelTo(Carbon::createFromTimestampUTC($counter * 30));
    $totp = app(TotpService::class);
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    expect($totp->verify($secret, $expected, $counter * 30))->toBe($counter);
})->with([
    [0, '755224'], [1, '287082'], [2, '359152'], [3, '969429'], [4, '338314'],
    [5, '254676'], [6, '287922'], [7, '162583'], [8, '399871'], [9, '520489'],
]);

it('matches RFC 6238 SHA1 vectors reduced to six digits', function (int $timestamp, string $expected) {
    $this->travelTo(Carbon::createFromTimestampUTC($timestamp));
    $totp = app(TotpService::class);
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    expect($totp->verify($secret, $expected, $timestamp))->toBe(intdiv($timestamp, 30));
})->with([
    [59, '287082'], [1111111109, '081804'], [1111111111, '050471'],
    [1234567890, '005924'], [2000000000, '279037'], [20000000000, '353130'],
]);

it('accepts only one step of clock skew and refuses reused or malformed codes', function () {
    $this->travelTo(Carbon::createFromTimestampUTC(60));
    $totp = app(TotpService::class);
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    expect($totp->verify($secret, '287082', 60))->toBe(1)
        ->and($totp->verify($secret, '287082', 90))->toBeFalse()
        ->and($totp->verify($secret, '287082', 59, 1))->toBeFalse()
        ->and($totp->verify($secret, '287082\n', 59))->toBeFalse()
        ->and($totp->verify($secret, '28708', 59))->toBeFalse()
        ->and($totp->verify($secret, '287082', -1))->toBeFalse();
});

it('generates unique 160-bit Base32 secrets and encodes provisioning labels', function () {
    $totp = app(TotpService::class);
    $secret = $totp->generateSecret();
    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->not->toBe($totp->generateSecret())
        ->and($totp->provisioningUri('Inventory & Co', 'user+test@example.com', $secret))
        ->toContain('user%2Btest%40example.com')
        ->toContain('secret='.$secret);
});

it('rejects corrupt stored secrets', function () {
    app(TotpService::class)->verify('INVALID!', '123456', 59);
})->throws(Exception::class);
