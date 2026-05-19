<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApnsService
{
    private ?string $keyId;
    private ?string $teamId;
    private ?string $privateKey;
    private ?string $bundleId;
    private bool $production;

    private ?string $cachedJwt = null;
    private int $jwtIssuedAt  = 0;

    public function __construct()
    {
        $this->keyId      = config('apns.key_id');
        $this->teamId     = config('apns.team_id');
        $this->bundleId   = config('apns.bundle_id');
        $this->production = (bool) config('apns.production', false);

        $keyPath = config('apns.key_path');
        $this->privateKey = ($keyPath && file_exists($keyPath))
            ? file_get_contents($keyPath)
            : null;
    }

    /**
     * Send a silent background push to a list of APNs device tokens.
     */
    public function sendSilent(array $deviceTokens, array $data = []): void
    {
        if (empty($deviceTokens)) {
            Log::info('APNs sendSilent: no device tokens registered');
            return;
        }

        if (!$this->keyId || !$this->teamId || !$this->privateKey || !$this->bundleId) {
            Log::warning('APNs sendSilent: missing config', [
                'key_id'      => $this->keyId      ? 'set' : 'MISSING',
                'team_id'     => $this->teamId     ? 'set' : 'MISSING',
                'private_key' => $this->privateKey ? 'set' : 'MISSING',
                'bundle_id'   => $this->bundleId   ? 'set' : 'MISSING',
            ]);
            return;
        }

        $jwt = $this->getJwt();
        if (!$jwt) {
            return;
        }

        Log::info('APNs sendSilent: sending to ' . count($deviceTokens) . ' token(s)', ['data' => $data]);

        foreach ($deviceTokens as $token) {
            $this->sendToToken($token, $data, $jwt);
        }
    }

    private function sendToToken(string $deviceToken, array $data, string $jwt): void
    {
        $host = $this->production
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        $payload = array_merge([
            // 'aps' => ['content-available' => 1]
            'aps' => [
                'alert' => [
                    'title' => 'New Message',
                    'body' => 'You have received a new message for HHSR.'
                ],
                'sound' => 'default',
                'badge' => 1,
                'content-available' => 1
            ]
        ], $data);

        $response = Http::withHeaders([
            'authorization'  => "bearer {$jwt}",
            'apns-topic'     => $this->bundleId,
            'apns-push-type' => 'alert',
            'apns-priority'  => '10',
        ])
        ->withOptions(['version' => 2.0])
        ->post("{$host}/3/device/{$deviceToken}", $payload);

        if ($response->successful()) {
            Log::info('APNs send ok', ['token' => substr($deviceToken, 0, 20) . '...', 'status' => $response->status()]);
        } else {
            Log::warning('APNs send failed', [
                'token'  => substr($deviceToken, 0, 20) . '...',
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }
    }

    private function getJwt(): ?string
    {
        $now = time();
        // APNs JWTs expire after 60 min; refresh after 55 min
        if ($this->cachedJwt && ($now - $this->jwtIssuedAt) < 3300) {
            return $this->cachedJwt;
        }

        try {
            $header   = $this->base64url(json_encode(['alg' => 'ES256', 'kid' => $this->keyId]));
            $claims   = $this->base64url(json_encode(['iss' => $this->teamId, 'iat' => $now]));
            $unsigned = "{$header}.{$claims}";

            openssl_sign($unsigned, $derSig, $this->privateKey, OPENSSL_ALGO_SHA256);

            $this->cachedJwt   = $unsigned . '.' . $this->base64url($this->ecDerToRaw($derSig));
            $this->jwtIssuedAt = $now;

            return $this->cachedJwt;
        } catch (\Throwable $e) {
            Log::error('APNs JWT error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert a DER-encoded ECDSA signature to the raw 64-byte R||S format JWT expects.
     */
    private function ecDerToRaw(string $der): string
    {
        $pos = 0;

        if (ord($der[$pos++]) !== 0x30) {
            throw new \RuntimeException('APNs signature: expected SEQUENCE');
        }
        $this->readDerLength($der, $pos); // skip sequence length

        if (ord($der[$pos++]) !== 0x02) {
            throw new \RuntimeException('APNs signature: expected INTEGER r');
        }
        $rLen = $this->readDerLength($der, $pos);
        $r    = substr($der, $pos, $rLen);
        $pos += $rLen;

        if (ord($der[$pos++]) !== 0x02) {
            throw new \RuntimeException('APNs signature: expected INTEGER s');
        }
        $sLen = $this->readDerLength($der, $pos);
        $s    = substr($der, $pos, $sLen);

        return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
             . str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    private function readDerLength(string $der, int &$pos): int
    {
        $first = ord($der[$pos++]);
        if ($first < 0x80) {
            return $first;
        }
        $numBytes = $first & 0x7f;
        $len = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $len = ($len << 8) | ord($der[$pos++]);
        }
        return $len;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
