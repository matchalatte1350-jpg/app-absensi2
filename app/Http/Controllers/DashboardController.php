<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\Lembur;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalKaryawan = User::count();
        $tanggalHariIni = today()->toDateString();
        $totalAbsenHariIni = Absensi::where('tanggal', $tanggalHariIni)->count();
        $totalCutiPending = Cuti::where('status', 'Pending')->count();
        $totalLemburPending = Lembur::where('status', 'Pending')->count();
        $user = Auth::user();

        return view('dashboard', compact(
            'totalKaryawan',
            'totalAbsenHariIni',
            'totalCutiPending',
            'totalLemburPending',
            'user'
        ));
    }
}
