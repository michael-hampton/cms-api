<?php

declare(strict_types=1);

namespace App\Controllers\Legal;

use App\Controllers\Controller;

final class LegalController extends Controller
{
    public function privacyPolicy()
    {
        return $this->view('legal.privacy-policy');
    }

    public function cookiePolicy()
    {
        return $this->view('legal.cookie-policy');
    }

    public function returnsPolicy()
    {
        return $this->view('legal.returns-policy');
    }

    public function cancellationRights()
    {
        return $this->view('legal.cancellation-rights');
    }

    public function reviewsPolicy()
    {
        return $this->view('legal.reviews-policy');
    }

    public function dataSubjectRights()
    {
        return $this->view('legal.data-subject-rights');
    }

    public function dataRetention()
    {
        return $this->view('legal.data-retention');
    }
}