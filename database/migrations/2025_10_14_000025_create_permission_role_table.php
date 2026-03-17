<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
            $table->index('permission_id', 'permission_role_permission_id_index');
            $table->index('role_id', 'permission_role_role_id_index');
            $table->foreign('permission_id', 'permission_role_permission_id_foreign')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
            $table->foreign('role_id', 'permission_role_role_id_foreign')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('permission_role');
    }
};
