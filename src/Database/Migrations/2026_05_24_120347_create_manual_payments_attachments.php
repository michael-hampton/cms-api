<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateManualPaymentsAttachments extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable();             // ManualPaymentType enum
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('received_at')->nullable();

            $table->foreign('member_id')->references('id')->on('members');
            $table->index(['member_id', 'site_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');
            $table->string('attachmentable_type', 64);      // AttachmentableType enum value
            $table->unsignedBigInteger('attachmentable_id');
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size');         // bytes
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members');
            $table->index(['attachmentable_type', 'attachmentable_id'], 'attachments_poly_index');
            $table->index(['member_id', 'site_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('charging_disabled')->default(false)->after('is_active');
            $table->text('charging_disabled_reason')->nullable()->after('charging_disabled');
            $table->dateTime('charging_disabled_at')->nullable()->after('charging_disabled_reason');
            $table->unsignedBigInteger('charging_disabled_by')->nullable()->after('charging_disabled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
