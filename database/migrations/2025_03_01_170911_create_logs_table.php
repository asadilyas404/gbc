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
        Schema::create('logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('logable_id');
            $table->string('logable_type');
            $table->string('action_type', 50);
            $table->string('model');
            $table->unsignedBigInteger('model_id');
            $table->string('action_details')->nullable();
            $table->string('ip_address')->nullable();
            $table->longText('before_state')->nullable();
            $table->longText('after_state')->nullable();
            $table->unsignedBigInteger('restaurant_id')->nullable();
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
        Schema::dropIfExists('logs');
    }
};
