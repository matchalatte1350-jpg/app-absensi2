<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbsensiController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $absens = Absensi::with('karyawan')
            ->latest('tanggal')
            ->latest('id')
            ->get();

        return view('absensi.index', compact('absens', 'user'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'status_kehadiran' => ['required', 'in:Hadir,Izin,Sakit,Cuti'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $idUser = Auth::id() ?? User::min('id_user');

        if ($idUser === null) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        Absensi::create(array_merge($validated, ['id_user' => $idUser]));

        return redirect()->back()->with('success', 'Presensi berhasil disimpan!');
    }
}
