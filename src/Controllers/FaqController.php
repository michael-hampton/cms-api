<?php

namespace App\Controllers;

use App\Framework\Support\SiteContext;

class FaqController extends Controller
{
    public function subscriptions()
    {
        return $this->view('faqs/subscriptions', [
            'site' => SiteContext::get(),
            'pageTitle' => 'Subscription & Newsletter FAQs'
        ]);
    }
}