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
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('order_amount', 24, 3)->default(0);
            $table->decimal('coupon_discount_amount', 24, 3)->default(0);
            $table->string('coupon_discount_title', 191)->nullable();
            $table->string('payment_status', 191)->default('unpaid');
            $table->string('order_status', 191)->default('pending');
            $table->decimal('total_tax_amount', 24, 3)->default(0);
            $table->string('payment_method', 30)->nullable();
            $table->string('transaction_reference', 191)->nullable();
            $table->bigInteger('delivery_address_id')->nullable();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->string('coupon_code', 191)->nullable();
            $table->text('order_note')->nullable();
            $table->string('order_type', 191)->default('delivery');
            $table->boolean('checked')->default(false);
            $table->unsignedBigInteger('restaurant_id');
            $table->timestamps();
            $table->decimal('delivery_charge', 24, 3)->default(0);
            $table->timestamp('schedule_at')->nullable();
            $table->string('callback', 191)->nullable();
            $table->string('otp', 191)->nullable();
            $table->timestamp('pending')->nullable();
            $table->timestamp('accepted')->nullable();
            $table->timestamp('confirmed')->nullable();
            $table->timestamp('processing')->nullable();
            $table->timestamp('handover')->nullable();
            $table->timestamp('picked_up')->nullable();
            $table->timestamp('delivered')->nullable();
            $table->timestamp('canceled')->nullable();
            $table->timestamp('refund_requested')->nullable();
            $table->timestamp('refunded')->nullable();
            $table->text('delivery_address')->nullable();
            $table->boolean('scheduled')->default(false);
            $table->decimal('restaurant_discount_amount', 24, 3);
            $table->decimal('original_delivery_charge', 24, 3)->default(0);
            $table->timestamp('failed')->nullable();
            $table->decimal('adjusment', 24, 3)->default(0);
            $table->boolean('edited')->default(false);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->double('dm_tips', 24, 3)->default(0);
            $table->string('processing_time', 10)->nullable();
            $table->string('free_delivery_by')->nullable();
            $table->timestamp('refund_request_canceled')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('canceled_by', 50)->nullable();
            $table->string('tax_status', 50)->nullable();
            $table->string('coupon_created_by', 50)->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('discount_on_product_by', 50)->default('vendor');
            $table->double('distance', 23, 3)->nullable()->default(0);
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->text('cancellation_note')->nullable();
            $table->double('tax_percentage', 24, 3)->nullable();
            $table->text('delivery_instruction')->nullable();
            $table->string('unavailable_item_note')->nullable();
            $table->boolean('cutlery')->default(false);
            $table->double('additional_charge', 23, 3)->default(0);
            $table->double('partially_paid_amount', 23, 3)->default(0);
            $table->string('order_proof')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->unsignedBigInteger('cash_back_id')->nullable();
            $table->double('extra_packaging_amount', 23, 3)->default(0);
            $table->double('ref_bonus_amount', 23, 3)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
