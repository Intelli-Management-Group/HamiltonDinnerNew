<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('data_types', function (Blueprint $table) {
            $table->increments('id'); // Primary key, auto_increment
            $table->string('name', 127);
            $table->string('slug', 127);
            $table->string('display_name_singular', 127);
            $table->string('display_name_plural', 127);
            $table->string('icon', 127)->nullable();
            $table->string('model_name', 127)->nullable();
            $table->string('policy_name', 127)->nullable();
            $table->string('controller', 127)->nullable();
            $table->string('description', 255)->nullable();
            $table->tinyInteger('generate_permissions')->default(0);
            $table->tinyInteger('server_side')->default(0);
            $table->text('details')->nullable();
            $table->timestamps();
            $table->primary('id');
            $table->unique('name');
            $table->unique('slug');
        });
    }
    public function down() {
        Schema::dropIfExists('data_types');
    }
};
