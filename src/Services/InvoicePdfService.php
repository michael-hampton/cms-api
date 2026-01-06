<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\Payment;

class InvoicePdfService
{
    public function generateInvoice(Payment $payment, Member $member): string
    {
        // Using TCPDF or similar library
        // For now, returning HTML that can be converted to PDF

        $site = SiteContext::get();
        $subscription = $payment->subscription;

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .invoice-header { text-align: center; margin-bottom: 30px; }
        .invoice-details { margin: 20px 0; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table th, .invoice-table td { 
            padding: 10px; 
            border-bottom: 1px solid #ddd; 
            text-align: left;
        }
        .total-row { font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h1>{$site->name}</h1>
        <p>INVOICE</p>
    </div>
    
    <div class="invoice-details">
        <p><strong>Invoice #:</strong> INV-{$payment->id}</p>
        <p><strong>Date:</strong> {$payment->created_at->format('F d, Y')}</p>
        <p><strong>Customer Account ID:</strong> {$member->id}</p>
        <p><strong>Customer Name:</strong> {$member->first_name} {$member->last_name}</p>
        <p><strong>Email:</strong> {$member->email}</p>
    </div>
    
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{$subscription->plan_name}</td>
                <td>{$payment->created_at->format('M d, Y')} - {$payment->period_end->format('M d, Y')}</td>
                <td>{$payment->currency} {$payment->amount}</td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Total Paid</td>
                <td>{$payment->currency} {$payment->amount}</td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 40px; font-size: 0.9em; color: #666;">
        <p>Thank you for your business!</p>
        <p>Payment Method: {$payment->payment_method}</p>
        <p>Transaction ID: {$payment->payment_intent_id}</p>
    </div>
</body>
</html>
HTML;

        // Here you would convert HTML to PDF using a library like TCPDF, DomPDF, or wkhtmltopdf
        // For this example, returning the HTML
        return $html;
    }
}