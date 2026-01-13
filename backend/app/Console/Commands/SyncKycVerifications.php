<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Models\DataVerification;
use App\Enums\VerificationMethod;
use App\Enums\VerifiableField;
use Illuminate\Console\Command;

class SyncKycVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyc:sync-verifications {--applicant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync KYC verifications for applicants who have completed KYC';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $applicantId = $this->option('applicant');

        if ($applicantId) {
            // Sync single applicant
            $applicant = Applicant::find($applicantId);
            if (!$applicant) {
                $this->error("Applicant not found: {$applicantId}");
                return 1;
            }

            $this->syncApplicant($applicant);
            return 0;
        }

        // Sync all applicants with KYC completed
        $applicants = Applicant::whereNotNull('kyc_verified_at')->get();
        $this->info("Found {$applicants->count()} applicants with KYC verified");

        $bar = $this->output->createProgressBar($applicants->count());
        $synced = 0;

        foreach ($applicants as $applicant) {
            if ($this->syncApplicant($applicant)) {
                $synced++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Synced verifications for {$synced} applicants");

        return 0;
    }

    private function syncApplicant(Applicant $applicant): bool
    {
        // Skip if already has verifications
        $existingCount = DataVerification::where('applicant_id', $applicant->id)->count();
        if ($existingCount > 0) {
            $this->line("  Skipping {$applicant->id} - already has {$existingCount} verifications");
            return false;
        }

        $created = 0;

        // Create verifications for KYC-verified fields
        // Note: We create them directly to set tenant_id properly
        if ($applicant->first_name) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::FIRST_NAME->value,
                'field_value' => $applicant->first_name,
                'method' => VerificationMethod::KYC_INE_OCR,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'ine_ocr', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        if ($applicant->last_name_1) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::LAST_NAME_1->value,
                'field_value' => $applicant->last_name_1,
                'method' => VerificationMethod::KYC_INE_OCR,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'ine_ocr', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        if ($applicant->last_name_2) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::LAST_NAME_2->value,
                'field_value' => $applicant->last_name_2,
                'method' => VerificationMethod::KYC_INE_OCR,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'ine_ocr', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        if ($applicant->birth_date) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::BIRTH_DATE->value,
                'field_value' => $applicant->birth_date->toDateString(),
                'method' => VerificationMethod::KYC_INE_OCR,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'ine_ocr', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        if ($applicant->gender) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::GENDER->value,
                'field_value' => $applicant->gender,
                'method' => VerificationMethod::KYC_INE_OCR,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'ine_ocr', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        if ($applicant->curp) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::CURP->value,
                'field_value' => $applicant->curp,
                'method' => VerificationMethod::KYC_CURP_RENAPO,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'curp_renapo', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        if ($applicant->rfc) {
            DataVerification::create([
                'tenant_id' => $applicant->tenant_id,
                'applicant_id' => $applicant->id,
                'field_name' => VerifiableField::RFC->value,
                'field_value' => $applicant->rfc,
                'method' => VerificationMethod::KYC_RFC_SAT,
                'is_verified' => true,
                'is_locked' => true,
                'status' => \App\Enums\VerificationStatus::VERIFIED,
                'metadata' => ['source' => 'rfc_sat', 'synced_at' => now()->toIso8601String()],
            ]);
            $created++;
        }

        $this->line("  Created {$created} verifications for {$applicant->full_name} ({$applicant->id})");
        return true;
    }
}
