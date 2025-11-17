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
            $tables = array_map(fn($t) => $t->TABLE_NAME, $tables);
        }
        
        try {
            foreach ($tables as $table) {

                echo "Checking and Syncing Table: " . strtoupper($table) . "\n\r";

                $tableExists = Schema::connection($connectionLocal)->hasTable($table);

                // ============================================================
                // CREATE TABLE IF NOT EXISTS
                // ============================================================
                if (!$tableExists) {

                    echo "Table not found. Creating: " . strtoupper($table) . "\n\r";

                    $owner = strtoupper(Config::get('oracle.' . $connectionLive)['username']);
                    $tableName = strtoupper($table);

                    // ==============================================
                    // 1. Fetch all columns
                    // ==============================================
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

                    // ==============================================
                    // 2. Fetch PRIMARY KEY columns
                    // ==============================================
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

                    // ==============================================
                    // 3. Detect auto-increment SEQUENCE and TRIGGER
                    // ==============================================
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

                    // ==============================================
                    // 4. Create TABLE in MySQL/MariaDB
                    // ==============================================
                    Schema::connection($connectionLocal)->create($table, function (Blueprint $tbl) use ($liveColumnsDetails, $pkColumns) {

                        foreach ($liveColumnsDetails as $col) {

                            $default = null;

                            if (!empty($col->data_default)) {
                                $default = trim($col->data_default);
                                $default = rtrim($default, " ;");

                                if (strtoupper($default) === 'SYSDATE') {
                                    $default = DB::raw('CURRENT_TIMESTAMP');
                                }
                            }

                            switch ($col->data_type) {
                                case 'NUMBER':
                                    $c = $tbl->bigInteger($col->column_name)->nullable();
                                    if ($default !== null) $c->default($default);
                                    break;

                                case 'INTEGER':
                                    $c = $tbl->integer($col->column_name)->nullable();
                                    if ($default !== null) $c->default($default);
                                    break;

                                case 'VARCHAR2':
                                case 'NVARCHAR2':
                                    $c = $tbl->string($col->column_name, $col->data_length)->nullable();
                                    if ($default !== null) $c->default($default);
                                    break;

                                case 'CHAR':
                                    $c = $tbl->char($col->column_name, $col->data_length)->nullable();
                                    if ($default !== null) $c->default($default);
                                    break;

                                case 'LONG':
                                case 'CLOB':
                                    $tbl->longText($col->column_name)->nullable();
                                    break;

                                case 'FLOAT':
                                    $c = $tbl->float($col->column_name)->nullable();
                                    if ($default !== null) $c->default($default);
                                    break;

                                case 'DATE':
                                case 'TIMESTAMP':
                                case 'TIMESTAMP(0)':
                                case 'TIMESTAMP(6)':
                                    $c = $tbl->dateTime($col->column_name)->nullable();
                                    if ($default !== null) $c->default($default);
                                    break;
                                default:
                                    // Unknown type → fallback to string
                                    $c = $tbl->string($col->column_name)->nullable()->default('');
                                    break;
                            }
                        }

                        // Add PRIMARY KEY if exists
                        if (!empty($pkColumns)) {
                            $tbl->primary($pkColumns);
                        }
                    });

                    echo "Table created successfully: " . strtoupper($table) . "\n\r";

                    // Store sequence info so you can use NEXTVAL when inserting back
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

                try {
                    // Fetch columns from live DB
                    $liveCols = DB::connection($connectionLive)->select("
                        SELECT COLUMN_NAME
                        FROM ALL_TAB_COLUMNS
                        WHERE OWNER = '" . strtoupper(Config::get('oracle.' . $connectionLive)['username']) . "'
                        AND TABLE_NAME = '" . strtoupper($table) . "'
                        ORDER BY COLUMN_ID
                    ");
                } catch (\Exception $e) {
                    $this->error("Could not connect to the live Oracle database. Please make sure the configuration is set correctly.");
                    $this->info("You can publish the config file with:");
                    $this->line("php artisan vendor:publish --provider=\"Zaryab\\OracleSchemaSync\\OracleSchemaSyncServiceProvider\" --tag=config");
                    $this->info("Then fill in the live database values in config/oracle.php");
                    return 1; // Stop command execution
                }

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
                        $col = collect($localCols)->first(fn($c) => strtolower($c->column_name) === $colName);
                        Schema::connection($connectionLocal)->table($table, function (Blueprint $tbl) use ($col) {
                            $default = $col->data_default ? rtrim(trim($col->data_default), " ;") : null;
                            if (strtoupper($default) === 'SYSDATE') $default = DB::raw('CURRENT_TIMESTAMP');

                            switch ($col->data_type) {
                                case 'NUMBER': $c = $tbl->bigInteger($col->column_name)->nullable(); break;
                                case 'INTEGER': $c = $tbl->integer($col->column_name)->nullable(); break;
                                case 'VARCHAR2':
                                case 'NVARCHAR2': $c = $tbl->string($col->column_name, $col->data_length)->nullable(); break;
                                case 'CHAR': $c = $tbl->char($col->column_name, $col->data_length)->nullable(); break;
                                case 'CLOB':
                                case 'LONG': $tbl->longText($col->column_name)->nullable(); break;
                                case 'FLOAT': $c = $tbl->float($col->column_name)->nullable(); break;
                                case 'DATE':
                                case 'TIMESTAMP':
                                case 'TIMESTAMP(0)':
                                case 'TIMESTAMP(6)': $c = $tbl->dateTime($col->column_name)->nullable(); break;
                                default: $c = $tbl->string($col->column_name)->nullable(); break;
                            }
                            if (isset($c) && $default !== null) $c->default($default);
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
}
