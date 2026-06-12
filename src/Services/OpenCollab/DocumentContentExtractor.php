<?php

namespace App\Services\OpenCollab;

use Throwable;
use ZipArchive;

class DocumentContentExtractor
{
    public function extract(string $path, ?string $extension = null): ExtractedDocumentContent
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt' => $this->extractText($path, 'completed'),
            'md' => $this->extractText($path, 'needs_review'),
            'docx' => $this->extractDocx($path),
            'pdf' => new ExtractedDocumentContent(null, 'pdf', 'needs_review'),
            default => new ExtractedDocumentContent(null, 'document', 'failed', 'Unsupported document type.'),
        };
    }

    private function extractText(string $path, string $status): ExtractedDocumentContent
    {
        try {
            $text = file_get_contents($path);

            if ($text === false) {
                return new ExtractedDocumentContent(null, 'html', 'failed', 'Unable to read document content.');
            }

            return new ExtractedDocumentContent($this->toHtml($text), 'html', $status);
        } catch (Throwable $exception) {
            return new ExtractedDocumentContent(null, 'html', 'failed', $exception->getMessage());
        }
    }

    private function extractDocx(string $path): ExtractedDocumentContent
    {
        if (!class_exists(ZipArchive::class)) {
            return new ExtractedDocumentContent(null, 'document', 'failed', 'ZipArchive is not available.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($path) !== true) {
                return new ExtractedDocumentContent(null, 'document', 'failed', 'Unable to open DOCX archive.');
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml === false) {
                return new ExtractedDocumentContent(null, 'document', 'failed', 'DOCX document body was not found.');
            }

            $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
            $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = preg_replace("/\n{3,}/", "\n\n", trim($text)) ?? trim($text);

            return new ExtractedDocumentContent($this->toHtml($text), 'html', 'needs_review');
        } catch (Throwable $exception) {
            return new ExtractedDocumentContent(null, 'document', 'failed', $exception->getMessage());
        }
    }

    private function toHtml(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }
}
