<?php

namespace App\Controllers\Admin;

use App\Controllers\Controller;

class BadgeController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('admin/badges/index');
    }
}