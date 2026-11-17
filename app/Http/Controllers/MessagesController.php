<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class MessagesController
{
    public function index(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        render('messages/index', ['title' => 'Messages', 'user' => $user]);
    }
}
