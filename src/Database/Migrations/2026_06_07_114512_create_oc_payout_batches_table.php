<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcPayoutBatchesTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_payout_batches', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('accrual_window_id')->nullable();

            $table->string('status', 50)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index('site_id', 'oc_payout_batches_site_id_idx');
            $table->index('accrual_window_id', 'oc_payout_batches_accrual_window_id_idx');
            $table->index('status', 'oc_payout_batches_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_payout_batches');
    }
}
