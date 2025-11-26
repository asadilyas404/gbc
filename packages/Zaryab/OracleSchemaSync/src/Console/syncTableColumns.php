<?php

namespace Zaryab\OracleSchemaSync\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use \Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class syncTableColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:schema {table? : The name of the table to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronizes a specific table (or all tables if none is specified) between the local and live Oracle databases.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $connectionLive = 'live_oracle';
        $connectionLocal = 'oracle';
        // Fetch all tables from live Oracle DB
        $tableName = $this->argument('table');

        echo "Establishing Connection With LIVE DB...\n";
        DB::connection($connectionLocal)->beginTransaction();
        try {
            // Test connection to live DB
            DB::connection($connectionLive)->getPdo();
            dump('Connected!');
        } catch (\Exception $e) {
            $this->error("Could not connect to the live Oracle database. Please make sure the configuration is correct.");
            $this->info("You can publish the config file with:");
            $this->line("php artisan vendor:publish --provider=\"Zaryab\\OracleSchemaSync\\OracleSchemaSyncServiceProvider\" --tag=config");
            $this->info("Then fill in the live database values in config/oracle.php");
            return 1; // Stop command execution
        }

        if ($tableName) {
            echo "Syncing table: " . strtoupper($tableName) . "\n";
            $tables = [$tableName]; // only this table
        } else {
            echo "No table specified. Syncing all tables.\n";
            $tables = DB::connection($connectionLive)->select("
                SELECT TABLE_NAME
                FROM ALL_TABLES
                WHERE OWNER = '" . strtoupper(config('oracle.' . $connectionLive)['username']) . "'
            ");
            $tables = array_map(fn($t) => $t->table_name, $tables);
        }

        try {
            foreach ($tables as $table) {

                echo "Checking and Syncing Table: " . strtoupper($table) . "\n\r";

                $tableExists = Schema::connection($connectionLocal)->hasTable($table);

                // ===============================================
                // FIXED TABLE CREATION FOR ORACLE
                // ===============================================
                if (!$tableExists) {

                    echo "Table not found. Creating: " . strtoupper($table) . "\n\r";

                    $owner = strtoupper(Config::get('oracle.' . $connectionLive)['username']);
                    $tableName = strtoupper($table);

                    $this->info("Error While Creating Table On Local Database.");
                    $this->error("Table Not Found On Local Database: " . $tableName . " Make this table manually.");
                    continue;

                    // 1. Fetch ALL columns
                    $liveColumnsDetails = DB::connection($connectionLive)->select("
                        SELECT OWNER,
                            TABLE_NAME,
                            COLUMN_NAME,
                            DATA_TYPE,
                            DATA_LENGTH,
                            DATA_DEFAULT,
                            NULLABLE,
                            COLUMN_ID
                        FROM ALL_TAB_COLUMNS
                        WHERE OWNER = '$owner'
                        AND TABLE_NAME = '$tableName'
                        ORDER BY COLUMN_ID
                    ");

                    // 2. Primary Keys
                    $primaryKeys = DB::connection($connectionLive)->select("
                        SELECT acc.COLUMN_NAME
                        FROM ALL_CONSTRAINTS ac
                        JOIN ALL_CONS_COLUMNS acc ON ac.OWNER = acc.OWNER 
                            AND ac.CONSTRAINT_NAME = acc.CONSTRAINT_NAME
                        WHERE ac.OWNER = '$owner'
                        AND ac.TABLE_NAME = '$tableName'
                        AND ac.CONSTRAINT_TYPE = 'P'
                        ORDER BY acc.POSITION
                    ");
                    $pkColumns = array_map(fn($c) => $c->column_name, $primaryKeys);

                    // 3. Sync the sequence and trigger
                    $this->syncSequenceAndTrigger($tableName, $connectionLive, $connectionLocal);

                    // 3. Detect sequence from trigger
                    $triggerInfo = DB::connection($connectionLive)->select("
                        SELECT TRIGGER_NAME, TRIGGER_BODY
                        FROM ALL_TRIGGERS
                        WHERE TABLE_OWNER = '$owner'
                        AND TABLE_NAME = '$tableName'
                        AND STATUS = 'ENABLED'
                    ");

                    $sequenceName = null;

                    foreach ($triggerInfo as $trigger) {
                        if (preg_match('/([A-Za-z0-9_]+)\.NEXTVAL/i', $trigger->trigger_body, $m)) {
                            $sequenceName = $m[1];
                            break;
                        }
                    }

                    echo "Detected PK: " . implode(',', $pkColumns) . "\n\r";
                    echo "Detected Oracle Sequence: " . ($sequenceName ?: 'None') . "\n\r";

                    // ===============================================
                    // Build RAW Oracle CREATE TABLE SQL
                    // ===============================================

                    $sql = "CREATE TABLE $tableName (";

                    foreach ($liveColumnsDetails as $col) {
                        $colName = $col->column_name;
                        $type = strtoupper($col->data_type);
                        $length = $col->data_length;
                        $nullable = $col->nullable === 'Y' ? 'NULL' : 'NOT NULL';

                        // Build datatype
                        switch ($type) {
                            case 'VARCHAR2':
                            case 'NVARCHAR2':
                                $dt = "$type($length)";
                                break;

                            case 'NUMBER':
                                $dt = "NUMBER";
                                break;

                            case 'INTEGER':
                                $dt = "NUMBER";
                                break;

                            case 'FLOAT':
                                $dt = "FLOAT";
                                break;

                            case 'DATE':
                                $dt = "DATE";
                                break;

                            case 'TIMESTAMP':
                            case 'TIMESTAMP(0)':
                            case 'TIMESTAMP(6)':
                                $dt = "TIMESTAMP";
                                break;

                            case 'CLOB':
                            case 'LONG':
                                $dt = "CLOB";
                                break;

                            default:
                                $dt = "VARCHAR2(4000)";
                                break;
                        }

                        // Fix default
                        $default = null;

                        if ($colName === 'ID' && $sequenceName) {
                            $default = "DEFAULT $sequenceName.NEXTVAL";
                        } else {
                            $normalized = $this->normalizeOracleDefault($col->data_default);

                            if ($normalized instanceof \Illuminate\Database\Query\Expression) {
                                $default = "DEFAULT " . $normalized->getValue();
                            } elseif ($normalized !== null) {
                                $default = "DEFAULT '" . addslashes($normalized) . "'";
                            }
                        }

                        $sql .= "    $colName $dt " . ($default ? $default . " " : "") . "$nullable,\n";
                    }

                    // Append primary key constraint
                    if (!empty($pkColumns)) {
                        $sql .= "    CONSTRAINT {$tableName}_PK PRIMARY KEY (" . implode(',', $pkColumns) . ")\n";
                    } else {
                        $sql = rtrim($sql, ",\n") . "\n"; // Remove trailing comma
                    }

                    $sql .= ");";

                    // Execute CREATE TABLE
                    DB::connection($connectionLocal)->unprepared($sql);

                    echo "Created Table Using RAW SQL: $tableName\n\r";

                    // Save sequence info for inserts
                    if ($sequenceName) {
                        DB::table('oracle_sequences_map')->updateOrInsert(
                            ['table_name' => $tableName],
                            ['sequence_name' => $sequenceName]
                        );
                    }

                    continue;
                }

                // ============================================================
                // TABLE EXISTS → SYNC MISSING COLUMNS
                // ============================================================
                echo "Table exists on local connection. Syncing missing columns...\n\r";

                $liveCols = DB::connection($connectionLive)->select("
                    SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, DATA_DEFAULT, NULLABLE
                    FROM ALL_TAB_COLUMNS
                    WHERE OWNER = '" . strtoupper(Config::get('oracle.' . $connectionLive)['username']) . "'
                    AND TABLE_NAME = '" . strtoupper($table) . "'
                    ORDER BY COLUMN_ID
                ");

                $liveColNames = array_map(fn($c) => strtolower($c->column_name), $liveCols);
                // Fetch columns from local DB
                $localCols = DB::connection($connectionLocal)->select("
                    SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, DATA_DEFAULT, NULLABLE
                    FROM ALL_TAB_COLUMNS
                    WHERE OWNER = '" . strtoupper(Config::get('oracle.' . $connectionLocal)['username']) . "'
                    AND TABLE_NAME = '" . strtoupper($table) . "'
                    ORDER BY COLUMN_ID
                ");

                $localColNames = array_map(fn($c) => strtolower($c->column_name), $localCols);

                // ---------------------------
                // Add missing columns to local DB (if any)
                $missingInLocal = array_diff($liveColNames, $localColNames);

                if (!empty($missingInLocal)) {
                    foreach ($missingInLocal as $colName) {
                        // Get column definition from live DB
                        $col = collect($liveCols)->first(fn($c) => strtolower($c->column_name) === $colName);

                        if (!$col) {
                            $this->error("Column '$colName' not found in live DB. Skipping.");
                            continue;
                        }

                        Schema::connection($connectionLocal)->table($table, function (Blueprint $tbl) use ($col) {
                            $default = $this->normalizeOracleDefault($col->data_default);
                            $columnType = strtoupper($col->data_type);

                            switch ($columnType) {
                                case 'NUMBER':
                                case 'INTEGER':
                                    $c = $tbl->bigInteger($col->column_name);
                                    $col->nullable === 'Y' ? $c->nullable() : $c->nullable(false);
                                    break;

                                case 'VARCHAR2':
                                case 'NVARCHAR2':
                                    $c = $tbl->string($col->column_name, $col->data_length);
                                    $col->nullable === 'Y' ? $c->nullable() : $c->nullable(false);
                                    break;

                                case 'CHAR':
                                    $c = $tbl->char($col->column_name, $col->data_length);
                                    $col->nullable === 'Y' ? $c->nullable() : $c->nullable(false);
                                    break;

                                case 'CLOB':
                                case 'LONG':
                                    $c = $tbl->longText($col->column_name);
                                    break;

                                case 'FLOAT':
                                    $c = $tbl->float($col->column_name);
                                    $col->nullable === 'Y' ? $c->nullable() : $c->nullable(false);
                                    break;

                                case 'DATE':
                                case 'TIMESTAMP':
                                case 'TIMESTAMP(0)':
                                case 'TIMESTAMP(6)':
                                    $c = $tbl->dateTime($col->column_name);
                                    $col->nullable === 'Y' ? $c->nullable() : $c->nullable(false);
                                    break;

                                default:
                                    $c = $tbl->string($col->column_name);
                                    $col->nullable === 'Y' ? $c->nullable() : $c->nullable(false);
                                    break;
                            }

                            // Set default safely
                            if ($default !== null) {
                                if ($c instanceof \Illuminate\Database\Schema\ForeignIdColumnDefinition || in_array($columnType, ['CLOB', 'LONG'])) {
                                    // Cannot set default for CLOB/LONG or foreign keys
                                } elseif ($default instanceof \Illuminate\Database\Query\Expression) {
                                    $c->default($default); // e.g., DB::raw('CURRENT_TIMESTAMP')
                                } else {
                                    $c->default($default);
                                }
                            }
                        });
                    }
                }

                // ---------------------------
                // Add missing columns to live DB (if any)
                //$missingInLive = array_diff($localColNames, $liveColNames);
                // if (!empty($missingInLive)) {
                //     foreach ($missingInLive as $colName) {
                //         $col = collect($localCols)->first(fn($c) => strtolower($c->column_name) === $colName);
                //         $sql = "ALTER TABLE " . strtoupper($table) . " ADD " . strtoupper($col->column_name) . " ";

                //         switch ($col->data_type) {
                //             case 'NUMBER': $sql .= "NUMBER"; break;
                //             case 'INTEGER': $sql .= "NUMBER"; break;
                //             case 'VARCHAR2':
                //             case 'NVARCHAR2': $sql .= "VARCHAR2(" . $col->data_length . ")"; break;
                //             case 'CHAR': $sql .= "CHAR(" . $col->data_length . ")"; break;
                //             case 'CLOB':
                //             case 'LONG': $sql .= "CLOB"; break;
                //             case 'FLOAT': $sql .= "FLOAT"; break;
                //             case 'DATE':
                //             case 'TIMESTAMP':
                //             case 'TIMESTAMP(0)':
                //             case 'TIMESTAMP(6)': $sql .= "DATE"; break;
                //             default: $sql .= "VARCHAR2(255)"; break;
                //         }

                //         $default = $col->data_default ? rtrim(trim($col->data_default), " ;") : null;
                //         if ($default) {
                //             if (strtoupper($default) === 'SYSDATE') $default = 'CURRENT_TIMESTAMP';
                //             $sql .= " DEFAULT " . $default;
                //         }

                //         $sql .= " NULL";

                //         DB::connection($connectionLive)->statement($sql);
                //     }
                // }

            }
            DB::connection($connectionLocal)->commit();
            DB::connection($connectionLive)->commit();
            dump('Column Sync is Done!');
        } catch (\Throwable $th) {
            DB::connection($connectionLocal)->rollBack();
            DB::connection($connectionLive)->rollBack();
            dump($th->getMessage());
        }
    }

    private function normalizeOracleDefault($default)
    {
        if ($default === null) {
            return null;
        }

        $default = trim($default);

        // Handle NULL default
        if (strtoupper($default) === 'NULL') {
            return null;
        }

        // Remove trailing whitespace or extra characters
        $default = rtrim($default, " \t\n\r\0\x0B");

        // Handle CURRENT_TIMESTAMP / SYSDATE / SYSTIMESTAMP (NO QUOTES)
        if (preg_match('/CURRENT_TIMESTAMP|SYSDATE|SYSTIMESTAMP/i', $default)) {
            return DB::raw('CURRENT_TIMESTAMP');
        }

        // Handle numeric defaults (NUMBER columns)
        if (is_numeric($default)) {
            return $default + 0;
        }

        // Handle quoted string defaults: `'ABC'`, `'N'`, `'0'`
        if (preg_match("/^'(.*)'$/", $default, $m)) {
            return $m[1]; // return ABC, N, etc.
        }

        // If string without quotes → wrap it
        return $default;
    }

    /**
     * Sync Oracle Sequence and Trigger from live DB to local DB.
     *
     * @param string $tableName
     * @param string $connectionLive
     * @param string $connectionLocal
     * @return void
     */
    protected function syncSequenceAndTrigger(string $tableName, string $connectionLive, string $connectionLocal)
    {
        $ownerLive = strtoupper(config('oracle.' . $connectionLive)['username']);
        $ownerLocal = strtoupper(config('oracle.' . $connectionLocal)['username']);
        $tableNameUpper = strtoupper($tableName);

        // 1. Detect sequence from live triggers
        $triggerInfo = DB::connection($connectionLive)->select("
            SELECT TRIGGER_NAME, TRIGGER_BODY
            FROM ALL_TRIGGERS
            WHERE TABLE_OWNER = '$ownerLive'
            AND TABLE_NAME = '$tableNameUpper'
            AND STATUS = 'ENABLED'
        ");

        $sequenceName = null;
        $triggerName = null;

        foreach ($triggerInfo as $trigger) {
            if (preg_match('/([A-Za-z0-9_]+)\.NEXTVAL/i', $trigger->TRIGGER_BODY, $m)) {
                $sequenceName = strtoupper($m[1]);
                $triggerName = strtoupper($trigger->TRIGGER_NAME);
                break;
            }
        }

        if (!$sequenceName || !$triggerName) {
            $this->info("No sequence/trigger found on live database for table $tableName.");
            return;
        }

        // 2. Check if sequence exists on local DB
        $sequenceExists = DB::connection($connectionLocal)->selectOne("
            SELECT SEQUENCE_NAME 
            FROM ALL_SEQUENCES 
            WHERE SEQUENCE_OWNER = '$ownerLocal' 
            AND SEQUENCE_NAME = '$sequenceName'
        ");

        // 3. Create sequence if missing
        if (!$sequenceExists) {
            $this->info("Creating sequence $sequenceName on local DB...");
            DB::connection($connectionLocal)->statement("
                CREATE SEQUENCE $sequenceName
                START WITH 1
                INCREMENT BY 1
                NOCACHE
                NOCYCLE
            ");
        }

        // 4. Check if trigger exists on local DB
        $triggerExists = DB::connection($connectionLocal)->selectOne("
            SELECT TRIGGER_NAME
            FROM ALL_TRIGGERS
            WHERE TABLE_OWNER = '$ownerLocal'
            AND TABLE_NAME = '$tableNameUpper'
            AND TRIGGER_NAME = '$triggerName'
        ");

        // 5. Create trigger if missing
        if (!$triggerExists) {
            $this->info("Creating trigger $triggerName on local DB...");

            $triggerSQL = "
                CREATE OR REPLACE TRIGGER $triggerName
                BEFORE INSERT ON $tableNameUpper
                FOR EACH ROW
                BEGIN
                    IF :NEW.ID IS NULL THEN
                        SELECT $sequenceName.NEXTVAL INTO :NEW.ID FROM dual;
                    END IF;
                END;
            ";

            DB::connection($connectionLocal)->statement($triggerSQL);
        }

        $this->info("Sequence and trigger synced successfully for table $tableName.");
    }
}
