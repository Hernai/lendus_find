<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing document snapshots for applications.
 *
 * Creates historical records of which documents were used for each application.
 * This preserves audit trail even if the person uploads new documents later.
 */
class ApplicationDocumentSnapshotService
{
    /**
     * Create snapshots of all required documents for an application.
     *
     * Uses documentable_relations to create USAGE relationships between documents and application.
     * This preserves which documents were used even if person uploads new ones later.
     *
     * @param Application $application
     * @param string $context SUBMISSION, APPROVAL, MANUAL_ATTACH
     * @param string|null $attachedBy User ID who triggered the snapshot
     * @return int Number of documents attached
     */
    public function createSnapshot(
        Application $application,
        string $context = 'SUBMISSION',
        ?string $attachedBy = null
    ): int {
        $person = $application->person;
        if (!$person) {
            Log::warning('Cannot create snapshot: application has no person', [
                'application_id' => $application->id,
            ]);
            return 0;
        }

        // Get all active documents for this person
        // Only include is_active = true documents (Active Document Pattern)
        $documents = Document::where('documentable_type', get_class($person))
            ->where('documentable_id', $person->id)
            ->where('is_active', true)
            ->currentlyValid()
            ->get();

        if ($documents->isEmpty()) {
            Log::info('No active documents found for snapshot', [
                'application_id' => $application->id,
                'person_id' => $person->id,
            ]);
            return 0;
        }

        // Create snapshots in transaction using documentable_relations
        return DB::transaction(function () use ($application, $person, $documents, $context, $attachedBy) {
            $attached = 0;

            foreach ($documents as $document) {
                // Check if USAGE relation already exists for this document type
                $existingRelation = DB::table('documentable_relations')
                    ->join('documents', 'documentable_relations.document_id', '=', 'documents.id')
                    ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
                    ->where('documentable_relations.relatable_id', $application->id)
                    ->where('documentable_relations.relation_context', 'USAGE')
                    ->where('documents.type', $document->type)
                    ->whereNull('documentable_relations.deleted_at')
                    ->select('documentable_relations.*', 'documents.id as doc_id')
                    ->first();

                if ($existingRelation) {
                    // Update existing relation if context is APPROVAL (more authoritative)
                    if ($context === 'APPROVAL') {
                        // Soft delete old relation if it's a different document
                        if ($existingRelation->document_id !== $document->id) {
                            DB::table('documentable_relations')
                                ->where('id', $existingRelation->id)
                                ->update(['deleted_at' => now()]);

                            // Create new relation
                            DB::table('documentable_relations')->insert([
                                'id' => \Illuminate\Support\Str::uuid(),
                                'tenant_id' => $application->tenant_id,
                                'document_id' => $document->id,
                                'relatable_type' => 'App\\Models\\Application',
                                'relatable_id' => $application->id,
                                'relation_context' => 'USAGE',
                                'notes' => "Context: {$context}",
                                'created_by' => $attachedBy,
                                'created_by_type' => 'App\\Models\\StaffAccount',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            // Same document, just update notes
                            DB::table('documentable_relations')
                                ->where('id', $existingRelation->id)
                                ->update([
                                    'notes' => "Context: {$context}",
                                    'updated_at' => now(),
                                ]);
                        }
                        $attached++;
                    }
                    continue;
                }

                // Create OWNERSHIP relation if not exists
                $ownershipExists = DB::table('documentable_relations')
                    ->where('document_id', $document->id)
                    ->where('relatable_type', 'App\\Models\\Person')
                    ->where('relatable_id', $person->id)
                    ->where('relation_context', 'OWNERSHIP')
                    ->whereNull('deleted_at')
                    ->exists();

                if (!$ownershipExists) {
                    DB::table('documentable_relations')->insert([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'tenant_id' => $application->tenant_id,
                        'document_id' => $document->id,
                        'relatable_type' => 'App\\Models\\Person',
                        'relatable_id' => $person->id,
                        'relation_context' => 'OWNERSHIP',
                        'created_by' => $attachedBy,
                        'created_by_type' => 'App\\Models\\StaffAccount',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Create new USAGE relation
                DB::table('documentable_relations')->insert([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'tenant_id' => $application->tenant_id,
                    'document_id' => $document->id,
                    'relatable_type' => 'App\\Models\\Application',
                    'relatable_id' => $application->id,
                    'relation_context' => 'USAGE',
                    'notes' => "Context: {$context}",
                    'created_by' => $attachedBy,
                    'created_by_type' => 'App\\Models\\StaffAccount',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $attached++;
            }

            Log::info('Document snapshots created via documentable_relations', [
                'application_id' => $application->id,
                'context' => $context,
                'documents_attached' => $attached,
            ]);

            return $attached;
        });
    }

    /**
     * Get snapshot documents for an application.
     *
     * @param Application $application
     * @return \Illuminate\Support\Collection
     */
    public function getSnapshotDocuments(Application $application)
    {
        return $application->snapshotDocuments()
            ->with(['tenant'])
            ->get();
    }

    /**
     * Check if all required documents have been attached to application.
     *
     * Uses documentable_relations to check USAGE relationships.
     *
     * @param Application $application
     * @return bool
     */
    public function hasAllRequiredDocuments(Application $application): bool
    {
        $product = $application->product;
        if (!$product || !isset($product->required_documents)) {
            return true; // No requirements defined
        }

        $requiredTypes = $product->required_documents;

        // Get attached document types via documentable_relations
        $attachedTypes = DB::table('documentable_relations')
            ->join('documents', 'documentable_relations.document_id', '=', 'documents.id')
            ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
            ->where('documentable_relations.relatable_id', $application->id)
            ->where('documentable_relations.relation_context', 'USAGE')
            ->whereNull('documentable_relations.deleted_at')
            ->pluck('documents.type')
            ->toArray();

        foreach ($requiredTypes as $type) {
            if (!in_array($type, $attachedTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing document types for an application.
     *
     * Uses documentable_relations to check USAGE relationships.
     *
     * @param Application $application
     * @return array
     */
    public function getMissingDocumentTypes(Application $application): array
    {
        $product = $application->product;
        if (!$product || !isset($product->required_documents)) {
            return [];
        }

        $requiredTypes = $product->required_documents;

        // Get attached document types via documentable_relations
        $attachedTypes = DB::table('documentable_relations')
            ->join('documents', 'documentable_relations.document_id', '=', 'documents.id')
            ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
            ->where('documentable_relations.relatable_id', $application->id)
            ->where('documentable_relations.relation_context', 'USAGE')
            ->whereNull('documentable_relations.deleted_at')
            ->pluck('documents.type')
            ->toArray();

        return array_values(array_diff($requiredTypes, $attachedTypes));
    }
}
