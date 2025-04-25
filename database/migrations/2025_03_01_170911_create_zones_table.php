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
        Schema::create('zones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->unique();
            $table->polygon('coordinates');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->string('restaurant_wise_topic', 191)->nullable();
            $table->string('customer_wise_topic', 191)->nullable();
            $table->string('deliveryman_wise_topic', 191)->nullable();
            $table->double('minimum_shipping_charge', 16, 3)->unsigned()->nullable();
            $table->double('per_km_shipping_charge', 16, 3)->unsigned()->nullable();
            $table->double('maximum_shipping_charge', 23, 3)->nullable();
            $table->double('max_cod_order_amount', 23, 3)->nullable();
            $table->double('increased_delivery_fee', 8, 2)->default(0);
            $table->boolean('increased_delivery_fee_status')->default(false);
            $table->string('increase_delivery_charge_message')->nullable();
            $table->string('display_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zones');
    }
};
