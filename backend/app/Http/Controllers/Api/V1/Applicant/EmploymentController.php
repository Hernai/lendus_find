<?php

namespace App\Http\Controllers\Api\V1\Applicant;

use App\Enums\ContractType;
use App\Enums\EmploymentType;
use App\Enums\IncomeType;
use App\Enums\PaymentFrequency;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApplicantHelpers;
use App\Http\Traits\ValidationHelpers;
use App\Http\Resources\EmploymentRecordResource;
use App\Models\EmploymentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Controller for managing applicant employment records.
 *
 * Single Responsibility: Handle CRUD operations for employment records only.
 */
class EmploymentController extends Controller
{
    use ApplicantHelpers;
    use ValidationHelpers;

    /**
     * List all employment records for the applicant.
     */
    public function index(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => EmploymentRecordResource::collection($applicant->employmentRecords)
        ]);
    }

    /**
     * Add a new employment record.
     */
    public function store(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validation = $this->validateRequest($request->all(), [
            'is_current' => 'sometimes|boolean',
            'employment_type' => ['required', Rule::in(EmploymentType::values())],
            'company_name' => 'nullable|string|max:200',
            'company_rfc' => 'nullable|string|max:13',
            'company_industry' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'contract_type' => ['nullable', Rule::in(ContractType::values())],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'seniority_months' => 'required|integer|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_net_income' => 'nullable|numeric|min:0',
            'payment_frequency' => ['sometimes', Rule::in(PaymentFrequency::values())],
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        // If setting as current, unset other current records
        if ($request->input('is_current', false)) {
            $applicant->employmentRecords()->where('is_current', true)->update(['is_current' => false]);
        }

        $employment = EmploymentRecord::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'is_current' => $request->input('is_current', false),
            'employment_type' => $request->employment_type,
            'company_name' => $request->company_name,
            'company_rfc' => $request->company_rfc,
            'company_industry' => $request->company_industry,
            'position' => $request->position,
            'contract_type' => $request->contract_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'seniority_months' => $request->seniority_months,
            'monthly_income' => $request->monthly_income,
            'monthly_net_income' => $request->monthly_net_income,
            'payment_frequency' => $request->input('payment_frequency', PaymentFrequency::MONTHLY->value),
            'other_income' => $request->other_income,
            'other_income_source' => $request->other_income_source,
        ]);

        return response()->json([
            'message' => 'Registro de empleo agregado',
            'data' => new EmploymentRecordResource($employment)
        ], 201);
    }

    /**
     * Update the current employment (Step 4 of onboarding).
     */
    public function updateCurrent(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validation = $this->validateRequest($request->all(), [
            'employment_type' => ['required', Rule::in(EmploymentType::values())],
            'company_name' => 'nullable|string|max:200',
            'company_rfc' => 'nullable|string|max:13',
            'company_industry' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'contract_type' => ['nullable', Rule::in(ContractType::values())],
            'start_date' => 'nullable|date',
            'seniority_months' => 'required|integer|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_net_income' => 'nullable|numeric|min:0',
            'payment_frequency' => ['sometimes', Rule::in(PaymentFrequency::values())],
            'income_type' => ['nullable', Rule::in(IncomeType::values())],
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
            'work_phone' => 'nullable|string|max:15',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        // Deactivate any existing current employment
        $applicant->employmentRecords()->where('is_current', true)->update(['is_current' => false]);

        // Create new current employment
        $employment = EmploymentRecord::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'is_current' => true,
            'employment_type' => $request->employment_type,
            'company_name' => $request->company_name,
            'company_rfc' => $request->company_rfc,
            'company_industry' => $request->company_industry,
            'position' => $request->position,
            'department' => $request->department,
            'contract_type' => $request->contract_type,
            'start_date' => $request->start_date,
            'seniority_months' => $request->seniority_months,
            'monthly_income' => $request->monthly_income,
            'monthly_net_income' => $request->monthly_net_income,
            'payment_frequency' => $request->input('payment_frequency', PaymentFrequency::MONTHLY->value),
            'income_type' => $request->income_type,
            'other_income' => $request->other_income,
            'other_income_source' => $request->other_income_source,
            'work_phone' => $request->work_phone,
        ]);

        return response()->json([
            'message' => 'Información de empleo actualizada',
            'data' => new EmploymentRecordResource($employment)
        ]);
    }
}
