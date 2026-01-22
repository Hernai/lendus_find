<?php

namespace App\Console\Commands;

use App\Enums\VerificationMethod;
use App\Models\DataVerification;
use App\Models\Person;
use App\Services\VerificationService;
use Illuminate\Console\Command;

class MigrateCurpVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verifications:migrate-curp
                          {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing CURP verifications to include birth_state and gender';

    /**
     * Execute the console command.
     */
    public function handle(VerificationService $verificationService): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Running in DRY RUN mode - no changes will be made');
        }

        // Find all CURP verifications that have RENAPO response metadata
        $curpVerifications = DataVerification::where('field_name', 'curp')
            ->where('method', VerificationMethod::RENAPO->value)
            ->whereNotNull('metadata')
            ->get();

        $this->info("Found {$curpVerifications->count()} CURP verifications with RENAPO data");

        $created = 0;
        $skipped = 0;

        foreach ($curpVerifications as $curpVerification) {
            $metadata = $curpVerification->metadata ?? [];
            $renapoResponse = $metadata['renapo_response'] ?? [];

            if (empty($renapoResponse)) {
                $this->warn("  Skipping verification {$curpVerification->id} - no RENAPO response data");
                $skipped++;
                continue;
            }

            $person = Person::find($curpVerification->applicant_id);
            if (!$person) {
                $this->warn("  Skipping verification {$curpVerification->id} - person not found");
                $skipped++;
                continue;
            }

            $this->info("Processing person {$person->id} ({$person->full_name})...");

            // Check for birth_state (entidad_nacimiento)
            if (!empty($renapoResponse['estado_nacimiento'])) {
                $existingBirthState = DataVerification::where('applicant_id', $person->id)
                    ->where('field_name', 'birth_state')
                    ->exists();

                if (!$existingBirthState) {
                    $this->line("  ✓ Creating birth_state verification: {$renapoResponse['estado_nacimiento']}");

                    if (!$dryRun) {
                        $verificationService->verify(
                            $person,
                            'birth_state',
                            $renapoResponse['estado_nacimiento'],
                            VerificationMethod::RENAPO,
                            ['verified_at' => $curpVerification->verified_at?->toIso8601String()]
                        );
                    }

                    $created++;
                } else {
                    $this->line("  - birth_state already verified");
                }
            }

            // Check for gender (sexo)
            if (!empty($renapoResponse['sexo'])) {
                $existingGender = DataVerification::where('applicant_id', $person->id)
                    ->where('field_name', 'gender')
                    ->exists();

                if (!$existingGender) {
                    $this->line("  ✓ Creating gender verification: {$renapoResponse['sexo']}");

                    if (!$dryRun) {
                        $verificationService->verify(
                            $person,
                            'gender',
                            $renapoResponse['sexo'],
                            VerificationMethod::RENAPO,
                            ['verified_at' => $curpVerification->verified_at?->toIso8601String()]
                        );
                    }

                    $created++;
                } else {
                    $this->line("  - gender already verified");
                }
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("🔍 DRY RUN COMPLETE");
            $this->info("Would create {$created} new verification records");
            $this->info("Skipped {$skipped} verifications");
            $this->newLine();
            $this->comment("Run without --dry-run to apply changes");
        } else {
            $this->info("✅ MIGRATION COMPLETE");
            $this->info("Created {$created} new verification records");
            $this->info("Skipped {$skipped} verifications");
        }

        return self::SUCCESS;
    }
}
