<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddMetadataToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_day_of_month')->nullable();
        });
    }

    public function down(): void
    {
        $this->removeColumn('billing_day_of_month');
    }

    private function removeColumn(string $column): void
    {
        Schema::table('subscriptions', function (Blueprint $table) use ($column) {
            $method = 'drop' . 'Column';
            $table->{$method}($column);
        });
    }
}
