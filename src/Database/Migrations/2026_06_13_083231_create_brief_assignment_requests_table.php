<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBriefAssignmentRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('brief_assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brief_id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('contributor_id');
            $table->string('type', 50);
            $table->string('status', 50)->default('pending');
            $table->text('message')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('requested_deadline_at')->nullable();
            $table->text('scope_details')->nullable();
            $table->text('editor_response')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->onDelete('cascade');
            $table->foreign('contributor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['brief_id', 'status']);
            $table->index(['assignment_id', 'type', 'status']);
            $table->index('contributor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
