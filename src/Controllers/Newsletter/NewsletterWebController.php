<?php

namespace App\Controllers\Newsletter;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Http\StreamedResponse;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Newsletter\NewsletterArchiveService;
use App\Services\Newsletter\NewsletterPageBuilderService;
use Dompdf\Dompdf;
use Dompdf\Options;

class NewsletterWebController extends Controller
{
    public function __construct(
        private readonly NewsletterRepository         $newsletterRepository,
        private readonly NewsletterPageBuilderService $pageBuilderService,
        private readonly NewsletterArchiveService     $archiveService
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

    public function show(int $id, Request $request)
    {
        $token = $request->query('token');
        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->redirectResponse('', 404);
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->redirectResponse('', 403);
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

    public function downloadPdf(int $id, Request $request)
    {
        $token = $request->query('token');
        $newsletter = $this->newsletterRepository->find($id);

        if (!$newsletter) {
            return $this->redirectResponse('', 404);
        }

        if ($newsletter->site_id !== SiteContext::getId()) {
            return $this->redirectResponse('', 403);
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

        // Wrap in complete HTML document for PDF
        $pdfHtml = $this->buildPdfHtml($newsletter, $html);

        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($pdfHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Generate filename
        $filename = $this->sanitizeFilename($newsletter->title) . '-' . date('Y-m-d') . '.pdf';

        // Stream PDF to browser
        return new StreamedResponse(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildPdfHtml($newsletter, $content): string
    {
        $site = SiteContext::get();

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{$newsletter->title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
        }
        
        .header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 24pt;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .header .meta {
            font-size: 10pt;
            color: #666;
        }
        
        .content {
            margin-bottom: 40px;
        }
        
        .content img {
            max-width: 100%;
            height: auto;
        }
        
        .content h1, .content h2, .content h3 {
            margin-top: 20px;
            margin-bottom: 10px;
            color: #1a202c;
        }
        
        .content p {
            margin-bottom: 12px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table th,
        table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        
        table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        blockquote {
            border-left: 4px solid #667eea;
            padding-left: 20px;
            margin: 20px 0;
            font-style: italic;
        }
        
        ul, ol {
            margin: 10px 0 10px 30px;
        }
        
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$newsletter->title}</h1>
        <div class="meta">
            {$site->name} | {$newsletter->created_at->format('F d, Y')}
        </div>
    </div>
    
    <div class="content">
        {$content}
    </div>
    
    <div class="footer">
        <p>&copy; {$site->name} | Generated on " . date('F d, Y') . "</p>
    </div>
</body>
</html>
HTML;
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove special characters and spaces
        $filename = preg_replace('/[^a-zA-Z0-9-_]/', '-', $filename);
        // Remove multiple dashes
        $filename = preg_replace('/-+/', '-', $filename);
        // Trim dashes from ends
        $filename = trim($filename, '-');
        // Limit length
        return substr($filename, 0, 50);
    }

    /**
     * Search newsletters (AJAX endpoint for index page)
     */
    public function search(Request $request)
    {
        $siteId = SiteContext::getId();

        // Get filter parameters
        $filters = [
            'search' => $request->get('search', ''),
            'interval' => $request->get('interval', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
            'sort_by' => $request->get('sort_by', 'last_sent'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => $value !== '');

        $page = (int)$request->get('page', 1);
        $perPage = 12;

        // Get filtered newsletters
        $result = $this->archiveService->searchNewsletters($siteId, $filters, $page, $perPage);

        // Format newsletters for JSON response
        $formattedNewsletters = $result['newsletters']->map(function ($newsletter) {
            return [
                'id' => $newsletter->id,
                'title' => $newsletter->title,
                'content' => $newsletter->content,
                'last_sent' => $newsletter->last_sent?->format('Y-m-d H:i:s'),
                'interval' => $newsletter->interval,
                'created_at' => $newsletter->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return $this->resourceResponse([
            'success' => true,
            'newsletters' => $formattedNewsletters,
            'pagination' => $result['pagination'],
            'filters_applied' => $result['filters_applied'],
        ]);
    }

    /**
     * Search and filter newsletters (AJAX endpoint)
     */
    public function searchArchive(Request $request)
    {
        $siteId = SiteContext::getId();

        // Get filter parameters from request
        $filters = [
            'search' => $request->get('search', ''),
            'interval' => $request->get('interval', ''),
            'date_from' => $request->get('date_from', ''),
            'date_to' => $request->get('date_to', ''),
            'sort_by' => $request->get('sort_by', 'last_sent'),
            'sort_order' => $request->get('sort_order', 'desc'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($value) => $value !== '');

        $page = (int)$request->get('page', 1);
        $perPage = 20;

        // Get filtered newsletters
        $result = $this->archiveService->searchNewsletters($siteId, $filters, $page, $perPage);

        // Format newsletters for JSON response
        $formattedNewsletters = $result['newsletters']->map(function ($newsletter) {
            return [
                'id' => $newsletter->id,
                'title' => $newsletter->title,
                'content' => $newsletter->content,
                'last_sent' => $newsletter->last_sent?->format('Y-m-d H:i:s'),
                'interval' => $newsletter->interval,
                'created_at' => $newsletter->created_at?->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        return $this->resourceResponse([
            'success' => true,
            'newsletters' => $formattedNewsletters,
            'pagination' => $result['pagination'],
            'filters_applied' => $result['filters_applied'],
        ]);
    }
}