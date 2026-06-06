<?php

use App\Framework\Database\Database;
use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCentTotalsToOrderItems extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_items', 'subtotal_cents')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->integer('subtotal_cents')->default(0)->after('subtotal');
            });
        }

        if (!Schema::hasColumn('order_items', 'shipping_cents')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->integer('shipping_cents')->default(0)->after('subtotal_cents');
            });
        }

        Database::getInstance()->exec("
            UPDATE order_items
            SET subtotal_cents = ROUND(COALESCE(subtotal, 0) * 100),
                shipping_cents = COALESCE(shipping_cents, 0)
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'shipping_cents')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('shipping_cents');
            });
        }

        if (Schema::hasColumn('order_items', 'subtotal_cents')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('subtotal_cents');
            });
        }
    }
}
