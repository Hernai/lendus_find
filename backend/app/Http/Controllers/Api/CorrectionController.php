<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\DataVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CorrectionController extends Controller
{
    /**
     * Get list of rejected fields that need correction.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'data' => [],
                'message' => 'No applicant profile found',
            ]);
        }

        // Get all rejected verifications
        $rejectedFields = DataVerification::where('applicant_id', $applicant->id)
            ->rejected()
            ->get()
            ->map(fn ($verification) => [
                'id' => $verification->id,
                'field_name' => $verification->field_name,
                'field_label' => DataVerification::getFieldLabel($verification->field_name),
                'current_value' => $verification->field_value,
                'rejection_reason' => $verification->rejection_reason,
                'rejected_at' => $verification->rejected_at?->toIso8601String(),
            ]);

        // Get applications with CORRECTIONS_PENDING status
        $pendingApplications = Application::where('applicant_id', $applicant->id)
            ->where('status', Application::STATUS_CORRECTIONS_PENDING)
            ->get(['id', 'folio', 'status', 'updated_at']);

        return response()->json([
            'data' => [
                'rejected_fields' => $rejectedFields,
                'pending_applications' => $pendingApplications,
                'has_corrections_pending' => $rejectedFields->count() > 0,
            ],
        ]);
    }

    /**
     * Submit a correction for a rejected field.
     */
    public function submitCorrection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'field_name' => 'required|string|max:100',
            'new_value' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'No applicant profile found',
            ], 404);
        }

        // Find the rejected verification
        $verification = DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $request->field_name)
            ->rejected()
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'No pending correction found for this field',
            ], 404);
        }

        $oldValue = $verification->field_value;
        $newValue = $request->new_value;

        // Update the field value based on field_name
        $this->updateApplicantField($applicant, $request->field_name, $newValue);

        // Update verification record
        $verification->field_value = is_array($newValue) ? json_encode($newValue) : $newValue;
        $verification->status = DataVerification::STATUS_CORRECTED;
        $verification->corrected_at = now();
        $verification->save();

        // Log the correction
        $metadata = $request->attributes->get('metadata', []);
        $tenant = $request->attributes->get('tenant');
        AuditLog::log(
            AuditLog::ACTION_DATA_CORRECTED,
            $tenant->id,
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $applicant->id,
                'entity_type' => 'DataVerification',
                'entity_id' => $verification->id,
                'old_values' => ['value' => $oldValue],
                'new_values' => ['value' => $newValue],
                'metadata' => [
                    'field_name' => $request->field_name,
                    'field_label' => DataVerification::getFieldLabel($request->field_name),
                ],
            ])
        );

        // Check if all corrections are done to update application status
        $this->checkAndUpdateApplicationStatus($applicant);

        return response()->json([
            'message' => 'Corrección enviada correctamente',
            'data' => [
                'field_name' => $request->field_name,
                'status' => 'corrected',
            ],
        ]);
    }

    /**
     * Update the applicant field based on field_name.
     */
    protected function updateApplicantField($applicant, string $fieldName, $value): void
    {
        switch ($fieldName) {
            case DataVerification::FIELD_FIRST_NAME:
                $applicant->first_name = $value;
                break;
            case DataVerification::FIELD_LAST_NAME_1:
                $applicant->last_name_1 = $value;
                break;
            case DataVerification::FIELD_LAST_NAME_2:
                $applicant->last_name_2 = $value;
                break;
            case DataVerification::FIELD_CURP:
                $personalData = $applicant->personal_data ?? [];
                $personalData['curp'] = $value;
                $applicant->personal_data = $personalData;
                break;
            case DataVerification::FIELD_RFC:
                $personalData = $applicant->personal_data ?? [];
                $personalData['rfc'] = $value;
                $applicant->personal_data = $personalData;
                break;
            case DataVerification::FIELD_INE:
                $personalData = $applicant->personal_data ?? [];
                $personalData['ine_clave'] = $value;
                $applicant->personal_data = $personalData;
                break;
            case DataVerification::FIELD_BIRTH_DATE:
                $personalData = $applicant->personal_data ?? [];
                $personalData['birth_date'] = $value;
                $applicant->personal_data = $personalData;
                break;
            case DataVerification::FIELD_PHONE:
                $applicant->phone = $value;
                break;
            case DataVerification::FIELD_EMAIL:
                $applicant->email = $value;
                break;
            case DataVerification::FIELD_ADDRESS:
                // For address, expect full address object
                if (is_array($value) && $applicant->primaryAddress) {
                    $address = $applicant->primaryAddress;
                    $address->fill($value);
                    $address->save();
                }
                break;
            case DataVerification::FIELD_EMPLOYMENT:
                // For employment, expect full employment object
                if (is_array($value)) {
                    $applicant->employment_info = array_merge(
                        $applicant->employment_info ?? [],
                        $value
                    );
                }
                break;
        }

        $applicant->save();
    }

    /**
     * Check if all corrections are done and update application status.
     */
    protected function checkAndUpdateApplicationStatus($applicant): void
    {
        // Check if there are still rejected fields
        $stillRejected = DataVerification::where('applicant_id', $applicant->id)
            ->rejected()
            ->exists();

        if ($stillRejected) {
            return; // Still has corrections pending
        }

        // Update any applications in CORRECTIONS_PENDING status
        $applications = Application::where('applicant_id', $applicant->id)
            ->where('status', Application::STATUS_CORRECTIONS_PENDING)
            ->get();

        foreach ($applications as $application) {
            // Move back to IN_REVIEW status
            $application->changeStatus(
                Application::STATUS_IN_REVIEW,
                'Correcciones completadas por el solicitante'
            );
        }
    }

    /**
     * Get correction details for a specific field.
     */
    public function show(Request $request, string $fieldName): JsonResponse
    {
        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'No applicant profile found',
            ], 404);
        }

        $verification = DataVerification::where('applicant_id', $applicant->id)
            ->where('field_name', $fieldName)
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Field verification not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $verification->id,
                'field_name' => $verification->field_name,
                'field_label' => DataVerification::getFieldLabel($verification->field_name),
                'current_value' => $verification->field_value,
                'status' => $verification->status,
                'status_label' => DataVerification::getStatusLabel($verification->status),
                'rejection_reason' => $verification->rejection_reason,
                'rejected_at' => $verification->rejected_at?->toIso8601String(),
                'corrected_at' => $verification->corrected_at?->toIso8601String(),
                'is_rejected' => $verification->isRejected(),
            ],
        ]);
    }
}
