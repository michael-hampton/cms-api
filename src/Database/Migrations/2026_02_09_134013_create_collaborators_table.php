<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateCollaboratorsTable extends Migration
{
    public function up(): void
    {
        Schema::create('collaborators', function ($table) {
            $table->id();
            $table->string('collaboratable_type');
            $table->unsignedBigInteger('collaboratable_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 50);
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->unsignedBigInteger('site_id');
            $table->timestamps();

            $table->index(['collaboratable_type', 'collaboratable_id']);
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unique(['collaboratable_type', 'collaboratable_id', 'user_id'], 'collaborator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
