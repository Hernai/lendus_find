<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create documentable_relations table for flexible document-entity relationships.
 *
 * This table enables many-to-many polymorphic relationships between documents and any entity.
 * Unlike the single documentable_type/documentable_id in documents table, this allows
 * ONE document to be related to MULTIPLE entities simultaneously.
 *
 * Use cases:
 * - Document belongs to Person AND is used in Application
 * - Document belongs to Person AND PersonIdentification AND Application
 * - Document belongs to Company AND used in multiple Applications
 *
 * Benefits:
 * - Flexible: Can relate document to any number of entities
 * - Contextual: Track WHY the relation exists (relation_context)
 * - Auditable: Know WHO created the relation and WHEN
 * - Queryable: Find all entities related to a document, or all documents for an entity
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentable_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // The document
            $table->uuid('document_id');

            // The related entity (polymorphic)
            $table->string('relatable_type'); // Person, Application, PersonIdentification, etc.
            $table->uuid('relatable_id');

            // Context of the relation
            $table->string('relation_context')->nullable(); // OWNERSHIP, USAGE, REFERENCE, etc.
            $table->text('notes')->nullable();

            // Audit fields
            $table->uuid('created_by')->nullable(); // Who created this relation
            $table->string('created_by_type')->nullable(); // ApplicantAccount, User (staff)

            $table->timestamps();
            $table->softDeletes(); // Allow soft delete for audit trail

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');

            // Indexes for performance
            $table->index(['document_id', 'relatable_type', 'relatable_id']); // Find entities for a document
            $table->index(['relatable_type', 'relatable_id']); // Find documents for an entity
            $table->index(['tenant_id', 'relatable_type']); // Tenant-scoped queries

            // Prevent duplicate relations (same document + entity + context)
            $table->unique(['document_id', 'relatable_type', 'relatable_id', 'relation_context'], 'unique_documentable_relation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentable_relations');
    }
};
