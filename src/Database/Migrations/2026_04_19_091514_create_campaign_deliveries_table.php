<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCampaignDeliveriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id');

            $table->string('key', 10); // A, B, C

            $table->unsignedSmallInteger('weight')->default(50);

            $table->json('blocks')->nullable();

            $table->timestamps();

            $table->foreign('campaign_id')->on('campaigns')->references('id')->cascadeOnDelete();

            $table->unique(['campaign_id', 'key'], 'uq_campaign_variant');
            $table->index('campaign_id');
        });

        Schema::create('campaign_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id');
            $table->foreignId('campaign_id');

            $table->enum('event_type', ['open', 'click']);

            $table->json('metadata')->nullable();

            $table->foreignId('variant_id')
                ->nullable();

            $table->timestamps();

            $table->foreign('variant_id')->on('campaign_variants')->references('id')->nullOnDelete();
            $table->foreign('campaign_id')->on('campaigns')->references('id')->cascadeOnDelete();
            $table->foreign('member_id')->on('members')->references('id')->cascadeOnDelete();

            $table->index(['member_id', 'campaign_id']);
            $table->index(['campaign_id', 'event_type']);
            $table->index('created_at');
        });

        Schema::create('campaign_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id');
            $table->foreignId('campaign_id');

            $table->string('channel', 50);

            $table->string('audience_key', 100)->nullable();

            $table->foreignId('variant_id')
                ->nullable();

            $table->foreign('variant_id')->on('campaign_variants')->references('id')->nullOnDelete();
            $table->foreign('campaign_id')->on('campaigns')->references('id')->cascadeOnDelete();
            $table->foreign('member_id')->on('members')->references('id')->cascadeOnDelete();

            $table->string('token', 64)->unique();

            $table->timestamps();

            $table->timestamp('delivered_at')->useCurrent();

            $table->index(['member_id', 'campaign_id'], 'idx_member_campaign');
            $table->index(['campaign_id', 'audience_key'], 'idx_campaign_audience');
            $table->index('delivered_at', 'idx_delivered_at');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id');

            $table->text('endpoint');

            $table->json('keys');

            $table->foreign('member_id')->on('members')->references('id')->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('member_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('force_channel', 50)->nullable()->after('fallback_channels');
            $table->text('push_body')->nullable()->after('force_channel');
            $table->string('push_icon')->nullable()->after('push_body');
            $table->string('push_url')->nullable()->after('push_icon');
        });

        Schema::table('campaign_executions', function (Blueprint $table) {
            $table->boolean('is_marketing')->default(true)->after('segment_key');
        });

        \App\Framework\Database\Database::table('campaign_executions')->update([
            'is_marketing' => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
