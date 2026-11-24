<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CallCenterSchema
{
    public function install(): void
    {
        $this->createSequence('call_center_zones_seq');
        $this->createSequence('order_requests_seq');
        $this->createSequence('order_req_details_seq');

        if (!$this->tableExists('call_center_settings')) {
            DB::statement("
                CREATE TABLE call_center_settings (
                    setting_key VARCHAR2(50) PRIMARY KEY,
                    setting_value VARCHAR2(50) NOT NULL,
                    updated_at TIMESTAMP NULL
                )
            ");
        }

        if (!$this->tableExists('call_center_zones')) {
            DB::statement("
                CREATE TABLE call_center_zones (
                    id NUMBER PRIMARY KEY,
                    restaurant_id NUMBER NOT NULL,
                    name VARCHAR2(150) NOT NULL,
                    delivery_minutes NUMBER NOT NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ");
            DB::statement('CREATE INDEX cc_zones_branch_idx ON call_center_zones (restaurant_id)');
        }

        if (!$this->tableExists('order_requests')) {
            DB::statement("
                CREATE TABLE order_requests (
                    id NUMBER PRIMARY KEY,
                    customer_name VARCHAR2(191) NULL,
                    customer_phone VARCHAR2(30) NULL,
                    address VARCHAR2(500) NULL,
                    latitude NUMBER(10,7) NULL,
                    longitude NUMBER(10,7) NULL,
                    restaurant_id NUMBER NULL,
                    zone_id NUMBER NULL,
                    zone_name VARCHAR2(150) NULL,
                    preparation_minutes NUMBER NULL,
                    zone_delivery_minutes NUMBER NULL,
                    handover_minutes NUMBER NULL,
                    customer_eta_minutes NUMBER NULL,
                    driver_return_minutes NUMBER NULL,
                    delivery_fee NUMBER(12,3) DEFAULT 0,
                    delivery_type VARCHAR2(20) NULL,
                    status VARCHAR2(20) DEFAULT 'pending',
                    reject_reason VARCHAR2(500) NULL,
                    agent_user_id NUMBER NULL,
                    customer_id NUMBER NULL,
                    order_id NUMBER NULL,
                    accepted_at TIMESTAMP NULL,
                    rejected_at TIMESTAMP NULL,
                    ready_at TIMESTAMP NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ");
            DB::statement('CREATE INDEX cc_req_branch_status_idx ON order_requests (restaurant_id, status)');
        }

        if ($this->tableExists('order_requests') && !$this->columnExists('order_requests', 'customer_id')) {
            DB::statement('ALTER TABLE order_requests ADD (customer_id NUMBER NULL)');
        }

        if ($this->tableExists('order_requests') && !$this->columnExists('order_requests', 'delivery_type')) {
            DB::statement('ALTER TABLE order_requests ADD (delivery_type VARCHAR2(20) NULL)');
        }

        foreach (['accepted_at', 'rejected_at', 'ready_at'] as $column) {
            if ($this->tableExists('order_requests') && !$this->columnExists('order_requests', $column)) {
                DB::statement('ALTER TABLE order_requests ADD (' . $column . ' TIMESTAMP NULL)');
            }
        }

        if (!$this->tableExists('order_request_details')) {
            DB::statement("
                CREATE TABLE order_request_details (
                    id NUMBER PRIMARY KEY,
                    order_request_id NUMBER NOT NULL,
                    food_id NUMBER NULL,
                    food_name VARCHAR2(255) NULL,
                    quantity NUMBER DEFAULT 1,
                    price NUMBER(12,3) DEFAULT 0,
                    variation CLOB NULL,
                    add_ons CLOB NULL,
                    notes VARCHAR2(500) NULL,
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL
                )
            ");
            DB::statement('CREATE INDEX cc_req_details_req_idx ON order_request_details (order_request_id)');
        }

        if (!$this->columnExists('orders', 'call_center_order')) {
            DB::statement("ALTER TABLE orders ADD (call_center_order CHAR(1) DEFAULT 'N')");
        }

        $now = now();
        foreach ([
            'preparation_minutes' => (string) config('callcenter.preparation_minutes', 20),
            'handover_minutes' => (string) config('callcenter.handover_minutes', 5),
        ] as $key => $value) {
            $exists = DB::table('call_center_settings')->where('setting_key', $key)->exists();
            if (!$exists) {
                DB::table('call_center_settings')->insert([
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public static function nextId(string $sequence): int
    {
        $allowed = ['call_center_zones_seq', 'order_requests_seq', 'order_req_details_seq'];
        if (!in_array($sequence, $allowed, true)) {
            throw new RuntimeException('Unknown sequence.');
        }

        $row = DB::selectOne('SELECT ' . $sequence . '.NEXTVAL AS id FROM dual');
        $values = array_change_key_case((array) $row, CASE_LOWER);

        return (int) $values['id'];
    }

    private function tableExists(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total FROM user_tables WHERE table_name = ?',
            [strtoupper($table)]
        );
        $values = array_change_key_case((array) $row, CASE_LOWER);

        return (int) ($values['total'] ?? 0) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total FROM user_tab_columns WHERE table_name = ? AND column_name = ?',
            [strtoupper($table), strtoupper($column)]
        );
        $values = array_change_key_case((array) $row, CASE_LOWER);

        return (int) ($values['total'] ?? 0) > 0;
    }

    private function createSequence(string $sequence): void
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS total FROM user_sequences WHERE sequence_name = ?',
            [strtoupper($sequence)]
        );
        $values = array_change_key_case((array) $row, CASE_LOWER);
        if ((int) ($values['total'] ?? 0) > 0) {
            return;
        }

        DB::statement('CREATE SEQUENCE ' . $sequence . ' START WITH 1 INCREMENT BY 1 NOCACHE');
    }
}
