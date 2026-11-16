<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\HomeContent;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomeContent $home): View
    {
        return view('home.show', $home->forLocale(app()->getLocale()) + [
            'plans' => $home->publicPlans(),
            'preview' => false,
        ]);
    }
}
