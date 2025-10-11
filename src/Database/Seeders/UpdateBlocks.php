<?php

namespace App\Database\Seeders;

use App\Models\Block;

class UpdateBlocks
{

    public function run() {
        $blocks = Block::all();

        foreach ($blocks as $block) {
            $data = $block->data;

            if(!empty($data['members']) && is_array($data['members'])) {

                foreach ($data['members'] as $key => $datum) {
                    if(!empty($datum['image']) && !is_array($datum['image'])) {
                        $datum['image'] = ['src' => $datum['image']];
                        $data['members'][$key] = $datum;
                    }
                }

                $block->data = $data;
                $block->save();
            }
        }
    }
}