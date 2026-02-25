final <?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUrlHandleToSites extends Migration
{
    #[\Override]
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('url_handle')->default('home')->nullable();
        });
    }

    #[\Override]
    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
