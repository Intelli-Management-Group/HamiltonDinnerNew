<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\ApnsService;
use Illuminate\Console\Command;

class TestApns extends Command
{
    protected $signature   = 'apns:test {--token= : Override with a specific device token}';
    protected $description = 'Send a test APNs push to all registered device tokens (or a specific one)';

    public function handle(ApnsService $apns): int
    {
        $override = $this->option('token');

        if ($override) {
            $tokens = [$override];
            $this->info("Sending to provided token: {$override}");
        } else {
            $tokens = DeviceToken::pluck('token')->all();
            $this->info('Tokens in DB: ' . count($tokens));

            if (empty($tokens)) {
                $this->warn('No device tokens registered. Register one first via POST /api/set-device-token.');
                return 1;
            }

            foreach ($tokens as $t) {
                $this->line('  ' . substr($t, 0, 20) . '...');
            }
        }

        $apns->sendSilent($tokens, ['type' => 'test']);

        $this->info('Done — check storage/logs/laravel.log for APNs send ok / send failed entries.');
        return 0;
    }
}
