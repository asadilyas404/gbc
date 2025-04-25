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
        Schema::create('coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 191)->nullable();
            $table->string('code', 100)->nullable()->unique();
            $table->date('start_date')->nullable();
            $table->date('expire_date')->nullable();
            $table->decimal('min_purchase', 24)->default(0);
            $table->decimal('max_discount', 24)->default(0);
            $table->decimal('discount', 24)->default(0);
            $table->string('discount_type', 15)->default('percentage');
            $table->string('coupon_type', 191)->default('default');
            $table->integer('limit')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->string('data', 191)->nullable();
            $table->bigInteger('total_uses')->nullable()->default(0);
            $table->string('created_by', 50)->nullable()->default('admin');
            $table->string('customer_id')->nullable()->default('["all"]');
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('restaurant_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coupons');
    }
};
