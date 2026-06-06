<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberNotesTable extends Migration
{
    public function up(): void
    {
        Schema::create('member_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('author_id')->nullable()->comment('CRM user who wrote the note');
            $table->string('author_name', 100)->nullable()->comment('Denormalised display name at time of writing');
            $table->text('body');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('NULL = top-level note, set = reply');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->index(['member_id', 'site_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
