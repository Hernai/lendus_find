<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use Illuminate\Database\Seeder;

class TwilioConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first tenant (or create one for testing)
        $tenant = Tenant::first();

        if (!$tenant) {
            $this->command->warn('No tenant found. Please create a tenant first.');
            return;
        }

        $this->command->info("Configuring Twilio for tenant: {$tenant->name} ({$tenant->slug})");

        // Twilio credentials from environment
        $accountSid = env('TWILIO_ACCOUNT_SID', '');
        $authToken = env('TWILIO_AUTH_TOKEN');
        $fromNumber = env('TWILIO_FROM_NUMBER', '');
        $whatsappFrom = env('TWILIO_WHATSAPP_FROM', '');

        if (!$authToken) {
            $this->command->error('TWILIO_AUTH_TOKEN not set in .env file');
            return;
        }

        // Create or update SMS config
        $smsConfig = TenantApiConfig::updateOrCreate(
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
                'extra_config' => [
                    'whatsapp_from' => $whatsappFrom,
                ],
            ]
        );

        $this->command->info('✅ SMS configuration created/updated');
        $this->command->info("   Account SID: {$accountSid}");
        $this->command->info("   From Number: {$fromNumber}");

        // Create or update WhatsApp config (optional)
        $whatsappConfig = TenantApiConfig::updateOrCreate(
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

        $this->command->info('✅ WhatsApp configuration created/updated');
        $this->command->info("   From Number: {$whatsappFrom}");

        $this->command->newLine();
        $this->command->info('🎉 Twilio configuration completed!');
        $this->command->info('Test with: php artisan twilio:test-sms 9611838818 --tenant=' . $tenant->id);
    }
}
