<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionPolicySettingOverridesTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_policy_setting_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('site_id');
            $table->string('policy_class');
            $table->string('setting_key');

            // JSON so a single column can hold bool/int/null values across
            // the different setting types without a column-per-type schema.
            $table->json('value');

            $table->text('reason');
            $table->foreignId('created_by_user_id');
            $table->boolean('active')->default(true);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('site_id')->references('id')->on('sites');

            // No unique index here: a key can be overridden, cleared, and
            // re-overridden many times, and every one of those rows is
            // kept for audit. "Only one active row per key" is enforced
            // by SubscriptionPolicySettingOverrideRepository::setActive()
            // deactivating any prior active row inside the same
            // transaction before inserting the new one.
            $table->index(['site_id', 'policy_class']);
            $table->index(['site_id', 'policy_class', 'setting_key', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
