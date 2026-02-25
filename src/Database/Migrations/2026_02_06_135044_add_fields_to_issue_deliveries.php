<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToIssueDeliveries extends Migration
{
    public function up(): void
    {
        Schema::table('issue_deliveries', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable();
            $table->foreignId('site_id')->nullable();
            $table->integer('promotion_id')->nullable();
            $table->string('issue_code')->nullable();
            $table->date('cut_off_date')->nullable();
            $table->date('fulfilment_date')->nullable();

            $table->foreign('site_id')->references('id')->on('sites');
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
