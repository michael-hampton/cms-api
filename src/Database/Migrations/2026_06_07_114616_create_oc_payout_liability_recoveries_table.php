<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcPayoutLiabilityRecoveriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_payout_liability_recoveries', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('payout_id');
            $table->unsignedBigInteger('creator_liability_id');

            $table->integer('amount');

            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index('payout_id', 'oc_payout_liability_recoveries_payout_id_idx');
            $table->index('creator_liability_id', 'oc_payout_liability_recoveries_liability_id_idx');
            $table->index(['source_type', 'source_id'], 'oc_payout_liability_recoveries_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_payout_liability_recoveries');
    }
}
