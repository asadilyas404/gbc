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
        Schema::create('food', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 191)->nullable();
            $table->text('description')->nullable();
            $table->string('image', 30)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('category_ids', 191)->nullable();
            $table->text('variations')->nullable();
            $table->string('add_ons', 191)->nullable();
            $table->string('attributes', 191)->nullable();
            $table->text('choice_options')->nullable();
            $table->decimal('price', 24)->default(0);
            $table->decimal('tax', 24)->default(0);
            $table->string('tax_type', 20)->default('percent');
            $table->decimal('discount', 24)->default(0);
            $table->string('discount_type', 20)->default('percent');
            $table->time('available_time_starts')->nullable();
            $table->time('available_time_ends')->nullable();
            $table->boolean('veg')->default(false);
            $table->boolean('status')->default(true);
            $table->unsignedBigInteger('restaurant_id');
            $table->timestamps();
            $table->integer('order_count')->default(0);
            $table->double('avg_rating', 16, 14)->default(0);
            $table->integer('rating_count')->default(0);
            $table->string('rating')->nullable();
            $table->boolean('recommended')->default(false);
            $table->string('slug')->nullable();
            $table->integer('maximum_cart_quantity')->nullable();
            $table->boolean('is_halal')->default(false);
            $table->integer('item_stock')->default(0);
            $table->integer('sell_count')->default(0);
            $table->string('stock_type', 20)->default('unlimited');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('food');
    }
};
