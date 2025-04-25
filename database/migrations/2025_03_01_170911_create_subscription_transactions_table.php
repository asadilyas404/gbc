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
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('restaurant_id');
            $table->double('price', 24, 3)->default(0);
            $table->integer('validity')->default(0);
            $table->string('payment_method', 191);
            $table->string('reference', 191)->nullable();
            $table->double('paid_amount', 24, 2);
            $table->integer('discount')->default(0);
            $table->longText('package_details');
            $table->string('created_by', 50);
            $table->timestamps();
            $table->string('payment_status', 50)->default('success');
            $table->boolean('transaction_status')->default(true);
            $table->unsignedBigInteger('restaurant_subscription_id')->nullable();
            $table->double('previous_due', 24, 3)->default(0);
            $table->boolean('is_trial')->default(false);
            $table->enum('plan_type', ['renew', 'new_plan', 'first_purchased', 'free_trial', 'old_subscription'])->default('old_subscription');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
