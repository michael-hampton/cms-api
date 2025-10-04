<?php

namespace App\Framework\View;

class PaginationView
{
    public static function render(array $paginationData, string $baseUrl = ''): string
    {
        $currentPage = $paginationData['current_page'];
        $lastPage = $paginationData['last_page'];
        $total = $paginationData['total'];
        $from = $paginationData['from'];
        $to = $paginationData['to'];

        if ($lastPage <= 1) {
            return '';
        }

        $html = '<nav class="pagination-wrapper">';
        $html .= "<div class=\"pagination-info\">Showing {$from} to {$to} of {$total} results</div>";
        $html .= '<ul class="pagination">';

        // Previous button
        if ($currentPage > 1) {
            $prevUrl = $baseUrl . '?page=' . ($currentPage - 1);
            $html .= "<li><a href=\"{$prevUrl}\" class=\"pagination-link\">&laquo; Previous</a></li>";
        }

        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($lastPage, $currentPage + 2);

        if ($start > 1) {
            $html .= "<li><a href=\"{$baseUrl}?page=1\" class=\"pagination-link\">1</a></li>";
            if ($start > 2) {
                $html .= "<li><span class=\"pagination-dots\">...</span></li>";
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $currentPage ? ' active' : '';
            $url = $baseUrl . '?page=' . $i;
            $html .= "<li><a href=\"{$url}\" class=\"pagination-link{$active}\">{$i}</a></li>";
        }

        if ($end < $lastPage) {
            if ($end < $lastPage - 1) {
                $html .= "<li><span class=\"pagination-dots\">...</span></li>";
            }
            $html .= "<li><a href=\"{$baseUrl}?page={$lastPage}\" class=\"pagination-link\">{$lastPage}</a></li>";
        }

        // Next button
        if ($currentPage < $lastPage) {
            $nextUrl = $baseUrl . '?page=' . ($currentPage + 1);
            $html .= "<li><a href=\"{$nextUrl}\" class=\"pagination-link\">Next &raquo;</a></li>";
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }
}