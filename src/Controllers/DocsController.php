<?php

namespace App\Controllers;

use App\Framework\Http\Response;

class DocsController extends Controller
{
    public function index()
    {
        return $this->view('docs');
    }

    public function openapi()
    {

        $specPath = dirname(getcwd()) . '/openapi.json';;

        $spec = file_get_contents($specPath);

        return new Response(
            $spec,
            200,
            [
                'Content-Type' => 'application/json'
            ]
        );
    }
}