<?php

namespace App\Controller;
use Core\Base\Controller;

class IndexController extends Controller
{
    public function index()
    {
        $this->jsonReturn([
            'status' => 200,
            'body' => C('body')
        ]);
    }
}
