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
        Schema::create('restaurant_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->boolean('instant_order')->default(false);
            $table->boolean('customer_date_order_sratus')->default(false);
            $table->integer('customer_order_date')->default(0);
            $table->timestamps();
            $table->boolean('halal_tag_status')->default(false);
            $table->boolean('extra_packaging_status')->default(false);
            $table->boolean('is_extra_packaging_active')->default(false);
            $table->double('extra_packaging_amount', 23, 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurant_configs');
    }
};
