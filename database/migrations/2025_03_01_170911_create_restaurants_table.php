<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191);
            $table->string('phone', 20)->unique();
            $table->string('email', 100)->nullable();
            $table->string('logo', 191)->nullable();
            $table->string('latitude', 191)->nullable();
            $table->string('longitude', 191)->nullable();
            $table->text('address')->nullable();
            $table->text('footer_text')->nullable();
            $table->decimal('minimum_order', 24)->default(0);
            $table->decimal('comission', 24)->nullable();
            $table->boolean('schedule_order')->default(false);
            $table->time('opening_time')->nullable()->default(DB::raw("TO_DATE('10:00:00', 'HH24:MI:SS')"));
            $table->time('closeing_time')->nullable()->default(DB::raw("TO_DATE('23:00:00', 'HH24:MI:SS')"));
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('vendor_id');
            $table->timestamps();
            $table->boolean('free_delivery')->default(false);
            $table->string('rating', 191)->nullable();
            $table->string('cover_photo', 191)->nullable();
            $table->boolean('delivery')->default(true);
            $table->boolean('take_away')->default(true);
            $table->boolean('food_section')->default(true);
            $table->decimal('tax', 24)->default(0);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->boolean('reviews_section')->default(true);
            $table->boolean('active')->default(true);
            $table->string('off_day', 191)->default(' ');
            $table->string('gst', 191)->nullable();
            $table->boolean('self_delivery_system')->default(false);
            $table->boolean('pos_system')->default(false);
            $table->decimal('minimum_shipping_charge', 24)->default(0);
            $table->string('delivery_time', 191)->nullable()->default('30-40');
            $table->boolean('veg')->default(true);
            $table->boolean('non_veg')->default(true);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('total_order')->default(0);
            $table->double('per_km_shipping_charge', 16, 3)->unsigned()->nullable();
            $table->string('restaurant_model', 50)->nullable()->default('commission');
            $table->double('maximum_shipping_charge', 23, 3)->nullable();
            $table->string('slug')->nullable();
            $table->boolean('order_subscription_active')->nullable()->default(false);
            $table->boolean('cutlery')->default(false);
            $table->string('meta_title', 100)->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_image', 100)->nullable();
            $table->boolean('announcement')->default(false);
            $table->string('announcement_message')->nullable();
            $table->text('qr_code')->nullable();
            $table->string('free_delivery_distance')->nullable();
            $table->text('additional_data')->nullable();
            $table->text('additional_documents')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurants');
    }
};
