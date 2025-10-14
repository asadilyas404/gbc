<?php
/**
 * Clean up orphaned translations
 * These are translations pointing to deleted addons
 *
 * Usage: php cleanup_orphaned_translations.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Translation;
use App\Models\AddOn;
use Illuminate\Support\Facades\DB;

echo "\n=== Orphaned Translations Cleanup ===\n\n";

// Find orphaned translations for AddOns
echo "1. Finding orphaned addon translations...\n";

try {
    $orphanedCount = DB::select("
        SELECT COUNT(*) as orphan_count
        FROM TRANSLATIONS t
        WHERE t.TRANSLATIONABLE_TYPE = 'App\\Models\\AddOn'
          AND NOT EXISTS (
              SELECT 1 FROM ADD_ONS a WHERE a.ID = t.TRANSLATIONABLE_ID
          )
    ");

    $count = $orphanedCount[0]->orphan_count;

    if ($count > 0) {
        echo "   Found {$count} orphaned translation(s)\n\n";

        // Show details
        echo "2. Details of orphaned translations:\n";
        $orphans = DB::select("
            SELECT
                t.ID,
                t.TRANSLATIONABLE_ID as ADDON_ID,
                t.LOCALE,
                SUBSTR(DBMS_LOB.SUBSTR(t.VALUE, 50, 1), 1, 50) as VALUE_PREVIEW
            FROM TRANSLATIONS t
            WHERE t.TRANSLATIONABLE_TYPE = 'App\\Models\\AddOn'
              AND NOT EXISTS (
                  SELECT 1 FROM ADD_ONS a WHERE a.ID = t.TRANSLATIONABLE_ID
              )
            ORDER BY t.TRANSLATIONABLE_ID
            FETCH FIRST 20 ROWS ONLY
        ");

        foreach ($orphans as $o) {
            echo "   - Translation ID {$o->id}: Addon #{$o->addon_id} ({$o->locale}) - \"{$o->value_preview}\"\n";
        }

        echo "\n3. Do you want to delete these orphaned translations? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);

        if (strtolower($line) === 'yes' || strtolower($line) === 'y') {
            echo "\n   Deleting orphaned translations...\n";

            $deleted = DB::delete("
                DELETE FROM TRANSLATIONS
                WHERE TRANSLATIONABLE_TYPE = 'App\\Models\\AddOn'
                  AND NOT EXISTS (
                      SELECT 1 FROM ADD_ONS a WHERE a.ID = TRANSLATIONABLE_ID
                  )
            ");

            echo "   ✓ Deleted {$deleted} orphaned translation(s)\n\n";
        } else {
            echo "   Skipped deletion.\n\n";
        }

    } else {
        echo "   ✓ No orphaned translations found!\n\n";
    }

} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Check for other orphaned translations (Food, Category, etc.)
echo "4. Checking for other orphaned translations...\n";

try {
    $otherOrphans = DB::select("
        SELECT
            t.TRANSLATIONABLE_TYPE,
            COUNT(*) as orphan_count
        FROM TRANSLATIONS t
        WHERE t.TRANSLATIONABLE_TYPE != 'App\\Models\\AddOn'
        GROUP BY t.TRANSLATIONABLE_TYPE
    ");

    if (count($otherOrphans) > 0) {
        echo "   Found translations for other models:\n";
        foreach ($otherOrphans as $o) {
            echo "   - {$o->translationable_type}: {$o->orphan_count} translations\n";
        }
        echo "\n   Note: You may want to check these for orphans too.\n\n";
    } else {
        echo "   Only addon translations exist.\n\n";
    }

} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Summary
echo "5. Current translation statistics:\n";
try {
    $stats = DB::select("
        SELECT
            'Total Translations' as metric,
            TO_CHAR(COUNT(*)) as value
        FROM TRANSLATIONS
        UNION ALL
        SELECT
            'Addon Translations',
            TO_CHAR(COUNT(*))
        FROM TRANSLATIONS
        WHERE TRANSLATIONABLE_TYPE = 'App\\Models\\AddOn'
        UNION ALL
        SELECT
            'Total Addons',
            TO_CHAR(COUNT(*))
        FROM ADD_ONS
    ");

    foreach ($stats as $s) {
        echo "   {$s->metric}: {$s->value}\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

echo "=== Cleanup Complete ===\n\n";

