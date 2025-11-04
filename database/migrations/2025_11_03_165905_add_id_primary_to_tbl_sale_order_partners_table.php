<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdPrimaryToTblSaleOrderPartnersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
      public function up()
    {
        Schema::table('TBL_SALE_ORDER_PARTNERS', function (Blueprint $table) {
            // Add auto-incrementing 'id' column as the first column
            $table->bigIncrements('id')->first();
        });
    }

    public function down()
    {
        Schema::table('TBL_SALE_ORDER_PARTNERS', function (Blueprint $table) {
            // Drop the column if rollback
            $table->dropColumn('id');
        });
    }
}
