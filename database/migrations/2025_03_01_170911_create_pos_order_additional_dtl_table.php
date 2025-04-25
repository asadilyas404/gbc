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
        Schema::create('pos_order_additional_dtl', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('car_number', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('invoice_amount', 10, 3)->default(0);
            $table->decimal('cash_paid', 10, 3)->default(0);
            $table->decimal('card_paid', 10, 3)->default(0);
            $table->bigInteger('bank_account')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_order_additional_dtl');
    }
};
