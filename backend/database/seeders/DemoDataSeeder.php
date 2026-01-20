<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\BankAccount;
use App\Models\Document;
use App\Models\EmploymentRecord;
use App\Models\Product;
use App\Models\Reference;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create Demo Tenant
        $tenant = Tenant::create([
            'id' => Str::uuid(),
            'name' => 'Lendus Demo',
            'slug' => 'demo',
            'legal_name' => 'Lendus Financiera S.A. de C.V. SOFOM E.N.R.',
            'rfc' => 'LFI180101ABC',
            'branding' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#1e40af',
                'logo_url' => null, // Uses default LendusFind logo when null
            ],
            'settings' => [
                'otp_provider' => 'twilio',
                'kyc_provider' => 'mati',
                'max_loan_amount' => 500000,
                'min_loan_amount' => 5000,
            ],
            'email' => 'contacto@lendus.mx',
            'phone' => '5555555555',
            'website' => 'https://lendus.mx',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        // Create Products
        $products = [
            [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Crédito Personal',
                'code' => 'PERS-001',
                'type' => 'PERSONAL',
                'description' => 'Crédito personal para cualquier necesidad',
                'min_amount' => 5000,
                'max_amount' => 150000,
                'min_term_months' => 3,
                'max_term_months' => 36,
                'interest_rate' => 36.0,
                'opening_commission' => 3.0,
                'late_fee_rate' => 5.0,
                'payment_frequencies' => ['WEEKLY', 'BIWEEKLY', 'MONTHLY'],
                'required_documents' => ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS', 'PAYSLIP'],
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Crédito Nómina',
                'code' => 'NOMI-001',
                'type' => 'NOMINA',
                'description' => 'Crédito con descuento vía nómina',
                'min_amount' => 10000,
                'max_amount' => 300000,
                'min_term_months' => 6,
                'max_term_months' => 48,
                'interest_rate' => 24.0,
                'opening_commission' => 2.0,
                'late_fee_rate' => 3.0,
                'payment_frequencies' => ['BIWEEKLY', 'MONTHLY'],
                'required_documents' => ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS', 'PAYSLIP_1', 'PAYSLIP_2', 'PAYSLIP_3'],
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'id' => Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => 'Arrendamiento',
                'code' => 'ARRE-001',
                'type' => 'ARRENDAMIENTO',
                'description' => 'Arrendamiento de vehículos y maquinaria',
                'min_amount' => 50000,
                'max_amount' => 1000000,
                'min_term_months' => 12,
                'max_term_months' => 60,
                'interest_rate' => 18.0,
                'opening_commission' => 2.5,
                'late_fee_rate' => 4.0,
                'payment_frequencies' => ['MONTHLY'],
                'required_documents' => ['INE_FRONT', 'INE_BACK', 'PROOF_OF_ADDRESS', 'PAYSLIP', 'VEHICLE_INVOICE'],
                'is_active' => true,
                'display_order' => 3,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }

        $personalProduct = Product::where('type', 'PERSONAL')->first();
        $nominaProduct = Product::where('type', 'NOMINA')->first();

        // Create Super Admin User
        $superAdminUser = User::create([
            'name' => 'Super Admin',
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@lendus.mx',
            'phone' => '5500000000',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'type' => 'SUPER_ADMIN',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Create Admin User
        $adminUser = User::create([
            'name' => 'Admin Demo',
            'first_name' => 'Admin',
            'last_name' => 'Demo',
            'email' => 'admin@lendus.mx',
            'phone' => '5500000001',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'type' => 'ADMIN',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        // Create Analyst Users
        $analysts = [];
        $analystNames = [
            ['Patricia', 'Moreno', 'Ruiz'],
            ['Fernando', 'Díaz', 'Castro'],
        ];

        foreach ($analystNames as $index => $nameParts) {
            $analysts[] = User::create([
                'name' => "{$nameParts[0]} {$nameParts[1]}",
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1],
                'email' => strtolower($nameParts[0]) . '.' . strtolower($nameParts[1]) . '@lendus.mx',
                'phone' => '550000010' . ($index + 1),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => 'ANALYST',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        // Create Agent Users (Promotores)
        $agents = [];
        $agentNames = [
            ['Carlos', 'Ramírez', 'López'],
            ['María', 'López', 'García'],
            ['Juan', 'Hernández', 'Martínez'],
            ['Ana', 'García', 'Sánchez'],
        ];

        foreach ($agentNames as $index => $nameParts) {
            $agents[] = User::create([
                'name' => "{$nameParts[0]} {$nameParts[1]}",
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1],
                'email' => strtolower($nameParts[0]) . '.' . strtolower($nameParts[1]) . '@lendus.mx',
                'phone' => '550000000' . ($index + 2),
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'type' => 'SUPERVISOR',
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);
        }

        // Applicants, Applications, Documents, References se crean desde el flujo de onboarding
        // No crear datos de prueba para mantener el sistema limpio

        $this->command->info('Demo data seeded successfully!');
        $this->command->info("Tenant slug: demo");
        $this->command->info("");
        $this->command->info("Staff Credentials (password: 'password'):");
        $this->command->info("  Super Admin: superadmin@lendus.mx");
        $this->command->info("  Admin: admin@lendus.mx");
        $this->command->info("  Analista: patricia.moreno@lendus.mx");
        $this->command->info("  Analista: fernando.diaz@lendus.mx");
        $this->command->info("  Supervisor: carlos.ramirez@lendus.mx");
        $this->command->info("  Supervisor: maria.lopez@lendus.mx");
    }

    /**
     * Create a placeholder image file for demo documents.
     */
    private function createPlaceholderImage(string $filePath, string $docType): void
    {
        // Create a simple placeholder image (PNG) that looks like a document
        $width = 800;
        $height = 1000;
        $image = imagecreatetruecolor($width, $height);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $darkGray = imagecolorallocate($image, 100, 100, 100);
        $primary = imagecolorallocate($image, 59, 130, 246); // Blue

        // Background
        imagefill($image, 0, 0, $white);

        // Border
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $gray);

        // Header area
        imagefilledrectangle($image, 0, 0, $width, 80, $primary);

        // Document type text
        $labels = [
            'INE_FRONT' => 'INE - Frente',
            'INE_BACK' => 'INE - Reverso',
            'PROOF_OF_ADDRESS' => 'Comprobante de Domicilio',
            'PAYSLIP' => 'Recibo de Nómina',
        ];
        $label = $labels[$docType] ?? $docType;

        // Title in header
        imagestring($image, 5, 20, 30, 'DOCUMENTO DE PRUEBA', $white);
        imagestring($image, 4, 20, 50, $label, $white);

        // Placeholder content
        imagestring($image, 3, 50, 150, 'Este es un documento de prueba generado', $darkGray);
        imagestring($image, 3, 50, 180, 'automaticamente para fines de desarrollo.', $darkGray);

        // Fake document structure
        for ($i = 0; $i < 15; $i++) {
            $y = 250 + ($i * 40);
            $lineWidth = rand(200, 600);
            imagefilledrectangle($image, 50, $y, 50 + $lineWidth, $y + 10, $gray);
        }

        // Save as PNG first
        $tempPath = sys_get_temp_dir() . '/' . uniqid('doc_') . '.png';
        imagepng($image, $tempPath);
        imagedestroy($image);

        // Store in Laravel storage
        \Storage::disk('local')->put($filePath, file_get_contents($tempPath));

        // Clean up temp file
        unlink($tempPath);
    }
}
