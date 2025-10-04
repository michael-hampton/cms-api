<?php

namespace App\Framework\Migration;

abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;
}