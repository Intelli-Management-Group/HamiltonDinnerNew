<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('backend_roles', function (Blueprint $table) {
                $table->increments('id'); // Primary key, auto_increment
                $table->string('name', 191);
                $table->string('guard_name', 191);
                $table->timestamps();
                $table->unique(['name', 'guard_name'], 'backend_roles_name_guard_unique');
                $table->primary('id');
        });
    }
    public function down() {
        Schema::dropIfExists('backend_roles');
    }
};
