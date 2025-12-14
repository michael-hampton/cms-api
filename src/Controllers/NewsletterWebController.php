<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;
use App\Repositories\NewsletterRepository;
use App\Services\NewsletterPageBuilderService;

class NewsletterWebController extends Controller
{
    public function __construct(
        private readonly NewsletterRepository         $newsletterRepository,
        private readonly NewsletterPageBuilderService $pageBuilderService
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $siteId = SiteContext::getId();
        $newsletters = $this->newsletterRepository->getPublished($siteId);

        return $this->view('newsletters/index', [
            'site' => SiteContext::get(),
            'newsletters' => $newsletters
        ]);
    }

    public function show(int $id, ?string $token = null)
    {
        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->notFound('Newsletter not found');
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->forbidden('Newsletter not available');
        }

        // Get pages for automated newsletter
        $pages = collect([]);
        if ($newsletter->isAutomated()) {
            $pages = $this->pageBuilderService->getPagesForNewsletter(
                $newsletter,
                $newsletter->site_id
            );
        }

        // Build HTML content
        $html = $this->pageBuilderService->buildNewsletterHtml(
            $newsletter,
            $pages,
            $token,
            true
        );

        echo $html;
        die;

        return $this->view('newsletters/show', [
            'site' => SiteContext::get(),
            'newsletter' => $newsletter,
            'html' => $html,
            'pages' => $pages,
            'token' => $token
        ]);
    }

    public function archive()
    {
        $siteId = SiteContext::getId();
        $newsletters = $this->newsletterRepository->getArchive($siteId);

        return $this->view('newsletters/archive', [
            'site' => SiteContext::get(),
            'newsletters' => $newsletters
        ]);
    }
}