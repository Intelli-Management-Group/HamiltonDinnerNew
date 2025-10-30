<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('user_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type', 50)
                ->comment('login, logout, create, update, delete, view, etc.');
            $table->text('description')->nullable();
            $table->string('entity_type', 100)->nullable()
                ->comment('Model/table name affected');
            $table->string('entity_id', 36)->nullable()
                ->comment('ID of the affected record');
            $table->longText('old_values')
                ->nullable()
                ->comment('Previous values before change');
            $table->longText('new_values')
                ->nullable()
                ->comment('New values after change');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_info', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('route', 255)
                ->nullable()
                ->comment('URL/route accessed');
            $table->string('method', 10)
                ->nullable()
                ->comment('HTTP method used');
            $table->longText('request_data')->nullable();
            $table->smallInteger('response_code')->nullable();
            $table->longText('additional_data')
                ->nullable()
                ->comment('Any extra information');
            $table->timestamps()->nullable();
            $table->primary('id');
            $table->index('user_id', 'user_activities_user_id_index');
            $table->index('activity_type', 'user_activities_activity_type_index');
            $table->index(['entity_type', 'entity_id'], 'user_activities_entity_type_entity_id_index');
            $table->index('created_at', 'user_activities_created_at_index');
        });
    }
    public function down()
    {
        Schema::dropIfExists('user_activities');
    }
};
