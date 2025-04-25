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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('payer_id', 64)->nullable();
            $table->string('receiver_id', 64)->nullable();
            $table->decimal('payment_amount', 24)->default(0);
            $table->string('gateway_callback_url', 191)->nullable();
            $table->string('success_hook', 100)->nullable();
            $table->string('failure_hook', 100)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('currency_code', 20)->default('USD');
            $table->string('payment_method', 50)->nullable();
            $table->longText('additional_data')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->timestamps();
            $table->longText('payer_information')->nullable();
            $table->string('external_redirect_link')->nullable();
            $table->longText('receiver_information')->nullable();
            $table->string('attribute_id', 64)->nullable();
            $table->string('attribute')->nullable();
            $table->string('payment_platform')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_requests');
    }
};
