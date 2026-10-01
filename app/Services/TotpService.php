<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

/**
 * Adapt Fortify's two-factor provider to the API's durable per-user replay checks.
 */
class TotpService
{
    public function __construct(private TwoFactorAuthenticationProvider $provider, private Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->provider->generateSecretKey(32);
    }

    public function provisioningUri(string $issuer, string $email, #[\SensitiveParameter] string $secret): string
    {
        return $this->provider->qrCodeUrl($issuer, $email, $secret);
    }

    public function qrCodeSvg(#[\SensitiveParameter] string $uri): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd)))->writeString($uri);
    }

    public function verify(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code, int $timestamp, ?int $lastUsedStep = null): int|false
    {
        if (! preg_match('/^[0-9]{6}$/D', $code) || $timestamp < 0) {
            return false;
        }

        $step = $this->engine->verifyKey($secret, $code, 1, intdiv($timestamp, 30), $lastUsedStep ?? -1);

        if ($step === false || ! $this->provider->verify($secret, $code)) {
            return false;
        }

        return $step;
    }
}
