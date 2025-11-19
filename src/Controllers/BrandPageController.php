<?php

namespace App\Controllers;

use App\Models\Tag;

class BrandPageController extends Controller
{
    public function show(string $slug)
    {
        // check if has corresponding page tag
        $tag = Tag::with(['pages', 'categories'])->where('slug', $slug)->first();
        $pages = !empty($tag) ? $tag->pages : null;

        return $this->view('brand.show', [
            'pages' => $pages,
            'tag' => $tag
        ]);
    }

}