<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddSupersededStatusToSubscriptionIssueFulfilments extends Migration
{
    public function up(): void
    {
        Schema::modifyEnum(
            'subscription_issue_fulfilments',
            'status',
            ['scheduled', 'delivered', 'failed', 'pending', 'superseded'],
            'scheduled'
        );
    }

    public function down(): void
    {
        Schema::modifyEnum(
            'subscription_issue_fulfilments',
            'status',
            ['scheduled', 'delivered', 'failed', 'pending'],
            'scheduled'
        );
    }
}
