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
        Schema::create('expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type')->default('custom');
            $table->decimal('amount', 23, 3)->default(0);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();
            $table->string('created_by', 50)->nullable()->default('admin');
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expenses');
    }
};
