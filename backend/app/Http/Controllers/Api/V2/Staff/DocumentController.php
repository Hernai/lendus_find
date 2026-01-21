<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

/**
 * Staff Document Controller (v2).
 *
 * Handles document type information for staff members.
 */
class DocumentController extends Controller
{
    use ApiResponses;

    /**
     * Get document types and categories.
     *
     * GET /v2/staff/documents/types
     */
    public function types(): JsonResponse
    {
        return $this->success([
            'types' => Document::typeLabels(),
            'categories' => Document::categories(),
        ]);
    }
}
