<?php
/**
 * Translation Save Test Script
 * Run this from command line to test translation insertion directly
 *
 * Usage: php test_translation_save.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Translation;
use App\Models\AddOn;
use Illuminate\Support\Facades\DB;

echo "\n=== Translation Save Test ===\n\n";

// Test 1: Check database connection
echo "1. Testing Oracle connection...\n";
try {
    $count = DB::table('translations')->count();
    echo "   ✓ Connected! Found {$count} translations in database\n\n";
} catch (Exception $e) {
    echo "   ✗ Connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Check constraints
echo "2. Checking for UNIQUE constraint...\n";
try {
    $constraint = DB::select("
        SELECT CONSTRAINT_NAME, STATUS
        FROM USER_CONSTRAINTS
        WHERE TABLE_NAME = 'TRANSLATIONS' AND CONSTRAINT_TYPE = 'U'
    ");

    if (count($constraint) > 0) {
        echo "   ✓ UNIQUE constraint exists: " . $constraint[0]->constraint_name . "\n\n";
    } else {
        echo "   ✗ MISSING UNIQUE CONSTRAINT! This is critical!\n";
        echo "   Run this SQL:\n";
        echo "   ALTER TABLE TRANSLATIONS ADD CONSTRAINT TRANSLATIONS_UNIQUE_TRANS \n";
        echo "   UNIQUE (TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY);\n\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Could not check constraint: " . $e->getMessage() . "\n\n";
}

// Test 3: Check sequence
echo "3. Checking sequence...\n";
try {
    $seq = DB::select("SELECT sequence_name, last_number FROM user_sequences WHERE UPPER(sequence_name) = 'TRANSLATIONS_ID_SEQ'");
    if (count($seq) > 0) {
        echo "   ✓ Sequence exists, last number: " . $seq[0]->last_number . "\n\n";
    } else {
        echo "   ⚠ Sequence not found\n\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Could not check sequence: " . $e->getMessage() . "\n\n";
}

// Test 4: Get a sample addon
echo "4. Finding a test addon...\n";
try {
    $addon = AddOn::withoutGlobalScope('translate')->first();
    if ($addon) {
        echo "   ✓ Found addon ID: {$addon->id}, Name: {$addon->getRawOriginal('name')}\n\n";
    } else {
        echo "   ✗ No addons found. Create an addon first.\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ✗ Error finding addon: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 5: Try to insert a test translation
echo "5. Testing translation insert...\n";
echo "   Attempting to save Arabic translation for addon {$addon->id}...\n";

try {
    $translation = Translation::updateOrCreate(
        [
            'translationable_type' => 'App\Models\AddOn',
            'translationable_id' => $addon->id,
            'locale' => 'ar',
            'key' => 'name'
        ],
        [
            'value' => 'اختبار الترجمة (Test Translation)'
        ]
    );

    echo "   ✓ SUCCESS! Translation saved with ID: {$translation->id}\n";
    echo "   Value: {$translation->value}\n\n";

} catch (Exception $e) {
    echo "   ✗ FAILED to save translation!\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n\n";
    echo "   Full trace:\n";
    echo $e->getTraceAsString() . "\n\n";
}

// Test 6: Verify it was saved
echo "6. Verifying saved translation...\n";
try {
    $saved = Translation::where('translationable_type', 'App\Models\AddOn')
        ->where('translationable_id', $addon->id)
        ->where('locale', 'ar')
        ->where('key', 'name')
        ->first();

    if ($saved) {
        echo "   ✓ Translation found in database!\n";
        echo "   ID: {$saved->id}\n";
        echo "   Value: {$saved->value}\n";
        echo "   Created: {$saved->created_at}\n";
        echo "   Updated: {$saved->updated_at}\n\n";
    } else {
        echo "   ✗ Translation not found after insert!\n\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error verifying: " . $e->getMessage() . "\n\n";
}

// Test 7: Show all translations for this addon
echo "7. All translations for addon {$addon->id}:\n";
try {
    $all = Translation::where('translationable_type', 'App\Models\AddOn')
        ->where('translationable_id', $addon->id)
        ->get();

    if ($all->count() > 0) {
        foreach ($all as $t) {
            echo "   - [{$t->locale}] {$t->value}\n";
        }
        echo "\n";
    } else {
        echo "   No translations found.\n\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

echo "=== Test Complete ===\n\n";

echo "NEXT STEPS:\n";
echo "1. If you see errors above, fix them first\n";
echo "2. Clear Laravel cache: php artisan config:clear && php artisan cache:clear\n";
echo "3. Try updating an addon through the web interface\n";
echo "4. Check storage/logs/laravel.log for debug output\n\n";

