<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSubjectTypeAndPriorityToSegmentsTable extends Migration
{
    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            $table->string('subject_type')
                ->default('member')
                ->index()
                ->after('category');

            $table->unsignedInteger('priority')
                ->default(100)
                ->after('subject_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
