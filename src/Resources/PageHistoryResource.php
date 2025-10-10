<?php

namespace App\Resources;

class PageHistoryResource
{
    private $history;

    private function __construct($history)
    {
        $this->history = $history;
    }

    public static function make($history): self
    {
        return new self($history);
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->history->id,
            'page_id' => $this->history->page_id,
            'action' => $this->history->action,
            'action_label' => $this->history->getActionLabel(),
            'description' => $this->history->description,
            'change_summary' => $this->history->getChangeSummary(),
            'changes' => $this->history->changes,
            'snapshot' => $this->history->snapshot,
            'ip_address' => $this->history->ip_address,
            'created_at' => $this->history->created_at,
            'user' => null,
            'page' => null
        ];

        if ($this->history->relationLoaded('user') && $this->history->user) {
            $data['user'] = [
                'id' => $this->history->user->id,
                'name' => $this->history->user->name,
                'email' => $this->history->user->email
            ];
        }

        if ($this->history->relationLoaded('page') && $this->history->page) {
            $data['page'] = [
                'id' => $this->history->page->id,
                'title' => $this->history->page->title,
                'slug' => $this->history->page->slug,
                'status' => $this->history->page->status
            ];
        }

        return $data;
    }
}