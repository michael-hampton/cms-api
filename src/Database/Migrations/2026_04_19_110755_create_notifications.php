<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNotifications extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('member_id');

            $table->string('title');
            $table->text('body')->nullable();

            $table->boolean('is_read')->default(false);

            // optional but very useful for future filtering (campaigns, system, etc.)
            $table->string('type')->nullable();

            // optional: store polymorphic source (campaign, system event, etc.)
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // optional: deep link target
            $table->string('action_url')->nullable();

            $table->timestamps();

            // indexes for performance (you WILL need these later)
            $table->index(['member_id', 'is_read']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
