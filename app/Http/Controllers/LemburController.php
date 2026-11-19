<?php

namespace App\Http\Controllers;

use App\Models\Lembur;
use App\Models\User;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    public function index()
    {
        $lemburs = Lembur::with('karyawan')->latest()->get();
        $karyawans = User::orderBy('nama_lengkap')->get();

        return view('lembur-index', compact('lemburs', 'karyawans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_user' => ['required', 'exists:users,id_user'],
            'tanggal_lembur' => ['required', 'date'],
            'durasi' => ['required', 'string'],
            'keterangan' => ['required', 'string'],
        ]);

        Lembur::create(array_merge($validated, ['status' => 'Pending']));

        return redirect()->back()->with('success', 'Pengajuan lembur berhasil disimpan!');
    }
}
