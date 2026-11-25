<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGlobalIdReservationsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('global_id_reservations')) {
            Schema::create('global_id_reservations', function (Blueprint $table) {
                $table->decimal('global_id', 19, 0)->primary();
                $table->unsignedInteger('branch_id');
                $table->string('scope', 64);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('global_id_reservations')) {
            Schema::drop('global_id_reservations');
        }
    }
}
