<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 127);
            $table->string('display_name', 127);
            $table->text('value')->nullable();
            $table->text('details')->nullable();
            $table->string('type', 127);
            $table->integer('order')->default(1);
            $table->string('group', 127)->nullable();
            $table->primary('id');
            $table->unique('key', 'settings_key_unique');
        });
    }
    public function down()
    {
        Schema::dropIfExists('settings');
    }
};
