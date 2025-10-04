<?php

namespace App\Repositories;

use App\Models\Block;
use Exception;

class BlockRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Block::class;
    }

    public function getPageBlocks(int $pageId): array
    {
        return $this->where('page_id', $pageId)
            ->orderBy('order', 'asc')
            ->get();
    }

    public function getBlocksByType(string $type): array
    {
        return $this->where('type', $type)->get();
    }

    public function createBlock(int $pageId, string $type, array $data, int $order): Block
    {
        return $this->create([
            'page_id' => $pageId,
            'type' => $type,
            'data' => $data,
            'order' => $order
        ]);
    }

    public function deletePageBlocks(int $pageId): int
    {
        return $this->database->delete('blocks', ['page_id' => $pageId]);
    }

    public function reorderBlocks(int $pageId, array $blockIds): bool
    {
        $this->database->beginTransaction();

        try {
            foreach ($blockIds as $order => $blockId) {
                $this->database->update('blocks',
                    ['order' => $order + 1],
                    ['id' => $blockId, 'page_id' => $pageId]
                );
            }

            $this->database->commit();
            return true;
        } catch (Exception $e) {
            $this->database->rollBack();
            return false;
        }
    }
}