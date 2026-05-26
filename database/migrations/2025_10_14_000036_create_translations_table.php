<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('table_name', 127);
            $table->string('column_name', 127);
            $table->unsignedInteger('foreign_key');
            $table->string('locale', 127);
            $table->text('value');
            $table->timestamps();
            $table->unique(['table_name', 'column_name', 'foreign_key', 'locale'], 'translations_table_name_column_name_foreign_key_locale_unique');
        });
    }
    public function down()
    {
        Schema::dropIfExists('translations');
    }
};
