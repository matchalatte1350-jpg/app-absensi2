<?php

namespace App\Http\Controllers;

use App\Models\Gaji;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GajiController extends Controller
{
    public function index(): View
    {
        $gajis = Gaji::with('karyawan')
            ->latest('periode')
            ->get();
        $user = Auth::user();

        return view('gaji.index', compact('gajis', 'user'));
    }
}
