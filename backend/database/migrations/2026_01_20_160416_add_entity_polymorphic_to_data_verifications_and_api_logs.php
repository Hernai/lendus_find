<?php

use App\Models\ApiLog;
use App\Models\Applicant;
use App\Models\ApplicantAccount;
use App\Models\DataVerification;
use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds polymorphic entity columns to data_verifications and api_logs tables
     * to support both Person (individual) and Company (enterprise) entities.
     */
    public function up(): void
    {
        // Add entity columns to data_verifications
        Schema::table('data_verifications', function (Blueprint $table) {
            $table->string('entity_type')->nullable()->after('applicant_id');
            $table->uuid('entity_id')->nullable()->after('entity_type');
            $table->index(['entity_type', 'entity_id'], 'data_verifications_entity_index');
        });

        // Add entity columns to api_logs
        Schema::table('api_logs', function (Blueprint $table) {
            $table->string('entity_type')->nullable()->after('applicant_id');
            $table->uuid('entity_id')->nullable()->after('entity_type');
            $table->index(['entity_type', 'entity_id'], 'api_logs_entity_index');
        });

        // Migrate existing data using PHP to handle the complex relationship
        $this->migrateDataVerifications();
        $this->migrateApiLogs();
    }

    /**
     * Migrate data_verifications to use entity_id/entity_type.
     */
    private function migrateDataVerifications(): void
    {
        DataVerification::whereNotNull('applicant_id')
            ->whereNull('entity_id')
            ->chunkById(100, function ($verifications) {
                foreach ($verifications as $verification) {
                    $personId = $this->findPersonIdForApplicant($verification->applicant_id);
                    if ($personId) {
                        $verification->update([
                            'entity_type' => Person::class,
                            'entity_id' => $personId,
                        ]);
                    }
                }
            });
    }

    /**
     * Migrate api_logs to use entity_id/entity_type.
     */
    private function migrateApiLogs(): void
    {
        ApiLog::whereNotNull('applicant_id')
            ->whereNull('entity_id')
            ->chunkById(100, function ($logs) {
                foreach ($logs as $log) {
                    $personId = $this->findPersonIdForApplicant($log->applicant_id);
                    if ($personId) {
                        $log->update([
                            'entity_type' => Person::class,
                            'entity_id' => $personId,
                        ]);
                    }
                }
            });
    }

    /**
     * Find the Person ID linked to an Applicant via ApplicantAccount.
     */
    private function findPersonIdForApplicant(string $applicantId): ?string
    {
        $applicant = Applicant::find($applicantId);
        if (!$applicant) {
            return null;
        }

        // Find ApplicantAccount that matches this applicant's phone or email
        $account = ApplicantAccount::where('tenant_id', $applicant->tenant_id)
            ->whereNotNull('person_id')
            ->whereHas('identities', function ($q) use ($applicant) {
                $q->where(function ($q2) use ($applicant) {
                    if ($applicant->phone) {
                        $normalizedPhone = preg_replace('/\D/', '', $applicant->phone);
                        $normalizedPhone = substr($normalizedPhone, -10);
                        $q2->orWhere(function ($q3) use ($normalizedPhone) {
                            $q3->where('type', 'phone')
                                ->whereRaw("RIGHT(REGEXP_REPLACE(identifier, '[^0-9]', '', 'g'), 10) = ?", [$normalizedPhone]);
                        });
                    }
                    if ($applicant->email) {
                        $q2->orWhere(function ($q3) use ($applicant) {
                            $q3->where('type', 'email')
                                ->whereRaw('LOWER(identifier) = ?', [strtolower($applicant->email)]);
                        });
                    }
                });
            })
            ->first();

        return $account?->person_id;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_verifications', function (Blueprint $table) {
            $table->dropIndex('data_verifications_entity_index');
            $table->dropColumn(['entity_type', 'entity_id']);
        });

        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropIndex('api_logs_entity_index');
            $table->dropColumn(['entity_type', 'entity_id']);
        });
    }
};
