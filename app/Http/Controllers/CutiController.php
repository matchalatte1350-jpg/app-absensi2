<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\User;
use Illuminate\Http\Request;

class CutiController extends Controller
{
    public function index()
    {
        $cutis = Cuti::with('karyawan')->latest()->get();
        $karyawans = User::orderBy('nama_lengkap')->get();

        return view('cuti.index', compact('cutis', 'karyawans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'jenis_cuti' => ['required', 'string'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string'],
        ]);

        Cuti::create(array_merge($validated, ['status' => 'Pending']));

        return redirect()->back()->with('success', 'Pengajuan cuti berhasil disimpan!');
    }
}
