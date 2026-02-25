<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class EnhanceBriefs extends Migration
{
    public function up(): void
    {
        Schema::table('briefs', function ($table) {
            $table->dropColumn('status');
            $table->enum('status', ['draft', 'in_review', 'ready', 'converted', 'archived'])->default('draft');
            $table->boolean('is_active')->default(true);
            $table->integer('target_word_count')->nullable();
            $table->date('target_publish_date')->nullable();
            $table->text('seo_keywords')->nullable();
            $table->string('target_audience')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->integer('last_activity_user_id')->nullable();
            $table->integer('template_id')->nullable();
            $table->integer('parent_brief_id')->nullable();
        });

        // Create brief_templates table
        Schema::create('brief_templates', function ($table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // review, listicle, how-to, guide, custom
            $table->text('structure')->nullable(); // JSON structure
            $table->json('default_fields')->nullable();
            $table->boolean('is_system')->default(false);
            $table->integer('created_by');
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        // Create brief_collaborators table
        Schema::create('brief_collaborators', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->foreignId('user_id');
            $table->string('role'); // writer, editor, reviewer, fact_checker
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Create brief_tasks table
        Schema::create('brief_tasks', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->foreignId('comment_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable();
            $table->foreignId('created_by');
            $table->string('status')->default('pending'); // pending, in_progress, completed
            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('comment_id')->references('id')->on('brief_comments')->cascadeOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // Create brief_versions table
        Schema::create('brief_versions', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->integer('version_number');
            $table->text('title');
            $table->text('description')->nullable();
            $table->json('data')->nullable(); // Full snapshot
            $table->foreignId('created_by');
            $table->string('change_summary')->nullable();
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });

        // Enhance brief_comments table
        Schema::table('brief_comments', function ($table) {
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('mentions')->nullable(); // Array of mentioned user IDs
            $table->boolean('is_task')->default(false);
            $table->foreignId('task_id')->nullable();

            $table->foreign('resolved_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('task_id')->references('id')->on('brief_tasks')->cascadeOnDelete();
        });

        // Create brief_relationships table
        Schema::create('brief_relationships', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->foreignId('related_brief_id')->nullable();
            $table->foreignId('related_page_id')->nullable();
            $table->string('relationship_type'); // series, related, reference, parent_child
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('related_brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('related_page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['brief_id', 'related_brief_id']);
        });

        // Create brief_activity_log table
        Schema::create('brief_activity_log', function ($table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->foreignId('user_id');
            $table->string('action'); // created, updated, commented, assigned, status_changed
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
