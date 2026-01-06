<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\RedirectResponse;
use App\Framework\Http\StreamedResponse;
use App\Framework\Support\SiteContext;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\InvoicePdfService;
use Dompdf\Dompdf;
use Dompdf\Options;

class MemberInvoiceController extends Controller
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
        private readonly InvoicePdfService $invoicePdfService
    )
    {
        parent::__construct();
    }

    public function download(int $paymentId): RedirectResponse|StreamedResponse
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $member = MemberAuth::getMember();
        $payment = Payment::find($paymentId);

        // Verify payment belongs to member
        if (!$payment) {
            $_SESSION['flash_error'] = 'Invoice not found.';
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions/payments');
        }

        // Load subscription for details
        $subscription = $payment->subscription;
        $site = SiteContext::get();

        return new StreamedResponse(function () use ($payment, $member, $subscription, $site) {
            // Generate PDF using DomPDF
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $options->set('isRemoteEnabled', true);

            $dompdf = new Dompdf($options);

            // Load HTML content
            $html = $this->generateInvoiceHtml($payment, $member, $subscription, $site);
            $dompdf->loadHtml($html);

            // Set paper size
            $dompdf->setPaper('A4', 'portrait');

            // Render PDF
            $dompdf->render();

            // Output PDF
            $dompdf->stream("invoice-{$payment->id}.pdf", [
                'Attachment' => true
            ]);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-' . $payment->id . '.pdf"'
        ]);
    }

    private function generateInvoiceHtml($payment, $member, $subscription, $site): string
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .invoice-header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #667eea; padding-bottom: 20px; }
                .invoice-header h1 { margin: 0; color: #667eea; font-size: 32px; }
                .invoice-header p { margin: 5px 0; color: #666; }
                .invoice-details { margin: 30px 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
                .detail-label { font-weight: bold; color: #333; }
                .detail-value { color: #666; }
                .invoice-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                .invoice-table th { background: #667eea; color: white; padding: 12px; text-align: left; }
                .invoice-table td { padding: 12px; border-bottom: 1px solid #ddd; }
                .total-row { font-weight: bold; font-size: 18px; background: #f5f7fa; }
                .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <h1>' . htmlspecialchars($site->name) . '</h1>
                <p>INVOICE</p>
            </div>
            
            <div class="invoice-details">
                <div class="detail-row">
                    <span class="detail-label">Invoice #:</span>
                    <span class="detail-value">INV-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">' . $payment->created_at->format('F d, Y') . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer Account ID:</span>
                    <span class="detail-value">' . $member->id . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer Name:</span>
                    <span class="detail-value">' . htmlspecialchars($member->first_name . ' ' . $member->last_name) . '</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value">' . htmlspecialchars($member->email) . '</span>
                </div>
            </div>
            
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Period</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . htmlspecialchars($subscription->plan_name ?? 'Subscription') . '</td>
                        <td>' . ($subscription ? $subscription->start_date->format('M d, Y') . ' - ' . $subscription->end_date->format('M d, Y') : 'N/A') . '</td>
                        <td style="text-align: right;">' . $payment->currency . ' ' . number_format($payment->amount, 2) . '</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="2">Total Paid</td>
                        <td style="text-align: right;">' . $payment->currency . ' ' . number_format($payment->amount, 2) . '</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="footer">
                <p><strong>Thank you for your business!</strong></p>
                <p>Payment Method: ' . ucfirst($payment->payment_method) . '</p>
                <p>Transaction ID: ' . htmlspecialchars($payment->payment_intent_id ?? 'N/A') . '</p>
                <p>Payment Date: ' . $payment->created_at->format('F d, Y \a\t g:i A') . '</p>
            </div>
        </body>
        </html>';

        return $html;
    }
}