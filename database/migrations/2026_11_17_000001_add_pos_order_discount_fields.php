<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPosOrderDiscountFields extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('tbl_pos_discount_types', 'is_order_discount')) {
            Schema::table('tbl_pos_discount_types', function (Blueprint $table) {
                $table->boolean('is_order_discount')->default(0);
            });
        }
        if (!Schema::hasColumn('tbl_pos_discount_types', 'max_discount')) {
            Schema::table('tbl_pos_discount_types', function (Blueprint $table) {
                $table->decimal('max_discount', 15, 3)->nullable();
            });
        }
        if (!Schema::hasColumn('orders', 'order_discount_type_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('order_discount_type_id')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('orders', 'order_discount_type_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('order_discount_type_id');
            });
        }
        // Catalogue columns belong to ERP too; keep them and their existing data.
    }
}
