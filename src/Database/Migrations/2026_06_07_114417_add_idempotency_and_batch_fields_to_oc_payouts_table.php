<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddIdempotencyAndBatchFieldsToOcPayoutsTable extends Migration
{
    public function up(): void
    {
        Schema::table('oc_payouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('batch_id')->nullable()->after('site_id');
            $table->unsignedBigInteger('accrual_window_id')->nullable()->after('batch_id');
            $table->string('idempotency_key')->nullable()->after('accrual_window_id');

            $table->index('batch_id', 'oc_payouts_batch_id_idx');
            $table->index('accrual_window_id', 'oc_payouts_accrual_window_id_idx');
            $table->unique('idempotency_key', 'oc_payouts_idempotency_key_unique');

            $table->unique(
                ['user_id', 'site_id', 'accrual_window_id', 'batch_id'],
                'oc_payouts_user_site_window_batch_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('oc_payouts', function (Blueprint $table): void {
            $table->dropColumn([
                'batch_id',
                'accrual_window_id',
                'idempotency_key',
            ]);
        });
    }
}
