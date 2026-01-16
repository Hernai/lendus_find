<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApplicantHelpers;
use App\Models\EmploymentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Controller for managing applicant employment records.
 *
 * Single Responsibility: Handle CRUD operations for employment records only.
 */
class EmploymentController extends Controller
{
    use ApplicantHelpers;

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
            'data' => $applicant->employmentRecords->map(fn($e) => $this->formatEmployment($e))
        ]);
    }

    /**
     * Add a new employment record.
     */
    public function store(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'is_current' => 'sometimes|boolean',
            'employment_type' => 'required|in:EMPLEADO,INDEPENDIENTE,EMPRESARIO,PENSIONADO,ESTUDIANTE,HOGAR,DESEMPLEADO,OTRO',
            'company_name' => 'nullable|string|max:200',
            'company_rfc' => 'nullable|string|max:13',
            'company_industry' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'contract_type' => 'nullable|in:INDEFINIDO,TEMPORAL,POR_OBRA,HONORARIOS,COMISION,OTRO',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'seniority_months' => 'required|integer|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_net_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'sometimes|in:SEMANAL,QUINCENAL,MENSUAL',
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
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
            'company_name' => $request->company_name ? strtoupper($request->company_name) : null,
            'company_rfc' => $request->company_rfc ? strtoupper($request->company_rfc) : null,
            'company_industry' => $request->company_industry,
            'position' => $request->position ? strtoupper($request->position) : null,
            'contract_type' => $request->contract_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'seniority_months' => $request->seniority_months,
            'monthly_income' => $request->monthly_income,
            'monthly_net_income' => $request->monthly_net_income,
            'payment_frequency' => $request->input('payment_frequency', 'MENSUAL'),
            'other_income' => $request->other_income,
            'other_income_source' => $request->other_income_source,
        ]);

        return response()->json([
            'message' => 'Employment record added',
            'data' => $this->formatEmployment($employment)
        ], 201);
    }

    /**
     * Update the current employment (Step 4 of onboarding).
     */
    public function updateCurrent(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'employment_type' => 'required|in:EMPLEADO,INDEPENDIENTE,EMPRESARIO,PENSIONADO,ESTUDIANTE,HOGAR,DESEMPLEADO,OTRO',
            'company_name' => 'nullable|string|max:200',
            'company_rfc' => 'nullable|string|max:13',
            'company_industry' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'contract_type' => 'nullable|in:INDEFINIDO,TEMPORAL,POR_OBRA,HONORARIOS,COMISION,OTRO',
            'start_date' => 'nullable|date',
            'seniority_months' => 'required|integer|min:0',
            'monthly_income' => 'required|numeric|min:0',
            'monthly_net_income' => 'nullable|numeric|min:0',
            'payment_frequency' => 'sometimes|in:SEMANAL,QUINCENAL,MENSUAL',
            'income_type' => 'nullable|in:NOMINA,HONORARIOS,MIXTO,COMISIONES,NEGOCIO_PROPIO,PENSION,OTRO',
            'other_income' => 'nullable|numeric|min:0',
            'other_income_source' => 'nullable|string|max:200',
            'work_phone' => 'nullable|string|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
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
            'company_name' => $request->company_name ? strtoupper($request->company_name) : null,
            'company_rfc' => $request->company_rfc ? strtoupper($request->company_rfc) : null,
            'company_industry' => $request->company_industry,
            'position' => $request->position ? strtoupper($request->position) : null,
            'department' => $request->department,
            'contract_type' => $request->contract_type,
            'start_date' => $request->start_date,
            'seniority_months' => $request->seniority_months,
            'monthly_income' => $request->monthly_income,
            'monthly_net_income' => $request->monthly_net_income,
            'payment_frequency' => $request->input('payment_frequency', 'MENSUAL'),
            'income_type' => $request->income_type,
            'other_income' => $request->other_income,
            'other_income_source' => $request->other_income_source,
            'work_phone' => $request->work_phone,
        ]);

        return response()->json([
            'message' => 'Employment info updated',
            'data' => $this->formatEmployment($employment)
        ]);
    }
}
