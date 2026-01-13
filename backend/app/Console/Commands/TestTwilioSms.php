<?php

namespace App\Console\Commands;

use App\Services\TwilioService;
use Illuminate\Console\Command;

class TestTwilioSms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:test-sms {phone} {--message=} {--tenant=} {--whatsapp}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Twilio SMS/WhatsApp integration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->option('message') ?? 'Hola, esto es una prueba desde LendusFind';
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $isWhatsApp = $this->option('whatsapp');

        $this->info('Testing Twilio integration...');
        $this->info('Phone: ' . $phone);
        $this->info('Message: ' . $message);
        $this->info('Tenant ID: ' . ($tenantId ?? 'global config'));
        $this->info('Channel: ' . ($isWhatsApp ? 'WhatsApp' : 'SMS'));
        $this->newLine();

        try {
            $twilioService = new TwilioService($tenantId);

            if ($isWhatsApp) {
                $result = $twilioService->sendWhatsApp($phone, $message);
            } else {
                $result = $twilioService->sendSms($phone, $message);
            }

            if ($result['success']) {
                $this->info('✅ Message sent successfully!');
                $this->info('SID: ' . $result['sid']);
                $this->info('Status: ' . $result['status']);
            } else {
                $this->error('❌ Failed to send message');
                $this->error('Error: ' . $result['error']);
                if (isset($result['code'])) {
                    $this->error('Error Code: ' . $result['code']);
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
