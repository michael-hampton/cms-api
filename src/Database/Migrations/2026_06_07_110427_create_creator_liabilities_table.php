<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCreatorLiabilitiesTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_creator_liabilities', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('site_id');

            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id')->nullable();

            $table->integer('amount');
            $table->integer('remaining_amount');

            $table->string('currency', 3)->default('GBP');
            $table->string('status', 50)->default('open');

            $table->text('reason');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamp('settled_at')->nullable();

            $table->unsignedBigInteger('written_off_by')->nullable();
            $table->text('write_off_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'site_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_creator_liabilities');
    }
}
