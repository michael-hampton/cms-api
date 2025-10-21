<?php

namespace App\Repositories;

use App\Models\Newsletter;

class NewsletterRepository extends Repository
{
    public function find(int $id): ?Newsletter
    {
        return Newsletter::find($id);
    }

    public function getDueNewsletters(int $siteId): array
    {
        $newsletters = Newsletter::where('active', true)->where('site_id', $siteId)->get();
        $due = [];

        foreach ($newsletters as $newsletter) {
            if ($newsletter->shouldSend()) {
                $due[] = $newsletter;
            }
        }

        return $due;
    }

//    public function update(Newsletter $newsletter, array $data): Newsletter
//    {
//        $newsletter->update($data);
//        return $newsletter;
//    }
//
//    public function create(array $data): Newsletter
//    {
//        $result = Newsletter::create($data);
//        return new Newsletter($result);
//    }
    protected function getModelClass(): string
    {
        return Newsletter::class;
    }
}