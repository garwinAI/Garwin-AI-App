<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class HomeController
{
    public function index(): void
    {
        if (current_user() !== null) {
            redirect('/dashboard');
        }

        render('home', ['title' => 'AI workspace, built for you']);
    }
}
