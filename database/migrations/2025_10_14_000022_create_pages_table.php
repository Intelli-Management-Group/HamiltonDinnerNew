<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('author_id');
            $table->string('title', 127);
            $table->text('excerpt')->nullable();
            $table->text('body')->nullable();
            $table->string('image', 127)->nullable();
            $table->string('slug', 127);
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('INACTIVE');
            $table->timestamps();
            $table->unique('slug', 'pages_slug_unique');
        });
    }
    public function down()
    {
        Schema::dropIfExists('pages');
    }
};
