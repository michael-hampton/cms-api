<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterRelationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_relations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('newsletter_id')
                ->constrained('newsletters')
                ->cascadeOnDelete();

            $table->foreignId('related_newsletter_id')
                ->constrained('newsletters')
                ->cascadeOnDelete();

            $table->string('relation_type');

            $table->integer('priority')->default(0);

            $table->timestamps();

            // Prevent duplicates (same relation twice)
            $table->unique([
                'newsletter_id',
                'related_newsletter_id',
                'relation_type'
            ], 'newsletter_relation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
