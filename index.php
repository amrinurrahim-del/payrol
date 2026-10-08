<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();
demo_siapkan();

$periode = periode_valid((string) ($_GET['periode'] ?? date('Y-m')));
$kal = hari_kerja_bulan($periode);
$karyawan = daftar_karyawan();
$slip = slip_daftar($periode);

/* Bila periode belum dihitung, tampilkan angka pratinjau dari mesin payroll. */
$pratinjau = [];
if (!$slip) {
    foreach ($karyawan as $k) {
        $h = hitung_slip((int) $k['id'], $periode);
        if (!empty($h['ok'])) {
            $pratinjau[] = $h;
        }
    }
}

$totalPendapatan = 0.0;
$totalPotongan = 0.0;
$totalThp = 0.0;
$totalLemburMenit = 0;
$totalTerlambat = 0;
$perDivisi = [];
foreach ($slip as $s) {
    $totalPendapatan += (float) $s['total_pendapatan'];
    $totalPotongan += (float) $s['total_potongan'];
    $totalThp += (float) $s['take_home_pay'];
    $totalLemburMenit += (int) $s['total_menit_lembur'];
    $totalTerlambat += (int) $s['total_menit_terlambat'];
    $d = (string) ($s['divisi'] ?: 'Tanpa Divisi');
    $perDivisi[$d] = ($perDivisi[$d] ?? 0) + (float) $s['take_home_pay'];
}
foreach ($pratinjau as $h) {
    $totalPendapatan += (float) $h['total_pendapatan'];
    $totalPotongan += (float) $h['total_potongan'];
    $totalThp += (float) $h['take_home_pay'];
    $totalLemburMenit += (int) $h['lembur']['menit'];
    $totalTerlambat += (int) $h['rekap']['menit_terlambat'];
    $d = (string) ($h['karyawan']['divisi_nama'] ?: 'Tanpa Divisi');
    $perDivisi[$d] = ($perDivisi[$d] ?? 0) + (float) $h['take_home_pay'];
}
$jmlDraft = 0;
$jmlFinal = 0;
foreach ($slip as $s) {
    if ($s['status'] === 'FINAL') {
        $jmlFinal++;
    } else {
        $jmlDraft++;
    }
}

/* Absensi hari ini yang belum dicatat */
$hariIni = date('Y-m-d');
$liburHariIni = hari_libur_map(date('Y-m'));
$perluAbsen = tanggal_hari_kerja($hariIni, $liburHariIni);
$belumAbsen = [];
if ($perluAbsen) {
    foreach ($karyawan as $k) {
        $st = db()->prepare('SELECT COUNT(*) FROM absensi WHERE karyawan_id = ? AND tanggal = ?');
        $st->execute([(int) $k['id'], $hariIni]);
        if ((int) $st->fetchColumn() === 0) {
            $belumAbsen[] = $k['nama'];
        }
    }
}

page_head('Dashboard', 'index.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Dashboard Penggajian</h1>
        <p class="muted">Ringkasan periode <strong><?= h(bulan_indo($periode)) ?></strong> — <?= (int) $kal['jumlah'] ?> hari kerja (basis <?= h($kal['mode']) ?>).</p>
    </div>
    <form method="get" class="btn-baris">
        <input type="month" name="periode" value="<?= h($periode) ?>" onchange="this.form.submit()">
        <a class="btn btn-primer" href="payroll.php?periode=<?= h($periode) ?>">Buka Penggajian</a>
    </form>
</div>

<?php flash(); ?>

<?php if (!$slip && $pratinjau): ?>
    <div class="catatan catatan-info" style="margin-bottom:20px">
        Periode <strong><?= h(bulan_indo($periode)) ?></strong> belum dihitung/disimpan. Angka di bawah adalah
        <strong>pratinjau</strong> hasil mesin payroll dengan konfigurasi saat ini —
        buka <a href="payroll.php?periode=<?= h($periode) ?>">Penggajian</a> lalu tekan “Hitung Semua” untuk menyimpannya.
    </div>
<?php endif; ?>

<div class="kartu-grid grid-4" style="margin-bottom:24px">
    <div class="stat">
        <span class="stat-ikon"><?= icon('users') ?></span>
        <span class="stat-label">Karyawan Aktif</span>
        <span class="stat-nilai"><?= count($karyawan) ?></span>
        <span class="stat-sub"><?= count(daftar_divisi()) ?> divisi</span>
    </div>
    <div class="stat is-hijau">
        <span class="stat-ikon"><?= icon('wallet') ?></span>
        <span class="stat-label">Total Take Home Pay</span>
        <span class="stat-nilai kecil"><?= h(rp($totalThp)) ?></span>
        <span class="stat-sub"><?= $slip ? 'dari slip tersimpan' : 'pratinjau belum disimpan' ?></span>
    </div>
    <div class="stat is-kuning">
        <span class="stat-ikon"><?= icon('calendar') ?></span>
        <span class="stat-label">Lembur Periode Ini</span>
        <span class="stat-nilai"><?= h(angka($totalLemburMenit / 60, 1)) ?> <small style="font-size:.9rem">jam</small></span>
        <span class="stat-sub">Keterlambatan <?= (int) $totalTerlambat ?> menit</span>
    </div>
    <div class="stat is-merah">
        <span class="stat-ikon"><?= icon('receipt') ?></span>
        <span class="stat-label">Pendapatan / Potongan</span>
        <span class="stat-nilai kecil"><?= h(rp($totalPendapatan)) ?></span>
        <span class="stat-sub">Potongan <?= h(rp($totalPotongan)) ?></span>
    </div>
</div>

<div class="kartu-grid grid-2-1">
    <div>
        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2><?= icon('chart', 17) ?> Rekap per Divisi</h2>
                    <p class="muted">Total gaji bersih yang harus dibayarkan tiap divisi.</p>
                </div>
            </div>
            <?php if ($perDivisi): ?>
                <?php arsort($perDivisi); $maks = max($perDivisi) ?: 1; ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Divisi</th><th class="angka">Gaji Bersih</th><th style="width:34%">Porsi</th></tr></thead>
                        <tbody>
                        <?php foreach ($perDivisi as $nama => $nilai): ?>
                            <tr>
                                <td><?= h($nama) ?></td>
                                <td class="angka"><?= h(rp($nilai)) ?></td>
                                <td>
                                    <div style="background:var(--biru-100);border-radius:6px;height:9px;overflow:hidden">
                                        <div style="height:9px;width:<?= max(3, round($nilai / $maks * 100)) ?>%;background:linear-gradient(90deg,var(--biru-700),var(--biru-500))"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="baris-total"><td>Total</td><td class="angka"><?= h(rp(array_sum($perDivisi))) ?></td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="kosong"><strong>Belum ada data</strong>Tambahkan karyawan lalu hitung slip gaji.</div>
            <?php endif; ?>
        </div>

        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2><?= icon('users', 17) ?> Karyawan Terbaru</h2>
                    <p class="muted">Data master karyawan beserta upah tetap.</p>
                </div>
                <a class="btn btn-kecil" href="karyawan.php">Kelola Karyawan</a>
            </div>
            <?php if ($karyawan): ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Nama</th><th>Jabatan</th><th>Divisi</th><th class="angka">Gaji Pokok</th><th class="angka">Tunj. Tetap</th><th>Pajak</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($karyawan, 0, 6) as $k): ?>
                            <tr>
                                <td><a href="karyawan.php?ubah=<?= (int) $k['id'] ?>"><?= h($k['nama']) ?></a><br><span class="muted"><?= h($k['nip']) ?></span></td>
                                <td><?= h($k['jabatan']) ?></td>
                                <td><?= h((string) ($k['divisi_nama'] ?? '-')) ?></td>
                                <td class="angka"><?= h(rp((float) $k['gaji_pokok'])) ?></td>
                                <td class="angka"><?= h(rp((float) $k['tunjangan_tetap'])) ?></td>
                                <td><span class="lencana lencana-biru"><?= h(metode_pajak()[$k['metode_pajak']] ?? $k['metode_pajak']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="kosong"><strong>Belum ada karyawan</strong><a href="karyawan.php">Tambah karyawan pertama</a></div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="kartu">
            <h3><?= icon('receipt', 17) ?> Status Slip <?= h(bulan_indo($periode)) ?></h3>
            <div class="rincian-baris"><span>Tersimpan (final)</span><span class="nilai"><?= $jmlFinal ?> slip</span></div>
            <div class="rincian-baris"><span>Draft</span><span class="nilai"><?= $jmlDraft ?> slip</span></div>
            <div class="rincian-baris"><span>Belum dihitung</span><span class="nilai"><?= count($pratinjau) ?> karyawan</span></div>
            <div class="btn-baris" style="margin-top:14px">
                <a class="btn btn-primer btn-kecil" href="payroll.php?periode=<?= h($periode) ?>">Hitung Gaji</a>
                <a class="btn btn-kecil" href="slip.php?periode=<?= h($periode) ?>">Daftar Slip</a>
            </div>
        </div>

        <div class="kartu">
            <h3><?= icon('calendar', 17) ?> Absensi Hari Ini</h3>
            <?php if (!$perluAbsen): ?>
                <p class="muted">Hari ini bukan hari kerja (akhir pekan / hari libur). Tidak ada yang perlu dicatat.</p>
            <?php elseif (!$belumAbsen): ?>
                <p><span class="lencana lencana-hijau">Lengkap</span> Semua karyawan sudah punya catatan absensi hari ini.</p>
            <?php else: ?>
                <p class="muted"><?= count($belumAbsen) ?> karyawan belum dicatat absensinya hari ini:</p>
                <ul class="daftar-sederhana">
                    <?php foreach (array_slice($belumAbsen, 0, 6) as $n): ?><li><?= h($n) ?></li><?php endforeach; ?>
                    <?php if (count($belumAbsen) > 6): ?><li>… dan <?= count($belumAbsen) - 6 ?> lainnya</li><?php endif; ?>
                </ul>
                <a class="btn btn-kecil" style="margin-top:8px" href="absensi.php?tanggal=<?= h($hariIni) ?>">Catat Absensi</a>
            <?php endif; ?>
        </div>

        <div class="kartu">
            <h3><?= icon('gear', 17) ?> Konfigurasi Aktif</h3>
            <div class="rincian-baris"><span>Jam kerja / hari</span><span class="nilai"><?= h(angka(jam_kerja_per_hari_efektif(), 0)) ?> jam (<?= h(jam_masuk_standar()) ?>–<?= h(jam_pulang_standar()) ?>)</span></div>
            <div class="rincian-baris"><span>Istirahat</span><span class="nilai"><?= h(durasi_indo(menit_istirahat_harian())) ?> (tidak dibayar)</span></div>
            <div class="rincian-baris"><span>Upah harian</span><span class="nilai"><?= h(conf('basis_upah_per_jam') === 'gaji_pokok' ? 'Pokok' : 'Pokok+Tunj') ?> ÷ <?= h(angka(conf('basis_hari_kerja', 'tetap') === 'otomatis' ? count($kal['tanggal']) : conf_num('hari_kerja_per_bulan', 25))) ?> hari<?= conf('upah_mode') === 'proporsional_hadir' ? ' × hari hadir' : ' (penuh)' ?></span></div>
            <div class="rincian-baris"><span>Upah lembur / jam</span><span class="nilai"><?= h(conf('basis_upah_per_jam') === 'gaji_pokok' ? 'Pokok' : 'Pokok+Tunj') ?> ÷ <?= h(angka(conf_num('lembur_pembagi', 173))) ?><?= conf_bool('lembur_pakai_faktor', false) ? ' × faktor' : '' ?></span></div>
            <div class="rincian-baris"><span>Upah hari Minggu</span><span class="nilai"><?= conf_bool('minggu_aktif') ? (conf('minggu_mode', 'tarif_jam') === 'tarif_jam' ? h(rp(conf_num('minggu_tarif_jam', 0)) . ' / jam') : '2 upah harian') : 'dimatikan' ?></span></div>
            <div class="rincian-baris"><span>Bonus aktif</span><span class="nilai"><?= (int) db()->query('SELECT COUNT(*) FROM bonus_parameter WHERE aktif = 1')->fetchColumn() ?> parameter</span></div>
            <div class="rincian-baris"><span>Potongan aktif</span><span class="nilai"><?php $n = 0; foreach (conf_kelompok('potongan') as $k => $m) { if (conf_bool($k)) { $n++; } } if (conf_bool('bpjs_aktif')) { $n++; } if (conf_bool('pajak_aktif')) { $n++; } echo $n; ?> jenis</span></div>
            <div class="rincian-baris"><span>Panjar berjalan</span><span class="nilai"><?= (int) db()->query('SELECT COUNT(*) FROM panjar WHERE aktif = 1')->fetchColumn() ?> panjar</span></div>
            <?php if (in_array(sesi_role(), role_pengaturan(), true)): ?>
                <div class="btn-baris" style="margin-top:12px">
                    <a class="btn btn-kecil" href="pengaturan.php?tab=jamkerja">Jam Kerja</a>
                    <a class="btn btn-kecil" href="pengaturan.php?tab=lembur">Lembur</a>
                    <a class="btn btn-kecil" href="pengaturan.php?tab=bonus">Bonus</a>
                    <a class="btn btn-kecil" href="pengaturan.php?tab=potongan">Potongan</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_foot(); ?>
