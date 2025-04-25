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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('address_type', 100);
            $table->string('contact_person_number', 20);
            $table->text('address')->nullable();
            $table->string('latitude', 191)->nullable();
            $table->string('longitude', 191)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('contact_person_name', 100)->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('zone_id');
            $table->string('floor')->nullable();
            $table->string('road')->nullable();
            $table->string('house')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_addresses');
    }
};
