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
        Schema::create('carts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('item_id');
            $table->boolean('is_guest')->default(false);
            $table->text('add_on_ids')->nullable();
            $table->text('add_on_qtys')->nullable();
            $table->string('item_type');
            $table->double('price', 24, 3);
            $table->integer('quantity');
            $table->text('variations')->nullable();
            $table->timestamps();
            $table->text('variation_options')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('carts');
    }
};
