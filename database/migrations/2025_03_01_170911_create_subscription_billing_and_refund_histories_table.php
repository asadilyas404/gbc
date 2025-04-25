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
        Schema::create('subscription_billing_and_refund_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('package_id')->nullable();
            $table->enum('transaction_type', ['pending_bill', 'refund'])->default('pending_bill');
            $table->double('amount', 24, 3);
            $table->boolean('is_success')->default(false);
            $table->string('reference', 191)->nullable();
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
        Schema::dropIfExists('subscription_billing_and_refund_histories');
    }
};
