<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use Illuminate\Database\Seeder;

class TwilioConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Required environment variables:
     * - TWILIO_ACCOUNT_SID
     * - TWILIO_AUTH_TOKEN
     * - TWILIO_FROM_NUMBER
     * - TWILIO_WHATSAPP_FROM (optional)
     */
    public function run(): void
    {
        $tenant = Tenant::first();

        if (!$tenant) {
            $this->command->warn('No tenant found. Please create a tenant first.');
            return;
        }

        $this->command->info("Configuring Twilio for tenant: {$tenant->name} ({$tenant->slug})");

        // Twilio credentials from environment only - no default values
        $accountSid = env('TWILIO_ACCOUNT_SID');
        $authToken = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_FROM_NUMBER');
        $whatsappFrom = env('TWILIO_WHATSAPP_FROM', $fromNumber);

        if (!$accountSid || !$authToken || !$fromNumber) {
            $this->command->error('Missing Twilio configuration. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER in .env');
            return;
        }

        TenantApiConfig::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => 'twilio',
                'service_type' => 'sms',
            ],
            [
                'account_sid' => $accountSid,
                'auth_token' => $authToken,
                'from_number' => $fromNumber,
                'is_active' => true,
                'is_sandbox' => env('APP_ENV') !== 'production',
                'extra_config' => ['whatsapp_from' => $whatsappFrom],
            ]
        );

        $this->command->info('SMS configuration created/updated');

        TenantApiConfig::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider' => 'twilio',
                'service_type' => 'whatsapp',
            ],
            [
                'account_sid' => $accountSid,
                'auth_token' => $authToken,
                'from_number' => $whatsappFrom,
                'is_active' => true,
                'is_sandbox' => env('APP_ENV') !== 'production',
            ]
        );

        $this->command->info('WhatsApp configuration created/updated');
        $this->command->info('Test with: php artisan twilio:test-sms <phone> --tenant=' . $tenant->id);
    }
}
