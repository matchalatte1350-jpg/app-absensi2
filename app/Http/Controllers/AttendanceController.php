<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();

        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        $histories = Attendance::where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->limit(7)
            ->get();

        return view('dashboard', compact('attendance', 'histories', 'today'));
    }

    public function store(Request $request)
    {
        $userId = Auth::id();
        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $time = $now->toTimeString();

        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            Attendance::create([
                'user_id' => $userId,
                'date' => $today,
                'time_in' => $time,
                'status' => 'Hadir',
            ]);

            return redirect()->route('dashboard')
                ->with('success', 'Berhasil melakukan Absen Masuk pada pukul ' . $time . '.');
        }

        if (!$attendance->time_out) {
            $attendance->update([
                'time_out' => $time,
            ]);

            return redirect()->route('dashboard')
                ->with('success', 'Berhasil melakukan Absen Pulang pada pukul ' . $time . '.');
        }

        return redirect()->route('dashboard')
            ->with('error', 'Anda sudah melakukan absen masuk dan pulang hari ini.');
    }
}