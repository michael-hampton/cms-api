<?php

use App\Framework\Database\Database;
use App\Framework\Migration\Migration;

class NormaliseStaticPageTypes extends Migration
{
    public function up(): void
    {
        Database::getInstance()->exec(
            "UPDATE pages SET page_type = 'page' WHERE page_type = 'content' AND slug IN ('about', 'contact')"
        );
    }

    public function down(): void
    {
        Database::getInstance()->exec(
            "UPDATE pages SET page_type = 'content' WHERE page_type = 'page' AND slug IN ('about', 'contact')"
        );
    }
}
