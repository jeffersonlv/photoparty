<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Termos e Privacidade: texto provisório (precisa de revisão jurídica antes de ir ao ar).
 */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('home.legal', ['page' => 'terms']);
    }

    public function privacy(): View
    {
        return view('home.legal', ['page' => 'privacy']);
    }
}
