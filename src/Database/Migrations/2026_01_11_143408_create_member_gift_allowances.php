<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateMemberGiftAllowances extends Migration
{
    public function up(): void
    {
        Schema::create('member_gift_allowances', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->integer('annual_gift_limit')->default(10);
            $table->integer('gifts_used_this_year')->default(0);
            $table->date('year_start_date');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->unique(['member_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
