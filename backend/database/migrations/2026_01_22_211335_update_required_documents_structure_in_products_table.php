<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing products to use new structure
        // Old format: ["INE_FRONT", "INE_BACK", ...]
        // New format: {"nationals": ["INE_FRONT", "INE_BACK", ...], "foreigners": ["PASSPORT", "RESIDENCE_CARD", ...]}

        $products = DB::table('products')->get();

        foreach ($products as $product) {
            if ($product->required_documents) {
                $docs = json_decode($product->required_documents, true);

                // Check if already in new format
                if (!isset($docs['nationals']) && !isset($docs['foreigners'])) {
                    // Convert old format to new format
                    $nationalDocs = $docs;

                    // Create foreigner equivalent by replacing INE with PASSPORT
                    $foreignerDocs = array_map(function($doc) {
                        if (is_string($doc)) {
                            if ($doc === 'INE_FRONT') return 'PASSPORT';
                            if ($doc === 'INE_BACK') return 'RESIDENCE_CARD';
                            return $doc; // Keep other documents the same
                        }
                        // Handle object format {type: "INE_FRONT", required: true}
                        if (is_array($doc) && isset($doc['type'])) {
                            $newDoc = $doc;
                            if ($doc['type'] === 'INE_FRONT') $newDoc['type'] = 'PASSPORT';
                            if ($doc['type'] === 'INE_BACK') $newDoc['type'] = 'RESIDENCE_CARD';
                            return $newDoc;
                        }
                        return $doc;
                    }, $docs);

                    // Remove duplicates and null values
                    $foreignerDocs = array_values(array_filter($foreignerDocs));

                    $newStructure = [
                        'nationals' => $nationalDocs,
                        'foreigners' => $foreignerDocs,
                    ];

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['required_documents' => json_encode($newStructure)]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old format (nationals only)
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            if ($product->required_documents) {
                $docs = json_decode($product->required_documents, true);

                // Check if in new format
                if (isset($docs['nationals'])) {
                    // Convert back to old format (use nationals only)
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['required_documents' => json_encode($docs['nationals'])]);
                }
            }
        }
    }
};
