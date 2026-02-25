<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class ChangeTypeOnBriefAttachments extends Migration
{
    public function up(): void
    {
        Schema::table('brief_attachments', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->enum('type', ['image', 'product', 'document', 'url', 'deal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
