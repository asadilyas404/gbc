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
        Schema::create('restaurant_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('restaurant_id');
            $table->date('expiry_date');
            $table->string('max_order');
            $table->string('max_product');
            $table->boolean('pos')->default(false);
            $table->boolean('mobile_app')->default(false);
            $table->boolean('chat')->default(false);
            $table->boolean('review')->default(false);
            $table->boolean('self_delivery')->default(false);
            $table->boolean('status')->default(true);
            $table->integer('total_package_renewed')->default(0);
            $table->timestamps();
            $table->integer('validity')->default(0);
            $table->boolean('is_trial')->default(false);
            $table->dateTime('renewed_at')->nullable();
            $table->boolean('is_canceled')->default(false);
            $table->enum('canceled_by', ['none', 'admin', 'restaurant'])->default('none');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurant_subscriptions');
    }
};
