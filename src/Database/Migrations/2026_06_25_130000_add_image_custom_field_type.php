<?php

use App\Framework\Database\Database;
use App\Framework\Migration\Migration;

class AddImageCustomFieldType extends Migration
{
    public function up(): void
    {
        $db = Database::getInstance();

        $db->exec(
            "ALTER TABLE custom_field_definitions MODIFY type ENUM('text', 'textarea', 'number', 'url', 'email', 'boolean', 'date', 'select', 'multi_select', 'file', 'image', 'json') DEFAULT 'text'"
        );

        $db->exec(
            "UPDATE custom_field_definitions SET type = 'image' WHERE context = 'contributor_profile' AND `key` = 'avatar'"
        );
    }

    public function down(): void
    {
        $db = Database::getInstance();

        $db->exec(
            "UPDATE custom_field_definitions SET type = 'file' WHERE context = 'contributor_profile' AND `key` = 'avatar' AND type = 'image'"
        );

        $db->exec(
            "ALTER TABLE custom_field_definitions MODIFY type ENUM('text', 'textarea', 'number', 'url', 'email', 'boolean', 'date', 'select', 'multi_select', 'file', 'json') DEFAULT 'text'"
        );
    }
}
