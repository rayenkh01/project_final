<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class FtpController extends Controller
{
    public function index(): View
    {
        return view('admin.ftp.index', [
            'activeRole' => User::ROLE_ADMIN,
            'roleLabel' => User::roleLabel(User::ROLE_ADMIN),
        ]);
    }
}
