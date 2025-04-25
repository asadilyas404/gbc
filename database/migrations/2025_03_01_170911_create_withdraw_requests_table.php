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
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('transaction_note', 191)->nullable();
            $table->decimal('amount', 23, 3)->default(0);
            $table->boolean('approved')->default(false);
            $table->timestamps();
            $table->unsignedBigInteger('delivery_man_id')->nullable();
            $table->unsignedBigInteger('withdrawal_method_id')->nullable();
            $table->longText('withdrawal_method_fields')->nullable();
            $table->string('type', 20)->default('manual');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
