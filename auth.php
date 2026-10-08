<?php
declare(strict_types=1);

/*
 * Autentikasi & hak akses.
 *
 * Dua peran (tanpa pendaftaran mandiri):
 *   - superadmin : akses penuh, termasuk kelola akun & hapus data periode.
 *   - hrd        : seluruh modul HR/payroll + sebagian besar Pengaturan,
 *                  tetapi TIDAK boleh mengelola akun dan menghapus data.
 *
 * Sesi memakai cookie acak + tabel `sesi` (hash sha256), bukan session_start(),
 * supaya tidak bergantung pada folder penyimpanan session milik server.
 */

require_once __DIR__ . '/db.php';

const DEFAULT_SUPER_USER = 'superadmin';
const DEFAULT_SUPER_PASS = 'superadmin';
const DEFAULT_HRD_USER   = 'hrd';
const DEFAULT_HRD_PASS   = 'hrd';

const SESI_COOKIE = 'payroll_sesi';
const SESI_JAM    = 10;    // masa berlaku sesi (jam), diperpanjang tiap aktivitas
const MAX_GAGAL   = 5;     // percobaan login gagal sebelum dikunci sementara
const KUNCI_DETIK = 300;   // lama penguncian (detik)

function request_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function shift_time(int $detik): string
{
    return date('Y-m-d H:i:s', time() + $detik);
}

function sesi_set_cookie(string $token, int $detik): void
{
    setcookie(SESI_COOKIE, $token, [
        'expires'  => time() + $detik,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_https(),
    ]);
    $_COOKIE[SESI_COOKIE] = $token;
}

function sesi_hapus_cookie(): void
{
    setcookie(SESI_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_https(),
    ]);
    unset($_COOKIE[SESI_COOKIE]);
}

/* ------------------------------------------------------------------ */
/* Pengguna                                                            */
/* ------------------------------------------------------------------ */

function daftar_user(): array
{
    return db()->query('SELECT id, username, nama, role, aktif, updated_at, failed_count, locked_until
                        FROM user
                        ORDER BY CASE role WHEN "superadmin" THEN 1 ELSE 2 END, username')->fetchAll();
}

function daftar_peran(): array
{
    return [
        'superadmin' => 'Superadmin',
        'hrd'        => 'HRD / Admin Payroll',
    ];
}

function label_role(string $role): string
{
    $p = daftar_peran();
    return $p[$role] ?? $role;
}

/** Peran yang boleh membuka halaman Pengaturan. */
function role_pengaturan(): array
{
    return ['superadmin', 'hrd'];
}

/** Tab Pengaturan yang boleh dibuka sebuah peran. */
function pengaturan_tab_diizinkan(string $role, string $tab): bool
{
    if ($role === 'superadmin') {
        return true;
    }
    if ($role === 'hrd') {
        return in_array($tab, ['perusahaan', 'jamkerja', 'penggajian', 'lembur', 'bonus', 'potongan', 'bpjs', 'pajak', 'libur', 'slip', 'akun-hrd'], true);
    }
    return false;
}

/** Tab yang hanya boleh dilihat superadmin. */
function pengaturan_tab_superadmin(): array
{
    return ['akun', 'keamanan', 'audit'];
}

/**
 * Tindakan (POST) halaman Pengaturan yang boleh dijalankan sebuah peran.
 * Diperiksa di sisi server: menyembunyikan tab TIDAK cukup melindungi data.
 */
function pengaturan_aksi_diizinkan(string $role, string $aksi): bool
{
    if ($role === 'superadmin') {
        return true;
    }
    if ($role === 'hrd') {
        /* Tindakan merusak data (hapus massal / bersihkan data uji) hanya untuk superadmin. */
        return !in_array($aksi, ['user_simpan', 'user_hapus', 'user_sandi', 'reset_data', 'data_hapus', 'audit_hapus',
            'data_uji_bersih', 'absensi_hapus_rentang', 'absensi_hapus_bulan'], true);
    }
    return false;
}

/** Tindakan yang hanya boleh superadmin (dipakai juga di halaman lain). */
function aksi_superadmin(string $aksi): bool
{
    return in_array($aksi, ['user_simpan', 'user_hapus', 'user_sandi', 'reset_data', 'data_hapus', 'audit_hapus'], true);
}

function user_row(string $username): ?array
{
    $st = db()->prepare('SELECT * FROM user WHERE username = ?');
    $st->execute([$username]);
    $r = $st->fetch();
    return $r ?: null;
}

function user_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM user WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function user_tambah(string $username, string $nama, string $sandi, string $role, bool $aktif = true): array
{
    $username = trim(strtolower($username));
    if (!preg_match('/^[a-z0-9._\-]{3,24}$/', $username)) {
        return ['ok' => false, 'error' => 'Username hanya huruf kecil, angka, titik, garis bawah, atau strip (3–24 karakter).'];
    }
    if (strlen($sandi) < 5) {
        return ['ok' => false, 'error' => 'Kata sandi minimal 5 karakter.'];
    }
    if (!isset(daftar_peran()[$role])) {
        return ['ok' => false, 'error' => 'Peran tidak dikenal.'];
    }
    if (user_row($username)) {
        return ['ok' => false, 'error' => 'Username sudah dipakai.'];
    }
    db()->prepare('INSERT INTO user (username, nama, password_hash, role, aktif, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$username, $nama, password_hash($sandi, PASSWORD_DEFAULT), $role, $aktif ? 1 : 0, now()]);
    return ['ok' => true];
}

function user_ubah(int $id, string $nama, string $role, bool $aktif, string $sandiBaru = ''): array
{
    $u = user_by_id($id);
    if (!$u) {
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    }
    if (!isset(daftar_peran()[$role])) {
        return ['ok' => false, 'error' => 'Peran tidak dikenal.'];
    }
    if ((int) $u['aktif'] === 1 && $u['role'] === 'superadmin' && ($role !== 'superadmin' || !$aktif)) {
        $jml = (int) db()->query('SELECT COUNT(*) FROM user WHERE role = "superadmin" AND aktif = 1')->fetchColumn();
        if ($jml <= 1) {
            return ['ok' => false, 'error' => 'Minimal harus ada satu superadmin yang aktif.'];
        }
    }
    db()->prepare('UPDATE user SET nama = ?, role = ?, aktif = ?, updated_at = ? WHERE id = ?')
        ->execute([$nama, $role, $aktif ? 1 : 0, now(), $id]);
    if ($sandiBaru !== '') {
        if (strlen($sandiBaru) < 5) {
            return ['ok' => false, 'error' => 'Kata sandi minimal 5 karakter.'];
        }
        user_set_sandi($id, $sandiBaru, true);
    }
    return ['ok' => true];
}

function user_hapus(int $id): array
{
    $u = user_by_id($id);
    if (!$u) {
        return ['ok' => false, 'error' => 'Akun tidak ditemukan.'];
    }
    $jml = (int) db()->query('SELECT COUNT(*) FROM user')->fetchColumn();
    if ($jml <= 1) {
        return ['ok' => false, 'error' => 'Tidak dapat menghapus satu-satunya akun.'];
    }
    if ($u['role'] === 'superadmin') {
        $jmlS = (int) db()->query('SELECT COUNT(*) FROM user WHERE role = "superadmin"')->fetchColumn();
        if ($jmlS <= 1) {
            return ['ok' => false, 'error' => 'Minimal harus ada satu akun superadmin.'];
        }
    }
    db()->prepare('DELETE FROM sesi WHERE user_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM user WHERE id = ?')->execute([$id]);
    return ['ok' => true];
}

/** Menyetel kata sandi; memutus semua sesi akun tersebut kecuali sesi yang sedang dipakai. */
function user_set_sandi(int $id, string $sandi, bool $putusSesi = true): void
{
    db()->prepare('UPDATE user SET password_hash = ?, updated_at = ? WHERE id = ?')
        ->execute([password_hash($sandi, PASSWORD_DEFAULT), now(), $id]);
    if ($putusSesi) {
        $token = (string) ($_COOKIE[SESI_COOKIE] ?? '');
        $hash  = $token !== '' ? hash('sha256', $token) : '';
        db()->prepare('DELETE FROM sesi WHERE user_id = ? AND token_hash <> ?')->execute([$id, $hash]);
    }
}

/* ------------------------------------------------------------------ */
/* Sesi                                                               */
/* ------------------------------------------------------------------ */

function sesi_mulai(int $userId): void
{
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO sesi (token_hash, user_id, created_at, expires_at, last_seen) VALUES (?, ?, ?, ?, ?)')
        ->execute([hash('sha256', $token), $userId, now(), shift_time(SESI_JAM * 3600), now()]);
    sesi_set_cookie($token, SESI_JAM * 3600);
}

function sesi_bersihkan(): void
{
    db()->prepare('DELETE FROM sesi WHERE expires_at < ?')->execute([now()]);
}

function sesi_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $token = (string) ($_COOKIE[SESI_COOKIE] ?? '');
    if ($token === '') {
        return null;
    }
    $hash = hash('sha256', $token);
    $st = db()->prepare('SELECT s.expires_at, u.* FROM sesi s JOIN user u ON u.id = s.user_id WHERE s.token_hash = ?');
    $st->execute([$hash]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    if ($row['expires_at'] < now() || (int) $row['aktif'] !== 1) {
        db()->prepare('DELETE FROM sesi WHERE token_hash = ?')->execute([$hash]);
        return null;
    }
    /* Perpanjang sesi (sliding) paling cepat 1 menit sekali agar tidak menulis terus. */
    if (strtotime((string) $row['expires_at']) - time() < SESI_JAM * 3600 - 60) {
        db()->prepare('UPDATE sesi SET expires_at = ?, last_seen = ? WHERE token_hash = ?')
            ->execute([shift_time(SESI_JAM * 3600), now(), $hash]);
        sesi_set_cookie($token, SESI_JAM * 3600);
    }
    $cache = $row;
    return $row;
}

function sesi_username(): string
{
    $u = sesi_user();
    return $u ? (string) $u['username'] : '';
}

function sesi_role(): string
{
    $u = sesi_user();
    return $u ? (string) $u['role'] : '';
}

function sesi_berakhir(): void
{
    $token = (string) ($_COOKIE[SESI_COOKIE] ?? '');
    if ($token !== '') {
        db()->prepare('DELETE FROM sesi WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }
    sesi_hapus_cookie();
}

/* ------------------------------------------------------------------ */
/* Login                                                              */
/* ------------------------------------------------------------------ */

function login_coba(string $username, string $sandi): array
{
    $username = trim(strtolower($username));
    $u = user_row($username);
    if (!$u) {
        usleep(200000);
        return ['ok' => false, 'error' => 'Username atau kata sandi salah.'];
    }
    if (!empty($u['locked_until']) && $u['locked_until'] > now()) {
        $sisa = max(1, (int) ceil((strtotime((string) $u['locked_until']) - time()) / 60));
        return ['ok' => false, 'error' => "Akun terkunci sementara. Coba lagi dalam {$sisa} menit."];
    }
    if ((int) $u['aktif'] !== 1) {
        return ['ok' => false, 'error' => 'Akun dinonaktifkan. Hubungi superadmin.'];
    }
    if (!password_verify($sandi, (string) $u['password_hash'])) {
        $gagal = (int) $u['failed_count'] + 1;
        $lock  = $gagal >= MAX_GAGAL ? shift_time(KUNCI_DETIK) : null;
        db()->prepare('UPDATE user SET failed_count = ?, locked_until = ? WHERE id = ?')
            ->execute([$gagal >= MAX_GAGAL ? 0 : $gagal, $lock, $u['id']]);
        usleep(200000);
        return ['ok' => false, 'error' => $gagal >= MAX_GAGAL
            ? 'Terlalu banyak percobaan gagal. Akun dikunci 5 menit.'
            : 'Username atau kata sandi salah.'];
    }
    db()->prepare('UPDATE user SET failed_count = 0, locked_until = NULL WHERE id = ?')->execute([$u['id']]);
    sesi_bersihkan();
    sesi_mulai((int) $u['id']);
    return ['ok' => true, 'user' => $u];
}

function require_login(): void
{
    if (!sesi_user()) {
        header('Location: login.php?err=' . rawurlencode('Silakan masuk terlebih dahulu.'));
        exit;
    }
}

function require_pengaturan(): void
{
    require_login();
    if (!in_array(sesi_role(), role_pengaturan(), true)) {
        header('Location: index.php?err=' . rawurlencode('Halaman Pengaturan hanya untuk Superadmin/HRD.'));
        exit;
    }
}

function require_superadmin(): void
{
    require_login();
    if (sesi_role() !== 'superadmin') {
        header('Location: pengaturan.php?tab=perusahaan&err=' . rawurlencode('Hanya Superadmin yang boleh membuka bagian ini.'));
        exit;
    }
}
