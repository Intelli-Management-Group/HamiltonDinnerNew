<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('move_in_summary_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('key_param');
            $table->text('value')->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('move_in_summary_values');
    }
};
