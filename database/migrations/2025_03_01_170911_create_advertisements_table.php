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
        Schema::create('advertisements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->enum('add_type', ['video_promotion', 'restaurant_promotion'])->default('restaurant_promotion');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('pause_note')->nullable();
            $table->text('cancellation_note')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('video_attachment')->nullable();
            $table->integer('priority')->nullable();
            $table->boolean('is_rating_active')->default(false);
            $table->boolean('is_review_active')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_updated')->default(false);
            $table->unsignedBigInteger('created_by_id');
            $table->string('created_by_type');
            $table->enum('status', ['pending', 'running', 'approved', 'expired', 'denied', 'paused'])->default('pending');
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
        Schema::dropIfExists('advertisements');
    }
};
