<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_activities', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type', 50)->comment('login, logout, create, update, delete, view, etc.');
            $table->text('description')->nullable();
            $table->string('entity_type', 100)->nullable()->comment('Model/table name affected');
            $table->string('entity_id', 36)->nullable()->comment('ID of the affected record');
            $table->json('old_values')->nullable()->comment('Previous values before change');
            $table->json('new_values')->nullable()->comment('New values after change');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_info', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('route', 255)->nullable()->comment('URL/route accessed');
            $table->string('method', 10)->nullable()->comment('HTTP method used');
            $table->json('request_data')->nullable();
            $table->smallInteger('response_code')->nullable();
            $table->json('additional_data')->nullable()->comment('Any extra information');
            $table->timestamps();

            
            // Create indexes
            $table->index('user_id');
            $table->index('activity_type');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
    }
};
