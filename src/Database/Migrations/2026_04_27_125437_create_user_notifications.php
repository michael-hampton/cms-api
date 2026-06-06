<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateUserNotifications extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            $table->string('type'); // article_approved, payout_processed, etc
            $table->json('data')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // ✅ Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['user_id'], 'user_notifications_user_idx');
            $table->index(['user_id', 'read_at'], 'user_notifications_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
