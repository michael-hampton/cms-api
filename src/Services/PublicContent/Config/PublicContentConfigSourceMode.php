<?php
namespace App\Services\PublicContent\Config;

enum PublicContentConfigSourceMode: string
{
    case File = 'file';
    case Database = 'database';
}