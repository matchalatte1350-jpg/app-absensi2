<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CutiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GajiController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\LemburController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'index'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.process');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/lembur', [LemburController::class, 'index'])->name('lembur.index');
Route::post('/lembur', [LemburController::class, 'store'])->name('lembur.store');

Route::get('/gaji', [GajiController::class, 'index'])->name('gaji.index');

Route::get('/cuti', [CutiController::class, 'index'])->name('cuti.index');
Route::post('/cuti', [CutiController::class, 'store'])->name('cuti.store');

Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
Route::post('/karyawan', [KaryawanController::class, 'store'])->name('karyawan.store');

Route::get('/absen', [AbsensiController::class, 'index'])->name('absen.index');
Route::post('/absen', [AbsensiController::class, 'store'])->name('absen.store');
Route::post('/absensi/store', [AbsensiController::class, 'store'])->name('absensi.store');
