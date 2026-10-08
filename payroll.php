<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();
demo_siapkan();

$periode = periode_valid((string) ($_GET['periode'] ?? date('Y-m')));
$filterDivisi = (string) ($_GET['divisi'] ?? '');
$kal = hari_kerja_bulan($periode);

if (is_post()) {
    $aksi = post('aksi');
    $target = post_int('karyawan_id');
    if ($aksi === 'hitung_semua' || $aksi === 'finalkan_semua') {
        $status = $aksi === 'finalkan_semua' ? 'FINAL' : 'DRAFT';
        $jml = 0;
        foreach (daftar_karyawan() as $k) {
            $hasil = hitung_slip((int) $k['id'], $periode);
            if (!empty($hasil['ok'])) {
                slip_simpan($hasil, sesi_username(), $status);
                $jml++;
            }
        }
        log_admin('slip_' . $aksi, "Periode {$periode}, {$jml} slip → {$status}");
        redirect('payroll.php?periode=' . $periode . '&divisi=' . urlencode($filterDivisi) . '&ok=' . rawurlencode("{$jml} slip dihitung ulang dan disimpan sebagai " . ($status === 'FINAL' ? 'FINAL' : 'DRAFT') . '.'));
    } elseif ($aksi === 'hitung_satu' || $aksi === 'finalkan_satu') {
        $status = $aksi === 'finalkan_satu' ? 'FINAL' : 'DRAFT';
        $hasil = hitung_slip($target, $periode);
        if (empty($hasil['ok'])) {
            redirect('payroll.php?periode=' . $periode . '&err=' . rawurlencode('Karyawan tidak ditemukan.'));
        }
        slip_simpan($hasil, sesi_username(), $status);
        log_admin('slip_hitung', 'Karyawan #' . $target . ' periode ' . $periode . ' → ' . $status);
        redirect('payroll.php?periode=' . $periode . '&divisi=' . urlencode($filterDivisi) . '&ok=' . rawurlencode('Slip ' . $hasil['karyawan']['nama'] . ' dihitung ulang (' . $status . ').'));
    } elseif ($aksi === 'hapus_slip') {
        slip_hapus(post_int('slip_id'));
        log_admin('slip_hapus', 'Hapus slip #' . post_int('slip_id') . ' periode ' . $periode);
        redirect('payroll.php?periode=' . $periode . '&ok=' . rawurlencode('Slip dihapus.'));
    }
}

$karyawan = daftar_karyawan('', true, $filterDivisi);
$tersimpan = [];
foreach (slip_daftar($periode) as $s) {
    $tersimpan[(int) $s['karyawan_id']] = $s;
}

/* Susun baris: pakai slip tersimpan bila ada, kalau tidak hitung pratinjau. */
$baris = [];
$total = ['pendapatan' => 0.0, 'potongan' => 0.0, 'thp' => 0.0];
foreach ($karyawan as $k) {
    $s = $tersimpan[(int) $k['id']] ?? null;
    if ($s) {
        $r = [
            'karyawan' => $k,
            'slip' => $s,
            'status' => (string) $s['status'],
            'pendapatan' => (float) $s['total_pendapatan'],
            'potongan' => (float) $s['total_potongan'],
            'thp' => (float) $s['take_home_pay'],
            'hadir' => (float) $s['hari_hadir'],
            'minggu' => (float) ($s['hari_minggu'] ?? 0),
            'bonus' => (float) ($s['total_bonus'] ?? 0),
            'panjar' => (float) ($s['total_panjar'] ?? 0),
            'alpa' => (float) $s['hari_alpa'],
            'terlambat' => (int) $s['total_menit_terlambat'],
            'lembur' => (int) $s['total_menit_lembur'],
        ];
    } else {
        $h = hitung_slip((int) $k['id'], $periode);
        $r = [
            'karyawan' => $k,
            'slip' => null,
            'status' => 'BELUM',
            'pendapatan' => (float) $h['total_pendapatan'],
            'potongan' => (float) $h['total_potongan'],
            'thp' => (float) $h['take_home_pay'],
            'hadir' => (float) $h['rekap']['hadir'],
            'minggu' => (float) $h['rekap']['hadir_minggu'],
            'bonus' => (float) $h['total_bonus'],
            'panjar' => (float) $h['panjar']['total'],
            'alpa' => (float) $h['rekap']['alpa'],
            'terlambat' => (int) $h['rekap']['menit_terlambat'],
            'lembur' => (int) $h['lembur']['menit'],
        ];
    }
    $total['pendapatan'] += $r['pendapatan'];
    $total['potongan'] += $r['potongan'];
    $total['thp'] += $r['thp'];
    $baris[] = $r;
}
usort($baris, fn($a, $b) => strcmp((string) $a['karyawan']['nama'], (string) $b['karyawan']['nama']));
$jmlFinal = count(array_filter($baris, fn($r) => $r['status'] === 'FINAL'));
$jmlDraft = count(array_filter($baris, fn($r) => $r['status'] === 'DRAFT'));
$jmlBelum = count(array_filter($baris, fn($r) => $r['status'] === 'BELUM'));

page_head('Penggajian', 'payroll.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Penggajian <?= h(bulan_indo($periode)) ?></h1>
        <p class="muted">
            <?= (int) $kal['jumlah'] ?> hari kerja (basis <?= h($kal['mode']) ?>) ·
            upah/jam dari <?= h(conf('basis_upah_per_jam') === 'gaji_pokok' ? 'Gaji Pokok' : 'Gaji Pokok + Tunjangan Tetap') ?>
            ÷ <?= h(angka($kal['jumlah'])) ?> hari ÷ <?= h(angka(conf_num('jam_kerja_per_hari', 8), 1)) ?> jam.
        </p>
    </div>
    <form method="get" class="btn-baris">
        <input type="month" name="periode" value="<?= h($periode) ?>" onchange="this.form.submit()">
        <select name="divisi" onchange="this.form.submit()">
            <option value="">Semua divisi</option>
            <?php foreach (daftar_divisi() as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= $filterDivisi === (string) $d['id'] ? 'selected' : '' ?>><?= h($d['nama']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php flash(); ?>

<div class="kartu-grid grid-4" style="margin-bottom:22px">
    <div class="stat"><span class="stat-ikon"><?= icon('users') ?></span><span class="stat-label">Karyawan</span><span class="stat-nilai"><?= count($baris) ?></span><span class="stat-sub"><?= $jmlBelum ?> belum dihitung</span></div>
    <div class="stat is-hijau"><span class="stat-ikon"><?= icon('wallet') ?></span><span class="stat-label">Total Pendapatan</span><span class="stat-nilai kecil"><?= h(rp($total['pendapatan'])) ?></span><span class="stat-sub">sebelum potongan</span></div>
    <div class="stat is-merah"><span class="stat-ikon"><?= icon('receipt') ?></span><span class="stat-label">Total Potongan</span><span class="stat-nilai kecil"><?= h(rp($total['potongan'])) ?></span><span class="stat-sub">absensi, BPJS, pajak, kasbon</span></div>
    <div class="stat"><span class="stat-ikon"><?= icon('chart') ?></span><span class="stat-label">Total Take Home Pay</span><span class="stat-nilai kecil"><?= h(rp($total['thp'])) ?></span><span class="stat-sub"><?= $jmlFinal ?> final · <?= $jmlDraft ?> draft</span></div>
</div>

<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2><?= icon('wallet', 17) ?> Perhitungan Slip Gaji</h2>
            <p class="muted">Angka diambil sepenuhnya dari halaman Pengaturan. Menekan “Hitung Ulang” akan menimpa slip periode ini.</p>
        </div>
        <div class="btn-baris">
            <form method="post">
                <input type="hidden" name="aksi" value="hitung_semua">
                <button class="btn btn-primer" type="submit">Hitung Semua (Draft)</button>
            </form>
            <form method="post" data-konfirmasi="Finalkan semua slip periode ini? Slip final sebaiknya tidak dihitung ulang.">
                <input type="hidden" name="aksi" value="finalkan_semua">
                <button class="btn btn-hijau" type="submit">Finalkan Semua</button>
            </form>
            <a class="btn" href="slip.php?periode=<?= h($periode) ?>&semua=1&cetak=1" target="_blank"><?= icon('print', 16) ?> Cetak Semua</a>
        </div>
    </div>

    <?php if ($baris): ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th class="angka">Hadir</th><th class="angka">Minggu</th><th class="angka">Alpa</th>
                        <th class="angka">Terlambat</th><th class="angka">Lembur</th>
                        <th class="angka">Pendapatan</th><th class="angka">Potongan</th><th class="angka">Take Home Pay</th>
                        <th>Status</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($baris as $r): $k = $r['karyawan']; ?>
                    <tr>
                        <td>
                            <strong><?= h($k['nama']) ?></strong><br>
                            <span class="muted"><?= h($k['nip']) ?> · <?= h($k['jabatan']) ?><?= $k['divisi_nama'] ? ' · ' . h($k['divisi_nama']) : '' ?></span>
                        </td>
                        <td class="angka"><?= h(angka($r['hadir'])) ?></td>
                        <td class="angka"><?= h(angka($r['minggu'])) ?></td>
                        <td class="angka"><?= h(angka($r['alpa'])) ?></td>
                        <td class="angka"><?= (int) $r['terlambat'] ?> mnt</td>
                        <td class="angka"><?= h(angka($r['lembur'] / 60, 2)) ?> jam</td>
                        <td class="angka"><?= h(rp($r['pendapatan'])) ?></td>
                        <td class="angka"><?= h(rp($r['potongan'])) ?></td>
                        <td class="angka"><strong><?= h(rp($r['thp'])) ?></strong></td>
                        <td>
                            <?php if ($r['status'] === 'FINAL'): ?><span class="lencana lencana-hijau">Final</span>
                            <?php elseif ($r['status'] === 'DRAFT'): ?><span class="lencana lencana-kuning">Draft</span>
                            <?php else: ?><span class="lencana lencana-abu">Belum dihitung</span><?php endif; ?>
                        </td>
                        <td class="nowrap">
                            <?php if ($r['slip']): ?>
                                <a class="btn btn-kecil" href="slip.php?id=<?= (int) $r['slip']['id'] ?>">Slip</a>
                                <a class="btn btn-kecil" href="slip.php?id=<?= (int) $r['slip']['id'] ?>&cetak=1" target="_blank">Cetak</a>
                            <?php else: ?>
                                <a class="btn btn-kecil" href="slip.php?karyawan=<?= (int) $k['id'] ?>&periode=<?= h($periode) ?>">Rincian</a>
                            <?php endif; ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="aksi" value="hitung_satu">
                                <input type="hidden" name="karyawan_id" value="<?= (int) $k['id'] ?>">
                                <button class="btn btn-kecil" type="submit">Hitung</button>
                            </form>
                            <?php if ($r['slip'] && $r['status'] !== 'FINAL'): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="aksi" value="finalkan_satu">
                                    <input type="hidden" name="karyawan_id" value="<?= (int) $k['id'] ?>">
                                    <button class="btn btn-kecil btn-hijau" type="submit">Finalkan</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($r['slip']): ?>
                                <form method="post" style="display:inline" data-konfirmasi="Hapus slip ini? Bisa dihitung ulang kapan saja.">
                                    <input type="hidden" name="aksi" value="hapus_slip">
                                    <input type="hidden" name="slip_id" value="<?= (int) $r['slip']['id'] ?>">
                                    <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="baris-total">
                        <td>Total</td><td class="angka"></td><td class="angka"></td><td class="angka"></td><td class="angka"></td><td class="angka"></td>
                        <td class="angka"><?= h(rp($total['pendapatan'])) ?></td>
                        <td class="angka"><?= h(rp($total['potongan'])) ?></td>
                        <td class="angka"><?= h(rp($total['thp'])) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <div class="kosong"><strong>Tidak ada karyawan aktif</strong><a href="karyawan.php">Tambahkan karyawan terlebih dahulu</a>.</div>
    <?php endif; ?>
</div>

<div class="kartu">
    <h3><?= icon('gear', 17) ?> Rumus yang dipakai periode ini</h3>
    <div class="kartu-grid grid-2">
        <div>
            <strong class="muted">UPAH (rumus perusahaan ini)</strong>
            <ul class="daftar-sederhana" style="margin-top:6px">
                <li><strong>Upah harian</strong> = (<?= h(conf('basis_upah_per_jam') === 'gaji_pokok' ? 'Gaji Pokok' : 'Gaji Pokok + Tunjangan Tetap') ?>)
                    ÷ <strong><?= h(angka($kal['jumlah'])) ?> hari kerja</strong>
                    <span class="muted"><?= $kal['mode'] === 'otomatis' ? '(otomatis: ' . (int) $kal['kalender'] . ' hari Senin–Sabtu' . ($kal['kurangi_libur'] ? ' dikurangi libur nasional' : '') . '; ' . (int) $kal['jumlah_minggu'] . ' hari Minggu TIDAK dihitung)' : '(angka tetap dari Pengaturan)' ?></span>
                    = <strong><?= h(rp(round(hitung_upah_contoh()['per_hari'] ?? 0))) ?></strong>
                    <?= conf('upah_mode') === 'proporsional_hadir' ? '(dibayar × jumlah hari hadir)' : '(gaji pokok dibayar penuh)' ?></li>
                <li><strong>Upah Lembur Minggu</strong> = jam kerja Minggu × <strong><?= h(rp(conf_num('minggu_tarif_jam', 0))) ?></strong> per jam
                    <?= conf('minggu_mode') === 'tarif_jam' ? '(Minggu berdiri sendiri — tidak masuk pembagi hari kerja & tidak memakai upah harian)' : '(mode lama: upah harian + upah lembur minggu)' ?></li>
                <li><strong>Upah lembur per jam</strong> = <?= h(conf('basis_upah_per_jam') === 'gaji_pokok' ? 'Gaji Pokok' : 'Gaji Pokok + Tunjangan Tetap') ?>
                    ÷ <strong><?= h(angka(conf_num('lembur_pembagi', 173))) ?></strong></li>
            </ul>
            <strong class="muted">JAM KERJA</strong>
            <ul class="daftar-sederhana" style="margin-top:6px">
                <li><strong>Jam kerja per hari</strong> = <?= h(jam_kerja_teks()) ?> (istirahat <?= h(durasi_indo(menit_istirahat_harian())) ?> tidak dihitung)</li>
            </ul>
            <strong class="muted">LEMBUR</strong>
            <ul class="daftar-sederhana" style="margin-top:6px">
                <li><strong>Hari kerja (Senin–Sabtu)</strong> = jam lembur × upah lembur per jam<?= conf_bool('lembur_pakai_faktor', false) ? ' × faktor ' . h(angka(conf_num('lembur_faktor_pertama', 1.5), 2)) . '× (jam pertama) / ' . h(angka(conf_num('lembur_faktor_berikutnya', 2), 2)) . '× (berikutnya)' : ' <span class="lencana lencana-hijau">tanpa faktor pengali</span>' ?></li>
                <li><strong>Hari libur nasional</strong> = jam × upah lembur per jam<?= conf_bool('lembur_pakai_faktor', false) ? ' × ' . h(angka(conf_num('lembur_faktor_hari_libur', 2), 2)) . '×' : '' ?></li>
            </ul>
        </div>
        <div>
            <strong class="muted">ABSENSI</strong>
            <ul class="daftar-sederhana" style="margin-top:6px">
                <li><strong>Toleransi keterlambatan</strong> = <?= (int) conf_int('absensi_toleransi_menit') ?> menit
                    <?= conf_bool('potongan_denda_aktif') ? '(denda aktif: ' . h(conf('absensi_denda_mode')) . ')' : '(potongan denda dimatikan)' ?></li>
                <li><strong>Potongan alpa</strong> = <?= conf_bool('potongan_alpa_aktif') ? h(conf('absensi_alpa_mode')) : 'dimatikan' ?>
                    · <strong>izin</strong> = <?= conf_bool('potongan_izin_aktif') ? h(conf('absensi_izin_mode')) : 'dimatikan' ?>
                    · <strong>sakit</strong> = <?= conf_bool('potongan_sakit_aktif') ? h(conf('absensi_sakit_mode')) : 'dimatikan' ?></li>
            </ul>
            <strong class="muted">BONUS (<?= count(db()->query('SELECT id FROM bonus_parameter WHERE aktif = 1')->fetchAll()) ?> parameter aktif)</strong>
            <ul class="daftar-sederhana" style="margin-top:6px">
                <?php foreach (db()->query('SELECT * FROM bonus_parameter WHERE aktif = 1 ORDER BY urutan, nama') as $b): ?>
                    <li><strong><?= h($b['nama']) ?></strong> — <?= h(tipe_bonus()[$b['tipe']] ?? $b['tipe']) ?><?= $b['tipe'] === 'persen_pokok' ? '' : ': <strong>' . h(angka((float) $b['nilai'], 0)) . '</strong>' ?>
                        <?php if ((int) $b['berlaku_semua'] !== 1): ?><span class="lencana lencana-kuning">khusus</span><?php endif; ?>
                        <?php if ((int) $b['syarat_alpa_nol'] === 1): ?><span class="lencana lencana-abu">tanpa alpa</span><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
            <strong class="muted">POTONGAN YANG AKTIF</strong>
            <ul class="daftar-sederhana" style="margin-top:6px">
                <?php $adaPotongan = false; foreach (conf_kelompok('potongan') as $kunci => $m): if (!conf_bool($kunci)) { continue; } $adaPotongan = true; ?>
                    <li><?= h((string) $m['label']) ?></li>
                <?php endforeach; ?>
                <?php if (conf_bool('bpjs_aktif')): $adaPotongan = true; ?><li>BPJS Ketenagakerjaan &amp; Kesehatan (karyawan <?= h(angka(conf_num('bpjs_tk_karyawan_persen') + conf_num('bpjs_kes_karyawan_persen'), 2)) ?>%)</li><?php endif; ?>
                <?php if (conf_bool('pajak_aktif')): $adaPotongan = true; ?><li>PPh 21 (metode per karyawan)</li><?php endif; ?>
                <?php if (!$adaPotongan): ?><li class="muted">Tidak ada potongan aktif.</li><?php endif; ?>
            </ul>
            <div class="btn-baris" style="margin-top:8px">
                <a class="btn btn-kecil" href="pengaturan.php?tab=potongan">Atur Potongan</a>
                <a class="btn btn-kecil" href="pengaturan.php?tab=bonus">Atur Bonus</a>
                <a class="btn btn-kecil" href="pengaturan.php?tab=jamkerja">Atur Jam Kerja</a>
            </div>
        </div>
    </div>
    <p class="muted" style="margin-top:10px">Semua nilai di atas bisa diubah di Pengaturan dan langsung berlaku pada perhitungan berikutnya.</p>
</div>
<?php page_foot(); ?>
