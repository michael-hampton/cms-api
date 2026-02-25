<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFilesizeToBriefAttachments extends Migration
{
    public function up(): void
    {
        Schema::table('brief_attachments', function (Blueprint $table) {
            $table->integer('filesize')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
