<?php

namespace App\Models;

class ImageTag extends Model
{
    protected $table = 'image_tag';

    protected $fillable = ['image_id', 'tag_id', 'created_at', 'updated_at'];

}