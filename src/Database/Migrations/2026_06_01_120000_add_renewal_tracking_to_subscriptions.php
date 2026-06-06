<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddRenewalTrackingToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('renewal_count')
                ->default(0)
                ->after('status');

            $table->dateTime('first_renewed_at')
                ->nullable()
                ->after('renewal_count');

            $table->dateTime('last_renewed_at')
                ->nullable()
                ->after('first_renewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'renewal_count',
                'first_renewed_at',
                'last_renewed_at',
            ]);
        });
    }
}
