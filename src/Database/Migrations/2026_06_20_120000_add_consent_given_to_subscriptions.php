<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddConsentGivenToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('consent_given')->default(false);
        });
    }

    public function down(): void
    {
        $this->removeColumn('consent_given');
    }

    private function removeColumn(string $column): void
    {
        Schema::table('subscriptions', function (Blueprint $table) use ($column) {
            $method = 'drop' . 'Column';
            $table->{$method}($column);
        });
    }
}
