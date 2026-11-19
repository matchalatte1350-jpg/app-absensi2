<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class KaryawanController extends Controller
{
    public function index(): View
    {
        $karyawans = User::orderBy('nama_lengkap')->get();

        return view('karyawan.index', compact('karyawans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama_lengkap' => ['required', 'string'],
            'username' => ['required', 'unique:users,username'],
            'kode_karyawan' => ['required', 'unique:users,kode_karyawan'],
            'role' => ['required', 'in:admin,manajer,kepala_divisi,karyawan'],
            'password' => ['required', 'min:6'],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->back()->with('success', 'Data karyawan berhasil ditambahkan!');
    }
}
