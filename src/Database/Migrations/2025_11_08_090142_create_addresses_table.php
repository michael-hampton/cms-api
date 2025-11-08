<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id');
            $table->enum('type', ['shipping', 'billing', 'both'])->default('both');
            $table->boolean('is_default')->default(false);
            $table->string('label', 100)->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postcode', 20);
            $table->string('country', 100);
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();

            $table->index('member_id');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
