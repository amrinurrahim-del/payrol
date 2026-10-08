<?php
declare(strict_types=1);

/*
 * ============================================================================
 *  HELPER APLIKASI + MESIN PENGGAJIAN (PAYROLL ENGINE)
 * ============================================================================
 *  Semua rumus di bawah HANYA membaca nilai dari tabel konfigurasi dinamis
 *  (system_settings + payroll_configurations, PTKP, dan pph21_layers).
 *  Tidak ada tarif/persentase yang ditulis di dalam kode.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/* ------------------------------------------------------------------ */
/* Utilitas dasar                                                     */
/* ------------------------------------------------------------------ */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function post(string $k, string $d = ''): string
{
    $v = $_POST[$k] ?? $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

function post_num(string $k, float $d = 0): float
{
    return (float) str_replace(['.', ','], ['', '.'], post($k, (string) $d));
}

function post_int(string $k, int $d = 0): int
{
    return (int) round(post_num($k, (float) $d));
}

function post_bool(string $k): bool
{
    return in_array((string) ($_POST[$k] ?? ''), ['1', 'on', 'ya', 'true'], true);
}

/* ------------------------------------------------------------------ */
/* Pengaturan dinamis                                                 */
/* ------------------------------------------------------------------ */

/** Seluruh nilai setelan (system_settings + payroll_configurations) sebagai key => string. */
function conf_all(bool $reload = false): array
{
    static $s = null;
    if ($s === null || $reload) {
        $s = [];
        foreach (db()->query('SELECT kunci, nilai FROM system_settings') as $r) {
            $s[$r['kunci']] = (string) $r['nilai'];
        }
        foreach (db()->query('SELECT kunci, nilai FROM payroll_configurations') as $r) {
            $s[$r['kunci']] = (string) $r['nilai'];
        }
    }
    return $s;
}

function conf(string $k, string $default = ''): string
{
    $s = conf_all();
    return array_key_exists($k, $s) ? $s[$k] : $default;
}

function conf_num(string $k, float $default = 0): float
{
    $v = trim(conf($k, ''));
    if ($v === '') {
        return $default;
    }
    return (float) str_replace(',', '.', $v);
}

function conf_int(string $k, int $default = 0): int
{
    return (int) round(conf_num($k, (float) $default));
}

function conf_bool(string $k, bool $default = false): bool
{
    $v = strtolower(trim(conf($k, $default ? '1' : '0')));
    return in_array($v, ['1', 'on', 'ya', 'true', 'aktif'], true);
}

function conf_json(string $k, array $default = []): array
{
    $raw = trim(conf($k, ''));
    if ($raw === '') {
        return $default;
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : $default;
}

/** Menyimpan satu setelan ke tabel yang tepat (system_settings atau payroll_configurations). */
function conf_set(string $k, string $v): void
{
    $sys = daftar_system_setting();
    $tabel = isset($sys[$k]) ? 'system_settings' : 'payroll_configurations';
    $st = db()->prepare("INSERT INTO {$tabel} (kunci, nilai, updated_at) VALUES (?, ?, ?)
                         ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai, updated_at = excluded.updated_at");
    $st->execute([$k, (string) $v, now()]);
    conf_all(true);
    if ($k === 'zona_waktu' && in_array($v, timezone_identifiers_list(), true)) {
        date_default_timezone_set($v);
    }
}

/** Daftar setelan satu kelompok, lengkap dengan metadata (label, tipe, nilai).
 *  PENTING: nilai HARUS diambil dari database. Metadata bawaan juga memuat kunci
 *  'nilai' (nilai contoh), sehingga penggabungan memakai array_merge (nilai DB
 *  menang) — memakai operator union `+` membuat halaman selalu menampilkan angka
 *  bawaan meski setelan sudah diubah (bug nyata yang pernah terjadi).
 */
function conf_kelompok(string $kelompok): array
{
    $out = [];
    foreach (daftar_system_setting() as $k => $m) {
        if (($m['kelompok'] ?? '') === $kelompok) {
            $out[$k] = array_merge($m, ['nilai' => conf($k, (string) ($m['nilai'] ?? ''))]);
        }
    }
    foreach (daftar_payroll_config() as $k => $m) {
        if (($m['kelompok'] ?? '') === $kelompok) {
            $out[$k] = array_merge($m, ['nilai' => conf($k, (string) ($m['nilai'] ?? ''))]);
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Format angka & tanggal                                             */
/* ------------------------------------------------------------------ */

function rp(float $n, bool $denganSimbol = true): string
{
    $s = ($n < 0 ? '-' : '') . ($denganSimbol ? conf('mata_uang_simbol', 'Rp') . ' ' : '');
    return $s . number_format(abs($n), 0, ',', '.');
}

function angka(float $n, int $desimal = 0): string
{
    return number_format($n, $desimal, ',', '.');
}

function persen_teks(float $n): string
{
    $t = rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    return $t . '%';
}

const NAMA_BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const NAMA_HARI  = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

function tanggal_indo(string $ymd, bool $pakaiHari = false): string
{
    $t = strtotime($ymd);
    if ($t === false) {
        return $ymd;
    }
    $teks = (int) date('j', $t) . ' ' . NAMA_BULAN[(int) date('n', $t)] . ' ' . date('Y', $t);
    return $pakaiHari ? (NAMA_HARI[(int) date('N', $t)] . ', ' . $teks) : $teks;
}

function bulan_indo(string $periode): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $periode, $m)) {
        return $periode;
    }
    return NAMA_BULAN[(int) $m[2]] . ' ' . $m[1];
}

function tanggal_pendek(string $ymd): string
{
    $t = strtotime($ymd);
    return $t === false ? $ymd : date('d/m/Y', $t);
}

/* ------------------------------------------------------------------ */
/* Input tanggal: format tampilan dd/mm/yyyy                          */
/* ------------------------------------------------------------------ */

/**
 * Membaca tanggal yang DIKETIK/DIPILIH pengguna.
 * Menerima dd/mm/yyyy (format tampilan aplikasi) maupun yyyy-mm-dd (data lama/impor).
 * Mengembalikan 'YYYY-MM-DD' bila sah, atau '' bila kosong / tidak sah.
 */
function tanggal_dari_teks(?string $teks): string
{
    $t = trim((string) $teks);
    if ($t === '') {
        return '';
    }
    /* dd/mm/yyyy — pemisah boleh / . atau - */
    if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $t, $m)) {
        $d = (int) $m[1];
        $b = (int) $m[2];
        $th = (int) $m[3];
        return checkdate($b, $d, $th) ? sprintf('%04d-%02d-%02d', $th, $b, $d) : '';
    }
    /* yyyy-mm-dd */
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $t, $m)) {
        $th = (int) $m[1];
        $b = (int) $m[2];
        $d = (int) $m[3];
        return checkdate($b, $d, $th) ? sprintf('%04d-%02d-%02d', $th, $b, $d) : '';
    }
    return '';
}

/** 'YYYY-MM-DD' → 'dd/mm/yyyy' untuk ditampilkan pada kolom input. */
function tanggal_teks(string $ymd): string
{
    $t = strtotime($ymd);
    return $t === false ? '' : date('d/m/Y', $t);
}

/**
 * Merender kolom input tanggal ber-format dd/mm/yyyy lengkap dengan tombol kalender.
 * Menggantikan <input type="date"> karena format tampilannya tidak bisa dipaksa
 * dd/mm/yyyy oleh browser (selalu mengikuti locale perangkat pengguna).
 */
function input_tanggal(string $name, string $ymd = '', array $opsi = []): string
{
    /* id memakai nama kolom itu sendiri supaya unik per halaman & mudah dipakai CSS/JS. */
    $id = (string) ($opsi['id'] ?? preg_replace('/[^A-Za-z0-9_]/', '_', $name));
    $kelas = 'tgl-input' . (!empty($opsi['kelas']) ? ' ' . $opsi['kelas'] : '');
    $attr = '';
    if (!empty($opsi['wajib'])) {
        $attr .= ' required';
    }
    if (!empty($opsi['min'])) {
        $attr .= ' data-min="' . h(tanggal_teks((string) $opsi['min'])) . '"';
    }
    if (!empty($opsi['max'])) {
        $attr .= ' data-max="' . h(tanggal_teks((string) $opsi['max'])) . '"';
    }
    if (!empty($opsi['data_tanggal'])) {
        $attr .= ' data-perubahan-tanggal="' . h((string) $opsi['data_tanggal']) . '"';
    }
    if (isset($opsi['placeholder'])) {
        $attr .= ' placeholder="' . h((string) $opsi['placeholder']) . '"';
    }
    return '<div class="tgl-bungkus">'
        . '<input type="text" class="' . h($kelas) . '" id="' . h($id) . '" name="' . h($name) . '"'
        . ' value="' . h($ymd !== '' ? tanggal_teks($ymd) : '') . '"'
        . ' placeholder="dd/mm/yyyy" inputmode="numeric" autocomplete="off" spellcheck="false"'
        . ' aria-label="Tanggal format hari/bulan/tahun" title="Format dd/mm/yyyy"'
        . $attr . '>'
        . '<button type="button" class="tgl-tombol" tabindex="-1" aria-hidden="true" title="Buka kalender">'
        . '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">'
        . '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 3v3.5M16 3v3.5"/>'
        . '</svg></button></div>';
}

function periode_valid(string $p): string
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : date('Y-m');
}

function tanggal_valid(string $t, string $cadangan = ''): string
{
    /* Menerima yyyy-mm-dd (data lama) DAN dd/mm/yyyy (format tampilan yang diketik pengguna). */
    $iso = tanggal_dari_teks($t);
    if ($iso !== '') {
        return $iso;
    }
    return $cadangan !== '' ? $cadangan : date('Y-m-d');
}

/** Jam kerja "HH:MM" dari menit sejak tengah malam. */
function menit_ke_jam(int $menit): string
{
    $jam = intdiv(max(0, $menit), 60);
    $sisa = max(0, $menit) % 60;
    return sprintf('%02d:%02d', $jam % 24, $sisa);
}

/** "8:30" atau "45 menit" untuk ditampilkan. */
function durasi_indo(int $menit): string
{
    $menit = max(0, $menit);
    if ($menit < 60) {
        return $menit . ' menit';
    }
    return intdiv($menit, 60) . ' jam ' . ($menit % 60 > 0 ? ($menit % 60) . ' menit' : '');
}

/* ------------------------------------------------------------------ */
/* Terbilang (untuk take home pay)                                    */
/* ------------------------------------------------------------------ */

/**
 * Mengubah angka menjadi kata (bahasa Indonesia) untuk slip gaji.
 * Bagian sisa yang nol TIDAK dieja, sehingga 1.000 = "seribu" (bukan "seribu nol")
 * dan 1.500.000 = "satu juta lima ratus ribu".
 */
function terbilang(float $angka): string
{
    $bulat = (int) round(abs($angka));
    $kata = trim(terbilang_int($bulat));
    if ($kata === '') {
        $kata = 'nol';
    }
    return $angka < 0 ? 'minus ' . $kata : $kata;
}

/** Bagian rekursif terbilang; mengembalikan string kosong untuk nilai 0. */
function terbilang_int(int $n): string
{
    if ($n <= 0) {
        return '';
    }
    $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if ($n < 12) {
        return $satuan[$n];
    }
    if ($n < 20) {
        return trim(terbilang_int($n - 10) . ' belas');
    }
    if ($n < 100) {
        return trim(terbilang_int(intdiv($n, 10)) . ' puluh ' . $satuan[$n % 10]);
    }
    if ($n < 200) {
        return trim('seratus ' . terbilang_int($n - 100));
    }
    if ($n < 1000) {
        return trim(terbilang_int(intdiv($n, 100)) . ' ratus ' . terbilang_int($n % 100));
    }
    if ($n < 2000) {
        return trim('seribu ' . terbilang_int($n - 1000));
    }
    if ($n < 1000000) {
        return trim(terbilang_int(intdiv($n, 1000)) . ' ribu ' . terbilang_int($n % 1000));
    }
    if ($n < 1000000000) {
        return trim(terbilang_int(intdiv($n, 1000000)) . ' juta ' . terbilang_int($n % 1000000));
    }
    if ($n < 1000000000000) {
        return trim(terbilang_int(intdiv($n, 1000000000)) . ' miliar ' . terbilang_int($n % 1000000000));
    }
    return trim(terbilang_int(intdiv($n, 1000000000000)) . ' triliun ' . terbilang_int($n % 100000000000));
}

function terbilang_rupiah(float $n): string
{
    return trim(ucwords(terbilang($n))) . ' Rupiah';
}

/* ------------------------------------------------------------------ */
/* Jam kerja harian (bisa bersegmen dengan jam istirahat)             */
/* ------------------------------------------------------------------ */

/** "HH:MM" → menit sejak tengah malam (null bila format tidak sah). */
function jam_ke_menit(?string $hhmm): ?int
{
    if (!is_string($hhmm) || !preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
        return null;
    }
    $j = (int) $m[1];
    $n = (int) $m[2];
    if ($j > 23 || $n > 59) {
        return null;
    }
    return $j * 60 + $n;
}

/**
 * Segmen jam kerja harian dari Pengaturan.
 * Selalu mengembalikan minimal satu segmen (memakai jam masuk/pulang standar bila
 * JSON tidak sah) supaya mesin penggajian tidak pernah kehilangan jam kerja.
 */
function jam_kerja_segmen(): array
{
    $out = [];
    if (conf('jam_kerja_mode', 'segmen') === 'segmen') {
        foreach (conf_json('jam_kerja_segmen', []) as $g) {
            $m = jam_ke_menit((string) ($g['mulai'] ?? ''));
            $k = jam_ke_menit((string) ($g['selesai'] ?? ''));
            if ($m === null || $k === null || $k <= $m) {
                continue;
            }
            $out[] = [
                'nama' => trim((string) ($g['nama'] ?? '')) !== '' ? (string) $g['nama'] : 'Segmen kerja',
                'mulai' => sprintf('%02d:%02d', intdiv($m, 60), $m % 60),
                'selesai' => sprintf('%02d:%02d', intdiv($k, 60), $k % 60),
                'istirahat' => !empty($g['istirahat']),
                'menit' => $k - $m,
                'menit_mulai' => $m,
                'menit_selesai' => $k,
            ];
        }
    }
    if (!$out) {
        $m = jam_ke_menit(conf('absensi_jam_masuk_standar', '08:00')) ?? 480;
        $k = jam_ke_menit(conf('absensi_jam_pulang_standar', '20:00')) ?? 1200;
        if ($k <= $m) {
            $k = $m + 60;
        }
        $out[] = [
            'nama' => 'Jam kerja', 'mulai' => sprintf('%02d:%02d', intdiv($m, 60), $m % 60),
            'selesai' => sprintf('%02d:%02d', intdiv($k, 60), $k % 60), 'istirahat' => false,
            'menit' => $k - $m, 'menit_mulai' => $m, 'menit_selesai' => $k,
        ];
    }
    return $out;
}

/** Jam kerja efektif per hari (segmen istirahat TIDAK dihitung). */
function jam_kerja_per_hari_efektif(): float
{
    if (conf('jam_kerja_mode', 'segmen') === 'segmen') {
        $menit = 0;
        foreach (jam_kerja_segmen() as $g) {
            if (!$g['istirahat']) {
                $menit += $g['menit'];
            }
        }
        if ($menit > 0) {
            return $menit / 60;
        }
    }
    return max(0.1, conf_num('jam_kerja_per_hari', 10));
}

/** Total menit istirahat per hari. */
function menit_istirahat_harian(): int
{
    $menit = 0;
    foreach (jam_kerja_segmen() as $g) {
        if ($g['istirahat']) {
            $menit += $g['menit'];
        }
    }
    return $menit;
}

/** Jam masuk standar = awal segmen kerja paling pagi. */
function jam_masuk_standar(): string
{
    $seg = jam_kerja_segmen();
    $awal = null;
    foreach ($seg as $g) {
        if (!$g['istirahat'] && ($awal === null || $g['menit_mulai'] < $awal)) {
            $awal = $g['menit_mulai'];
        }
    }
    if ($awal === null) {
        return conf('absensi_jam_masuk_standar', '08:00');
    }
    return sprintf('%02d:%02d', intdiv($awal, 60), $awal % 60);
}

/** Jam pulang standar = akhir segmen kerja paling malam (mulai perhitungan lembur). */
function jam_pulang_standar(): string
{
    $seg = jam_kerja_segmen();
    $akhir = null;
    foreach ($seg as $g) {
        if (!$g['istirahat'] && ($akhir === null || $g['menit_selesai'] > $akhir)) {
            $akhir = $g['menit_selesai'];
        }
    }
    if ($akhir === null) {
        return conf('absensi_jam_pulang_standar', '20:00');
    }
    return sprintf('%02d:%02d', intdiv($akhir, 60), $akhir % 60);
}

/** Ringkasan jam kerja sebagai teks, mis. "10 jam (08:00–11:00, 13:00–20:00)". */
function jam_kerja_teks(): string
{
    $kerja = [];
    foreach (jam_kerja_segmen() as $g) {
        if (!$g['istirahat']) {
            $kerja[] = $g['mulai'] . '–' . $g['selesai'];
        }
    }
    return angka(jam_kerja_per_hari_efektif(), jam_kerja_per_hari_efektif() == floor(jam_kerja_per_hari_efektif()) ? 0 : 1)
        . ' jam (' . implode(', ', $kerja) . ')';
}

/**
 * Menit kerja aktual antara jam masuk & pulang, DENGAN mengurangi segmen istirahat
 * yang terlewati. Dipakai untuk lembur hari Minggu (mode per jam) dan rekap.
 */
function menit_kerja_aktual(?string $masuk, ?string $pulang): ?int
{
    $m = jam_ke_menit($masuk);
    $k = jam_ke_menit($pulang);
    if ($m === null || $k === null || $k <= $m) {
        return null;
    }
    $istirahat = 0;
    foreach (jam_kerja_segmen() as $g) {
        if (!$g['istirahat']) {
            continue;
        }
        $mulai = max($m, (int) $g['menit_mulai']);
        $selesai = min($k, (int) $g['menit_selesai']);
        if ($selesai > $mulai) {
            $istirahat += $selesai - $mulai;
        }
    }
    return max(0, ($k - $m) - $istirahat);
}

/* ------------------------------------------------------------------ */
/* Kalender kerja                                                     */
/* ------------------------------------------------------------------ */

/** Hari kerja mingguan sebagai array nomor hari (1=Senin … 7=Minggu). */
function hari_kerja_mingguan(): array
{
    $out = [];
    foreach (explode(',', conf('hari_kerja_mingguan', '1,2,3,4,5,6')) as $d) {
        $n = (int) trim($d);
        if ($n >= 1 && $n <= 7) {
            $out[] = $n;
        }
    }
    return $out ?: [1, 2, 3, 4, 5, 6];
}

/** Semua hari libur (tanggal => keterangan) untuk rentang bulan tertentu. */
function hari_libur_map(string $periode): array
{
    $st = db()->prepare('SELECT tanggal, keterangan FROM hari_libur WHERE tanggal LIKE ?');
    $st->execute([$periode . '%']);
    $out = [];
    foreach ($st as $r) {
        $out[$r['tanggal']] = (string) $r['keterangan'];
    }
    return $out;
}

/** Apakah sebuah tanggal termasuk hari kerja (bukan akhir pekan & bukan libur)? */
function tanggal_hari_kerja(string $ymd, array $libur = []): bool
{
    $n = (int) date('N', strtotime($ymd));
    if ($n === 7) {
        return false;   /* Minggu tidak pernah dihitung sebagai hari kerja */
    }
    if (!in_array($n, hari_kerja_mingguan(), true)) {
        return false;
    }
    return !isset($libur[$ymd]);
}

/**
 * Jumlah hari kerja dalam sebuah bulan.
 * Diambil dari KALENDER (hari kerja mingguan dikurangi libur) bila basis = otomatis,
 * atau dari angka tetap di Pengaturan bila basis = tetap.
 */
function hari_kerja_bulan(string $periode): array
{
    $periode = periode_valid($periode);
    [$tahun, $bulan] = array_map('intval', explode('-', $periode));
    $jmlHari = (int) date('t', strtotime($periode . '-01'));
    $libur = hari_libur_map($periode);
    $kurangiLibur = conf_bool('hari_kerja_kurangi_libur', true);
    $hariKerjaMinggu = hari_kerja_mingguan();

    $tanggal = [];
    $jmlMinggu = 0;
    $jmlSabtu = 0;
    for ($d = 1; $d <= $jmlHari; $d++) {
        $ymd = sprintf('%04d-%02d-%02d', $tahun, $bulan, $d);
        $dow = (int) date('N', strtotime($ymd));
        /* HARI MINGGU SELALU DIKECUALIKAN dari pembagian hari kerja — Minggu berdiri sendiri
           (dibayar terpisah lewat tarif per jam Minggu). Ini berlaku walau pengaturan
           "hari kerja mingguan" keliru memuat angka 7. */
        if ($dow === 7) {
            $jmlMinggu++;
            continue;
        }
        if ($dow === 6) {
            $jmlSabtu++;
        }
        if (!in_array($dow, $hariKerjaMinggu, true)) {
            continue;
        }
        if ($kurangiLibur && isset($libur[$ymd])) {
            continue;
        }
        $tanggal[] = $ymd;
    }

    $mode = conf('basis_hari_kerja', 'otomatis');
    $jumlah = $mode === 'tetap' ? max(1.0, conf_num('hari_kerja_per_bulan', 25)) : (float) count($tanggal);
    return [
        'periode'   => $periode,
        'mode'      => $mode,
        'jumlah'    => $jumlah,
        'kalender'  => count($tanggal),
        'tanggal'   => $tanggal,
        'libur'     => $libur,
        'kurangi_libur' => $kurangiLibur,
        'jumlah_minggu' => $jmlMinggu,          /* Minggu TIDAK ikut pembagi — hanya untuk info & bonus minggu */
        'jumlah_sabtu'  => $jmlSabtu,
        'jumlah_hari_bulan' => $jmlHari,
    ];
}

/** Tanggal kerja yang tidak punya catatan absensi (dipakai untuk opsi hitung alpa otomatis). */
function tanggal_tanpa_catatan(int $karyawanId, string $periode): array
{
    $kal = hari_kerja_bulan($periode);
    $st = db()->prepare('SELECT tanggal FROM absensi WHERE karyawan_id = ? AND tanggal LIKE ?');
    $st->execute([$karyawanId, $periode . '%']);
    $ada = [];
    foreach ($st as $r) {
        $ada[$r['tanggal']] = true;
    }
    $hariIni = date('Y-m-d');
    $out = [];
    foreach ($kal['tanggal'] as $t) {
        if (!isset($ada[$t]) && $t <= $hariIni) {
            $out[] = $t;
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Master karyawan                                                    */
/* ------------------------------------------------------------------ */

function daftar_divisi(bool $hanyaAktif = true): array
{
    $sql = 'SELECT * FROM divisi' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY nama';
    return db()->query($sql)->fetchAll();
}

function daftar_karyawan(string $cari = '', bool $hanyaAktif = true, string $divisi = ''): array
{
    $sql = 'SELECT k.*, d.nama AS divisi_nama FROM karyawan k LEFT JOIN divisi d ON d.id = k.divisi_id WHERE 1=1';
    $par = [];
    if ($hanyaAktif) {
        $sql .= ' AND k.aktif = 1';
    }
    if ($cari !== '') {
        $sql .= ' AND (k.nama LIKE ? OR k.nip LIKE ? OR k.jabatan LIKE ?)';
        $par[] = '%' . $cari . '%';
        $par[] = '%' . $cari . '%';
        $par[] = '%' . $cari . '%';
    }
    if ($divisi !== '') {
        $sql .= ' AND k.divisi_id = ?';
        $par[] = (int) $divisi;
    }
    $sql .= ' ORDER BY k.nama';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function karyawan_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT k.*, d.nama AS divisi_nama FROM karyawan k LEFT JOIN divisi d ON d.id = k.divisi_id WHERE k.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function komponen_karyawan(int $karyawanId, bool $hanyaAktif = true): array
{
    $sql = 'SELECT * FROM komponen_gaji WHERE karyawan_id = ?' . ($hanyaAktif ? ' AND aktif = 1' : '') . ' ORDER BY jenis, nama';
    $st = db()->prepare($sql);
    $st->execute([$karyawanId]);
    return $st->fetchAll();
}

function karyawan_simpan(?int $id, array $d): array
{
    $nama = trim((string) ($d['nama'] ?? ''));
    $nip  = trim((string) ($d['nip'] ?? ''));
    if ($nama === '') {
        return ['ok' => false, 'error' => 'Nama karyawan wajib diisi.'];
    }
    if ($nip === '') {
        /* Nomor induk dibuat otomatis bila dikosongkan. */
        $urut = (int) db()->query('SELECT COUNT(*) FROM karyawan')->fetchColumn() + 1;
        do {
            $nip = 'KRY-' . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
            $st = db()->prepare('SELECT COUNT(*) FROM karyawan WHERE nip = ?');
            $st->execute([$nip]);
            $dipakai = (int) $st->fetchColumn() > 0;
            if ($dipakai) {
                $urut++;
            }
        } while ($dipakai);
    }
    $st = db()->prepare('SELECT id FROM karyawan WHERE nip = ? AND id <> ?');
    $st->execute([$nip, $id ?? 0]);
    if ($st->fetchColumn()) {
        return ['ok' => false, 'error' => 'NIP sudah dipakai karyawan lain.'];
    }
    $kolom = [
        'nip' => $nip,
        'nama' => $nama,
        'jabatan' => trim((string) ($d['jabatan'] ?? '')),
        'divisi_id' => (int) ($d['divisi_id'] ?? 0) ?: null,
        'tanggal_masuk' => tanggal_valid((string) ($d['tanggal_masuk'] ?? ''), date('Y-m-d')),
        'status_kepegawaian' => array_key_exists((string) ($d['status_kepegawaian'] ?? ''), status_karyawan()) ? (string) $d['status_kepegawaian'] : 'tetap',
        'metode_pajak' => array_key_exists((string) ($d['metode_pajak'] ?? ''), metode_pajak()) ? (string) $d['metode_pajak'] : conf('pajak_metode_default', 'progresif'),
        'ptkp_kode' => trim((string) ($d['ptkp_kode'] ?? 'TK/0')),
        'npwp' => trim((string) ($d['npwp'] ?? '')),
        'gaji_pokok' => (float) ($d['gaji_pokok'] ?? 0),
        'tunjangan_tetap' => (float) ($d['tunjangan_tetap'] ?? 0),
        'tunjangan_tidak_tetap' => (float) ($d['tunjangan_tidak_tetap'] ?? 0),
        'bank' => trim((string) ($d['bank'] ?? '')),
        'no_rekening' => trim((string) ($d['no_rekening'] ?? '')),
        'email' => trim((string) ($d['email'] ?? '')),
        'telepon' => trim((string) ($d['telepon'] ?? '')),
        'aktif' => !empty($d['aktif']) ? 1 : 0,
        'updated_at' => now(),
    ];
    if ($id === null) {
        $kolom['created_at'] = now();
        $sql = 'INSERT INTO karyawan (' . implode(', ', array_keys($kolom)) . ') VALUES (' . implode(', ', array_fill(0, count($kolom), '?')) . ')';
        db()->prepare($sql)->execute(array_values($kolom));
        return ['ok' => true, 'id' => (int) db()->lastInsertId(), 'nip' => $nip];
    }
    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($kolom)));
    $par = array_values($kolom);
    $par[] = $id;
    db()->prepare("UPDATE karyawan SET {$set} WHERE id = ?")->execute($par);
    return ['ok' => true, 'id' => $id, 'nip' => $nip];
}

function karyawan_hapus(int $id): array
{
    $st = db()->prepare('SELECT COUNT(*) FROM slip_gaji WHERE karyawan_id = ?');
    $st->execute([$id]);
    if ((int) $st->fetchColumn() > 0) {
        /* Riwayat slip harus tetap ada: nonaktifkan saja, jangan hapus. */
        db()->prepare('UPDATE karyawan SET aktif = 0, updated_at = ? WHERE id = ?')->execute([now(), $id]);
        return ['ok' => true, 'nonaktif' => true];
    }
    db()->prepare('DELETE FROM karyawan WHERE id = ?')->execute([$id]);
    return ['ok' => true, 'nonaktif' => false];
}

function daftar_ptkp(bool $hanyaAktif = true): array
{
    $sql = 'SELECT * FROM ptkp' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY urutan, kode';
    return db()->query($sql)->fetchAll();
}

function ptkp_setahun(string $kode): float
{
    $st = db()->prepare('SELECT setahun FROM ptkp WHERE kode = ?');
    $st->execute([$kode]);
    $v = $st->fetchColumn();
    return $v === false ? 0.0 : (float) $v;
}

function pph21_layers(): array
{
    return db()->query('SELECT * FROM pph21_layers ORDER BY urutan')->fetchAll();
}

/* ------------------------------------------------------------------ */
/* Absensi                                                            */
/* ------------------------------------------------------------------ */

function absensi_rentang(string $periode, int $karyawanId = 0): array
{
    $sql = 'SELECT a.*, k.nama, k.nip FROM absensi a JOIN karyawan k ON k.id = a.karyawan_id WHERE a.tanggal LIKE ?';
    $par = [$periode . '%'];
    if ($karyawanId > 0) {
        $sql .= ' AND a.karyawan_id = ?';
        $par[] = $karyawanId;
    }
    $sql .= ' ORDER BY a.tanggal DESC, k.nama';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

/** Menyimpan (insert/replace) satu baris absensi. */
function absensi_simpan(array $d, string $oleh): array
{
    $karyawanId = (int) ($d['karyawan_id'] ?? 0);
    $tanggal = tanggal_valid((string) ($d['tanggal'] ?? ''));
    if ($karyawanId <= 0 || !karyawan_by_id($karyawanId)) {
        return ['ok' => false, 'error' => 'Karyawan tidak ditemukan.'];
    }
    $status = array_key_exists((string) ($d['status'] ?? ''), status_absensi()) ? (string) $d['status'] : 'HADIR';
    $masuk = trim((string) ($d['jam_masuk'] ?? ''));
    $pulang = trim((string) ($d['jam_pulang'] ?? ''));
    $terlambat = max(0, (int) ($d['menit_terlambat'] ?? 0));
    $lembur = max(0, (int) ($d['menit_lembur'] ?? 0));
    $disetujui = !empty($d['lembur_disetujui']) ? 1 : 0;
    $menitMinggu = max(0, (int) ($d['menit_minggu'] ?? 0));
    if (array_key_exists('jam_minggu', $d) && trim((string) $d['jam_minggu']) !== '') {
        /* Petugas mengisi JUMLAH JAM KERJA Minggu (mis. 8 atau 7,5) → disimpan dalam menit. */
        $menitMinggu = (int) round(((float) str_replace(',', '.', (string) $d['jam_minggu'])) * 60);
    }

    /* ================= HARI MINGGU: aturan khusus =================
       Status hanya HADIR atau LIBUR, dan satu-satunya isian adalah JUMLAH JAM KERJA.
       Jam masuk/pulang, keterlambatan, lembur harian, dan persetujuan tidak dipakai.
       Yang dibayar = jam kerja itu sendiri (lembur minggu) × tarif per jam khusus Minggu. */
    if ((int) date('N', strtotime($tanggal)) === 7) {
        if (!array_key_exists($status, status_absensi_minggu())) {
            $status = 'HADIR';
        }
        if ($status !== 'HADIR') {
            $menitMinggu = 0;   /* Libur / tidak dicatat → tidak ada upah Minggu */
        }
        db()->prepare('INSERT INTO absensi (karyawan_id, tanggal, jam_masuk, jam_pulang, status, menit_terlambat, menit_lembur, menit_minggu, lembur_disetujui, keterangan, dibuat_oleh, created_at, updated_at)
                       VALUES (?, ?, "", "", ?, 0, 0, ?, 0, ?, ?, ?, ?)
                       ON CONFLICT(karyawan_id, tanggal) DO UPDATE SET
                         jam_masuk = "", jam_pulang = "", status = excluded.status,
                         menit_terlambat = 0, menit_lembur = 0, menit_minggu = excluded.menit_minggu,
                         lembur_disetujui = 0, keterangan = excluded.keterangan,
                         dibuat_oleh = excluded.dibuat_oleh, updated_at = excluded.updated_at')
            ->execute([$karyawanId, $tanggal, $status, $menitMinggu, trim((string) ($d['keterangan'] ?? '')), $oleh, now(), now()]);
        return ['ok' => true, 'hari_minggu' => true, 'menit_minggu' => $menitMinggu];
    }

    if ($status !== 'HADIR') {
        /* Jam masuk/pulang & menit hanya berlaku untuk status Hadir. */
        $masuk = '';
        $pulang = '';
        $terlambat = 0;
        $lembur = 0;
        $disetujui = 0;
    } else {
        /* Perhitungan otomatis juga dilakukan di server (pengaman bila JavaScript mati),
           memakai jam kerja standar dari segmen di Pengaturan → Jam Kerja.
           Penanda auto_* SELALU dikirim oleh formulir absensi (termasuk saat JavaScript mati,
           karena penandanya adalah kolom tersembunyi). Bila penanda tidak ada — mis. impor
           manual atau skrip — nilai menit yang dikirim dipakai apa adanya, tidak dihitung ulang. */
        $autoTerlambat = !empty($d['auto_terlambat']);
        $autoLembur = !empty($d['auto_lembur']);
        if ($autoTerlambat && $masuk !== '') {
            $m = jam_ke_menit($masuk);
            $s = jam_ke_menit(jam_masuk_standar());
            $terlambat = ($m === null || $s === null) ? 0 : max(0, $m - $s);
        }
        /* Lembur hanya dihitung & disimpan bila disetujui. */
        if ($disetujui === 0) {
            $lembur = 0;
        } elseif ($autoLembur && $pulang !== '') {
            $k = jam_ke_menit($pulang);
            $sp = jam_ke_menit(jam_pulang_standar());
            $lembur = ($k === null || $sp === null) ? 0 : max(0, $k - $sp);
        }
    }
    db()->prepare('INSERT INTO absensi (karyawan_id, tanggal, jam_masuk, jam_pulang, status, menit_terlambat, menit_lembur, menit_minggu, lembur_disetujui, keterangan, dibuat_oleh, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)
                   ON CONFLICT(karyawan_id, tanggal) DO UPDATE SET
                     jam_masuk = excluded.jam_masuk, jam_pulang = excluded.jam_pulang, status = excluded.status,
                     menit_terlambat = excluded.menit_terlambat, menit_lembur = excluded.menit_lembur,
                     menit_minggu = 0,
                     lembur_disetujui = excluded.lembur_disetujui, keterangan = excluded.keterangan,
                     dibuat_oleh = excluded.dibuat_oleh, updated_at = excluded.updated_at')
        ->execute([$karyawanId, $tanggal, $masuk, $pulang, $status, $terlambat, $lembur, $disetujui, trim((string) ($d['keterangan'] ?? '')), $oleh, now(), now()]);
    return ['ok' => true];
}

function absensi_hapus(int $id): void
{
    db()->prepare('DELETE FROM absensi WHERE id = ?')->execute([$id]);
}

/** Rekap absensi & lembur satu karyawan pada satu periode penggajian. */
function absensi_rekap(int $karyawanId, string $periode): array
{
    $periode = periode_valid($periode);
    $rekap = [
        'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'alpa' => 0, 'libur' => 0,
        'hadir_kerja' => 0, 'hadir_minggu' => 0, 'menit_minggu' => 0,
        'menit_terlambat' => 0, 'kali_terlambat' => 0, 'menit_lembur' => 0, 'menit_lembur_disetujui' => 0,
        'tanggal_lembur' => [], 'rincian_terlambat' => [], 'rincian_lembur' => [], 'hari_alpa_tanggal' => [],
        'rincian_minggu' => [],
    ];
    $liburMap = hari_libur_map($periode);
    $minimalMinggu = conf_int('minggu_minimal_menit', 240);
    $st = db()->prepare('SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal LIKE ? ORDER BY tanggal');
    $st->execute([$karyawanId, $periode . '%']);
    foreach ($st as $r) {
        $status = (string) $r['status'];
        $key = ['HADIR' => 'hadir', 'IZIN' => 'izin', 'SAKIT' => 'sakit', 'CUTI' => 'cuti', 'ALPA' => 'alpa', 'LIBUR' => 'libur'][$status] ?? 'hadir';
        $rekap[$key]++;
        if ($status === 'ALPA') {
            $rekap['hari_alpa_tanggal'][] = (string) $r['tanggal'];
        }
        /* Hari MINGGU punya aturan tersendiri (upah harian + upah lembur minggu).
           Minggu tidak termasuk hari kerja, jadi tidak pernah "alpa" — hanya dihitung
           bila karyawan benar-benar bekerja. */
        if ((int) date('N', strtotime((string) $r['tanggal'])) === 7) {
            /* Jam kerja Minggu diisi LANGSUNG oleh petugas (kolom menit_minggu) — yang dibayar
               hanya jam kerja itu, bukan seluruh jam kerja normal. Data lama (sebelum kolom ini
               ada) masih dihitung dari jam masuk/pulang dengan batas minimal sebagai pengaman. */
            $menitEksplisit = (int) ($r['menit_minggu'] ?? 0);
            if ($menitEksplisit > 0) {
                $menitKerja = $menitEksplisit;
                $sumberJam = 'isian jam kerja';
            } else {
                $menitKerja = menit_kerja_aktual((string) $r['jam_masuk'], (string) $r['jam_pulang']) ?? 0;
                $sumberJam = 'data lama (jam masuk/pulang)';
                if ($menitKerja < $minimalMinggu) {
                    $menitKerja = 0;
                }
            }
            if ($status === 'HADIR' && $menitKerja > 0) {
                $rekap['hadir_minggu']++;
                $rekap['menit_minggu'] += $menitKerja;
                $rekap['rincian_minggu'][] = [
                    'tanggal' => (string) $r['tanggal'],
                    'masuk' => (string) $r['jam_masuk'],
                    'pulang' => (string) $r['jam_pulang'],
                    'menit' => $menitKerja,
                    'sumber' => $sumberJam,
                ];
            }
        } elseif ($status === 'HADIR') {
            $rekap['hadir_kerja']++;
        }
        $menit = (int) $r['menit_terlambat'];
        if ($menit > 0) {
            $rekap['menit_terlambat'] += $menit;
            $rekap['kali_terlambat']++;
            $rekap['rincian_terlambat'][] = ['tanggal' => (string) $r['tanggal'], 'menit' => $menit];
        }
        $lm = (int) $r['menit_lembur'];
        if ($lm > 0) {
            $rekap['menit_lembur'] += $lm;
            if ((int) $r['lembur_disetujui'] === 1) {
                $rekap['menit_lembur_disetujui'] += $lm;
            }
            $rekap['tanggal_lembur'][] = (string) $r['tanggal'];
            $dow = (int) date('N', strtotime((string) $r['tanggal']));
            $rekap['rincian_lembur'][] = [
                'tanggal' => (string) $r['tanggal'],
                'menit' => $lm,
                'disetujui' => (int) $r['lembur_disetujui'],
                'dow' => $dow,
                'minggu' => $dow === 7,
                'hari_libur' => !tanggal_hari_kerja((string) $r['tanggal'], $liburMap),
            ];
        }
    }
    /* Opsi: hari kerja tanpa catatan absensi dihitung alpa. */
    if (conf_bool('absensi_alpa_tanpa_catatan', false)) {
        $tanpa = tanggal_tanpa_catatan($karyawanId, $periode);
        $rekap['alpa'] += count($tanpa);
        $rekap['hari_alpa_tanggal'] = array_merge($rekap['hari_alpa_tanggal'], $tanpa);
    }
    return $rekap;
}

/* ------------------------------------------------------------------ */
/* Mesin penggajian (payroll engine)                                  */
/* ------------------------------------------------------------------ */

/** Pembulatan sesuai setelan Pengaturan. */
function payroll_bulat(float $n): float
{
    switch (conf('pembulatan_gaji', 'bulat')) {
        case 'ribuan':
            return round($n / 1000) * 1000;
        case 'none':
            return round($n, 2);
        default:
            return round($n);
    }
}

/** Upah per jam & per hari berdasarkan setelan "basis upah per jam". */
function payroll_upah(array $kar, float $hariKerjaKalender): array
{
    /* Dasar upah: Gaji Pokok saja (bawaan perusahaan ini) atau Pokok + Tunjangan Tetap. */
    $pakaiTunjangan = conf('basis_upah_per_jam', 'gaji_pokok') === 'gaji_pokok_tunjangan';
    $basis = $pakaiTunjangan
        ? (float) $kar['gaji_pokok'] + (float) $kar['tunjangan_tetap']
        : (float) $kar['gaji_pokok'];

    /* Pembagi upah harian: angka tetap (25 hari — rumus perusahaan ini) atau kalender. */
    $hariBulan = conf('basis_hari_kerja', 'otomatis') === 'otomatis'
        ? max(1.0, $hariKerjaKalender)
        : max(1.0, conf_num('hari_kerja_per_bulan', 25));

    /* Upah lembur per jam = Dasar ÷ pembagi lembur (bawaan 173, rumus 1/173 upah sebulan). */
    $pembagiLembur = max(1.0, conf_num('lembur_pembagi', 173));

    $jamPerHari = jam_kerja_per_hari_efektif();
    $perHari = $basis / $hariBulan;
    $perJamLembur = $basis / $pembagiLembur;
    return [
        'basis' => $basis,
        'basis_label' => $pakaiTunjangan ? 'Gaji Pokok + Tunjangan Tetap' : 'Gaji Pokok',
        'hari_kerja' => $hariBulan,
        'hari_kerja_kalender' => $hariKerjaKalender,
        'jam_per_hari' => $jamPerHari,
        'per_jam' => $perJamLembur,          /* kompatibilitas: upah lembur per jam */
        'per_jam_lembur' => $perJamLembur,
        'per_hari' => $perHari,
        'per_jam_normal' => $perHari / max(0.1, $jamPerHari),
        'pembagi_lembur' => $pembagiLembur,
        'istirahat_menit' => menit_istirahat_harian(),
    ];
}

/**
 * Menghitung upah lembur BERBASIS JAM dari daftar baris lembur.
 * Dipakai untuk lembur hari kerja (Senin–Sabtu) dan lembur hari libur nasional.
 * Hari MINGGU dihitung terpisah oleh payroll_minggu() karena berbasis hari (2 upah).
 *
 * Aturan (dinamis):
 *   - jenis 'kerja' : jam pertama × `lembur_faktor_pertama`, jam berikutnya × `lembur_faktor_berikutnya`
 *   - jenis 'libur' : seluruh jam × `lembur_faktor_hari_libur`
 *   - pembulatan waktu: `lembur_pembulatan`
 *   - batas bulanan : `lembur_maks_jam_bulan` (0 = tanpa batas)
 *   - `lembur_wajib_disetujui` = hanya lembur yang disetujui yang dibayar
 */
function payroll_lembur(array $lepasan, float $upahPerJam, string $jenis = 'kerja'): array
{
    /* Rumus perusahaan ini: upah lembur per jam = Gaji Pokok ÷ 173 (pembagi di Pengaturan),
       total = jam lembur × upah lembur per jam. Faktor pengali (1,5× / 2×) OPSIONAL —
       hanya dipakai bila "Pakai faktor pengali" dinyalakan di Pengaturan → Lembur. */
    $pakaiFaktor = conf_bool('lembur_pakai_faktor', false);
    $faktor1 = $pakaiFaktor ? conf_num('lembur_faktor_pertama', 1.5) : 1.0;
    $faktorN = $pakaiFaktor ? conf_num('lembur_faktor_berikutnya', 2) : 1.0;
    $faktorL = $pakaiFaktor ? conf_num('lembur_faktor_hari_libur', $faktorN) : 1.0;
    $mode    = conf('lembur_pembulatan', 'per_menit');
    $maksJam = conf_num('lembur_maks_jam_bulan', 0);
    $wajibSetuju = conf_bool('lembur_wajib_disetujui', true);

    $menitDibayar = 0;
    $total = 0.0;
    $rincian = [];
    foreach ($lepasan as $r) {
        if ($wajibSetuju && empty($r['disetujui'])) {
            continue;
        }
        $menit = max(0, (int) $r['menit']);
        if ($mode === 'per_15menit') {
            $menit = (int) (round($menit / 15) * 15);
        } elseif ($mode === 'per_30menit') {
            $menit = (int) (round($menit / 30) * 30);
        } elseif ($mode === 'per_jam') {
            $menit = (int) (ceil($menit / 60) * 60);
        }
        if ($menit <= 0) {
            continue;
        }
        if ($maksJam > 0 && $menitDibayar + $menit > $maksJam * 60) {
            $menit = (int) max(0, $maksJam * 60 - $menitDibayar);
        }
        if ($menit <= 0) {
            break;
        }
        $jam = $menit / 60;
        if ($jenis === 'libur') {
            $nilai = $jam * $faktorL * $upahPerJam;
            $uraian = angka($jam, 2) . ' jam × ' . ($pakaiFaktor ? angka($faktorL, 2) . '× ' : '') . 'upah lembur per jam (hari libur nasional)';
        } elseif (!$pakaiFaktor) {
            /* Rumus dasar: jam lembur × (Gaji Pokok ÷ 173). */
            $nilai = $jam * $upahPerJam;
            $uraian = angka($jam, 2) . ' jam × upah lembur per jam ' . rp(round($upahPerJam));
        } else {
            $jamPertama = min(1, $jam);
            $jamSisa = max(0, $jam - 1);
            $nilai = ($jamPertama * $faktor1 + $jamSisa * $faktorN) * $upahPerJam;
            $uraian = angka($jamPertama, 2) . ' jam × ' . angka($faktor1, 2) . '×'
                . ($jamSisa > 0 ? ' + ' . angka($jamSisa, 2) . ' jam × ' . angka($faktorN, 2) . '×' : '');
        }
        $menitDibayar += $menit;
        $total += $nilai;
        $rincian[] = ['tanggal' => $r['tanggal'], 'menit' => $menit, 'nilai' => payroll_bulat($nilai), 'uraian' => $uraian];
    }
    return ['nilai' => payroll_bulat($total), 'menit' => $menitDibayar, 'jam' => $menitDibayar / 60, 'rincian' => $rincian];
}

/** Denda keterlambatan sesuai mode yang dipilih di Pengaturan. */
function payroll_denda(array $rekap, array $upah): array
{
    if (!conf_bool('absensi_aktif', true)) {
        return ['nilai' => 0.0, 'rincian' => [], 'uraian' => 'Potongan absensi dimatikan di Pengaturan.'];
    }
    $toleransi = conf_int('absensi_toleransi_menit', 15);
    $mode = conf('absensi_denda_mode', 'bertingkat');
    $total = 0.0;
    $rincian = [];
    $uraian = '';
    foreach ($rekap['rincian_terlambat'] as $r) {
        $net = max(0, (int) $r['menit'] - $toleransi);
        if ($net <= 0) {
            continue;
        }
        $nilai = 0.0;
        if ($mode === 'per_menit') {
            $perMenit = conf_num('absensi_denda_per_menit', 0);
            $nilai = $net * $perMenit;
            $uraian = angka($net) . ' menit × ' . rp($perMenit);
        } elseif ($mode === 'per_kejadian') {
            $nilai = conf_num('absensi_denda_per_kejadian', 0);
            $uraian = 'per kejadian (terlambat ' . $net . ' menit)';
        } elseif ($mode === 'bertingkat') {
            foreach (conf_json('absensi_denda_bertingkat', []) as $t) {
                $batas = (int) ($t['menit'] ?? 0);
                if ($batas === 0 || $net <= $batas) {
                    $nilai = (float) ($t['denda'] ?? 0);
                    $uraian = 'terlambat ' . $net . ' menit → rentang ≤ ' . ($batas === 0 ? 'tanpa batas' : $batas . ' menit');
                    break;
                }
            }
        }
        if ($nilai <= 0) {
            continue;
        }
        $total += $nilai;
        $rincian[] = ['tanggal' => $r['tanggal'], 'menit' => $net, 'nilai' => payroll_bulat($nilai), 'uraian' => $uraian];
    }
    $maks = conf_num('absensi_maks_denda_bulan', 0);
    $dipotong = false;
    if ($maks > 0 && $total > $maks) {
        $total = $maks;
        $dipotong = true;
    }
    return [
        'nilai' => payroll_bulat($total),
        'rincian' => $rincian,
        'uraian' => $uraian,
        'maks_dipakai' => $dipotong,
        'toleransi' => $toleransi,
        'mode' => $mode,
    ];
}

/** Potongan untuk satu status absensi (alpa/izin/sakit) mengikuti mode di Pengaturan. */
function payroll_potongan_status(string $jenis, int $hari, array $upah): array
{
    if ($hari <= 0) {
        return ['nilai' => 0.0, 'uraian' => ''];
    }
    $modeKey = $jenis === 'alpa' ? 'absensi_alpa_mode' : ($jenis === 'izin' ? 'absensi_izin_mode' : 'absensi_sakit_mode');
    $nominalKey = $jenis === 'alpa' ? 'absensi_alpa_nominal' : ($jenis === 'izin' ? 'absensi_izin_nominal' : 'absensi_sakit_nominal');
    $mode = conf($modeKey, 'tidak_ada');
    if (!conf_bool('absensi_aktif', true) && $jenis === 'alpa') {
        return ['nilai' => 0.0, 'uraian' => 'Potongan absensi dimatikan.'];
    }
    if ($mode === 'upah_harian') {
        $faktor = $jenis === 'alpa' ? conf_num('absensi_alpa_faktor', 1) : 1.0;
        $nilai = $hari * $upah['per_hari'] * $faktor;
        return [
            'nilai' => payroll_bulat($nilai),
            'uraian' => $hari . ' hari × ' . angka($faktor, 2) . ' × upah harian ' . rp($upah['per_hari']),
        ];
    }
    if ($mode === 'nominal') {
        $nom = conf_num($nominalKey, 0);
        return ['nilai' => payroll_bulat($hari * $nom), 'uraian' => $hari . ' hari × ' . rp($nom)];
    }
    return ['nilai' => 0.0, 'uraian' => 'Tidak dipotong (setelan Pengaturan).'];
}

/**
 * Upah kerja HARI MINGGU — aturan khusus perusahaan ini: karyawan mendapat DUA upah,
 *   1) upah harian (faktor `minggu_upah_harian_faktor`)
 *   2) upah lembur minggu, dihitung menurut `minggu_lembur_mode`:
 *        faktor_upah_harian → sekian kali upah harian per hari
 *        nominal_per_hari   → nominal rupiah per hari
 *        per_jam_faktor     → sekian kali upah per jam × jam kerja aktual hari itu
 */
function payroll_minggu(array $rekap, array $upah): array
{
    $kosong = [
        'aktif' => false, 'hari' => 0, 'menit' => 0, 'jam' => 0.0,
        'upah_harian' => 0.0, 'lembur' => 0.0, 'nilai' => 0.0,
        'mode' => conf('minggu_mode', 'tarif_jam'), 'items' => [], 'uraian' => '', 'rincian' => [],
    ];
    if (!conf_bool('minggu_aktif', true)) {
        return $kosong + ['uraian' => 'Aturan kerja hari Minggu dimatikan di Pengaturan.'];
    }
    $rincian = $rekap['rincian_minggu'] ?? [];
    if (!$rincian) {
        return $kosong;
    }
    $hari = count($rincian);
    $menit = (int) ($rekap['menit_minggu'] ?? 0);
    $jam = $menit / 60;
    $mode = conf('minggu_mode', 'tarif_jam');

    /* ---- Mode perusahaan ini: jam kerja Minggu × tarif per jam khusus Minggu ---- */
    if ($mode === 'tarif_jam') {
        $tarif = conf_num('minggu_tarif_jam', 0);
        $nilai = payroll_bulat($jam * $tarif);
        $items = [];
        if ($nilai > 0) {
            /* Hari Minggu berdiri sendiri & tunggal: hanya lembur minggu dengan tarif per jam custom.
               Upah harian biasa TIDAK berlaku pada hari Minggu. */
            $items[] = [
                'nama' => 'Upah Lembur Minggu (' . angka($jam, 2) . ' jam × ' . rp($tarif) . ')',
                'nilai' => $nilai, 'kategori' => 'Minggu', 'sumber' => 'absensi',
            ];
        }
        return [
            'aktif' => true, 'hari' => $hari, 'menit' => $menit, 'jam' => $jam,
            'upah_harian' => 0.0, 'lembur' => $nilai, 'nilai' => $nilai,
            'mode' => 'tarif_jam', 'tarif_jam' => $tarif, 'items' => $items,
            'uraian' => 'Lembur minggu: ' . $hari . ' hari Minggu dikerjakan (' . angka($jam, 2) . ' jam) × tarif khusus Minggu ' . rp($tarif)
                . ' = ' . rp($nilai) . ' — Minggu berdiri sendiri, upah harian biasa tidak berlaku dan Minggu tidak dihitung pada pembagi hari kerja.',
            'rincian' => $rincian,
        ];
    }

    /* ---- Mode lama: upah harian + upah lembur minggu ("2 upah") ---- */
    $faktorHarian = conf_num('minggu_upah_harian_faktor', 1);
    $upahHarian = payroll_bulat($upah['per_hari'] * $faktorHarian * $hari);
    if (conf('minggu_lembur_mode', 'faktor_upah_harian') === 'nominal_per_hari') {
        $lembur = payroll_bulat(conf_num('minggu_lembur_nominal', 0) * $hari);
        $uraianLembur = $hari . ' hari × nominal ' . rp(conf_num('minggu_lembur_nominal', 0));
    } elseif (conf('minggu_lembur_mode', 'faktor_upah_harian') === 'per_jam_faktor') {
        $faktorJam = conf_num('minggu_lembur_faktor_jam', 2);
        $lembur = payroll_bulat($jam * $faktorJam * $upah['per_jam_normal']);
        $uraianLembur = angka($jam, 2) . ' jam × ' . angka($faktorJam, 2) . '× upah per jam biasa';
    } else {
        $faktorLembur = conf_num('minggu_lembur_faktor_harian', 1);
        $lembur = payroll_bulat($upah['per_hari'] * $faktorLembur * $hari);
        $uraianLembur = $hari . ' hari × ' . angka($faktorLembur, 2) . '× upah harian';
    }
    $items = [];
    if ($upahHarian > 0) {
        $items[] = ['nama' => 'Upah Harian Hari Minggu (' . $hari . ' hari)', 'nilai' => $upahHarian, 'kategori' => 'Minggu', 'sumber' => 'absensi'];
    }
    if ($lembur > 0) {
        $items[] = ['nama' => 'Upah Lembur Minggu (' . $hari . ' hari)', 'nilai' => $lembur, 'kategori' => 'Minggu', 'sumber' => 'absensi'];
    }
    return [
        'aktif' => true, 'hari' => $hari, 'menit' => $menit, 'jam' => $jam,
        'upah_harian' => $upahHarian, 'lembur' => $lembur, 'nilai' => $upahHarian + $lembur,
        'faktor_harian' => $faktorHarian, 'mode' => 'dua_upah', 'items' => $items,
        'uraian' => $hari . ' hari Minggu dikerjakan → upah harian ' . angka($faktorHarian, 2) . '× ' . rp($upah['per_hari'])
            . ' (' . rp($upahHarian) . ') + upah lembur minggu: ' . $uraianLembur . ' (' . rp($lembur) . ')',
        'rincian' => $rincian,
    ];
}

/**
 * Menghitung bonus yang diterima seorang karyawan pada satu periode.
 * Parameter bonus (nama, tipe, nilai, syarat) SEMUA berasal dari tabel bonus_parameter
 * yang dikelola di Pengaturan → Bonus, sehingga admin dapat menambah bonus baru.
 */
function bonus_hitung(array $kar, array $rekap, array $upah, string $periode): array
{
    $hasil = [];
    $dilewati = [];
    $jumlahMingguKalender = jumlah_minggu_periode($periode);
    $stKB = db()->prepare('SELECT aktif, nilai FROM karyawan_bonus WHERE karyawan_id = ? AND bonus_id = ?');
    foreach (db()->query('SELECT * FROM bonus_parameter WHERE aktif = 1 ORDER BY urutan, nama') as $b) {
        $stKB->execute([(int) $kar['id'], (int) $b['id']]);
        $kb = $stKB->fetch();
        if ($kb) {
            if ((int) $kb['aktif'] !== 1) {
                $dilewati[] = $b['nama'] . ': tidak diikutkan untuk karyawan ini';
                continue;
            }
            $nilai = (float) $kb['nilai'] >= 0 ? (float) $kb['nilai'] : (float) $b['nilai'];
            $sumberNilai = 'khusus karyawan ini';
        } elseif ((int) $b['berlaku_semua'] === 1) {
            $nilai = (float) $b['nilai'];
            $sumberNilai = 'pengaturan umum';
        } else {
            $dilewati[] = $b['nama'] . ': hanya untuk karyawan yang diikutkan';
            continue;
        }
        if ($nilai <= 0) {
            continue;
        }
        if ((int) $b['syarat_alpa_nol'] === 1 && (int) $rekap['alpa'] > 0) {
            $dilewati[] = $b['nama'] . ': tidak dibayar karena ada ' . (int) $rekap['alpa'] . ' hari alpa';
            continue;
        }
        if ((int) $b['syarat_tanpa_terlambat'] === 1 && (int) $rekap['menit_terlambat'] > 0) {
            $dilewati[] = $b['nama'] . ': tidak dibayar karena ada keterlambatan';
            continue;
        }
        $v = 0.0;
        $uraian = '';
        switch ((string) $b['tipe']) {
            case 'persen_pokok':
                $v = (float) $kar['gaji_pokok'] * $nilai / 100;
                $uraian = angka($nilai, 2) . '% × gaji pokok';
                break;
            case 'per_hari_hadir':
                $v = $nilai * (int) $rekap['hadir'];
                $uraian = $nilai . ' × ' . (int) $rekap['hadir'] . ' hari hadir';
                break;
            case 'per_minggu':
                $v = $nilai * $jumlahMingguKalender;
                $uraian = (string) $nilai . ' × ' . $jumlahMingguKalender . ' minggu dalam periode';
                break;
            case 'per_minggu_kerja':
                $v = $nilai * (int) $rekap['hadir_minggu'];
                $uraian = (string) $nilai . ' × ' . (int) $rekap['hadir_minggu'] . ' hari Minggu dikerjakan';
                break;
            default:
                $v = $nilai;
                $uraian = 'nominal tetap';
        }
        $v = payroll_bulat($v);
        if ($v <= 0) {
            continue;
        }
        $hasil[] = [
            'nama' => (string) $b['nama'],
            'nilai' => $v,
            'kategori' => 'Bonus',
            'sumber' => 'bonus:' . $b['kode'],
            'kena_pajak' => (int) $b['kena_pajak'] === 1,
            'uraian' => $uraian . ' (' . $sumberNilai . ')',
        ];
    }
    return ['bonus' => $hasil, 'dilewati' => $dilewati, 'jumlah_minggu' => $jumlahMingguKalender];
}

/** Daftar dasar hitung (tipe) bonus beserta labelnya. */
function tipe_bonus(): array
{
    return [
        'nominal' => 'Nominal tetap (rupiah per bulan)',
        'persen_pokok' => 'Persentase dari gaji pokok',
        'per_hari_hadir' => 'Rupiah × jumlah hari hadir',
        'per_minggu' => 'Rupiah × jumlah minggu dalam periode',
        'per_minggu_kerja' => 'Rupiah × jumlah hari Minggu yang dikerjakan',
    ];
}

/** Penjelasan rumus tiap dasar hitung bonus (dipakai di halaman Pengaturan). */
function tipe_bonus_rumus(string $tipe): string
{
    return [
        'nominal' => 'Bonus = nilai (tetap setiap bulan).',
        'persen_pokok' => 'Bonus = nilai% × gaji pokok karyawan.',
        'per_hari_hadir' => 'Bonus = nilai × jumlah hari hadir pada periode (termasuk hari Minggu yang dikerjakan).',
        'per_minggu' => 'Bonus = nilai × jumlah hari Minggu dalam periode (jumlah minggu kalender).',
        'per_minggu_kerja' => 'Bonus = nilai × jumlah hari Minggu yang benar-benar dikerjakan karyawan.',
    ][$tipe] ?? '-';
}

/**
 * Contoh angka upah untuk gaji pokok tertentu — dipakai halaman Pengaturan & kartu rumus.
 * Mengikuti rumus yang sedang berlaku: upah harian = pokok ÷ hari kerja,
 * upah lembur per jam = pokok ÷ pembagi lembur (173).
 */
function hitung_upah_contoh(float $gajiPokok = 5000000): array
{
    $hariKerja = conf('basis_hari_kerja', 'otomatis') === 'otomatis'
        ? (float) count(hari_kerja_bulan(date('Y-m'))['tanggal'])
        : max(1.0, conf_num('hari_kerja_per_bulan', 25));
    $pembagiLembur = max(1.0, conf_num('lembur_pembagi', 173));
    $jam = jam_kerja_per_hari_efektif();
    $perHari = $gajiPokok / $hariKerja;
    return [
        'gaji_pokok' => $gajiPokok,
        'hari_kerja' => $hariKerja,
        'per_hari' => $perHari,
        'per_jam' => $perHari / max(0.1, $jam),
        'per_jam_lembur' => $gajiPokok / $pembagiLembur,
        'tarif_minggu_jam' => conf_num('minggu_tarif_jam', 0),
    ];
}

/** Jumlah hari Minggu (= jumlah minggu) dalam satu periode. */
function jumlah_minggu_periode(string $periode): int
{
    $periode = periode_valid($periode);
    $jml = (int) date('t', strtotime($periode . '-01'));
    $n = 0;
    for ($d = 1; $d <= $jml; $d++) {
        if ((int) date('N', strtotime(sprintf('%s-%02d', $periode, $d))) === 7) {
            $n++;
        }
    }
    return max(1, $n);
}

/** Daftar panjar aktif seorang karyawan beserta sisa yang belum dipotong. */
function panjar_karyawan(int $karyawanId, bool $hanyaAktif = true): array
{
    $sql = 'SELECT * FROM panjar WHERE karyawan_id = ?' . ($hanyaAktif ? ' AND aktif = 1' : '') . ' ORDER BY tanggal, id';
    $st = db()->prepare($sql);
    $st->execute([$karyawanId]);
    return $st->fetchAll();
}

/** Total cicilan panjar yang sudah dipotong pada slip-sebelum periode tertentu. */
function panjar_sudah_dipotong(int $panjarId, string $sebelumPeriode): float
{
    $st = db()->prepare('SELECT COALESCE(SUM(i.nilai), 0)
                         FROM slip_item i JOIN slip_gaji s ON s.id = i.slip_id
                         WHERE i.sumber = ? AND s.periode < ?');
    $st->execute(['panjar:' . $panjarId, $sebelumPeriode]);
    return (float) $st->fetchColumn();
}

/**
 * Potongan panjar periode ini: sebesar cicilan, dibatasi sisa panjar yang belum lunas.
 * Sisa dihitung dari riwayat slip (bukan dari kolom saldo) supaya perhitungan bersifat
 * idempoten — menghitung ulang slip yang sama tidak mengurangi panjar dua kali.
 */
function panjar_potongan(int $karyawanId, string $periode): array
{
    if (!conf_bool('potongan_panjar_aktif', true)) {
        return ['items' => [], 'total' => 0.0, 'catatan' => ['Potongan panjar dimatikan di Pengaturan.']];
    }
    $items = [];
    $total = 0.0;
    $catatan = [];
    foreach (panjar_karyawan($karyawanId) as $pj) {
        $jumlah = (float) $pj['jumlah'];
        $cicilan = (float) $pj['cicilan_per_bulan'];
        if ($jumlah <= 0 || $cicilan <= 0) {
            continue;
        }
        $sudah = panjar_sudah_dipotong((int) $pj['id'], $periode);
        $sisa = max(0.0, $jumlah - $sudah);
        if ($sisa <= 0) {
            $catatan[] = 'Panjar ' . rp($jumlah) . ' (' . ($pj['keterangan'] ?: 'tanpa keterangan') . ') sudah lunas.';
            continue;
        }
        $nilai = payroll_bulat(min($cicilan, $sisa));
        $items[] = [
            'nama' => 'Potongan Panjar' . ($pj['keterangan'] !== '' ? ' — ' . $pj['keterangan'] : ''),
            'nilai' => $nilai,
            'kategori' => 'Panjar',
            'sumber' => 'panjar:' . $pj['id'],
            'uraian' => 'cicilan ' . rp($cicilan) . ' dari panjar ' . rp($jumlah)
                . ' (sisa sebelum bulan ini ' . rp($sisa) . ')',
        ];
        $total += $nilai;
        if ($nilai < $cicilan) {
            $catatan[] = 'Panjar ' . ($pj['keterangan'] ?: '') . ' dilunasi bulan ini (cicilan penuh ' . rp($cicilan) . ', sisa ' . rp($sisa) . ').';
        }
    }
    return ['items' => $items, 'total' => payroll_bulat($total), 'catatan' => $catatan];
}

/**
 * ★ MESIN UTAMA — Menghitung slip gaji satu karyawan untuk satu periode.
 * Semua parameter diambil dari tabel konfigurasi; tidak ada nilai tetap di kode.
 */
function hitung_slip(int $karyawanId, string $periode, array $opsi = []): array
{
    $periode = periode_valid($periode);
    $kar = karyawan_by_id($karyawanId);
    if (!$kar) {
        return ['ok' => false, 'error' => 'Karyawan tidak ditemukan.'];
    }
    $kal = hari_kerja_bulan($periode);
    $rekap = absensi_rekap($karyawanId, $periode);
    $upah = payroll_upah($kar, $kal['jumlah']);

    $pendapatan = [];
    $potongan = [];
    $catatan = [];
    $langkah = [];

    /* 1. Upah bulanan ------------------------------------------------------ */
    /* Dua cara (dipilih di Pengaturan → Upah Harian & Kehadiran):
       - proporsional_hadir (bawaan perusahaan ini):
             Upah Harian = Dasar ÷ hari kerja (mis. 25) ; dibayar × jumlah hari hadir Senin–Sabtu
             → hari tidak hadir otomatis tidak dibayar.
       - penuh: gaji pokok dibayar penuh, potongan absensi dihitung terpisah. */
    $modeUpah = conf('upah_mode', 'proporsional_hadir');
    $hariHadirKerja = (float) $rekap['hadir_kerja'];
    $faktorKehadiran = $upah['hari_kerja'] > 0 ? min(1.0, $hariHadirKerja / $upah['hari_kerja']) : 1.0;
    if ($modeUpah === 'proporsional_hadir') {
        $nilaiUpah = payroll_bulat($upah['per_hari'] * $hariHadirKerja);
        $pendapatan[] = [
            'nama' => 'Upah Harian (' . angka($hariHadirKerja) . ' hari × ' . rp(round($upah['per_hari'])) . ')',
            'nilai' => $nilaiUpah, 'kategori' => 'Tetap', 'sumber' => 'master_karyawan',
            'uraian' => 'Dasar ' . rp($upah['basis']) . ' ÷ ' . angka($upah['hari_kerja']) . ' hari kerja × ' . angka($hariHadirKerja) . ' hari hadir',
        ];
        $langkah[] = 'Upah harian = ' . rp($upah['basis']) . ' ÷ ' . angka($upah['hari_kerja']) . ' hari = ' . rp(round($upah['per_hari']))
            . ' → dibayar × ' . angka($hariHadirKerja) . ' hari hadir = ' . rp($nilaiUpah);
    } else {
        $pendapatan[] = ['nama' => 'Gaji Pokok', 'nilai' => payroll_bulat((float) $kar['gaji_pokok']), 'kategori' => 'Tetap', 'sumber' => 'master_karyawan'];
    }
    /* Tunjangan: penuh (bawaan) atau ikut proporsional kehadiran bila dinyalakan. */
    $ikutHadir = $modeUpah === 'proporsional_hadir' && conf_bool('tunjangan_ikut_kehadiran', false);
    foreach ([['tunjangan_tetap', 'Tunjangan Tetap', 'Tetap'], ['tunjangan_tidak_tetap', 'Tunjangan Tidak Tetap', 'Tidak Tetap']] as $t) {
        $nilaiTunj = (float) $kar[$t[0]];
        if ($nilaiTunj == 0) {
            continue;
        }
        $namaTunj = $t[1];
        if ($ikutHadir) {
            $nilaiTunj = payroll_bulat($nilaiTunj * $faktorKehadiran);
            $namaTunj .= ' (' . angka($hariHadirKerja) . '/' . angka($upah['hari_kerja']) . ' hari)';
        }
        $pendapatan[] = ['nama' => $namaTunj, 'nilai' => payroll_bulat($nilaiTunj), 'kategori' => $t[2], 'sumber' => 'master_karyawan'];
    }

    /* 2. Upah kerja HARI MINGGU — "2 upah" (upah harian + upah lembur minggu) ---- */
    $minggu = payroll_minggu($rekap, $upah);
    foreach ($minggu['items'] as $mi) {
        $pendapatan[] = $mi;
    }
    if ($minggu['nilai'] > 0) {
        $langkah[] = 'Kerja hari Minggu: ' . $minggu['uraian'];
    }

    /* 3. Upah lembur hari kerja (Senin–Sabtu) & hari libur nasional -------- */
    $lembur = ['nilai' => 0.0, 'menit' => 0, 'jam' => 0.0, 'rincian' => []];
    $lemburLibur = ['nilai' => 0.0, 'menit' => 0, 'jam' => 0.0, 'rincian' => []];
    $barisKerja = [];
    $barisLibur = [];
    foreach ($rekap['rincian_lembur'] as $r) {
        if (!empty($r['minggu'])) {
            continue; /* hari Minggu memakai aturan 2 upah di atas */
        }
        if (!empty($r['hari_libur'])) {
            $barisLibur[] = $r;
        } else {
            $barisKerja[] = $r;
        }
    }
    if (conf_bool('lembur_aktif', true)) {
        if ($barisKerja) {
            $lembur = payroll_lembur($barisKerja, $upah['per_jam_lembur'], 'kerja');
            if ($lembur['nilai'] > 0) {
                $pendapatan[] = [
                    'nama' => 'Upah Lembur Hari Kerja (' . angka($lembur['jam'], 2) . ' jam)',
                    'nilai' => $lembur['nilai'], 'kategori' => 'Lembur', 'sumber' => 'absensi',
                ];
                $langkah[] = 'Lembur hari kerja = ' . angka($lembur['jam'], 2) . ' jam × ('
                    . rp($upah['basis']) . ' ÷ ' . angka($upah['pembagi_lembur']) . ' = ' . rp(round($upah['per_jam_lembur'])) . ' per jam)'
                    . (conf_bool('lembur_pakai_faktor', false)
                        ? ' dengan faktor ' . angka(conf_num('lembur_faktor_pertama', 1.5), 2) . '× (jam pertama) / ' . angka(conf_num('lembur_faktor_berikutnya', 2), 2) . '× (berikutnya)'
                        : ' tanpa faktor pengali');
            }
        }
        if ($barisLibur) {
            $lemburLibur = payroll_lembur($barisLibur, $upah['per_jam_lembur'], 'libur');
            if ($lemburLibur['nilai'] > 0) {
                $pendapatan[] = [
                    'nama' => 'Upah Lembur Hari Libur (' . angka($lemburLibur['jam'], 2) . ' jam)',
                    'nilai' => $lemburLibur['nilai'], 'kategori' => 'Lembur', 'sumber' => 'absensi',
                ];
                $langkah[] = 'Lembur hari libur nasional = ' . angka($lemburLibur['jam'], 2) . ' jam × ' . rp(round($upah['per_jam_lembur']))
                    . (conf_bool('lembur_pakai_faktor', false) ? ' × ' . angka(conf_num('lembur_faktor_hari_libur', 2), 2) . '×' : '');
            }
        }
    }

    /* 4. BONUS (parameter dinamis dari Pengaturan → Bonus) ----------------- */
    $bonusHasil = bonus_hitung($kar, $rekap, $upah, $periode);
    foreach ($bonusHasil['bonus'] as $b) {
        $pendapatan[] = [
            'nama' => $b['nama'], 'nilai' => $b['nilai'], 'kategori' => 'Bonus',
            'sumber' => $b['sumber'], 'kena_pajak' => $b['kena_pajak'], 'uraian' => $b['uraian'],
        ];
    }
    foreach ($bonusHasil['dilewati'] as $d) {
        $catatan[] = 'Bonus tidak dibayar — ' . $d;
    }

    /* 5. Komponen tambahan per karyawan (tunjangan & potongan manual) ------ */
    $komponen = komponen_karyawan($karyawanId);
    foreach ($komponen as $k) {
        $berlaku = (int) $k['berulang'] === 1 || (string) $k['periode'] === $periode;
        if (!$berlaku) {
            continue;
        }
        $nilai = (string) $k['tipe'] === 'persen'
            ? payroll_bulat((float) $kar['gaji_pokok'] * (float) $k['nilai'] / 100)
            : payroll_bulat((float) $k['nilai']);
        if ($nilai === 0.0) {
            continue;
        }
        $item = ['nama' => (string) $k['nama'], 'nilai' => $nilai, 'kategori' => (string) ($k['kategori'] ?: 'Lainnya'), 'sumber' => 'komponen'];
        if ($k['jenis'] === 'PENDAPATAN') {
            $pendapatan[] = $item;
        } elseif (conf_bool('potongan_lain_aktif', true)) {
            $potongan[] = $item;
        } else {
            $catatan[] = 'Potongan tambahan "' . $k['nama'] . '" dilewati (potongan tambahan dimatikan di Pengaturan).';
        }
    }

    /* 6. BPJS Ketenagakerjaan & Kesehatan --------------------------------- */
    $bpjs = ['karyawan' => 0.0, 'perusahaan' => 0.0, 'dasar' => 0.0];
    if (conf_bool('bpjs_aktif', true)) {
        $dasarBpjs = conf('bpjs_dasar', 'gaji_pokok') === 'gaji_pokok_tunjangan'
            ? (float) $kar['gaji_pokok'] + (float) $kar['tunjangan_tetap']
            : (float) $kar['gaji_pokok'];
        $maksDasar = conf_num('bpjs_maks_dasar', 0);
        if ($maksDasar > 0) {
            $dasarBpjs = min($dasarBpjs, $maksDasar);
        }
        $pKaryawan = conf_num('bpjs_tk_karyawan_persen', 0) + conf_num('bpjs_kes_karyawan_persen', 0);
        $pPerusahaan = conf_num('bpjs_tk_perusahaan_persen', 0) + conf_num('bpjs_kes_perusahaan_persen', 0);
        $bpjs = [
            'dasar' => $dasarBpjs,
            'karyawan' => payroll_bulat($dasarBpjs * $pKaryawan / 100),
            'perusahaan' => payroll_bulat($dasarBpjs * $pPerusahaan / 100),
            'persen_karyawan' => $pKaryawan,
            'persen_perusahaan' => $pPerusahaan,
        ];
        if ($bpjs['karyawan'] > 0) {
            $potongan[] = ['nama' => 'BPJS Karyawan (' . angka($pKaryawan, 2) . '%)', 'nilai' => $bpjs['karyawan'], 'kategori' => 'BPJS', 'sumber' => 'bpjs'];
        }
        if (conf_bool('bpjs_tampil_tunjangan_perusahaan', true) && $bpjs['perusahaan'] > 0) {
            $pendapatan[] = ['nama' => 'Tunjangan BPJS Perusahaan (' . angka($pPerusahaan, 2) . '%)', 'nilai' => $bpjs['perusahaan'], 'kategori' => 'BPJS Perusahaan', 'sumber' => 'bpjs'];
        }
    }

    /* 7. Potongan absensi (semua patuh saklar di Pengaturan → Potongan) --- */
    $denda = ['nilai' => 0.0, 'rincian' => [], 'toleransi' => conf_int('absensi_toleransi_menit', 15), 'mode' => conf('absensi_denda_mode', 'tidak_ada')];
    $potAlpa = ['nilai' => 0.0, 'uraian' => ''];
    $potIzin = ['nilai' => 0.0, 'uraian' => ''];
    $potSakit = ['nilai' => 0.0, 'uraian' => ''];
    if (conf_bool('potongan_denda_aktif', false)) {
        $denda = payroll_denda($rekap, $upah);
        if ($denda['nilai'] > 0) {
            $potongan[] = ['nama' => 'Denda Keterlambatan', 'nilai' => $denda['nilai'], 'kategori' => 'Absensi', 'sumber' => 'absensi'];
        }
    } elseif ((int) $rekap['menit_terlambat'] > 0) {
        $catatan[] = 'Ada keterlambatan ' . durasi_indo((int) $rekap['menit_terlambat']) . ', tetapi denda keterlambatan dimatikan di Pengaturan.';
    }
    /* Alpa: bila upah sudah dihitung proporsional kehadiran, hari alpa OTOMATIS tidak
       dibayar — potongan alpa tidak dihitung lagi agar tidak dobel (kecuali pemilik
       sengaja menyalakan "Tetap potong alpa walau upah sudah proporsional"). */
    $alpaDobel = $modeUpah === 'proporsional_hadir' && !conf_bool('alpa_potong_saat_proporsional', false);
    if (conf_bool('potongan_alpa_aktif', false) && !$alpaDobel) {
        $potAlpa = payroll_potongan_status('alpa', (int) $rekap['alpa'], $upah);
        if ($potAlpa['nilai'] > 0) {
            $potongan[] = ['nama' => 'Potongan Alpa (' . (int) $rekap['alpa'] . ' hari)', 'nilai' => $potAlpa['nilai'], 'kategori' => 'Absensi', 'sumber' => 'absensi'];
        }
    } elseif ((int) $rekap['alpa'] > 0) {
        $catatan[] = $alpaDobel
            ? 'Ada ' . (int) $rekap['alpa'] . ' hari alpa — tidak ada potongan tambahan karena upah sudah dibayar proporsional kehadiran.'
            : 'Ada ' . (int) $rekap['alpa'] . ' hari alpa, tetapi potongan alpa dimatikan di Pengaturan.';
    }
    if (conf_bool('potongan_izin_aktif', false)) {
        $potIzin = payroll_potongan_status('izin', (int) $rekap['izin'], $upah);
        if ($potIzin['nilai'] > 0) {
            $potongan[] = ['nama' => 'Potongan Izin (' . (int) $rekap['izin'] . ' hari)', 'nilai' => $potIzin['nilai'], 'kategori' => 'Absensi', 'sumber' => 'absensi'];
        }
    }
    if (conf_bool('potongan_sakit_aktif', false)) {
        $potSakit = payroll_potongan_status('sakit', (int) $rekap['sakit'], $upah);
        if ($potSakit['nilai'] > 0) {
            $potongan[] = ['nama' => 'Potongan Sakit (' . (int) $rekap['sakit'] . ' hari)', 'nilai' => $potSakit['nilai'], 'kategori' => 'Absensi', 'sumber' => 'absensi'];
        }
    }

    /* 8. Potongan PANJAR (cicilan, dihitung dari sisa yang belum dipotong) - */
    $panjar = panjar_potongan($karyawanId, $periode);
    foreach ($panjar['items'] as $pj) {
        $potongan[] = [
            'nama' => $pj['nama'], 'nilai' => $pj['nilai'], 'kategori' => 'Panjar',
            'sumber' => $pj['sumber'], 'uraian' => $pj['uraian'],
        ];
    }
    foreach ($panjar['catatan'] as $c) {
        $catatan[] = $c;
    }

    /* 9. Dasar pajak: hanya item yang ditandai kena pajak ------------------ */
    $brutoPajak = 0.0;
    foreach ($pendapatan as $p) {
        if (($p['kena_pajak'] ?? true) === false) {
            continue;
        }
        if (!conf_bool('pajak_bpjs_perusahaan_kena_pajak', true)
            && $p['sumber'] === 'bpjs' && str_starts_with((string) $p['nama'], 'Tunjangan BPJS')) {
            continue;
        }
        $brutoPajak += $p['nilai'];
    }

    /* 7. PPh 21 (metode per karyawan) ------------------------------------ */
    $pajak = ['nilai' => 0.0, 'metode' => 'tidak_ada', 'uraian' => '', 'pkp' => 0.0, 'ptkp' => 0.0];
    $metode = (string) $kar['metode_pajak'];
    if (!isset(metode_pajak()[$metode])) {
        $metode = conf('pajak_metode_default', 'progresif');
    }
    if (conf_bool('pajak_aktif', false) && $metode !== 'tidak_ada') {
        /* Bruto untuk pajak = pendapatan yang ditandai kena pajak (bonus non-pajak dikecualikan). */
        $bruto = $brutoPajak;
        if ($metode === 'tunggal') {
            $dasar = $bruto;
            if (conf('pajak_tunggal_dasar', 'bruto') === 'bruto_setelah_bpjs') {
                $dasar -= $bpjs['karyawan'];
            }
            $persen = conf_num('pajak_tunggal_persen', 0);
            $pajak['nilai'] = payroll_bulat(max(0, $dasar) * $persen / 100);
            $pajak['metode'] = 'tunggal';
            $pajak['uraian'] = 'Pajak tunggal = ' . angka($persen, 2) . '% × ' . rp(max(0, $dasar));
        } else {
            $bulanSetahun = max(1, conf_int('pajak_bulan_setahun', 12));
            $brutoSetahun = $bruto * $bulanSetahun;
            $persenJabatan = conf_num('pajak_biaya_jabatan_persen', 0);
            $maksBiaya = conf_num('pajak_biaya_jabatan_maks_bulan', 0) * $bulanSetahun;
            $biayaJabatan = $brutoSetahun * $persenJabatan / 100;
            if ($maksBiaya > 0) {
                $biayaJabatan = min($biayaJabatan, $maksBiaya);
            }
            $bpjsSetahun = conf_bool('pajak_bpjs_pengurang', true) ? $bpjs['karyawan'] * $bulanSetahun : 0.0;
            $ptkp = ptkp_setahun((string) $kar['ptkp_kode']);
            $pkpAwal = max(0, $brutoSetahun - $biayaJabatan - $bpjsSetahun - $ptkp);
            $bulatPkp = max(1, conf_num('pajak_bulat_pkp', 1000));
            $pkp = floor($pkpAwal / $bulatPkp) * $bulatPkp;
            $pajakSetahun = 0.0;
            $lapis = [];
            foreach (pph21_layers() as $l) {
                $bawah = (float) $l['batas_bawah'];
                $atas = (float) $l['batas_atas'];
                if ($atas <= 0) {
                    $atas = PHP_FLOAT_MAX;
                }
                if ($pkp <= $bawah) {
                    continue;
                }
                $kena = min($pkp, $atas) - $bawah;
                if ($kena <= 0) {
                    continue;
                }
                $nilaiLapis = $kena * (float) $l['tarif_persen'] / 100;
                $pajakSetahun += $nilaiLapis;
                $lapis[] = [
                    'urutan' => (int) $l['urutan'], 'tarif' => (float) $l['tarif_persen'],
                    'kena' => $kena, 'nilai' => payroll_bulat($nilaiLapis),
                ];
            }
            $pajakBulan = $pajakSetahun / $bulanSetahun;
            if ($pajakBulan > 0 && trim((string) $kar['npwp']) === '') {
                $tambahan = conf_num('pajak_tanpa_npwp_tambahan_persen', 0);
                if ($tambahan > 0) {
                    $pajakBulan *= (1 + $tambahan / 100);
                    $catatan[] = 'Karyawan belum memiliki NPWP → tarif pajak ditambah ' . angka($tambahan, 2) . '%.';
                }
            }
            $pajak = [
                'nilai' => payroll_bulat(max(0, $pajakBulan)),
                'metode' => 'progresif',
                'bruto_setahun' => $brutoSetahun,
                'biaya_jabatan' => $biayaJabatan,
                'bpjs_pengurang' => $bpjsSetahun,
                'ptkp' => $ptkp,
                'ptkp_kode' => (string) $kar['ptkp_kode'],
                'pkp' => $pkp,
                'pajak_setahun' => $pajakSetahun,
                'lapis' => $lapis,
                'uraian' => 'Bruto setahun ' . rp($brutoSetahun) . ' − biaya jabatan ' . rp($biayaJabatan)
                    . ' − BPJS karyawan ' . rp($bpjsSetahun) . ' − PTKP ' . rp($ptkp) . ' = PKP ' . rp($pkp)
                    . ' → pajak setahun ' . rp($pajakSetahun) . ' ÷ ' . $bulanSetahun . ' bulan',
            ];
        }
        if ($pajak['nilai'] > 0) {
            $potongan[] = ['nama' => 'PPh 21 (' . ($metode === 'progresif' ? 'progresif' : 'tunggal') . ')', 'nilai' => $pajak['nilai'], 'kategori' => 'Pajak', 'sumber' => 'pajak'];
        }
    }

    /* 8. Total akhir ------------------------------------------------------- */
    $totalPendapatan = 0.0;
    foreach ($pendapatan as $p) {
        $totalPendapatan += $p['nilai'];
    }
    $totalPotongan = 0.0;
    foreach ($potongan as $p) {
        $totalPotongan += $p['nilai'];
    }
    $totalBonus = 0.0;
    foreach ($pendapatan as $p) {
        if ($p['kategori'] === 'Bonus') {
            $totalBonus += $p['nilai'];
        }
    }
    $takeHome = payroll_bulat(max(0, $totalPendapatan - $totalPotongan));
    if ($totalPotongan > $totalPendapatan) {
        $catatan[] = 'Total potongan melebihi total pendapatan. Gaji bersih ditetapkan 0 (selisih ' . rp($totalPotongan - $totalPendapatan) . ' menjadi catatan HRD).';
    }

    return [
        'ok' => true,
        'karyawan' => $kar,
        'periode' => $periode,
        'kalender' => $kal,
        'rekap' => $rekap,
        'upah' => $upah,
        'lembur' => $lembur,
        'lembur_libur' => $lemburLibur,
        'minggu' => $minggu,
        'upah_mode' => $modeUpah,
        'hari_hadir_kerja' => $hariHadirKerja,
        'bonus' => $bonusHasil,
        'total_bonus' => payroll_bulat($totalBonus),
        'panjar' => $panjar,
        'bruto_pajak' => payroll_bulat($brutoPajak),
        'bpjs' => $bpjs,
        'denda' => $denda,
        'pajak' => $pajak,
        'potongan_alpa' => $potAlpa,
        'potongan_izin' => $potIzin,
        'potongan_sakit' => $potSakit,
        'pendapatan' => $pendapatan,
        'potongan' => $potongan,
        'total_pendapatan' => payroll_bulat($totalPendapatan),
        'total_potongan' => payroll_bulat($totalPotongan),
        'take_home_pay' => $takeHome,
        'langkah' => $langkah,
        'catatan' => $catatan,
    ];
}

/** Nomor slip: PREFIX/YYYY-MM/0001 — urutan diambil dari nomor terbesar yang sudah ada
 *  supaya nomor tidak pernah dipakai dua kali walau ada slip yang dihapus. */
function slip_nomor(string $periode, int $urut = 0): string
{
    $prefix = conf('slip_kode_prefix', 'SLIP');
    if ($urut <= 0) {
        $st = db()->prepare('SELECT nomor FROM slip_gaji WHERE periode = ? AND nomor <> ""');
        $st->execute([$periode]);
        $maks = 0;
        foreach ($st as $r) {
            if (preg_match('/\/(\d+)$/', (string) $r['nomor'], $m)) {
                $maks = max($maks, (int) $m[1]);
            }
        }
        $urut = $maks + 1;
    }
    return $prefix . '/' . $periode . '/' . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);
}

/** Menyimpan hasil hitung menjadi slip (snapshot item, tidak berubah walau setelan diubah). */
function slip_simpan(array $hasil, string $oleh, string $status = 'DRAFT'): int
{
    $kar = $hasil['karyawan'];
    $periode = $hasil['periode'];
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $st = $pdo->prepare('SELECT id FROM slip_gaji WHERE karyawan_id = ? AND periode = ?');
        $st->execute([(int) $kar['id'], $periode]);
        $id = (int) ($st->fetchColumn() ?: 0);
        $data = [
            'karyawan_id' => (int) $kar['id'],
            'periode' => $periode,
            'tanggal_proses' => date('Y-m-d'),
            'jabatan' => (string) $kar['jabatan'],
            'divisi' => (string) ($kar['divisi_nama'] ?? ''),
            'metode_pajak' => (string) $kar['metode_pajak'],
            'hari_kerja' => (float) $hasil['kalender']['jumlah'],
            'hari_hadir' => (float) $hasil['rekap']['hadir'],
            'hari_alpa' => (float) $hasil['rekap']['alpa'],
            'hari_izin' => (float) $hasil['rekap']['izin'],
            'hari_sakit' => (float) $hasil['rekap']['sakit'],
            'hari_cuti' => (float) $hasil['rekap']['cuti'],
            'total_menit_terlambat' => (int) $hasil['rekap']['menit_terlambat'],
            'total_menit_lembur' => (int) $hasil['lembur']['menit'] + (int) $hasil['lembur_libur']['menit'],
            'hari_minggu' => (float) ($hasil['minggu']['hari'] ?? 0),
            'upah_minggu' => (float) ($hasil['minggu']['nilai'] ?? 0),
            'total_bonus' => (float) ($hasil['total_bonus'] ?? 0),
            'total_panjar' => (float) ($hasil['panjar']['total'] ?? 0),
            'upah_per_jam' => (float) $hasil['upah']['per_jam_lembur'],
            'upah_harian' => (float) $hasil['upah']['per_hari'],
            'total_pendapatan' => (float) $hasil['total_pendapatan'],
            'total_potongan' => (float) $hasil['total_potongan'],
            'take_home_pay' => (float) $hasil['take_home_pay'],
            'status' => $status,
            'dibuat_oleh' => $oleh,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $par = array_values($data);
            $par[] = $id;
            $pdo->prepare("UPDATE slip_gaji SET {$set} WHERE id = ?")->execute($par);
        } else {
            $data['nomor'] = slip_nomor($periode);
            $data['created_at'] = now();
            $pdo->prepare('INSERT INTO slip_gaji (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')
                ->execute(array_values($data));
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM slip_item WHERE slip_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO slip_item (slip_id, jenis, kategori, nama, nilai, sumber, urutan) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $u = 1;
        foreach ($hasil['pendapatan'] as $p) {
            $ins->execute([$id, 'PENDAPATAN', (string) ($p['kategori'] ?? ''), (string) $p['nama'], (float) $p['nilai'], (string) ($p['sumber'] ?? ''), $u++]);
        }
        $u = 1;
        foreach ($hasil['potongan'] as $p) {
            $ins->execute([$id, 'POTONGAN', (string) ($p['kategori'] ?? ''), (string) $p['nama'], (float) $p['nilai'], (string) ($p['sumber'] ?? ''), $u++]);
        }
        $pdo->exec('COMMIT');
        return $id;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function slip_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT s.*, k.nama, k.nip, k.email, k.bank, k.no_rekening, k.ptkp_kode, k.tunjangan_tetap, k.tunjangan_tidak_tetap, k.gaji_pokok, k.jabatan AS jabatan_kini
                         FROM slip_gaji s JOIN karyawan k ON k.id = s.karyawan_id WHERE s.id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Item slip dikelompokkan untuk tampilan slip (Upah, Bonus, Tunjangan, Potongan). */
function slip_item_kelompok(int $slipId): array
{
    $kelompok = ['Tetap' => [], 'Minggu' => [], 'Lembur' => [], 'Bonus' => [], 'Lainnya' => []];
    foreach (slip_item($slipId, 'PENDAPATAN') as $i) {
        $k = (string) $i['kategori'];
        $kelompok[isset($kelompok[$k]) ? $k : 'Lainnya'][] = $i;
    }
    return $kelompok;
}

function slip_item(int $slipId, string $jenis = ''): array
{
    $sql = 'SELECT * FROM slip_item WHERE slip_id = ?' . ($jenis !== '' ? ' AND jenis = ?' : '') . ' ORDER BY urutan';
    $st = db()->prepare($sql);
    $st->execute($jenis !== '' ? [$slipId, $jenis] : [$slipId]);
    return $st->fetchAll();
}

function slip_daftar(string $periode, string $divisi = '', string $status = ''): array
{
    $sql = 'SELECT s.*, k.nama, k.nip, k.aktif FROM slip_gaji s JOIN karyawan k ON k.id = s.karyawan_id WHERE s.periode = ?';
    $par = [$periode];
    if ($divisi !== '') {
        $sql .= ' AND k.divisi_id = ?';
        $par[] = (int) $divisi;
    }
    if ($status !== '') {
        $sql .= ' AND s.status = ?';
        $par[] = $status;
    }
    $sql .= ' ORDER BY k.nama';
    $st = db()->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function slip_hapus(int $id): void
{
    db()->prepare('DELETE FROM slip_gaji WHERE id = ?')->execute([$id]);
}

/* ------------------------------------------------------------------ */
/* Migrasi aturan perusahaan (sekali saja, dijaga flag versi_aturan)   */
/* ------------------------------------------------------------------ */

/**
 * Versi aturan perusahaan yang sedang berlaku.
 * Naikkan angka ini bila aturan baku berubah lagi, lalu tambahkan langkahnya
 * di migrasi_aturan() — dengan begitu database yang sudah jalan ikut menyesuaikan
 * tanpa perlu dihapus.
 *
 * Riwayat: 1 = bawaan awal
 *          2 = jam kerja bersegmen 10 jam, aturan Minggu "2 upah", bonus dinamis, potongan hanya panjar
 *          3 = melengkapi data panjar contoh
 *          4 = RUMUS UPAH BARU: upah harian = Gaji Pokok ÷ hari kerja × jumlah hadir (Senin–Sabtu),
 *              upah hari Minggu = jam kerja × tarif per jam khusus Minggu, upah lembur per jam
 *              = Gaji Pokok ÷ 173 (tanpa faktor pengali).
 *          5 = jumlah hari kerja dihitung OTOMATIS hanya dari Senin–Sabtu (Minggu selalu
 *              dikecualikan & berdiri sendiri sebagai lembur minggu/upah per jam custom).
 */
const VERSI_ATURAN = '5';

/** Apakah isi database masih sepenuhnya data contoh (belum ada data asli)? */
function masih_demo_saja(): bool
{
    $absensiNyata = (int) db()->query('SELECT COUNT(*) FROM absensi WHERE dibuat_oleh <> "demo"')->fetchColumn();
    $slipNyata = (int) db()->query('SELECT COUNT(*) FROM slip_gaji WHERE dibuat_oleh <> "demo"')->fetchColumn();
    return $absensiNyata === 0 && $slipNyata === 0;
}

/**
 * Melengkapi data contoh: penugasan bonus per karyawan & panjar contoh.
 * Aman dijalankan berulang (hanya mengisi bila tabelnya masih kosong) dan
 * TIDAK menyentuh apa pun begitu ada data asli. Dipakai untuk database baru
 * maupun database yang sudah jalan.
 */
function demo_pelengkap(): void
{
    if (!masih_demo_saja()) {
        return;
    }
    $idKaryawan = [];
    foreach (db()->query('SELECT id, nip FROM karyawan') as $r) {
        $idKaryawan[$r['nip']] = (int) $r['id'];
    }
    if (!$idKaryawan) {
        return;
    }

    /* Bonus Insentif Minggu: hanya untuk karyawan Operasional, nilainya berbeda. */
    $idBonusInsentif = (int) db()->query('SELECT id FROM bonus_parameter WHERE kode = "bonus_insentif_minggu"')->fetchColumn();
    if ($idBonusInsentif > 0 && (int) db()->query('SELECT COUNT(*) FROM karyawan_bonus')->fetchColumn() === 0) {
        $ins = db()->prepare('INSERT OR REPLACE INTO karyawan_bonus (karyawan_id, bonus_id, aktif, nilai) VALUES (?, ?, 1, ?)');
        foreach (db()->query('SELECT k.id FROM karyawan k JOIN divisi d ON d.id = k.divisi_id WHERE d.nama = "Operasional"') as $r) {
            $ins->execute([(int) $r['id'], $idBonusInsentif, 250000]);
        }
    }

    /* Panjar contoh (uang muka) — dipotong mencicil tiap bulan sampai lunas. */
    if ((int) db()->query('SELECT COUNT(*) FROM panjar')->fetchColumn() === 0) {
        $ins = db()->prepare('INSERT INTO panjar (karyawan_id, tanggal, jumlah, cicilan_per_bulan, keterangan, aktif, created_at)
                              VALUES (?, ?, ?, ?, ?, 1, ?)');
        $panjar = [
            ['NSS-003', 2400000, 400000, 'Panjar perbaikan sepeda motor'],
            ['NSS-006', 1500000, 250000, 'Panjar keperluan keluarga'],
            ['NSS-007', 1000000, 500000, 'Panjar tunjangan transport awal'],
        ];
        foreach ($panjar as $pj) {
            if (isset($idKaryawan[$pj[0]])) {
                $ins->execute([$idKaryawan[$pj[0]], date('Y-m-05'), $pj[1], $pj[2], $pj[3], now()]);
            }
        }
    }
}

/**
 * Menerapkan aturan kerja perusahaan ini pada database yang sudah ada:
 *   - jam kerja bersegmen 08:00–11:00 dan 13:00–20:00 (10 jam/hari, istirahat tidak dibayar)
 *   - aturan hari Minggu: 2 upah (upah harian + upah lembur minggu)
 *   - potongan: hanya PANJAR yang aktif; denda/alpa/izin/sakit/BPJS/PPh 21 dimatikan
 *     (semuanya tetap bisa dinyalakan kembali dari Pengaturan → Potongan)
 *
 * Data contoh (absensi & slip) dihitung ulang HANYA bila isi database memang masih
 * data contoh. Bila sudah ada data asli, migrasi hanya mengubah setelan — tidak
 * menghapus apa pun. Semua dikerjakan dalam satu transaksi.
 */
function migrasi_aturan(): void
{
    static $berjalan = false;
    if ($berjalan || conf('versi_aturan', '1') === VERSI_ATURAN) {
        return;
    }
    $berjalan = true;
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        /* Cek ulang di dalam kunci supaya dua permintaan bersamaan tidak berebut. */
        $versi = (string) $pdo->query('SELECT nilai FROM payroll_configurations WHERE kunci = "versi_aturan"')->fetchColumn();
        if ($versi === VERSI_ATURAN) {
            $pdo->exec('COMMIT');
            return;
        }
        $nilai = [
            'jam_kerja_mode' => 'segmen',
            'jam_kerja_segmen' => JAM_KERJA_SEGMEN_BAWAAN,
            'jam_kerja_per_hari' => '10',
            'absensi_jam_masuk_standar' => '08:00',
            'absensi_jam_pulang_standar' => '20:00',
            'minggu_aktif' => '1',
            'minggu_upah_harian_faktor' => '1',
            'minggu_lembur_mode' => 'faktor_upah_harian',
            'minggu_lembur_faktor_harian' => '1',
            'minggu_lembur_nominal' => '0',
            'minggu_lembur_faktor_jam' => '2',
            'minggu_minimal_menit' => '240',
            'potongan_denda_aktif' => '0',
            'potongan_alpa_aktif' => '0',
            'potongan_izin_aktif' => '0',
            'potongan_sakit_aktif' => '0',
            'potongan_panjar_aktif' => '1',
            'potongan_lain_aktif' => '1',
            'absensi_denda_mode' => 'tidak_ada',
            'absensi_alpa_mode' => 'tidak_ada',
            'absensi_izin_mode' => 'tidak_ada',
            'absensi_sakit_mode' => 'tidak_ada',
            'bpjs_aktif' => '0',
            'pajak_aktif' => '0',
        ];
        /* --- Rumus upah versi 4 & 5 (perusahaan ini) --- */
        $nilai4 = [
            'basis_hari_kerja' => 'otomatis',
            'hari_kerja_kurangi_libur' => '1',
            'hari_kerja_per_bulan' => '25',
            'basis_upah_per_jam' => 'gaji_pokok',
            'upah_mode' => 'proporsional_hadir',
            'tunjangan_ikut_kehadiran' => '0',
            'alpa_potong_saat_proporsional' => '0',
            'lembur_pembagi' => '173',
            'lembur_pakai_faktor' => '0',
            'minggu_mode' => 'tarif_jam',
            'minggu_tarif_jam' => '20000',
            'minggu_aktif' => '1',
            'minggu_minimal_menit' => '240',
        ];
        /* Selalu diterapkan (versi 3 → 4 → 5) supaya database yang sudah jalan ikut menyesuaikan. */
        if ($versi !== VERSI_ATURAN) {
            $nilai = array_merge($nilai, $nilai4);
        }
        $st = $pdo->prepare('INSERT INTO payroll_configurations (kunci, nilai, updated_at) VALUES (?, ?, ?)
                             ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai, updated_at = excluded.updated_at');
        foreach ($nilai as $k => $v) {
            $st->execute([$k, $v, now()]);
        }
        $pdo->prepare('INSERT INTO payroll_configurations (kunci, nilai, tipe, kelompok, label, keterangan, updated_at)
                       VALUES ("versi_aturan", ?, "teks", "umum", "Versi aturan", "Penanda migrasi aturan perusahaan", ?)
                       ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai, updated_at = excluded.updated_at')
            ->execute([VERSI_ATURAN, now()]);

        /* Potongan lama yang berasal dari komponen manual (kasbon/koperasi) dihapus
           supaya "hanya panjar" benar-benar berlaku. Hanya menyentuh data contoh. */
        $pdo->exec('DELETE FROM komponen_gaji WHERE jenis = "POTONGAN"');

        /* Hitung ulang data contoh bila isinya masih sepenuhnya data contoh. */
        $adaAbsensiNyata = (int) $pdo->query('SELECT COUNT(*) FROM absensi WHERE dibuat_oleh <> "demo"')->fetchColumn();
        $adaSlipNyata = (int) $pdo->query('SELECT COUNT(*) FROM slip_gaji WHERE dibuat_oleh <> "demo"')->fetchColumn();
        if ($adaAbsensiNyata === 0 && $adaSlipNyata === 0) {
            $pdo->exec('DELETE FROM absensi WHERE dibuat_oleh = "demo"');
            $pdo->exec('DELETE FROM slip_item');
            $pdo->exec('DELETE FROM slip_gaji');
            /* Slip contoh dihitung ulang supaya aturan baru (mis. potongan panjar)
               ikut terlihat. demo_pelengkap() yang mengisi panjar & penugasan bonus. */
            $pdo->prepare('UPDATE payroll_configurations SET nilai = "0" WHERE kunci IN ("demo_absensi_dibuat", "demo_slip_dibuat")')->execute();
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    conf_all(true);
    $berjalan = false;
}

/* ------------------------------------------------------------------ */
/* Data contoh (sekali saja, agar aplikasi langsung bisa dicoba)       */
/* ------------------------------------------------------------------ */

/**
 * Mengisi absensi & slip contoh untuk bulan lalu (final) dan bulan ini (draft).
 * Dijalankan sekali saja — dijaga oleh flag di payroll_configurations.
 */
function demo_siapkan(): void
{
    migrasi_aturan();
    /* Setelah pemilik membersihkan data contoh (Pengaturan → Data & Reset),
       data contoh tidak boleh dibuat lagi. Migrasi tetap dijalankan di atas. */
    if (conf_bool('demo_nonaktif', false)) {
        return;
    }
    /* Panjar & penugasan bonus contoh harus ada SEBELUM slip contoh dihitung,
       supaya potongan panjar ikut terlihat pada slip. */
    if (conf('demo_slip_dibuat', '0') !== '1') {
        demo_pelengkap();
    }
    if (conf('demo_absensi_dibuat', '0') !== '1') {
        $idKar = db()->query('SELECT id FROM karyawan WHERE aktif = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        if ($idKar) {
            $bulan = [date('Y-m', strtotime('first day of last month')), date('Y-m')];
            $hariIni = date('Y-m-d');
            $ins = db()->prepare('INSERT OR IGNORE INTO absensi
                (karyawan_id, tanggal, jam_masuk, jam_pulang, status, menit_terlambat, menit_lembur, lembur_disetujui, keterangan, dibuat_oleh, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            db()->exec('BEGIN IMMEDIATE');
            try {
                $urutan = 0;
                foreach ($bulan as $periode) {
                    $kal = hari_kerja_bulan($periode);
                    /* Hari kerja + hari MINGGU (Minggu dijadwalkan kerja sebagian karyawan,
                       supaya aturan "2 upah" ikut terlihat pada data contoh). */
                    $semuaHari = $kal['tanggal'];
                    $jmlHari = (int) date('t', strtotime($periode . '-01'));
                    for ($d = 1; $d <= $jmlHari; $d++) {
                        $t = sprintf('%s-%02d', $periode, $d);
                        if ((int) date('N', strtotime($t)) === 7) {
                            $semuaHari[] = $t;
                        }
                    }
                    sort($semuaHari);
                    foreach ($idKar as $idx => $kid) {
                        foreach ($semuaHari as $tgl) {
                            if ($tgl > $hariIni) {
                                continue;
                            }
                            $urutan++;
                            /* Pola deterministik berbasis hash (tanggal + karyawan) supaya tiap
                               karyawan punya pola berbeda, tetapi hasilnya selalu bisa diulang. */
                            $acak = crc32($kid . '|' . $tgl) % 100;
                            $minggu = (int) date('N', strtotime($tgl)) === 7;
                            $status = 'HADIR';
                            $masuk = jam_masuk_standar();
                            $pulang = jam_pulang_standar();
                            $terlambat = 0;
                            $lembur = 0;
                            $setuju = 0;
                            $ket = '';
                            if ($minggu) {
                                /* Hari Minggu: hanya sebagian karyawan masuk; kalau masuk
                                   dicatat sebagai kerja hari Minggu (aturan 2 upah). */
                                if ($acak >= 45) {
                                    continue;
                                }
                                $masuk = '08:00';
                                $pulang = $acak % 3 === 0 ? '20:00' : '16:00';
                                if ($acak % 4 === 0) {
                                    $pulang = '21:00';
                                    $lembur = 60;
                                    $setuju = 1;
                                    $ket = 'Kerja hari Minggu (permintaan produksi)';
                                } else {
                                    $ket = 'Kerja hari Minggu';
                                }
                            } elseif ($acak < 4) {
                                $status = 'ALPA';
                                $masuk = $pulang = '';
                                $ket = 'Tanpa keterangan';
                            } elseif ($acak < 8) {
                                $status = 'IZIN';
                                $masuk = $pulang = '';
                                $ket = 'Izin keperluan keluarga';
                            } elseif ($acak < 11) {
                                $status = 'SAKIT';
                                $masuk = $pulang = '';
                                $ket = 'Sakit (surat dokter)';
                            } elseif ($acak < 13) {
                                $status = 'CUTI';
                                $masuk = $pulang = '';
                                $ket = 'Cuti tahunan';
                            } else {
                                if ($acak % 3 === 0) {
                                    $terlambat = 5 + ($acak % 26);
                                    $masuk = menit_ke_jam((jam_ke_menit(jam_masuk_standar()) ?? 480) + $terlambat);
                                }
                                if ($acak % 5 === 0) {
                                    $lembur = 60 + ($acak % 4) * 30;
                                    $setuju = $acak % 10 === 0 ? 0 : 1;
                                    $pulang = menit_ke_jam((jam_ke_menit(jam_pulang_standar()) ?? 1200) + $lembur);
                                }
                                if ($terlambat > 0 && $lembur > 0) {
                                    $ket = 'Terlambat & lembur penyelesaian laporan';
                                } elseif ($terlambat > 0) {
                                    $ket = 'Terjebak macet';
                                } elseif ($lembur > 0) {
                                    $ket = 'Lembur penyelesaian laporan';
                                }
                            }
                            $ins->execute([$kid, $tgl, $masuk, $pulang, $status, $terlambat, $lembur, $setuju, $ket, 'demo', $tgl . ' 00:00:00', $tgl . ' 00:00:00']);
                        }
                    }
                }
                db()->exec('COMMIT');
            } catch (Throwable $e) {
                db()->exec('ROLLBACK');
                throw $e;
            }
        }
        conf_set('demo_absensi_dibuat', '1');
    }

    if (conf('demo_slip_dibuat', '0') !== '1') {
        $idKar = db()->query('SELECT id FROM karyawan WHERE aktif = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        foreach ([date('Y-m', strtotime('first day of last month')) => 'FINAL', date('Y-m') => 'DRAFT'] as $periode => $status) {
            foreach ($idKar as $kid) {
                $hasil = hitung_slip((int) $kid, (string) $periode);
                if (!empty($hasil['ok'])) {
                    slip_simpan($hasil, 'demo', $status);
                }
            }
        }
        conf_set('demo_slip_dibuat', '1');
    }
}

/* ------------------------------------------------------------------ */
/* Data uji & pembersihannya                                          */
/* ------------------------------------------------------------------ */

/**
 * Penanda baris yang berasal dari data contoh/pengujian.
 * Baris yang dibuat lewat aplikasi (akun asli) TIDAK pernah dianggap data uji.
 */
const SUMBER_UJI = ['demo', 'uji', 'test', 'tes'];

/** Ringkasan jumlah data uji yang ada di database (dipakai sebagai pratinjau). */
function data_uji_ringkas(): array
{
    $tanda = "'" . implode("', '", SUMBER_UJI) . "'";
    $q = fn(string $sql) => (int) db()->query($sql)->fetchColumn();

    $absensiUji = $q("SELECT COUNT(*) FROM absensi WHERE dibuat_oleh IN ({$tanda})");
    $absensiUjiDiubah = $q("SELECT COUNT(*) FROM absensi WHERE dibuat_oleh IN ({$tanda}) AND updated_at <> created_at");
    $slipUji = $q("SELECT COUNT(*) FROM slip_gaji WHERE dibuat_oleh IN ({$tanda})");
    $absensiAsli = $q("SELECT COUNT(*) FROM absensi WHERE dibuat_oleh NOT IN ({$tanda}) AND dibuat_oleh <> ''");
    $absensiTanpaPenanda = $q("SELECT COUNT(*) FROM absensi WHERE dibuat_oleh = ''");
    $slipAsli = $q("SELECT COUNT(*) FROM slip_gaji WHERE dibuat_oleh NOT IN ({$tanda}) AND dibuat_oleh <> ''");

    /* Sebaran per karyawan supaya pemilik bisa melihat apa yang akan terhapus. */
    $sebaran = [];
    $st = db()->query("SELECT k.nama, COUNT(*) AS jml, MIN(a.tanggal) AS dari, MAX(a.tanggal) AS sampai
                       FROM absensi a JOIN karyawan k ON k.id = a.karyawan_id
                       WHERE a.dibuat_oleh IN ({$tanda})
                       GROUP BY a.karyawan_id ORDER BY k.nama");
    foreach ($st as $r) {
        $sebaran[] = $r;
    }
    $periode = [];
    $st2 = db()->query("SELECT substr(tanggal, 1, 7) AS bulan, COUNT(*) AS jml
                        FROM absensi WHERE dibuat_oleh IN ({$tanda})
                        GROUP BY bulan ORDER BY bulan");
    foreach ($st2 as $r) {
        $periode[] = $r;
    }
    /* Baris uji yang sudah pernah diubah — ditampilkan detailnya agar pemilik bisa
       memutuskan apakah baris itu masih data contoh atau sudah menjadi data nyata. */
    $diubah = [];
    $st3 = db()->query("SELECT k.nama, a.tanggal, a.status, a.menit_terlambat, a.menit_lembur, a.updated_at
                        FROM absensi a JOIN karyawan k ON k.id = a.karyawan_id
                        WHERE a.dibuat_oleh IN ({$tanda}) AND a.updated_at <> a.created_at
                        ORDER BY a.tanggal, k.nama LIMIT 50");
    foreach ($st3 as $r) {
        $diubah[] = $r;
    }
    return [
        'absensi_uji_diubah_daftar' => $diubah,
        'absensi_uji' => $absensiUji,
        'absensi_uji_diubah' => $absensiUjiDiubah,
        'slip_uji' => $slipUji,
        'absensi_asli' => $absensiAsli,
        'absensi_tanpa_penanda' => $absensiTanpaPenanda,
        'slip_asli' => $slipAsli,
        'sebaran' => $sebaran,
        'periode' => $periode,
        'demo_nonaktif' => conf_bool('demo_nonaktif', false),
    ];
}

/**
 * Menghapus data uji (absensi & slip bertanda uji).
 *
 * Bersifat sangat hati-hati:
 *  - hanya menyentuh baris dengan penanda uji (demo/uji/test/tes);
 *  - baris uji yang sudah diedit pemilik OPSIONAL (default: dipertahankan);
 *  - data karyawan, divisi, panjar, bonus, dan setelan TIDAK disentuh;
 *  - setelah dibersihkan, data contoh dimatikan permanen agar tidak dibuat ulang.
 *
 * @return array jumlah baris yang terhapus
 */
function data_uji_bersihkan(bool $ikutDiubah = false, bool $hapusSlip = true, bool $matikanDemo = true, bool $tandaiNyata = true): array
{
    $tanda = "'" . implode("', '", SUMBER_UJI) . "'";
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        /* Baris data contoh yang pernah diedit pemilik kemungkinan sudah menjadi data nyata:
           lepaskan penanda uji-nya supaya tidak lagi dianggap data uji (dan tidak ikut terhapus
           pada pembersihan berikutnya). Tidak menyentuh isi barisnya. */
        $jmlDitandai = 0;
        if ($tandaiNyata) {
            $oleh = sesi_username() !== '' ? sesi_username() : 'pemilik';
            $st = $pdo->prepare("UPDATE absensi SET dibuat_oleh = ?
                                 WHERE dibuat_oleh IN ({$tanda}) AND updated_at <> created_at");
            $st->execute([$oleh]);
            $jmlDitandai = $st->rowCount();
        }
        $syaratAbsensi = "dibuat_oleh IN ({$tanda})";
        if (!$ikutDiubah) {
            /* Baris uji yang pernah diubah pemilik dipertahankan (kemungkinan sudah jadi data nyata). */
            $syaratAbsensi .= ' AND updated_at = created_at';
        }
        $jmlAbsensi = (int) $pdo->query("SELECT COUNT(*) FROM absensi WHERE {$syaratAbsensi}")->fetchColumn();
        $pdo->exec("DELETE FROM absensi WHERE {$syaratAbsensi}");

        $jmlSlip = 0;
        if ($hapusSlip) {
            $syaratSlip = "dibuat_oleh IN ({$tanda})";
            $jmlSlip = (int) $pdo->query("SELECT COUNT(*) FROM slip_gaji WHERE {$syaratSlip}")->fetchColumn();
            $pdo->exec("DELETE FROM slip_gaji WHERE {$syaratSlip}");   /* slip_item ikut terhapus (ON DELETE CASCADE) */
        }

        if ($matikanDemo) {
            conf_set('demo_nonaktif', '1');
            conf_set('demo_absensi_dibuat', '1');
            conf_set('demo_slip_dibuat', '1');
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
    log_admin('data_uji_bersih', 'Hapus ' . $jmlAbsensi . ' baris absensi uji & ' . $jmlSlip . ' slip uji'
        . ($ikutDiubah ? ' (termasuk baris uji yang sudah diubah)' : ' (baris uji yang sudah diubah dipertahankan)')
        . ($jmlDitandai > 0 ? '; ' . $jmlDitandai . ' baris diedit ditandai sebagai data nyata' : ''));
    return ['absensi' => $jmlAbsensi, 'slip' => $jmlSlip, 'ditandai' => $jmlDitandai];
}

/** Menghapus absensi pada rentang tanggal tertentu (untuk pemilik, bukan data uji). */
function absensi_hapus_rentang(string $dari, string $sampai): int
{
    $dari = tanggal_valid($dari);
    $sampai = tanggal_valid($sampai, $dari);
    if ($sampai < $dari) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    $st = db()->prepare('SELECT COUNT(*) FROM absensi WHERE tanggal BETWEEN ? AND ?');
    $st->execute([$dari, $sampai]);
    $jml = (int) $st->fetchColumn();
    db()->prepare('DELETE FROM absensi WHERE tanggal BETWEEN ? AND ?')->execute([$dari, $sampai]);
    log_admin('absensi_hapus_rentang', 'Hapus ' . $jml . ' baris absensi ' . $dari . ' s/d ' . $sampai);
    return $jml;
}

/* ------------------------------------------------------------------ */
/* Jejak audit                                                        */
/* ------------------------------------------------------------------ */

function log_admin(string $aksi, string $rincian = ''): void
{
    db()->prepare('INSERT INTO log_admin (waktu, oleh, aksi, rincian) VALUES (?, ?, ?, ?)')
        ->execute([now(), sesi_username(), $aksi, $rincian]);
    /* Simpan maksimum 500 catatan terakhir. */
    db()->exec('DELETE FROM log_admin WHERE id NOT IN (SELECT id FROM log_admin ORDER BY id DESC LIMIT 500)');
}

function log_admin_terakhir(int $batas = 20): array
{
    return db()->query('SELECT * FROM log_admin ORDER BY id DESC LIMIT ' . max(1, $batas))->fetchAll();
}

/* ------------------------------------------------------------------ */
/* Unggah media (logo perusahaan & tanda tangan)                      */
/* ------------------------------------------------------------------ */

function media_token(): string
{
    $f = __DIR__ . '/.vibecoder-media-token';
    return is_readable($f) ? trim((string) file_get_contents($f)) : '';
}

/** Kirim berkas ke penyimpanan media platform. Hanya dipanggil dari sisi server. */
function media_upload(string $filePath, string $filename): array
{
    $token = media_token();
    if ($token === '') {
        return ['ok' => false, 'error' => 'Penyimpanan media belum aktif (aplikasi belum dipublikasikan). Di server lokal, logo belum bisa diunggah — isi URL logo secara manual untuk uji coba.'];
    }
    if (!is_file($filePath) || filesize($filePath) < 1) {
        return ['ok' => false, 'error' => 'Berkas tidak dapat dibaca.'];
    }
    $url = 'http://127.0.0.1:4310/api/app-media/upload?filename=' . rawurlencode($filename);
    $bytes = @file_get_contents($filePath);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'error' => 'Berkas tidak dapat dibaca.'];
    }
    $out = false;
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $bytes,
            CURLOPT_HTTPHEADER     => ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            return ['ok' => false, 'error' => 'Gagal menghubungi penyimpanan media: ' . $err];
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "X-App-Media-Token: $token\r\nContent-Type: application/octet-stream\r\n",
            'content'       => $bytes,
            'timeout'       => 120,
            'ignore_errors' => true,
        ]]);
        $out = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        if ($out === false) {
            return ['ok' => false, 'error' => 'Gagal menghubungi penyimpanan media.'];
        }
    }
    $json = json_decode((string) $out, true);
    if (is_array($json) && !empty($json['ok']) && !empty($json['url'])) {
        return ['ok' => true, 'url' => (string) $json['url']];
    }
    $pesan = 'Penyimpanan media menolak berkas ini';
    if (is_array($json)) {
        $pesan = (string) ($json['error'] ?? $json['message'] ?? $pesan);
    }
    if ($code === 403) {
        $pesan = 'Kuota penyimpanan media penuh. ' . $pesan;
    }
    return ['ok' => false, 'error' => $pesan];
}

/** Simpan berkas unggahan (logo/ttd) ke penyimpanan media platform. */
function unggah_gambar(string $field, int $maksByte = 5_000_000): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Tidak ada berkas yang dipilih.'];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Berkas gagal diunggah (kode ' . (int) $f['error'] . ').'];
    }
    if ($f['size'] > $maksByte) {
        return ['ok' => false, 'error' => 'Ukuran berkas terlalu besar (maks ' . round($maksByte / 1048576, 1) . ' MB).'];
    }
    $ext = strtolower((string) pathinfo((string) $f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        return ['ok' => false, 'error' => 'Format berkas harus PNG atau JPG.'];
    }
    $nama = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $f['name']);
    $hasil = media_upload((string) $f['tmp_name'], $nama);
    if (!$hasil['ok']) {
        return $hasil;
    }
    return ['ok' => true, 'url' => $hasil['url'], 'nama' => $nama];
}

/* ------------------------------------------------------------------ */
/* Kerangka tampilan                                                  */
/* ------------------------------------------------------------------ */

function menu_daftar(): array
{
    return [
        'index.php'      => ['label' => 'Dashboard',    'ikon' => 'grid'],
        'karyawan.php'   => ['label' => 'Karyawan',     'ikon' => 'users'],
        'komponen.php'   => ['label' => 'Komponen & Bonus', 'ikon' => 'coins'],
        'absensi.php'    => ['label' => 'Absensi & Lembur', 'ikon' => 'calendar'],
        'payroll.php'    => ['label' => 'Penggajian',    'ikon' => 'wallet'],
        'slip.php'       => ['label' => 'Slip Gaji',     'ikon' => 'receipt'],
        'laporan.php'    => ['label' => 'Laporan',       'ikon' => 'chart'],
        'pengaturan.php' => ['label' => 'Pengaturan',    'ikon' => 'gear'],
    ];
}

function menu_diizinkan(string $file): bool
{
    if ($file === 'pengaturan.php') {
        return in_array(sesi_role(), role_pengaturan(), true);
    }
    return true;
}

/**
 * URL aset dengan penanda versi (waktu ubah berkas).
 * Tanpa ini, browser bisa menyajikan CSS/JS lama dari cache setelah aplikasi
 * diperbarui — pengguna lalu mengira perubahan tidak terkirim.
 */
function asset(string $berkas): string
{
    $path = __DIR__ . '/' . ltrim($berkas, '/');
    $v = is_file($path) ? (string) filemtime($path) : '1';
    return ltrim($berkas, '/') . '?v=' . $v;
}

function icon(string $nama, int $ukuran = 18): string
{
    $p = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"/><path d="M17 11.2a3 3 0 1 0-1.6-5.5"/><path d="M18 20c0-2.2-.7-3.9-2-5"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 3v3.5M16 3v3.5"/><path d="M7.5 13.5h3M7.5 17h6"/>',
        'wallet'   => '<rect x="2.5" y="5.5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M16 14.5h2.5"/>',
        'receipt'  => '<path d="M6 2.5h12v19l-2.2-1.6-2 1.6-2-1.6-2 1.6-1.8-1.6L6 21.5z"/><path d="M9 7.5h6M9 11.5h6M9 15.5h3.5"/>',
        'chart'    => '<path d="M4 20V9M9.5 20V4M15 20v-7M20.5 20v-10"/>',
        'gear'     => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.6v2.2M12 19.2v2.2M4.2 7.1l1.9 1.1M17.9 15.8l1.9 1.1M4.2 16.9l1.9-1.1M17.9 8.2l1.9-1.1"/>',
        'print'    => '<path d="M7 8.5V3.5h10v5"/><rect x="3.5" y="8.5" width="17" height="8" rx="2"/><path d="M7 14.5h10v6H7z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'logout'   => '<path d="M15 5.5H19a1.5 1.5 0 0 1 1.5 1.5v10a1.5 1.5 0 0 1-1.5 1.5h-4"/><path d="M10 8l-4 4 4 4M6 12h9"/>',
        'undo'     => '<path d="M4 9h9.5a5.5 5.5 0 0 1 0 11H8"/><path d="M7.5 5.5 4 9l3.5 3.5"/>',
        'coins'    => '<ellipse cx="12" cy="6.5" rx="7" ry="3"/><path d="M5 6.5v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5"/><path d="M5 11.5v5c0 1.7 3.1 3 7 3s7-1.3 7-3"/><path d="M12 11.5v9"/>',
    ];
    $isi = $p[$nama] ?? $p['grid'];
    return '<svg class="ikon" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $isi . '</svg>';
}

function page_head(string $judul, string $aktif = '', bool $denganNav = true): void
{
    $app = conf('app_nama', 'HR & Payroll');
    $logo = conf('logo_url', '');
    $role = sesi_role();
    $user = sesi_user();
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($judul) ?> — <?= h($app) ?></title>
<link rel="stylesheet" href="<?= h(asset('assets/style.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%231d4ed8'/><text x='50' y='68' font-size='52' text-anchor='middle' fill='white' font-family='Arial'>Rp</text></svg>">
</head>
<body>
<?php if ($denganNav): ?>
<header class="topbar">
    <div class="topbar-in">
        <a class="brand" href="index.php">
            <?php if ($logo !== ''): ?>
                <img class="brand-logo" src="<?= h($logo) ?>" alt="Logo <?= h(conf('perusahaan_nama')) ?>">
            <?php else: ?>
                <span class="brand-mark"><?= icon('wallet', 20) ?></span>
            <?php endif; ?>
            <span class="brand-tex">
                <strong><?= h(conf('perusahaan_nama', $app)) ?></strong>
                <small><?= h($app) ?></small>
            </span>
        </a>
        <nav class="nav">
            <?php foreach (menu_daftar() as $file => $m):
                if (!menu_diizinkan($file)) {
                    continue;
                } ?>
                <a class="nav-link<?= $aktif === $file ? ' is-aktif' : '' ?>" href="<?= h($file) ?>">
                    <?= icon($m['ikon'], 17) ?><span><?= h($m['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="topbar-kanan">
            <span class="nav-user" title="<?= h($user['nama'] ?? '') ?>">
                <?= icon('users', 16) ?>
                <span class="nav-user-tex"><?= h($user['nama'] ?? $user['username'] ?? '') ?><small><?= h(label_role($role)) ?></small></span>
            </span>
            <a class="btn btn-ghost btn-kecil" href="login.php?keluar=1" title="Keluar"><?= icon('logout', 16) ?><span class="sr-only">Keluar</span></a>
        </div>
    </div>
</header>
<?php endif; ?>
<main class="wrap">
<?php
}

function page_foot(bool $denganNav = true): void
{
    ?>
</main>
<?php if ($denganNav): ?>
<footer class="kaki">
    <div><?= h(conf('perusahaan_nama')) ?> · <?= h(conf('perusahaan_alamat') !== '' ? (string) strtok(conf('perusahaan_alamat'), "\n") : '') ?></div>
    <div><?= h(conf('app_nama')) ?> — data penggajian bersifat rahasia.</div>
</footer>
<?php endif; ?>
<script src="<?= h(asset('assets/app.js')) ?>"></script>
</body>
</html><?php
}

function flash(): void
{
    $ok  = (string) ($_GET['ok'] ?? '');
    $err = (string) ($_GET['err'] ?? '');
    if ($ok !== '') {
        echo '<div class="flash flash-ok">' . h($ok) . '</div>';
    }
    if ($err !== '') {
        echo '<div class="flash flash-err">' . h($err) . '</div>';
    }
}

function judul_halaman(string $judul, string $keterangan = '', bool $denganKembali = false): void
{
    ?>
    <div class="judul-halaman">
        <div>
            <h1><?= h($judul) ?></h1>
            <?php if ($keterangan !== ''): ?><p class="muted"><?= h($keterangan) ?></p><?php endif; ?>
        </div>
        <?php if ($denganKembali): ?><a class="btn btn-ghost" href="payroll.php">Kembali ke Penggajian</a><?php endif; ?>
    </div>
    <?php
}

/* ------------------------------------------------------------------ */
/* Slip gaji siap cetak                                               */
/* ------------------------------------------------------------------ */

/**
 * Menampilkan satu lembar slip gaji.
 * Nilai SELALU berasal dari konfigurasi (nama/alamat/logo/ttd/catatan/terbilang),
 * sehingga perubahan di halaman Pengaturan langsung terlihat di slip.
 *
 * @param array      $s          baris slip (dari slip_gaji atau hasil hitung)
 * @param array      $pendapatan item pendapatan (dari slip_item atau hasil hitung)
 * @param array      $potongan   item potongan
 * @param array|null $live       hasil hitung langsung (untuk menampilkan dasar perhitungan)
 */
function slip_tampil(array $s, array $pendapatan, array $potongan, ?array $live = null): void
{
    $simbol = conf('mata_uang_simbol', 'Rp');
    $status = (string) ($s['status'] ?? '');
    $namaPt = conf('perusahaan_nama');
    ?>
    <div class="slip-lembar">
        <div class="slip-kepala">
            <div class="slip-kop">
                <?php if (conf_bool('slip_tampil_logo', true) && conf('logo_url') !== ''): ?>
                    <img class="slip-logo" src="<?= h(conf('logo_url')) ?>" alt="Logo <?= h($namaPt) ?>">
                <?php endif; ?>
                <div>
                    <div class="slip-nama-pt"><?= h($namaPt) ?></div>
                    <div class="slip-alamat"><?= h(conf('perusahaan_alamat')) ?></div>
                    <div class="slip-alamat-kontak">
                        <?php $kontak = array_filter([
                            conf('perusahaan_telepon') !== '' ? 'Telp. ' . conf('perusahaan_telepon') : '',
                            conf('perusahaan_email'),
                            conf('perusahaan_website'),
                        ]); ?>
                        <?= h(implode(' · ', $kontak)) ?>
                        <?php if (conf('perusahaan_npwp') !== ''): ?><br>NPWP: <?= h(conf('perusahaan_npwp')) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="slip-judul">
                <div class="judul"><?= h(conf('slip_judul', 'SLIP GAJI KARYAWAN')) ?></div>
                <div class="periode">Periode <?= h(bulan_indo((string) $s['periode'])) ?></div>
                <div class="nomor">No. <?= h((string) ($s['nomor'] ?? '-')) ?></div>
                <?php if ($status === 'PRATINJAU'): ?>
                    <div class="nomor" style="color:var(--kuning);font-weight:700">PRATINJAU — BELUM DISIMPAN</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="slip-info">
            <div class="slip-info-baris"><span>Nama Karyawan</span><span><?= h((string) $s['nama']) ?></span></div>
            <div class="slip-info-baris"><span>NIP</span><span><?= h((string) $s['nip']) ?></span></div>
            <div class="slip-info-baris"><span>Jabatan</span><span><?= h((string) $s['jabatan']) ?></span></div>
            <div class="slip-info-baris"><span>Divisi</span><span><?= h((string) ($s['divisi'] ?: '-')) ?></span></div>
            <div class="slip-info-baris"><span>Status Pajak / PTKP</span><span><?= h((string) ($s['ptkp_kode'] ?? '-')) ?> (<?= h(metode_pajak()[(string) ($s['metode_pajak'] ?? '')] ?? '-') ?>)</span></div>
            <div class="slip-info-baris"><span>Rekening</span><span><?= h(trim((string) ($s['bank'] ?? '') . ' ' . (string) ($s['no_rekening'] ?? '')) ?: '-') ?></span></div>
            <div class="slip-info-baris"><span>Hari Kerja / Hadir</span><span><?= h(angka((float) $s['hari_kerja'])) ?> hari / <?= h(angka((float) $s['hari_hadir'])) ?> hari</span></div>
            <div class="slip-info-baris"><span>Jam Kerja</span><span><?= h(jam_kerja_teks()) ?></span></div>
            <div class="slip-info-baris"><span>Hari Kerja (pembagi, Senin–Sabtu)</span><span><?= h(angka((float) $s['hari_kerja'])) ?> hari</span></div>
            <div class="slip-info-baris"><span>Upah Harian (<?= h(conf('basis_upah_per_jam') === 'gaji_pokok' ? 'Pokok' : 'Pokok+Tunj') ?> ÷ <?= h(angka((float) $s['hari_kerja'])) ?> hari)</span>
                <span><?= h($simbol . ' ' . angka(round((float) ($s['upah_harian'] ?? 0)))) ?></span></div>
            <div class="slip-info-baris"><span>Upah Lembur per Jam (÷ <?= h(angka(conf_num('lembur_pembagi', 173))) ?>)</span><span><?= h($simbol . ' ' . angka(round((float) $s['upah_per_jam']))) ?></span></div>
            <div class="slip-info-baris"><span>Tarif Lembur Minggu / Jam</span><span><?= h(conf('minggu_mode', 'tarif_jam') === 'tarif_jam' ? $simbol . ' ' . angka(conf_num('minggu_tarif_jam', 0)) : 'aturan 2 upah') ?></span></div>
        </div>

        <div class="slip-kolom">
            <div class="slip-blok">
                <div class="slip-blok-judul"><span>Pendapatan</span><span>Jumlah</span></div>
                <table>
                    <tbody>
                    <?php foreach ($pendapatan as $p): ?>
                        <tr>
                            <td>
                                <?= h((string) $p['nama']) ?>
                                <?php if (!empty($p['kategori'])): ?><span class="sub-akun"><?= h((string) $p['kategori']) ?></span><?php endif; ?>
                            </td>
                            <td><?= h(angka((float) $p['nilai'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="slip-total"><span>Total Pendapatan (A)</span><span><?= h($simbol . ' ' . angka((float) $s['total_pendapatan'])) ?></span></div>
            </div>

            <div class="slip-blok is-potongan">
                <div class="slip-blok-judul"><span>Potongan</span><span>Jumlah</span></div>
                <table>
                    <tbody>
                    <?php if (!$potongan): ?>
                        <tr><td>Tidak ada potongan</td><td>0</td></tr>
                    <?php endif; ?>
                    <?php foreach ($potongan as $p): ?>
                        <tr>
                            <td>
                                <?= h((string) $p['nama']) ?>
                                <?php if (!empty($p['kategori'])): ?><span class="sub-akun"><?= h((string) $p['kategori']) ?></span><?php endif; ?>
                            </td>
                            <td><?= h(angka((float) $p['nilai'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="slip-total"><span>Total Potongan (B)</span><span><?= h($simbol . ' ' . angka((float) $s['total_potongan'])) ?></span></div>
            </div>
        </div>

        <div class="slip-thp">
            <div>
                <div class="label">Gaji Bersih / Take Home Pay (A − B)</div>
                <?php if (conf_bool('slip_tampil_terbilang', true)): ?>
                    <div class="slip-terbilang">Terbilang: <strong><?= h(terbilang_rupiah((float) $s['take_home_pay'])) ?></strong></div>
                <?php endif; ?>
            </div>
            <div class="nilai"><?= h($simbol . ' ' . angka((float) $s['take_home_pay'])) ?></div>
        </div>

        <?php if (conf_bool('slip_tampil_rekap', true)): ?>
            <div class="slip-rekap">
                <div class="slip-rekap-sel"><div class="l">Hari Kerja</div><div class="v"><?= h(angka((float) $s['hari_kerja'])) ?> hari</div></div>
                <div class="slip-rekap-sel"><div class="l">Hadir</div><div class="v"><?= h(angka((float) $s['hari_hadir'])) ?> hari</div></div>
                <div class="slip-rekap-sel"><div class="l">Kerja Hari Minggu</div><div class="v"><?= h(angka((float) ($s['hari_minggu'] ?? 0))) ?> hari</div></div>
                <div class="slip-rekap-sel"><div class="l">Alpa</div><div class="v"><?= h(angka((float) ($s['hari_alpa'] ?? 0))) ?> hari</div></div>
                <div class="slip-rekap-sel"><div class="l">Izin / Sakit / Cuti</div><div class="v"><?= h(angka((float) ($s['hari_izin'] ?? 0))) ?> / <?= h(angka((float) ($s['hari_sakit'] ?? 0))) ?> / <?= h(angka((float) ($s['hari_cuti'] ?? 0))) ?></div></div>
                <div class="slip-rekap-sel"><div class="l">Keterlambatan</div><div class="v"><?= (int) $s['total_menit_terlambat'] ?> menit</div></div>
                <div class="slip-rekap-sel"><div class="l">Lembur</div><div class="v"><?= h(angka((int) $s['total_menit_lembur'] / 60, 2)) ?> jam</div></div>
                <div class="slip-rekap-sel"><div class="l">Jam Kerja / Hari</div><div class="v"><?= h(angka(jam_kerja_per_hari_efektif(), 0)) ?> jam</div></div>
            </div>
        <?php endif; ?>

        <?php if ($live !== null && $live['langkah']): ?>
            <div class="slip-catatan no-print">
                <strong>Dasar perhitungan (tidak dicetak):</strong>
                <ul class="daftar-sederhana" style="margin-top:6px">
                    <?php foreach ($live['langkah'] as $l): ?><li><?= h($l) ?></li><?php endforeach; ?>
                    <?php if (!empty($live['minggu']['uraian'])): ?><li><?= h((string) $live['minggu']['uraian']) ?></li><?php endif; ?>
                    <?php foreach ($live['bonus']['bonus'] ?? [] as $b): ?><li>Bonus <?= h((string) $b['nama']) ?>: <?= h((string) $b['uraian']) ?></li><?php endforeach; ?>
                    <?php foreach ($live['panjar']['items'] ?? [] as $pj): ?><li><?= h((string) $pj['nama']) ?>: <?= h((string) $pj['uraian']) ?></li><?php endforeach; ?>
                    <?php if (!empty($live['pajak']['uraian'])): ?><li>PPh 21: <?= h((string) $live['pajak']['uraian']) ?></li><?php endif; ?>
                    <?php if (!empty($live['denda']['uraian'])): ?><li>Denda: <?= h((string) $live['denda']['uraian']) ?></li><?php endif; ?>
                    <?php foreach ($live['catatan'] as $c): ?><li><?= h((string) $c) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($live !== null && $live['catatan'] !== []): ?>
            <div class="slip-catatan">
                <?php foreach ($live['catatan'] as $c): ?><div>Catatan: <?= h((string) $c) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="slip-tanda">
            <div class="slip-tanda-sel">
                <div class="slip-tanggal-ttd">Diterima oleh,</div>
                <div class="slip-ttd-gambar"></div>
                <div class="slip-tanda-garis"></div>
                <div class="nama"><?= h((string) $s['nama']) ?></div>
                <div class="jabatan"><?= h((string) $s['jabatan']) ?></div>
            </div>
            <div class="slip-tanda-sel">
                <div class="slip-tanggal-ttd">
                    <?= h(conf('perusahaan_kota', '')) ?><?= conf('perusahaan_kota') !== '' ? ', ' : '' ?><?= h(tanggal_indo((string) ($s['tanggal_proses'] ?: date('Y-m-d')))) ?>
                </div>
                <div class="slip-ttd-gambar">
                    <?php if (conf_bool('ttd_tampil_gambar', false) && conf('ttd_url') !== ''): ?>
                        <img src="<?= h(conf('ttd_url')) ?>" alt="Tanda tangan">
                    <?php endif; ?>
                </div>
                <div class="slip-tanda-garis"></div>
                <div class="nama"><?= h(conf('ttd_nama')) ?></div>
                <div class="jabatan"><?= h(conf('ttd_jabatan')) ?></div>
            </div>
        </div>

        <?php if (conf('slip_catatan') !== ''): ?>
            <div class="slip-catatan"><?= nl2br(h(conf('slip_catatan'))) ?></div>
        <?php endif; ?>
    </div>
    <?php
}
