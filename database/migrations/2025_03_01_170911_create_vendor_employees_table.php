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
        Schema::create('vendor_employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('f_name', 100)->nullable();
            $table->string('l_name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 100)->unique();
            $table->string('image', 100)->nullable();
            $table->unsignedBigInteger('employee_role_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('restaurant_id');
            $table->string('password', 100);
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->string('firebase_token', 191)->nullable();
            $table->string('auth_token', 191)->nullable();
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
        Schema::dropIfExists('vendor_employees');
    }
};
