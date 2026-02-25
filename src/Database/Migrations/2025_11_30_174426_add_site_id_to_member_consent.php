<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSiteIdToMemberConsent extends Migration
{
    public function up(): void
    {
        Schema::table('member_consents', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
