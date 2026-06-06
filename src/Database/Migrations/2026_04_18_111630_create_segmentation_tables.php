<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSegmentationTables extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // activation|engagement|retention|monetisation|behaviour
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('segment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id');
            $table->string('field');          // dot-notation path into profile, e.g. scores.activity_score
            $table->string('operator');       // >, <, =, !=, IN, CONTAINS
            $table->json('value');            // scalar or array
            $table->string('boolean')->default('AND'); // AND | OR (how this rule combines with the PREVIOUS one)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('segment_id')->on('segments')->references('id')->cascadeOnDelete();
            $table->index('segment_id');
        });

        Schema::create('member_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id');
            $table->unsignedBigInteger('site_id');
            $table->foreignId('segment_id');
            $table->timestamp('assigned_at');
            $table->timestamp('last_seen_at');

            // Each member can only appear once per segment per site
            $table->unique(['member_id', 'site_id', 'segment_id']);

            $table->foreign('member_id')->on('members')->references('id')->cascadeOnDelete();

            $table->foreign('site_id')->on('sites')->references('id')->cascadeOnDelete();
            $table->foreign('segment_id')->on('segments')->references('id')->cascadeOnDelete();


            $table->index(['member_id', 'site_id']);
            $table->index('segment_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('segment_id')->nullable();
            $table->string('channel');        // email | notification | push
            $table->string('template');       // e.g. emails.we_miss_you
            $table->unsignedSmallInteger('cooldown_hours')->default(48);
            $table->unsignedSmallInteger('priority')->default(0);

            $table->index(['segment_id', 'is_active']);
        });

        Schema::create('campaign_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('campaign_id');
            $table->string('segment_key');
            $table->timestamp('sent_at');

            $table->foreign('member_id')->on('members')->references('id')->cascadeOnDelete();
            $table->foreign('campaign_id')->on('campaigns')->references('id')->cascadeOnDelete();

            // For fast cooldown lookups
            $table->index(['member_id', 'campaign_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
