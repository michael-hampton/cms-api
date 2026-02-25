<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateProductSpecificationGroups extends Migration
{
    public function up(): void
    {
        Schema::create('product_specification_groups', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });

        Schema::table('product_specifications', function ($table) {
            $table->foreignId('specification_group_id')->nullable()->after('product_id');
            $table->foreign('specification_group_id')
                ->references('id')
                ->on('product_specification_groups')
                ->onDelete('set null');
            $table->index('specification_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
