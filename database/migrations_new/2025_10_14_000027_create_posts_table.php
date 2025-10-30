<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('author_id');
            $table->integer('category_id')->nullable();
            $table->string('title', 127);
            $table->string('seo_title', 127)->nullable();
            $table->text('excerpt')->nullable();
            $table->text('body');
            $table->string('image', 127)->nullable();
            $table->string('slug', 127);
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->enum('status', ['PUBLISHED','DRAFT','PENDING'])->default('DRAFT');
            $table->tinyInteger('featured')->default(0);
            $table->timestamps()->nullable();
            $table->unique('slug', 'posts_slug_unique');
        });
    }
    public function down()
    {
        Schema::dropIfExists('posts');
    }
};
