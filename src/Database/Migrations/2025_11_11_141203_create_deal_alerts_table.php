final <?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateDealAlertsTable extends Migration
{
    #[\Override]
    public function up(): void
    {
        Schema::create('deal_alerts', function ($table) {
            $table->id();
            $table->foreignId('member_id')->nullable();
            $table->string('email');
            $table->enum('frequency', ['instant', 'daily', 'weekly'])->default('daily');
            $table->json('preferences')->nullable(); // category preferences, discount thresholds, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();

            $table->index('email');
            $table->index('is_active');
        });
    }

    #[\Override]
    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
