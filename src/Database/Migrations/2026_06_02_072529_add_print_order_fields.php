<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPrintOrderFields extends Migration
{
    public function up(): void
    {
//        Schema::table('issue_deliveries', function (Blueprint $table) {
//            $table->date('print_order_date')->nullable()->after('fulfilment_date');
//            $table->unsignedInteger('print_overrun')->default(0)->after('print_order_date');
//            $table->unsignedInteger('additional_stock')->default(0)->after('print_overrun');
//            $table->unsignedInteger('export_overrun')->default(0)->after('additional_stock');
//            $table->unsignedInteger('subscription_total')->nullable()->after('export_overrun');
//            $table->boolean('print_order_done')->default(false)->after('subscription_total');
//        });

        Schema::create('issue_delivery_regions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('issue_delivery_id')
                ->constrained('issue_deliveries')
                ->cascadeOnDelete();

            $table->string('region_code', 50);

            $table->unsignedInteger('uk_surplus')->default(0);
            $table->unsignedInteger('export_surplus')->default(0);

            $table->timestamps();

            $table->unique(['issue_delivery_id', 'region_code']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
