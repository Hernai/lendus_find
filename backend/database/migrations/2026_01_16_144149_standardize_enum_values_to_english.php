<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration to standardize all enum values from Spanish to English.
 *
 * This migration updates existing data in the database to use English enum values
 * for consistency across the codebase. The enums now include normalize() methods
 * to handle legacy Spanish values for backward compatibility.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // For PostgreSQL, drop all CHECK constraints first
        if ($driver === 'pgsql') {
            $this->dropPostgresConstraints();
        }

        // Applicant type: PERSONA_FISICA -> INDIVIDUAL, PERSONA_MORAL -> BUSINESS
        DB::table('applicants')
            ->where('type', 'PERSONA_FISICA')
            ->update(['type' => 'INDIVIDUAL']);
        DB::table('applicants')
            ->where('type', 'PERSONA_MORAL')
            ->update(['type' => 'BUSINESS']);

        // Marital status
        $maritalMap = [
            'SOLTERO' => 'SINGLE',
            'CASADO' => 'MARRIED',
            'UNION_LIBRE' => 'COMMON_LAW',
            'DIVORCIADO' => 'DIVORCED',
            'VIUDO' => 'WIDOWED',
            'SEPARADO' => 'SEPARATED',
        ];
        foreach ($maritalMap as $old => $new) {
            DB::table('applicants')
                ->where('marital_status', $old)
                ->update(['marital_status' => $new]);
        }

        // Education level
        $educationMap = [
            'PRIMARIA' => 'PRIMARY',
            'SECUNDARIA' => 'SECONDARY',
            'PREPARATORIA' => 'HIGH_SCHOOL',
            'TECNICO' => 'TECHNICAL',
            'LICENCIATURA' => 'BACHELOR',
            'MAESTRIA' => 'MASTER',
            'DOCTORADO' => 'DOCTORATE',
        ];
        foreach ($educationMap as $old => $new) {
            DB::table('applicants')
                ->where('education_level', $old)
                ->update(['education_level' => $new]);
        }

        // Employment type in employment_records
        $employmentMap = [
            'EMPLEADO' => 'EMPLOYEE',
            'INDEPENDIENTE' => 'SELF_EMPLOYED',
            'EMPRESARIO' => 'BUSINESS_OWNER',
            'PENSIONADO' => 'RETIRED',
            'ESTUDIANTE' => 'STUDENT',
            'HOGAR' => 'HOMEMAKER',
            'DESEMPLEADO' => 'UNEMPLOYED',
            'OTRO' => 'OTHER',
        ];
        foreach ($employmentMap as $old => $new) {
            DB::table('employment_records')
                ->where('employment_type', $old)
                ->update(['employment_type' => $new]);
        }

        // Contract type in employment_records
        $contractMap = [
            'INDEFINIDO' => 'PERMANENT',
            'TEMPORAL' => 'TEMPORARY',
            'POR_OBRA' => 'PROJECT_BASED',
            'HONORARIOS' => 'FREELANCE',
            'COMISION' => 'COMMISSION',
            'OTRO' => 'OTHER',
        ];
        foreach ($contractMap as $old => $new) {
            DB::table('employment_records')
                ->where('contract_type', $old)
                ->update(['contract_type' => $new]);
        }

        // Income type in employment_records
        $incomeMap = [
            'NOMINA' => 'SALARY',
            'HONORARIOS' => 'FREELANCE',
            'MIXTO' => 'MIXED',
            'COMISIONES' => 'COMMISSION',
            'NEGOCIO_PROPIO' => 'SELF_EMPLOYED',
            'OTRO' => 'OTHER',
        ];
        foreach ($incomeMap as $old => $new) {
            DB::table('employment_records')
                ->where('income_type', $old)
                ->update(['income_type' => $new]);
        }

        // Payment frequency in employment_records and applications
        $frequencyMap = [
            'SEMANAL' => 'WEEKLY',
            'QUINCENAL' => 'BIWEEKLY',
            'MENSUAL' => 'MONTHLY',
        ];
        foreach ($frequencyMap as $old => $new) {
            DB::table('employment_records')
                ->where('payment_frequency', $old)
                ->update(['payment_frequency' => $new]);
            DB::table('applications')
                ->where('payment_frequency', $old)
                ->update(['payment_frequency' => $new]);
        }

        // Bank account type
        $bankAccountMap = [
            'DEBITO' => 'DEBIT',
            'NOMINA' => 'PAYROLL',
            'AHORRO' => 'SAVINGS',
            'CHEQUES' => 'CHECKING',
            'INVERSION' => 'INVESTMENT',
            'OTRO' => 'OTHER',
        ];
        foreach ($bankAccountMap as $old => $new) {
            DB::table('bank_accounts')
                ->where('account_type', $old)
                ->update(['account_type' => $new]);
        }

        // Housing type in addresses
        $housingMap = [
            'PROPIA_PAGADA' => 'OWNED_PAID',
            'PROPIA_HIPOTECA' => 'OWNED_MORTGAGE',
            'RENTADA' => 'RENTED',
            'FAMILIAR' => 'FAMILY',
            'PRESTADA' => 'BORROWED',
            'OTRO' => 'OTHER',
        ];
        foreach ($housingMap as $old => $new) {
            DB::table('addresses')
                ->where('housing_type', $old)
                ->update(['housing_type' => $new]);
        }

        // Company size in employment_records
        $companySizeMap = [
            'PEQUENA' => 'SMALL',
            'MEDIANA' => 'MEDIUM',
            'GRANDE' => 'LARGE',
        ];
        foreach ($companySizeMap as $old => $new) {
            DB::table('employment_records')
                ->where('company_size', $old)
                ->update(['company_size' => $new]);
        }

        // Employment verification method in employment_records
        $verificationMap = [
            'RECIBO_NOMINA' => 'PAYSLIP',
            'CONSTANCIA' => 'EMPLOYMENT_LETTER',
            'LLAMADA' => 'PHONE_CALL',
        ];
        foreach ($verificationMap as $old => $new) {
            DB::table('employment_records')
                ->where('verification_method', $old)
                ->update(['verification_method' => $new]);
        }

        // For PostgreSQL, recreate all CHECK constraints with new values
        if ($driver === 'pgsql') {
            $this->createPostgresConstraints();
        }
    }

    /**
     * Drop PostgreSQL CHECK constraints before updating enum values.
     */
    private function dropPostgresConstraints(): void
    {
        // applicants table
        DB::statement('ALTER TABLE applicants DROP CONSTRAINT IF EXISTS applicants_type_check');
        DB::statement('ALTER TABLE applicants DROP CONSTRAINT IF EXISTS applicants_marital_status_check');
        DB::statement('ALTER TABLE applicants DROP CONSTRAINT IF EXISTS applicants_education_level_check');

        // employment_records table
        DB::statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_employment_type_check');
        DB::statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_contract_type_check');
        DB::statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_income_type_check');
        DB::statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_payment_frequency_check');
        DB::statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_company_size_check');
        DB::statement('ALTER TABLE employment_records DROP CONSTRAINT IF EXISTS employment_records_verification_method_check');

        // applications table
        DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_payment_frequency_check');

        // bank_accounts table
        DB::statement('ALTER TABLE bank_accounts DROP CONSTRAINT IF EXISTS bank_accounts_account_type_check');

        // addresses table
        DB::statement('ALTER TABLE addresses DROP CONSTRAINT IF EXISTS addresses_housing_type_check');
    }

    /**
     * Create PostgreSQL CHECK constraints with new English enum values.
     */
    private function createPostgresConstraints(): void
    {
        // applicants table
        DB::statement("ALTER TABLE applicants ADD CONSTRAINT applicants_type_check CHECK (type IN ('INDIVIDUAL', 'BUSINESS'))");
        DB::statement("ALTER TABLE applicants ADD CONSTRAINT applicants_marital_status_check CHECK (marital_status IN ('SINGLE', 'MARRIED', 'COMMON_LAW', 'DIVORCED', 'WIDOWED', 'SEPARATED'))");
        DB::statement("ALTER TABLE applicants ADD CONSTRAINT applicants_education_level_check CHECK (education_level IN ('PRIMARY', 'SECONDARY', 'HIGH_SCHOOL', 'TECHNICAL', 'BACHELOR', 'MASTER', 'DOCTORATE'))");

        // employment_records table
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_employment_type_check CHECK (employment_type IN ('EMPLOYEE', 'SELF_EMPLOYED', 'BUSINESS_OWNER', 'RETIRED', 'STUDENT', 'HOMEMAKER', 'UNEMPLOYED', 'OTHER'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_contract_type_check CHECK (contract_type IS NULL OR contract_type IN ('PERMANENT', 'TEMPORARY', 'PROJECT_BASED', 'FREELANCE', 'COMMISSION', 'OTHER'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_income_type_check CHECK (income_type IS NULL OR income_type IN ('SALARY', 'FREELANCE', 'MIXED', 'COMMISSION', 'SELF_EMPLOYED', 'OTHER'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_payment_frequency_check CHECK (payment_frequency IS NULL OR payment_frequency IN ('WEEKLY', 'BIWEEKLY', 'MONTHLY'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_company_size_check CHECK (company_size IS NULL OR company_size IN ('SMALL', 'MEDIUM', 'LARGE'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_verification_method_check CHECK (verification_method IS NULL OR verification_method IN ('PAYSLIP', 'EMPLOYMENT_LETTER', 'PHONE_CALL'))");

        // applications table
        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_payment_frequency_check CHECK (payment_frequency IS NULL OR payment_frequency IN ('WEEKLY', 'BIWEEKLY', 'MONTHLY'))");

        // bank_accounts table
        DB::statement("ALTER TABLE bank_accounts ADD CONSTRAINT bank_accounts_account_type_check CHECK (account_type IS NULL OR account_type IN ('DEBIT', 'PAYROLL', 'SAVINGS', 'CHECKING', 'INVESTMENT', 'OTHER'))");

        // addresses table
        DB::statement("ALTER TABLE addresses ADD CONSTRAINT addresses_housing_type_check CHECK (housing_type IS NULL OR housing_type IN ('OWNED_PAID', 'OWNED_MORTGAGE', 'RENTED', 'FAMILY', 'BORROWED', 'OTHER'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        // For PostgreSQL, drop all CHECK constraints first
        if ($driver === 'pgsql') {
            $this->dropPostgresConstraintsDown();
        }

        // Applicant type: INDIVIDUAL -> PERSONA_FISICA, BUSINESS -> PERSONA_MORAL
        DB::table('applicants')
            ->where('type', 'INDIVIDUAL')
            ->update(['type' => 'PERSONA_FISICA']);
        DB::table('applicants')
            ->where('type', 'BUSINESS')
            ->update(['type' => 'PERSONA_MORAL']);

        // Marital status
        $maritalMap = [
            'SINGLE' => 'SOLTERO',
            'MARRIED' => 'CASADO',
            'COMMON_LAW' => 'UNION_LIBRE',
            'DIVORCED' => 'DIVORCIADO',
            'WIDOWED' => 'VIUDO',
            'SEPARATED' => 'SEPARADO',
        ];
        foreach ($maritalMap as $old => $new) {
            DB::table('applicants')
                ->where('marital_status', $old)
                ->update(['marital_status' => $new]);
        }

        // Education level
        $educationMap = [
            'PRIMARY' => 'PRIMARIA',
            'SECONDARY' => 'SECUNDARIA',
            'HIGH_SCHOOL' => 'PREPARATORIA',
            'TECHNICAL' => 'TECNICO',
            'BACHELOR' => 'LICENCIATURA',
            'MASTER' => 'MAESTRIA',
            'DOCTORATE' => 'DOCTORADO',
        ];
        foreach ($educationMap as $old => $new) {
            DB::table('applicants')
                ->where('education_level', $old)
                ->update(['education_level' => $new]);
        }

        // Employment type
        $employmentMap = [
            'EMPLOYEE' => 'EMPLEADO',
            'SELF_EMPLOYED' => 'INDEPENDIENTE',
            'BUSINESS_OWNER' => 'EMPRESARIO',
            'RETIRED' => 'PENSIONADO',
            'STUDENT' => 'ESTUDIANTE',
            'HOMEMAKER' => 'HOGAR',
            'UNEMPLOYED' => 'DESEMPLEADO',
            'OTHER' => 'OTRO',
        ];
        foreach ($employmentMap as $old => $new) {
            DB::table('employment_records')
                ->where('employment_type', $old)
                ->update(['employment_type' => $new]);
        }

        // Contract type
        $contractMap = [
            'PERMANENT' => 'INDEFINIDO',
            'TEMPORARY' => 'TEMPORAL',
            'PROJECT_BASED' => 'POR_OBRA',
            'FREELANCE' => 'HONORARIOS',
            'COMMISSION' => 'COMISION',
            'OTHER' => 'OTRO',
        ];
        foreach ($contractMap as $old => $new) {
            DB::table('employment_records')
                ->where('contract_type', $old)
                ->update(['contract_type' => $new]);
        }

        // Income type
        $incomeMap = [
            'SALARY' => 'NOMINA',
            'FREELANCE' => 'HONORARIOS',
            'MIXED' => 'MIXTO',
            'COMMISSION' => 'COMISIONES',
            'SELF_EMPLOYED' => 'NEGOCIO_PROPIO',
            'OTHER' => 'OTRO',
        ];
        foreach ($incomeMap as $old => $new) {
            DB::table('employment_records')
                ->where('income_type', $old)
                ->update(['income_type' => $new]);
        }

        // Payment frequency
        $frequencyMap = [
            'WEEKLY' => 'SEMANAL',
            'BIWEEKLY' => 'QUINCENAL',
            'MONTHLY' => 'MENSUAL',
        ];
        foreach ($frequencyMap as $old => $new) {
            DB::table('employment_records')
                ->where('payment_frequency', $old)
                ->update(['payment_frequency' => $new]);
            DB::table('applications')
                ->where('payment_frequency', $old)
                ->update(['payment_frequency' => $new]);
        }

        // Bank account type
        $bankAccountMap = [
            'DEBIT' => 'DEBITO',
            'PAYROLL' => 'NOMINA',
            'SAVINGS' => 'AHORRO',
            'CHECKING' => 'CHEQUES',
            'INVESTMENT' => 'INVERSION',
            'OTHER' => 'OTRO',
        ];
        foreach ($bankAccountMap as $old => $new) {
            DB::table('bank_accounts')
                ->where('account_type', $old)
                ->update(['account_type' => $new]);
        }

        // Housing type
        $housingMap = [
            'OWNED_PAID' => 'PROPIA_PAGADA',
            'OWNED_MORTGAGE' => 'PROPIA_HIPOTECA',
            'RENTED' => 'RENTADA',
            'FAMILY' => 'FAMILIAR',
            'BORROWED' => 'PRESTADA',
            'OTHER' => 'OTRO',
        ];
        foreach ($housingMap as $old => $new) {
            DB::table('addresses')
                ->where('housing_type', $old)
                ->update(['housing_type' => $new]);
        }

        // Company size
        $companySizeMap = [
            'SMALL' => 'PEQUENA',
            'MEDIUM' => 'MEDIANA',
            'LARGE' => 'GRANDE',
        ];
        foreach ($companySizeMap as $old => $new) {
            DB::table('employment_records')
                ->where('company_size', $old)
                ->update(['company_size' => $new]);
        }

        // Employment verification method
        $verificationMap = [
            'PAYSLIP' => 'RECIBO_NOMINA',
            'EMPLOYMENT_LETTER' => 'CONSTANCIA',
            'PHONE_CALL' => 'LLAMADA',
        ];
        foreach ($verificationMap as $old => $new) {
            DB::table('employment_records')
                ->where('verification_method', $old)
                ->update(['verification_method' => $new]);
        }

        // For PostgreSQL, recreate all CHECK constraints with Spanish values
        if ($driver === 'pgsql') {
            $this->createPostgresConstraintsDown();
        }
    }

    /**
     * Drop PostgreSQL CHECK constraints before reverting enum values.
     */
    private function dropPostgresConstraintsDown(): void
    {
        // Same as dropPostgresConstraints - constraints have English values
        $this->dropPostgresConstraints();
    }

    /**
     * Create PostgreSQL CHECK constraints with original Spanish enum values.
     */
    private function createPostgresConstraintsDown(): void
    {
        // applicants table
        DB::statement("ALTER TABLE applicants ADD CONSTRAINT applicants_type_check CHECK (type IN ('PERSONA_FISICA', 'PERSONA_MORAL'))");
        DB::statement("ALTER TABLE applicants ADD CONSTRAINT applicants_marital_status_check CHECK (marital_status IN ('SOLTERO', 'CASADO', 'UNION_LIBRE', 'DIVORCIADO', 'VIUDO', 'SEPARADO'))");
        DB::statement("ALTER TABLE applicants ADD CONSTRAINT applicants_education_level_check CHECK (education_level IN ('PRIMARIA', 'SECUNDARIA', 'PREPARATORIA', 'TECNICO', 'LICENCIATURA', 'MAESTRIA', 'DOCTORADO'))");

        // employment_records table
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_employment_type_check CHECK (employment_type IN ('EMPLEADO', 'INDEPENDIENTE', 'EMPRESARIO', 'PENSIONADO', 'ESTUDIANTE', 'HOGAR', 'DESEMPLEADO', 'OTRO'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_contract_type_check CHECK (contract_type IS NULL OR contract_type IN ('INDEFINIDO', 'TEMPORAL', 'POR_OBRA', 'HONORARIOS', 'COMISION', 'OTRO'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_income_type_check CHECK (income_type IS NULL OR income_type IN ('NOMINA', 'HONORARIOS', 'MIXTO', 'COMISIONES', 'NEGOCIO_PROPIO', 'OTRO'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_payment_frequency_check CHECK (payment_frequency IS NULL OR payment_frequency IN ('SEMANAL', 'QUINCENAL', 'MENSUAL'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_company_size_check CHECK (company_size IS NULL OR company_size IN ('PEQUENA', 'MEDIANA', 'GRANDE'))");
        DB::statement("ALTER TABLE employment_records ADD CONSTRAINT employment_records_verification_method_check CHECK (verification_method IS NULL OR verification_method IN ('RECIBO_NOMINA', 'CONSTANCIA', 'LLAMADA'))");

        // applications table
        DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_payment_frequency_check CHECK (payment_frequency IS NULL OR payment_frequency IN ('SEMANAL', 'QUINCENAL', 'MENSUAL'))");

        // bank_accounts table
        DB::statement("ALTER TABLE bank_accounts ADD CONSTRAINT bank_accounts_account_type_check CHECK (account_type IS NULL OR account_type IN ('DEBITO', 'NOMINA', 'AHORRO', 'CHEQUES', 'INVERSION', 'OTRO'))");

        // addresses table
        DB::statement("ALTER TABLE addresses ADD CONSTRAINT addresses_housing_type_check CHECK (housing_type IS NULL OR housing_type IN ('PROPIA_PAGADA', 'PROPIA_HIPOTECA', 'RENTADA', 'FAMILIAR', 'PRESTADA', 'OTRO'))");
    }
};
