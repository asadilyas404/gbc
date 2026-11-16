<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlaceIdToRestaurantsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('restaurants', 'place_id')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->string('place_id', 255)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('restaurants', 'place_id')) {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->dropColumn('place_id');
            });
        }
    }
}
