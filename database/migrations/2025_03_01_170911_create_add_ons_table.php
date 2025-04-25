<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('add_ons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->nullable();
            $table->decimal('price', 24)->default(0);
            $table->timestamps();
            $table->unsignedBigInteger('restaurant_id');
            $table->boolean('status')->default(true);
            $table->string('stock_type', 20)->default('unlimited');
            $table->integer('addon_stock')->default(0);
            $table->integer('sell_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('add_ons');
    }
};
