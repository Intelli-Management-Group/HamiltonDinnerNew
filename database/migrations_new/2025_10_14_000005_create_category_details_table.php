<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('category_details', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cat_name', 127);
            $table->string('category_chinese_name', 255)
                ->nullable()
                ->charset('utf8mb3')
                ->collation('utf8mb3_unicode_ci');
            $table->tinyInteger('type')->comment('1-Breakfast,2-Lunch,3-Dinner');

            // Original has default 0, but TODO: should this be default null?
            // Also the unsigned() was not in original
            $table->integer('parent_id')
                ->unsigned()
                ->nullable()
                ->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            $table->primary('id');

            // This was not in the original, but similar to categories table
            $table->index('parent_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('category_details')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }
    public function down() {
        Schema::dropIfExists('category_details');
    }
};
