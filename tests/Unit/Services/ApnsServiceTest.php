<?php

namespace Tests\Unit\Services;

use App\Services\ApnsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApnsServiceTest extends TestCase
{
    // Throwaway EC P-256 key — only used for JWT signing in tests.
    private const TEST_KEY = <<<'KEY'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIOw8PWoM6cSACZ/0CrMpu/bBluLHOgGKzTMGJeu4locZoAoGCCqGSM49
AwEHoUQDQgAElIEpWjj2HabRnUIxuBuZbXm0uWdUl/MsNaxgZzdg9k4wiWQLOvFX
FzTwKQRWdXwCrLkRwrezaoWeSZrLnaIdMQ==
-----END EC PRIVATE KEY-----
KEY;

    private function makeService(array $overrides = []): ApnsService
    {
        $keyFile = tempnam(sys_get_temp_dir(), 'apns_test_');
        file_put_contents($keyFile, self::TEST_KEY);

        config([
            'apns.key_id'     => $overrides['key_id']     ?? 'TESTKEYID1',
            'apns.team_id'    => $overrides['team_id']    ?? 'TESTTEAMID',
            'apns.bundle_id'  => $overrides['bundle_id']  ?? 'com.test.app',
            'apns.key_path'   => $overrides['key_path']   ?? $keyFile,
            'apns.production' => $overrides['production'] ?? false,
        ]);

        return new ApnsService();
    }

    #[Test]
    public function it_sends_push_to_sandbox_with_correct_headers(): void
    {
        Http::fake(['https://api.sandbox.push.apple.com/*' => Http::response('', 200)]);

        $this->makeService()->sendSilent(['fake-device-token-abc123'], ['type' => 'test']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.sandbox.push.apple.com/3/device/fake-device-token-abc123')
                && $request->hasHeader('authorization')
                && str_starts_with($request->header('authorization')[0], 'bearer ')
                && $request->header('apns-topic')[0]     === 'com.test.app'
                && $request->header('apns-push-type')[0] === 'alert'
                && $request->header('apns-priority')[0]  === '10';
        });
    }

    #[Test]
    public function it_sends_push_to_production_when_configured(): void
    {
        Http::fake(['https://api.push.apple.com/*' => Http::response('', 200)]);

        $this->makeService(['production' => true])->sendSilent(['prod-device-token']);

        Http::assertSent(fn($r) => str_contains($r->url(), 'api.push.apple.com/3/device/prod-device-token'));
    }

    #[Test]
    public function it_logs_warning_on_apns_rejection(): void
    {
        Http::fake(['*' => Http::response('{"reason":"BadDeviceToken"}', 400)]);
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn($msg, $ctx) => $msg === 'APNs send failed' && $ctx['status'] === 400);

        $this->makeService()->sendSilent(['bad-token']);
    }

    #[Test]
    public function it_skips_send_when_no_tokens(): void
    {
        Http::fake();
        Log::shouldReceive('info')->once()->withArgs(fn($msg) => str_contains($msg, 'no device tokens'));

        $this->makeService()->sendSilent([]);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_skips_send_and_logs_warning_when_config_missing(): void
    {
        Http::fake();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn($msg) => str_contains($msg, 'missing config'));

        config(['apns.key_id' => null, 'apns.team_id' => null, 'apns.bundle_id' => null, 'apns.key_path' => null]);
        (new ApnsService())->sendSilent(['some-token']);

        Http::assertNothingSent();
    }
}
