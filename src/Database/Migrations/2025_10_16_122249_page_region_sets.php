<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageRegionSets extends Migration
{
    public function up(): void
    {
        Schema::create('page_region_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('region_set_id');
            $table->foreignId('site_id');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            $table->foreign('region_set_id')->references('id')->on('region_sets')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unique(['page_id', 'region_set_id']);
        });

        Schema::create('page_territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('territory_id');
            $table->foreignId('site_id');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            $table->foreign('territory_id')->references('id')->on('territories')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unique(['page_id', 'territory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
