<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Printing\Label;

use App\Models\PrintFulfillment;

/**
 * Generates a print-ready PDF label for one subscriber.
 *
 * Layout (A6 / 148mm × 105mm — standard shipping label):
 *
 *   ┌─────────────────────────────────────┐
 *   │  RETURN:                            │
 *   │  {return_name}                      │
 *   │  {return_address}                   │
 *   │─────────────────────────────────────│
 *   │  TO:                                │
 *   │  {full_name}                        │
 *   │  {delivery_address}                 │
 *   │─────────────────────────────────────│
 *   │  Issue: {issue_number} {issue_title}│
 *   │  Acct:  {subscription_id}           │
 *   └─────────────────────────────────────┘
 *
 * Requires: composer require setasign/fpdf
 * If FPDF is not available this class throws a clear RuntimeException
 * rather than a cryptic class-not-found error, guiding the developer
 * to install the dependency.
 *
 * To swap PDF libraries (e.g. Dompdf, mPDF) only this class changes —
 * the interface and all callers stay untouched.
 */
class PdfLabelExportFormatStrategy implements LabelExportFormatStrategy
{
    // A6 dimensions in mm
    private const PAGE_WIDTH = 148.0;
    private const PAGE_HEIGHT = 105.0;
    private const MARGIN = 8.0;

    public function generate(PrintFulfillment $fulfillment, LabelContext $context): string
    {
        $this->assertFpdfAvailable();

        $pdf = new \FPDF('L', 'mm', [self::PAGE_HEIGHT, self::PAGE_WIDTH]);
        $pdf->SetMargins(self::MARGIN, self::MARGIN);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $usableWidth = self::PAGE_WIDTH - (self::MARGIN * 2);

        // ── Return address block ──────────────────────────────────────────────
        $pdf->SetFont('Helvetica', 'B', 7);
        $pdf->Cell($usableWidth, 4, 'RETURN:', 0, 1);
        $pdf->SetFont('Helvetica', '', 7);

        foreach ($context->returnAddressLines() as $line) {
            $pdf->Cell($usableWidth, 4, $line, 0, 1);
        }

        $pdf->Ln(2);
        $pdf->Line(self::MARGIN, $pdf->GetY(), self::PAGE_WIDTH - self::MARGIN, $pdf->GetY());
        $pdf->Ln(3);

        // ── Delivery address block ────────────────────────────────────────────
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($usableWidth, 5, 'TO:', 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell($usableWidth, 5, $fulfillment->full_name, 0, 1);
        $pdf->Cell($usableWidth, 5, $fulfillment->address_line_1, 0, 1);

        $addressLine2 = $fulfillment->address_line_2;
        if ($addressLine2 !== null && $addressLine2 !== '') {
            $pdf->Cell($usableWidth, 5, $addressLine2, 0, 1);
        }

        $pdf->Cell($usableWidth, 5, $fulfillment->city, 0, 1);
        $pdf->Cell($usableWidth, 5, $fulfillment->postcode, 0, 1);
        $pdf->Cell($usableWidth, 5, $fulfillment->country, 0, 1);

        $pdf->Ln(2);
        $pdf->Line(self::MARGIN, $pdf->GetY(), self::PAGE_WIDTH - self::MARGIN, $pdf->GetY());
        $pdf->Ln(3);

        // ── Issue + account metadata ──────────────────────────────────────────
        $pdf->SetFont('Helvetica', '', 7);

        if ($context->issueNumber || $context->issueTitle) {
            $issueText = implode(' — ', array_filter([
                $context->issueNumber ? "Issue {$context->issueNumber}" : null,
                $context->issueTitle,
            ]));
            $pdf->Cell($usableWidth, 4, $issueText, 0, 1);
        }

        $pdf->Cell($usableWidth, 4, "Acct: {$fulfillment->subscription_id}", 0, 1);

        return $pdf->Output('S');
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function formatName(): string
    {
        return 'pdf';
    }

    private function assertFpdfAvailable(): void
    {
        if (!class_exists(\FPDF::class)) {
            throw new \RuntimeException(
                'FPDF is not installed. Run: composer require setasign/fpdf'
            );
        }
    }
}