<?php
/**
 * COMPREHENSIVE TEST SUITE: Document Architecture
 *
 * Ejecuta todas las pruebas del document architecture implementation
 * de manera exhaustiva y genera reporte completo.
 *
 * Usage: php backend/test_document_architecture.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Test Results Tracker
$results = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'tests' => []
];

function test($name, $callback) {
    global $results;
    $results['total']++;

    echo "\n" . str_repeat('=', 70) . "\n";
    echo "TEST: {$name}\n";
    echo str_repeat('=', 70) . "\n";

    try {
        $result = $callback();
        if ($result['passed']) {
            $results['passed']++;
            echo "\n✅ PASS\n";
        } else {
            $results['failed']++;
            echo "\n❌ FAIL: {$result['reason']}\n";
        }
        $results['tests'][] = [
            'name' => $name,
            'passed' => $result['passed'],
            'reason' => $result['reason'] ?? '',
            'details' => $result['details'] ?? []
        ];
    } catch (\Exception $e) {
        $results['failed']++;
        echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
        $results['tests'][] = [
            'name' => $name,
            'passed' => false,
            'reason' => 'Exception: ' . $e->getMessage()
        ];
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                  DOCUMENT ARCHITECTURE TEST SUITE                    ║\n";
echo "║                    Exhaustive Testing Mode                           ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n";

// ============================================================================
// PHASE 1: DATABASE SCHEMA
// ============================================================================

test('Database Schema: documents table has new fields', function() {
    $columns = DB::select("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'documents'
        AND column_name IN ('is_active', 'valid_from', 'valid_to', 'superseded_by_id')
    ");

    $passed = count($columns) === 4;
    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'Missing columns',
        'details' => array_map(fn($c) => "{$c->column_name}: {$c->data_type}", $columns)
    ];
});

test('Database Schema: Performance indexes exist', function() {
    $indexes = DB::select("
        SELECT indexname
        FROM pg_indexes
        WHERE tablename = 'documents'
        AND (indexname LIKE 'idx_%' OR indexname = 'unique_active_document')
    ");

    $expected = ['idx_active_documents', 'idx_supersession_chain', 'idx_temporal_validity', 'unique_active_document'];
    $found = array_map(fn($i) => $i->indexname, $indexes);
    $passed = empty(array_diff($expected, $found));

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'Missing indexes: ' . implode(', ', array_diff($expected, $found)),
        'details' => $found
    ];
});

test('Data Integrity: No duplicate active documents', function() {
    $duplicates = DB::select("
        SELECT documentable_type, documentable_id, type, COUNT(*) as active_count
        FROM documents
        WHERE is_active = true
        GROUP BY documentable_type, documentable_id, type
        HAVING COUNT(*) > 1
    ");

    return [
        'passed' => count($duplicates) === 0,
        'reason' => count($duplicates) > 0 ? count($duplicates) . ' duplicates found' : '',
        'details' => array_map(fn($d) => "{$d->type}: {$d->active_count} active", $duplicates)
    ];
});

test('Data Integrity: No orphaned supersession references', function() {
    $orphans = DB::selectOne("
        SELECT COUNT(*) as count
        FROM documents
        WHERE superseded_by_id IS NOT NULL
        AND superseded_by_id NOT IN (SELECT id FROM documents)
    ")->count;

    return [
        'passed' => $orphans == 0,
        'reason' => $orphans > 0 ? "{$orphans} orphaned references" : '',
        'details' => ["Orphaned refs: {$orphans}"]
    ];
});

test('Data Integrity: No orphaned documentable_relations', function() {
    $orphans = DB::selectOne("
        SELECT COUNT(*) as count
        FROM documentable_relations
        WHERE document_id NOT IN (SELECT id FROM documents)
    ")->count;

    return [
        'passed' => $orphans == 0,
        'reason' => $orphans > 0 ? "{$orphans} orphaned relations" : '',
        'details' => ["Orphaned relations: {$orphans}"]
    ];
});

// ============================================================================
// PHASE 2: DOCUMENT MODEL METHODS
// ============================================================================

test('Document Model: activate() method', function() {
    $person = App\Models\Person::whereNotNull('tenant_id')->first();
    if (!$person) {
        return ['passed' => false, 'reason' => 'No person found for testing'];
    }

    // Create document
    $doc = App\Models\Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PROOF_OF_ADDRESS',
        'category' => 'IDENTITY',
        'file_name' => 'test_activate.pdf',
        'file_path' => 's3://test/test.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 12345,
        'status' => 'PENDING',
        'is_active' => false,
    ]);

    // Call activate()
    $doc->activate();
    $doc->refresh();

    // Verify
    $activeCount = App\Models\Document::where('documentable_type', 'App\Models\Person')
        ->where('documentable_id', $person->id)
        ->where('type', 'PROOF_OF_ADDRESS')
        ->where('is_active', true)
        ->count();

    $passed = ($activeCount === 1) && ($doc->is_active === true) && ($doc->valid_to === null);

    // Cleanup
    $doc->delete();

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'activate() did not work correctly',
        'details' => [
            "Active count: {$activeCount} (should be 1)",
            "Doc is_active: " . ($doc->is_active ? 'true' : 'false'),
            "Doc valid_to: " . ($doc->valid_to ? 'set' : 'NULL')
        ]
    ];
});

test('Document Model: supersedeWith() method', function() {
    $person = App\Models\Person::whereNotNull('tenant_id')->first();

    // Create old doc
    $oldDoc = App\Models\Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PAYSLIP',
        'category' => 'INCOME',
        'file_name' => 'old.pdf',
        'file_path' => 's3://test/old.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 10000,
        'status' => 'APPROVED',
        'is_active' => true,
    ]);

    // Create new doc
    $newDoc = App\Models\Document::create([
        'tenant_id' => $person->tenant_id,
        'documentable_type' => 'App\Models\Person',
        'documentable_id' => $person->id,
        'type' => 'PAYSLIP',
        'category' => 'INCOME',
        'file_name' => 'new.pdf',
        'file_path' => 's3://test/new.pdf',
        'storage_driver' => 'local',
        'mime_type' => 'application/pdf',
        'file_size' => 12000,
        'status' => 'PENDING',
        'is_active' => false,
    ]);

    // Call supersedeWith()
    $oldDoc->supersedeWith($newDoc, 'Updated');
    $oldDoc->refresh();
    $newDoc->refresh();

    // Verify
    $passed = (
        $oldDoc->superseded_by_id === $newDoc->id &&
        $oldDoc->status === 'SUPERSEDED' &&
        $oldDoc->is_active === false &&
        $oldDoc->valid_to !== null &&
        $newDoc->is_active === true &&
        $newDoc->valid_to === null
    );

    // Cleanup
    $oldDoc->delete();
    $newDoc->delete();

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'supersedeWith() did not work correctly'
    ];
});

test('Document Model: getSupersessionChain() performance', function() {
    $person = App\Models\Person::whereNotNull('tenant_id')->first();

    // Create chain of 5 documents
    $docs = [];
    for ($i = 1; $i <= 5; $i++) {
        $docs[$i] = App\Models\Document::create([
            'tenant_id' => $person->tenant_id,
            'documentable_type' => 'App\Models\Person',
            'documentable_id' => $person->id,
            'type' => 'BANK_STATEMENT',
            'category' => 'FINANCIAL',
            'file_name' => "bank_v{$i}.pdf",
            'file_path' => "s3://test/bank_v{$i}.pdf",
            'storage_driver' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => 10000 + $i,
            'status' => 'PENDING',
            'is_active' => false,
        ]);
    }

    // Link chain
    for ($i = 1; $i < 5; $i++) {
        $docs[$i]->supersedeWith($docs[$i + 1], "v{$i} to v" . ($i + 1));
    }

    // Test performance
    $start = microtime(true);
    $chain = $docs[1]->getSupersessionChain();
    $end = microtime(true);
    $time = ($end - $start) * 1000; // ms

    $passed = (
        $chain->count() === 5 &&
        $chain[0]->id === $docs[1]->id &&
        $chain[4]->id === $docs[5]->id &&
        $time < 50
    );

    // Cleanup
    foreach ($docs as $doc) {
        $doc->delete();
    }

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'Chain incorrect or too slow',
        'details' => [
            "Chain length: {$chain->count()} (expected 5)",
            "Execution time: " . number_format($time, 2) . " ms (should be < 50ms)",
            "Performance: " . ($time < 10 ? 'EXCELLENT' : ($time < 50 ? 'GOOD' : 'SLOW'))
        ]
    ];
});

test('Document Model: Scopes work correctly', function() {
    $activeViaScope = App\Models\Document::isActive()->count();
    $activeViaWhere = App\Models\Document::where('is_active', true)->count();

    $currentlyValid = App\Models\Document::currentlyValid()->count();
    $superseded = App\Models\Document::superseded()->count();

    $passed = ($activeViaScope === $activeViaWhere);

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'Scopes mismatch',
        'details' => [
            "isActive scope: {$activeViaScope}",
            "currentlyValid: {$currentlyValid}",
            "superseded: {$superseded}"
        ]
    ];
});

// ============================================================================
// PHASE 3: CONTROLLERS
// ============================================================================

test('DocumentHistoryController routes exist', function() {
    $routes = [
        'documents.history' => 'v2/applicant/documents/history/{type}',
        'documents.supersession-chain' => 'v2/applicant/documents/{id}/supersession-chain',
        'documents.valid-at' => 'v2/applicant/documents/valid-at',
        'documents.timeline' => 'v2/applicant/documents/timeline'
    ];

    $routeCollection = app('router')->getRoutes();
    $found = 0;

    foreach ($routeCollection as $route) {
        $uri = $route->uri();
        foreach ($routes as $expected) {
            if (strpos($uri, $expected) !== false) {
                $found++;
                break;
            }
        }
    }

    $passed = $found >= 4;

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : "Only found {$found}/4 routes",
        'details' => ["Routes found: {$found}/4"]
    ];
});

test('ApplicationController has metadata in status_history', function() {
    // This tests the code exists, not the runtime behavior
    $file = file_get_contents(__DIR__ . '/app/Http/Controllers/Api/V2/Staff/ApplicationController.php');

    $hasMetadataExtraction = strpos($file, '$metadata = $h->metadata ?? []') !== false;
    $hasIpAddress = strpos($file, "'ip_address' => \$metadata['ip_address'] ?? null") !== false;
    $hasUserAgent = strpos($file, "'user_agent' => \$metadata['user_agent'] ?? null") !== false;
    $hasMetadataField = strpos($file, "'metadata' => \$metadata") !== false;
    $hasLifecycleEvent = strpos($file, "'is_lifecycle_event'") !== false;

    $passed = $hasMetadataExtraction && $hasIpAddress && $hasUserAgent && $hasMetadataField && $hasLifecycleEvent;

    return [
        'passed' => $passed,
        'reason' => $passed ? '' : 'Missing metadata fields in status_history mapping',
        'details' => [
            "metadata extraction: " . ($hasMetadataExtraction ? '✓' : '✗'),
            "ip_address field: " . ($hasIpAddress ? '✓' : '✗'),
            "user_agent field: " . ($hasUserAgent ? '✓' : '✗'),
            "metadata field: " . ($hasMetadataField ? '✓' : '✗'),
            "is_lifecycle_event: " . ($hasLifecycleEvent ? '✓' : '✗')
        ]
    ];
});

// ============================================================================
// FINAL REPORT
// ============================================================================

echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║                         TEST RESULTS SUMMARY                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

echo "Total Tests:  {$results['total']}\n";
echo "Passed:       " . ($results['passed'] === $results['total'] ? '✅' : '⚠️') . " {$results['passed']}\n";
echo "Failed:       " . ($results['failed'] > 0 ? '❌' : '✅') . " {$results['failed']}\n";
echo "Success Rate: " . number_format(($results['passed'] / $results['total']) * 100, 2) . "%\n\n";

if ($results['failed'] > 0) {
    echo "Failed Tests:\n";
    foreach ($results['tests'] as $test) {
        if (!$test['passed']) {
            echo "  ❌ {$test['name']}\n";
            echo "     Reason: {$test['reason']}\n";
        }
    }
    echo "\n";
}

echo "Detailed Results:\n";
foreach ($results['tests'] as $i => $test) {
    $status = $test['passed'] ? '✅ PASS' : '❌ FAIL';
    echo sprintf("%2d. %s %s\n", $i + 1, $status, $test['name']);
    if (!empty($test['details'])) {
        foreach ($test['details'] as $detail) {
            echo "    - {$detail}\n";
        }
    }
}

echo "\n";
if ($results['failed'] === 0) {
    echo "╔══════════════════════════════════════════════════════════════════════╗\n";
    echo "║                   🎉 ALL TESTS PASSED! 🎉                            ║\n";
    echo "║          Document Architecture is Production Ready!                  ║\n";
    echo "╚══════════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔══════════════════════════════════════════════════════════════════════╗\n";
    echo "║                     ⚠️  TESTS FAILED  ⚠️                             ║\n";
    echo "║           Please review failed tests above                           ║\n";
    echo "╚══════════════════════════════════════════════════════════════════════╝\n";
}

echo "\n";
exit($results['failed'] === 0 ? 0 : 1);
