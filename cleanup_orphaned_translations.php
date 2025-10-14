<?php
/**
 * Clean up orphaned translations
 * These are translations pointing to deleted records (AddOns, Food, OptionsList, etc.)
 *
 * Usage: php cleanup_orphaned_translations.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Translation;
use App\Models\AddOn;
use App\Models\Food;
use App\Models\OptionsList;
use Illuminate\Support\Facades\DB;

echo "\n=== Orphaned Translations Cleanup ===\n\n";

// Models to check for orphaned translations
$modelsToCheck = [
    ['model' => 'App\\Models\\AddOn', 'table' => 'ADD_ONS', 'name' => 'AddOns'],
    ['model' => 'App\\Models\\Food', 'table' => 'FOOD', 'name' => 'Food'],
    ['model' => 'App\\Models\\OptionsList', 'table' => 'OPTIONS_LIST', 'name' => 'OptionsList'],
];

$totalOrphaned = 0;
$stepNum = 1;

foreach ($modelsToCheck as $modelInfo) {
    $modelClass = $modelInfo['model'];
    $tableName = $modelInfo['table'];
    $displayName = $modelInfo['name'];

    echo "{$stepNum}. Finding orphaned {$displayName} translations...\n";

    try {
        $orphanedCount = DB::select("
            SELECT COUNT(*) as orphan_count
            FROM TRANSLATIONS t
            WHERE t.TRANSLATIONABLE_TYPE = '{$modelClass}'
              AND NOT EXISTS (
                  SELECT 1 FROM {$tableName} a WHERE a.ID = t.TRANSLATIONABLE_ID
              )
        ");

        $count = $orphanedCount[0]->orphan_count;

        if ($count > 0) {
            echo "   ⚠ Found {$count} orphaned {$displayName} translation(s)\n";
            $totalOrphaned += $count;

            // Show sample
            $orphans = DB::select("
                SELECT
                    t.ID,
                    t.TRANSLATIONABLE_ID as RECORD_ID,
                    t.LOCALE,
                    SUBSTR(DBMS_LOB.SUBSTR(t.VALUE, 50, 1), 1, 50) as VALUE_PREVIEW
                FROM TRANSLATIONS t
                WHERE t.TRANSLATIONABLE_TYPE = '{$modelClass}'
                  AND NOT EXISTS (
                      SELECT 1 FROM {$tableName} a WHERE a.ID = t.TRANSLATIONABLE_ID
                  )
                ORDER BY t.TRANSLATIONABLE_ID
                FETCH FIRST 5 ROWS ONLY
            ");

            echo "   Sample orphaned records:\n";
            foreach ($orphans as $o) {
                echo "     • Translation ID {$o->id}: {$displayName} #{$o->record_id} ({$o->locale}) - \"{$o->value_preview}\"\n";
            }
            echo "\n";
        } else {
            echo "   ✓ No orphaned {$displayName} translations found!\n\n";
        }

    } catch (Exception $e) {
        echo "   ✗ Error checking {$displayName}: " . $e->getMessage() . "\n\n";
    }

    $stepNum++;
}

// Ask user if they want to delete orphans
if ($totalOrphaned > 0) {
    echo "\n{$stepNum}. TOTAL ORPHANED: {$totalOrphaned} translation(s)\n";
    echo "   Do you want to delete ALL orphaned translations? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
        echo "\n   Deleting orphaned translations...\n";

        $totalDeleted = 0;
        foreach ($modelsToCheck as $modelInfo) {
            $modelClass = $modelInfo['model'];
            $tableName = $modelInfo['table'];
            $displayName = $modelInfo['name'];

            try {
                $deleted = DB::delete("
                    DELETE FROM TRANSLATIONS
                    WHERE TRANSLATIONABLE_TYPE = '{$modelClass}'
                      AND NOT EXISTS (
                          SELECT 1 FROM {$tableName} a WHERE a.ID = TRANSLATIONABLE_ID
                      )
                ");

                if ($deleted > 0) {
                    echo "     ✓ Deleted {$deleted} {$displayName} orphaned translation(s)\n";
                    $totalDeleted += $deleted;
                }
            } catch (Exception $e) {
                echo "     ✗ Error deleting {$displayName} orphans: " . $e->getMessage() . "\n";
            }
        }

        echo "\n   ✓ Total deleted: {$totalDeleted} orphaned translation(s)\n\n";
    } else {
        echo "   Skipped deletion.\n\n";
    }
    $stepNum++;
}

// Check for other model translations not in our cleanup list
echo "{$stepNum}. Checking for other model translations...\n";

try {
    $knownModels = array_column($modelsToCheck, 'model');
    $placeholders = str_repeat('?,', count($knownModels) - 1) . '?';

    $otherModels = DB::select("
        SELECT
            t.TRANSLATIONABLE_TYPE,
            COUNT(*) as translation_count
        FROM TRANSLATIONS t
        WHERE t.TRANSLATIONABLE_TYPE NOT IN ({$placeholders})
        GROUP BY t.TRANSLATIONABLE_TYPE
    ", $knownModels);

    if (count($otherModels) > 0) {
        echo "   Found translations for other models:\n";
        foreach ($otherModels as $o) {
            echo "     • {$o->translationable_type}: {$o->translation_count} translations\n";
        }
        echo "\n   Note: These models should also have cascade delete implemented.\n\n";
    } else {
        echo "   ✓ Only known models have translations.\n\n";
    }

} catch (Exception $e) {
    echo "   ⚠ Error: " . $e->getMessage() . "\n\n";
}
$stepNum++;

// Summary
echo "{$stepNum}. Current translation statistics:\n";
try {
    $stats = DB::select("
        SELECT
            'Total Translations' as metric,
            TO_CHAR(COUNT(*)) as value
        FROM TRANSLATIONS
        UNION ALL
        SELECT
            'AddOn Translations',
            TO_CHAR(COUNT(*))
        FROM TRANSLATIONS
        WHERE TRANSLATIONABLE_TYPE = 'App\\Models\\AddOn'
        UNION ALL
        SELECT
            'Food Translations',
            TO_CHAR(COUNT(*))
        FROM TRANSLATIONS
        WHERE TRANSLATIONABLE_TYPE = 'App\\Models\\Food'
        UNION ALL
        SELECT
            'OptionsList Translations',
            TO_CHAR(COUNT(*))
        FROM TRANSLATIONS
        WHERE TRANSLATIONABLE_TYPE = 'App\\Models\\OptionsList'
        UNION ALL
        SELECT
            'Total AddOns',
            TO_CHAR(COUNT(*))
        FROM ADD_ONS
        UNION ALL
        SELECT
            'Total Food Items',
            TO_CHAR(COUNT(*))
        FROM FOOD
        UNION ALL
        SELECT
            'Total Options',
            TO_CHAR(COUNT(*))
        FROM OPTIONS_LIST
    ");

    foreach ($stats as $s) {
        echo "   {$s->metric}: {$s->value}\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

echo "=== Cleanup Complete ===\n\n";

