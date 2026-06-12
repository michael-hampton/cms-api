<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class BriefResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'title' => $this->getAttribute('title'),
            'description' => $this->getAttribute('description'),
            'owner_id' => $this->getAttribute('owner_id'),
            'category_id' => $this->getAttribute('category_id'),
            'site_id' => $this->getAttribute('site_id'),
            'status' => $this->getAttribute('status'),
            'converted_page_id' => $this->getAttribute('converted_page_id'),
            'converted_at' => $this->getAttribute('converted_at')?->format('Y-m-d H:i:s'),
            'target_word_count' => $this->getAttribute('target_word_count'),
            'seo_keywords' => $this->getAttribute('seo_keywords'),
            'template_id' => $this->getAttribute('template_id'),
            'last_activity_at' => $this->getAttribute('last_activity_at'),
            'last_activity_user_id' => $this->getAttribute('last_activity_user_id'),
            'parent_brief_id' => $this->getAttribute('parent_brief_id'),
            'target_audience' => $this->getAttribute('target_audience'),
            'is_active' => $this->getAttribute('is_active'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            // Always-loaded relations (Brief model declares $alwaysInclude for these)
            'owner' => $this->whenLoaded('owner', fn() => [
                'id' => $this->getAttribute('owner.id'),
                'name' => $this->getAttribute('owner.name'),
            ]),

            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->getAttribute('category.id'),
                'name' => $this->getAttribute('category.name'),
                'slug' => $this->getAttribute('category.slug'),
            ]),
            'attachments' => $this->getAttribute('attachments') ?? [],
            'comments' => $this->getAttribute('comments') ?? [],
            'collaborators' => $this->getAttribute('collaborators') ?? [],
            'pending_contributor_requests' => $this->pendingContributorRequests(),
        ];
    }

    private function pendingContributorRequests(): array
    {
        $activity = $this->getAttribute('activityLog') ?? [];
        $requestActions = ['deadline_change_requested', 'negotiation_requested'];
        $requests = [];

        foreach ($activity as $event) {
            $action = is_array($event) ? ($event['action'] ?? null) : ($event->action ?? null);
            if (!in_array($action, $requestActions, true)) {
                continue;
            }

            $metadata = is_array($event) ? ($event['metadata'] ?? []) : ($event->metadata ?? []);
            $requests[] = [
                'type' => $action,
                'description' => is_array($event) ? ($event['description'] ?? '') : ($event->description ?? ''),
                'metadata' => is_array($metadata) ? $metadata : [],
                'created_at' => is_array($event) ? ($event['created_at'] ?? null) : ($event->created_at ?? null),
                'user_id' => is_array($event) ? ($event['user_id'] ?? null) : ($event->user_id ?? null),
            ];
        }

        return $requests;
    }
}
