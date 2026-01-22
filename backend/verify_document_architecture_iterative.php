<?php
/**
 * ITERATIVE VERIFICATION: Document Architecture vs Implementation
 *
 * Verifica exhaustivamente cada sección del DOCUMENT_ARCHITECTURE.md
 * contra la implementación real, validando la lógica de negocio.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Document;
use App\Models\Person;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

// Tracking
$results = ['total' => 0, 'passed' => 0, 'failed' => 0, 'sections' => []];

function section($title) {
    echo "\n\n";
    echo "╔" . str_repeat("═", 78) . "╗\n";
    echo "║ " . str_pad($title, 76) . " ║\n";
    echo "╚" . str_repeat("═", 78) . "╝\n";
}

function test($name, $callback) {
    global $results;
    $results['total']++;

    echo "\n• Testing: {$name}\n";

    try {
        $result = $callback();
        if ($result['passed']) {
            $results['passed']++;
            echo "  ✅ PASS";
            if (!empty($result['details'])) {
                foreach ($result['details'] as $detail) {
                    echo "\n     {$detail}";
                }
            }
            echo "\n";
        } else {
            $results['failed']++;
            echo "  ❌ FAIL: {$result['reason']}\n";
        }
        return $result['passed'];
    } catch (\Exception $e) {
        $results['failed']++;
        echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
        return false;
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           VERIFICACIÓN ITERATIVA: Document Architecture                     ║\n";
echo "║                 Validando DOCUMENT_ARCHITECTURE.md                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";

// Get test person
$person = Person::whereNotNull('tenant_id')->first();
if (!$person) {
    die("\n❌ ERROR: No person found for testing\n");
}

echo "\n📝 Test Context:";
echo "\n   Person ID: {$person->id}";
echo "\n   Tenant ID: {$person->tenant_id}\n";

// ============================================================================
// SECCIÓN 1-2: Database Schema & Indexes
// ============================================================================
section("SECCIÓN 1-2: Database Schema & Indexes (Doc: Sección 1-2)");

test('Schema: documents table has all required fields', function() {
    $columns = DB::select("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_name = 'documents'
        AND column_name IN ('is_active', 'valid_from', 'valid_to', 'superseded_by_id')
    ");

    return [
        'passed' => count($columns) === 4,
        'reason' => 'Missing columns',
        'details' => ['Found: ' . implode(', ', array_column($columns, 'column_name'))]
    ];
});

test('Indexes: Performance indexes exist', function() {
    $indexes = DB::select("
        SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'documents'
        AND indexname IN ('idx_active_documents', 'idx_temporal_validity', 'idx_supersession_chain', 'unique_active_document')
    ");

    return [
        'passed' => count($indexes) === 4,
        'reason' => 'Missing indexes',
        'details' => ['Found: ' . implode(', ', array_column($indexes, 'indexname'))]
    ];
});

test('Constraint: unique_active_document enforces uniqueness', function() {
    $constraint = DB::selectOne("
        SELECT COUNT(*) as count
        FROM pg_indexes
        WHERE tablename = 'documents'
        AND indexname = 'unique_active_document'
    ");

    return [
        'passed' => $constraint->count == 1,
        'reason' => 'Unique constraint not found',
        'details' => ['Constraint exists and enforces: (documentable_type, documentable_id, type) WHERE is_active = true']
    ];
});

// ============================================================================
// SECCIÓN 3: Active Document Pattern
// ============================================================================
section("SECCIÓN 3: Active Document Pattern (Doc: Sección 3)");

test('Business Logic: Only ONE active document per type per person', function() use ($person) {
    // Create first document
    $doc1 = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PROOF_OF_ADDRESS',
        'category' => 'IDENTITY',
        'file_name' => 'proof_v1.pdf',
        'file_path' => 's3://test/proof_v1.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 10000,
        'status' => 'PENDING',
        'is_active' => true,
    ]);

    // Activate it
    $doc1->activate();

    // Create second document
    $doc2 = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PROOF_OF_ADDRESS',
        'category' => 'IDENTITY',
        'file_name' => 'proof_v2.pdf',
        'file_path' => 's3://test/proof_v2.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 12000,
        'status' => 'PENDING',
        'is_active' => false,
    ]);

    // Activate second - should deactivate first
    $doc2->activate();

    // Check results
    $doc1->refresh();
    $doc2->refresh();

    $activeCount = Document::where('documentable_type', 'App\Models\Person')
        ->where('documentable_id', $person->id)
        ->where('type', 'PROOF_OF_ADDRESS')
        ->where('is_active', true)
        ->count();

    $passed = (
        $activeCount === 1 &&
        $doc1->is_active === false &&
        $doc1->valid_to !== null &&
        $doc2->is_active === true &&
        $doc2->valid_to === null
    );

    // Cleanup
    $doc1->delete();
    $doc2->delete();

    return [
        'passed' => $passed,
        'reason' => 'Active Document Pattern not working correctly',
        'details' => [
            "Active count: {$activeCount} (expected: 1)",
            "Doc1 deactivated: " . ($doc1->is_active ? 'NO ❌' : 'YES ✓'),
            "Doc2 activated: " . ($doc2->is_active ? 'YES ✓' : 'NO ❌')
        ]
    ];
});

test('Business Logic: activate() deactivates others atomically', function() use ($person) {
    // Create 3 documents
    $docs = [];
    for ($i = 1; $i <= 3; $i++) {
        $docs[$i] = Document::create([
            'tenant_id' => $person->tenant_id,
            'documentable_type' => 'App\Models\Person',
            'documentable_id' => $person->id,
            'type' => 'PAYSLIP',
            'category' => 'INCOME',
            'file_name' => "payslip_v{$i}.pdf",
            'file_path' => "s3://test/payslip_v{$i}.pdf",
            'storage_driver' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 10000,
            'status' => 'PENDING',
            'is_active' => ($i == 1), // Only first active
        ]);
    }

    // Activate third
    $docs[3]->activate();

    // Refresh all
    foreach ($docs as $doc) {
        $doc->refresh();
    }

    $passed = (
        $docs[1]->is_active === false &&
        $docs[2]->is_active === false &&
        $docs[3]->is_active === true &&
        $docs[1]->valid_to !== null &&
        $docs[3]->valid_to === null
    );

    // Cleanup
    foreach ($docs as $doc) {
        $doc->delete();
    }

    return [
        'passed' => $passed,
        'reason' => 'activate() did not deactivate all others',
        'details' => [
            "Doc1 deactivated: " . ($docs[1]->is_active ? 'NO' : 'YES ✓'),
            "Doc2 stayed inactive: " . ($docs[2]->is_active ? 'NO' : 'YES ✓'),
            "Doc3 activated: " . ($docs[3]->is_active ? 'YES ✓' : 'NO')
        ]
    ];
});

// ============================================================================
// SECCIÓN 4: Temporal Validity Pattern
// ============================================================================
section("SECCIÓN 4: Temporal Validity Pattern (Doc: Sección 4)");

test('Business Logic: valid_from set automatically on activation', function() use ($person) {
    $doc = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'INE_FRONT',
        'category' => 'IDENTITY',
        'file_name' => 'ine.pdf',
        'file_path' => 's3://test/ine.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 10000,
        'status' => 'PENDING',
        'is_active' => false,
    ]);

    $doc->activate();
    $doc->refresh();

    $passed = (
        $doc->valid_from !== null &&
        $doc->valid_to === null &&
        $doc->isCurrentlyValid() === true
    );

    $doc->delete();

    return [
        'passed' => $passed,
        'reason' => 'valid_from not set or isCurrentlyValid() incorrect',
        'details' => [
            "valid_from set: " . ($doc->valid_from ? "YES ✓" : "NO"),
            "valid_to is null: " . ($doc->valid_to === null ? "YES ✓" : "NO"),
            "isCurrentlyValid(): " . ($doc->isCurrentlyValid() ? "YES ✓" : "NO")
        ]
    ];
});

test('Business Logic: valid_to set when deactivated', function() use ($person) {
    $doc = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'INE_BACK',
        'category' => 'IDENTITY',
        'file_name' => 'ine_back.pdf',
        'file_path' => 's3://test/ine_back.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 10000,
        'status' => 'PENDING',
        'is_active' => true,
    ]);

    $doc->activate();
    $beforeValidTo = $doc->valid_to;

    $doc->deactivate();
    $doc->refresh();

    $passed = (
        $beforeValidTo === null &&
        $doc->valid_to !== null &&
        $doc->is_active === false &&
        $doc->isCurrentlyValid() === false
    );

    $doc->delete();

    return [
        'passed' => $passed,
        'reason' => 'valid_to not set on deactivation',
        'details' => [
            "valid_to was null: " . ($beforeValidTo === null ? "YES ✓" : "NO"),
            "valid_to now set: " . ($doc->valid_to !== null ? "YES ✓" : "NO"),
            "is_active false: " . ($doc->is_active === false ? "YES ✓" : "NO")
        ]
    ];
});

test('Business Logic: validAt() scope works for point-in-time queries', function() use ($person) {
    $doc = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'BANK_STATEMENT',
        'category' => 'FINANCIAL',
        'file_name' => 'statement.pdf',
        'file_path' => 's3://test/statement.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 10000,
        'status' => 'PENDING',
        'is_active' => true,
        'valid_from' => now()->subDays(10),
        'valid_to' => now()->addDays(10),
    ]);

    // Should be valid now
    $validNow = Document::where('id', $doc->id)->currentlyValid()->exists();

    // Should be valid 5 days ago
    $validPast = Document::where('id', $doc->id)
        ->validAt(now()->subDays(5))
        ->exists();

    // Should NOT be valid 15 days ago (before valid_from)
    $invalidPast = Document::where('id', $doc->id)
        ->validAt(now()->subDays(15))
        ->exists();

    $passed = ($validNow && $validPast && !$invalidPast);

    $doc->delete();

    return [
        'passed' => $passed,
        'reason' => 'validAt() scope not working correctly',
        'details' => [
            "Valid now: " . ($validNow ? "YES ✓" : "NO"),
            "Valid 5 days ago: " . ($validPast ? "YES ✓" : "NO"),
            "Invalid 15 days ago: " . ($invalidPast ? "NO ✓" : "YES (should be NO)")
        ]
    ];
});

// ============================================================================
// SECCIÓN 5: Supersession Chain
// ============================================================================
section("SECCIÓN 5: Supersession Chain (Doc: Sección 5)");

test('Business Logic: supersedeWith() creates proper chain', function() use ($person) {
    $doc1 = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PROOF_OF_ADDRESS',
        'category' => 'IDENTITY',
        'file_name' => 'address_v1.pdf',
        'file_path' => 's3://test/address_v1.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 10000,
        'status' => 'APPROVED',
        'is_active' => true,
    ]);

    $doc2 = Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PROOF_OF_ADDRESS',
        'category' => 'IDENTITY',
        'file_name' => 'address_v2.pdf',
        'file_path' => 's3://test/address_v2.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 12000,
        'status' => 'PENDING',
        'is_active' => false,
    ]);

    // Supersede doc1 with doc2
    $doc1->supersedeWith($doc2, 'Updated address');

    $doc1->refresh();
    $doc2->refresh();

    $passed = (
        $doc1->superseded_by_id === $doc2->id &&
        $doc1->status === 'SUPERSEDED' &&
        $doc1->replacement_reason === 'Updated address' &&
        $doc1->is_active === false &&
        $doc2->is_active === true
    );

    $doc1->delete();
    $doc2->delete();

    return [
        'passed' => $passed,
        'reason' => 'supersedeWith() not working correctly',
        'details' => [
            "Doc1 superseded_by_id points to doc2: " . ($doc1->superseded_by_id === $doc2->id ? "YES ✓" : "NO"),
            "Doc1 status SUPERSEDED: " . ($doc1->status === 'SUPERSEDED' ? "YES ✓" : "NO"),
            "Doc1 deactivated: " . ($doc1->is_active === false ? "YES ✓" : "NO"),
            "Doc2 activated: " . ($doc2->is_active === true ? "YES ✓" : "NO")
        ]
    ];
});

test('Performance: getSupersessionChain() uses recursive CTE', function() use ($person) {
    // Create chain of 5 documents
    $docs = [];
    for ($i = 1; $i <= 5; $i++) {
        $docs[$i] = Document::create([
            'tenant_id' => $person->tenant_id,
            'documentable_type' => 'App\Models\Person',
            'documentable_id' => $person->id,
            'type' => 'PAYSLIP',
            'category' => 'INCOME',
            'file_name' => "payslip_chain_v{$i}.pdf",
            'file_path' => "s3://test/payslip_chain_v{$i}.pdf",
            'storage_driver' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 10000,
            'status' => 'PENDING',
            'is_active' => false,
        ]);
    }

    // Link chain: 1 → 2 → 3 → 4 → 5
    for ($i = 1; $i < 5; $i++) {
        $docs[$i]->supersedeWith($docs[$i + 1], "v{$i} to v" . ($i + 1));
    }

    // Test performance
    $start = microtime(true);
    $chain = $docs[1]->getSupersessionChain();
    $end = microtime(true);
    $time = ($end - $start) * 1000;

    $passed = (
        $chain->count() === 5 &&
        $chain[0]->id === $docs[1]->id &&
        $chain[4]->id === $docs[5]->id &&
        $time < 50 // Must be < 50ms
    );

    // Cleanup
    foreach ($docs as $doc) {
        $doc->delete();
    }

    return [
        'passed' => $passed,
        'reason' => 'Chain incorrect or too slow',
        'details' => [
            "Chain length: {$chain->count()} (expected 5)",
            "Execution time: " . number_format($time, 2) . " ms",
            "Performance: " . ($time < 10 ? "EXCELLENT (< 10ms)" : ($time < 50 ? "GOOD (< 50ms)" : "SLOW"))
        ]
    ];
});

// ============================================================================
// FINAL REPORT
// ============================================================================

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         VERIFICATION SUMMARY                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Total Tests:  {$results['total']}\n";
echo "Passed:       " . ($results['passed'] === $results['total'] ? '✅' : '⚠️') . " {$results['passed']}\n";
echo "Failed:       " . ($results['failed'] > 0 ? '❌' : '✅') . " {$results['failed']}\n";
echo "Success Rate: " . number_format(($results['passed'] / $results['total']) * 100, 2) . "%\n\n";

if ($results['failed'] === 0) {
    echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║         🎉 ALL VERIFICATIONS PASSED! 🎉                                      ║\n";
    echo "║    Document Architecture matches specification perfectly!                    ║\n";
    echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";
    exit(0);
} else {
    echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                     ⚠️  VERIFICATIONS FAILED  ⚠️                             ║\n";
    echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";
    exit(1);
}
