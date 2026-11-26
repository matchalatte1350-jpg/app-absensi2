<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private function resolvePk(string $table): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        if (Schema::hasColumn($table, 'id')) {
            return 'id';
        }

        $singular = rtrim($table, 's');
        $customPk = 'id_' . $singular;
        if (Schema::hasColumn($table, $customPk)) {
            return $customPk;
        }

        $columns = Schema::getColumnListing($table);
        foreach ($columns as $col) {
            if (str_starts_with($col, 'id')) {
                return $col;
            }
        }

        return $columns[0] ?? null;
    }

    private function applyOrder($query, string $table, string $direction = 'desc')
    {
        $pk = $this->resolvePk($table);
        if ($pk) {
            return $query->orderBy($table . '.' . $pk, $direction);
        }

        return $query;
    }

    private function resolveAbsensiTable(): ?string
    {
        foreach (['absensis', 'absensi', 'riwayat_absens', 'riwayat_absensi', 'kehadirans', 'presensis'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                return $tbl;
            }
        }

        return null;
    }

    private function findFirstColumn(string $table, array $candidates): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $now = Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $user = Auth::user();
        $userId = Auth::id();

        $riwayatPresensi = collect();
        $absenHariIni = null;
        $sudahMasuk = false;
        $sudahPulang = false;
        $jamMasukHariIni = null;
        $jamPulangHariIni = null;

        $table = $this->resolveAbsensiTable();
        if ($table) {
            try {
                $rows = $this->applyOrder(DB::table($table), $table, 'desc')->limit(20)->get();
                $riwayatPresensi = $rows;

                $tglCol = $this->findFirstColumn($table, ['tanggal', 'tgl_absen', 'tanggal_absen']);
                $masukCol = $this->findFirstColumn($table, ['jam_masuk', 'waktu_masuk', 'check_in']);
                $pulangCol = $this->findFirstColumn($table, ['jam_pulang', 'waktu_pulang', 'check_out']);

                foreach ($rows as $row) {
                    $rawDate = $tglCol ? data_get($row, $tglCol) : data_get($row, 'created_at');
                    $rowDate = null;
                    if (!empty($rawDate)) {
                        try {
                            $rowDate = Carbon::parse($rawDate, 'Asia/Jakarta')->toDateString();
                        } catch (\Throwable $e) {
                            $rowDate = substr((string) $rawDate, 0, 10);
                        }
                    }

                    if ($rowDate === $today) {
                        $absenHariIni = $row;
                        $jamMasukHariIni = $masukCol ? data_get($row, $masukCol) : null;
                        $jamPulangHariIni = $pulangCol ? data_get($row, $pulangCol) : null;

                        $sudahMasuk = true;
                        $sudahPulang = !empty($jamPulangHariIni);
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $riwayatPresensi = collect();
            }
        }

        foreach (['dashboard', 'dashboard.index', 'karyawan.dashboard', 'admin.dashboard'] as $viewName) {
            if (view()->exists($viewName)) {
                return view($viewName, [
                    'user' => $user,
                    'userId' => $userId,
                    'now' => $now,
                    'today' => $today,
                    'riwayatPresensi' => $riwayatPresensi,
                    'riwayatAbsen' => $riwayatPresensi,
                    'absenHariIni' => $absenHariIni,
                    'sudahMasuk' => $sudahMasuk,
                    'sudahPulang' => $sudahPulang,
                    'jamMasukHariIni' => $jamMasukHariIni,
                    'jamPulangHariIni' => $jamPulangHariIni,
                ]);
            }
        }

        return response('Dashboard view tidak ditemukan.', 404);
    }
}