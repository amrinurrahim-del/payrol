<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();
demo_siapkan();

/* -------------------------------------------------------- aksi (POST) */
if (is_post()) {
    $aksi = post('aksi');
    if ($aksi === 'simpan_slip' || $aksi === 'simpan_final') {
        $kid = post_int('karyawan_id');
        $per = periode_valid(post('periode'));
        $hasil = hitung_slip($kid, $per);
        if (empty($hasil['ok'])) {
            redirect('slip.php?periode=' . $per . '&err=' . rawurlencode('Karyawan tidak ditemukan.'));
        }
        $status = $aksi === 'simpan_final' ? 'FINAL' : 'DRAFT';
        $id = slip_simpan($hasil, sesi_username(), $status);
        log_admin('slip_simpan', 'Slip karyawan #' . $kid . ' periode ' . $per . ' → ' . $status);
        redirect('slip.php?id=' . $id . '&ok=' . rawurlencode('Slip disimpan sebagai ' . $status . '.'));
    }
}

/* -------------------------------------------------------- parameter */
$idSlip  = (int) ($_GET['id'] ?? 0);
$periode = periode_valid((string) ($_GET['periode'] ?? date('Y-m')));
$kid     = (int) ($_GET['karyawan'] ?? 0);
$semua   = !empty($_GET['semua']);
$cetak   = !empty($_GET['cetak']);

/* Tampilkan satu slip dari parameter ?id= atau ?karyawan=&periode= */
if ($idSlip > 0 || $kid > 0) {
    $live = null;
    if ($idSlip > 0) {
        $s = slip_by_id($idSlip);
        if (!$s) {
            redirect('slip.php?periode=' . $periode . '&err=' . rawurlencode('Slip tidak ditemukan.'));
        }
        $periode = (string) $s['periode'];
        $itemsPendapatan = slip_item($idSlip, 'PENDAPATAN');
        $itemsPotongan   = slip_item($idSlip, 'POTONGAN');
    } else {
        /* Belum tersimpan → hitung langsung dari konfigurasi terkini. */
        $h = hitung_slip($kid, $periode);
        if (empty($h['ok'])) {
            redirect('slip.php?periode=' . $periode . '&err=' . rawurlencode('Karyawan tidak ditemukan.'));
        }
        $live = $h;
        $kar = $h['karyawan'];
        $s = [
            'id' => 0, 'nomor' => '(belum disimpan)', 'periode' => $periode,
            'nama' => $kar['nama'], 'nip' => $kar['nip'], 'jabatan' => $kar['jabatan'],
            'divisi' => (string) ($kar['divisi_nama'] ?? ''), 'tanggal_proses' => date('Y-m-d'),
            'status' => 'PRATINJAU', 'metode_pajak' => $kar['metode_pajak'], 'ptkp_kode' => $kar['ptkp_kode'],
            'bank' => $kar['bank'], 'no_rekening' => $kar['no_rekening'],
            'hari_kerja' => $h['kalender']['jumlah'], 'hari_hadir' => $h['rekap']['hadir'],
            'hari_alpa' => $h['rekap']['alpa'], 'hari_izin' => $h['rekap']['izin'],
            'hari_sakit' => $h['rekap']['sakit'], 'hari_cuti' => $h['rekap']['cuti'],
            'total_menit_terlambat' => $h['rekap']['menit_terlambat'], 'total_menit_lembur' => $h['lembur']['menit'],
            'upah_per_jam' => $h['upah']['per_jam'], 'total_pendapatan' => $h['total_pendapatan'],
            'total_potongan' => $h['total_potongan'], 'take_home_pay' => $h['take_home_pay'],
            'karyawan_id' => (int) $kar['id'],
        ];
        $itemsPendapatan = [];
        foreach ($h['pendapatan'] as $i => $p) {
            $itemsPendapatan[] = ['nama' => $p['nama'], 'kategori' => $p['kategori'] ?? '', 'nilai' => $p['nilai'], 'urutan' => $i + 1];
        }
        $itemsPotongan = [];
        foreach ($h['potongan'] as $i => $p) {
            $itemsPotongan[] = ['nama' => $p['nama'], 'kategori' => $p['kategori'] ?? '', 'nilai' => $p['nilai'], 'urutan' => $i + 1];
        }
    }

    page_head('Slip ' . $s['nama'], 'slip.php', !$cetak);
    ?>
    <div class="slip-aksi no-print">
        <a class="btn" href="slip.php?periode=<?= h($periode) ?>">‹ Daftar Slip <?= h(bulan_indo($periode)) ?></a>
        <button class="btn btn-primer" type="button" data-cetak><?= icon('print', 16) ?> Cetak Slip Gaji</button>
        <?php if ($idSlip > 0): ?>
            <a class="btn" href="slip.php?id=<?= (int) $idSlip ?>&cetak=1" target="_blank">Versi Cetak (tab baru)</a>
        <?php else: ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="aksi" value="simpan_slip">
                <input type="hidden" name="karyawan_id" value="<?= (int) $s['karyawan_id'] ?>">
                <input type="hidden" name="periode" value="<?= h($periode) ?>">
                <button class="btn btn-hijau" type="submit">Simpan sebagai Draft</button>
            </form>
            <form method="post" style="display:inline">
                <input type="hidden" name="aksi" value="simpan_final">
                <input type="hidden" name="karyawan_id" value="<?= (int) $s['karyawan_id'] ?>">
                <input type="hidden" name="periode" value="<?= h($periode) ?>">
                <button class="btn" type="submit">Simpan &amp; Finalkan</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($live !== null): ?>
        <div class="catatan catatan-info no-print" style="max-width:940px">
            Slip ini <strong>belum tersimpan</strong> — ditampilkan sebagai pratinjau hasil perhitungan konfigurasi saat ini.
            Tekan “Simpan sebagai Draft” atau “Simpan &amp; Finalkan” agar nilainya tersimpan sebagai riwayat.
        </div>
    <?php endif; ?>
    <?php
    flash();
    if ($live !== null) {
        slip_tampil($s, $itemsPendapatan, $itemsPotongan, $live);
    } else {
        slip_tampil($s, $itemsPendapatan, $itemsPotongan, null);
    }
    page_foot(!$cetak);
    exit;
}

/* -------------------------------------------------------- daftar slip */
$daftar = slip_daftar($periode);
$totalThp = 0.0;
foreach ($daftar as $s) {
    $totalThp += (float) $s['take_home_pay'];
}

page_head('Slip Gaji', 'slip.php', !$cetak);
?>
<div class="judul-halaman no-print">
    <div>
        <h1>Slip Gaji <?= h(bulan_indo($periode)) ?></h1>
        <p class="muted"><?= count($daftar) ?> slip tersimpan · total take home pay <?= h(rp($totalThp)) ?></p>
    </div>
    <form method="get" class="btn-baris">
        <input type="month" name="periode" value="<?= h($periode) ?>" onchange="this.form.submit()">
        <?php if ($daftar): ?>
            <a class="btn btn-primer" href="slip.php?periode=<?= h($periode) ?>&semua=1&cetak=1" target="_blank"><?= icon('print', 16) ?> Cetak Semua Slip</a>
        <?php endif; ?>
    </form>
</div>
<?php flash(); ?>

<?php if ($cetak && $semua): ?>
    <?php foreach ($daftar as $s):
        slip_tampil($s, slip_item((int) $s['id'], 'PENDAPATAN'), slip_item((int) $s['id'], 'POTONGAN'), null);
    endforeach; ?>
    <?php if (!$daftar): ?>
        <div class="catatan">Belum ada slip tersimpan untuk periode <?= h(bulan_indo($periode)) ?>.</div>
    <?php endif; ?>
<?php else: ?>
    <div class="kartu no-print">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('receipt', 17) ?> Daftar Slip Tersimpan</h2>
                <p class="muted">Hanya slip yang sudah dihitung dan disimpan yang muncul di sini.</p>
            </div>
            <a class="btn btn-kecil" href="payroll.php?periode=<?= h($periode) ?>">Buka Penggajian</a>
        </div>
        <?php if ($daftar): ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr><th>Nomor</th><th>Karyawan</th><th>Jabatan / Divisi</th><th>Periode</th>
                            <th class="angka">Pendapatan</th><th class="angka">Potongan</th><th class="angka">Take Home Pay</th>
                            <th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($daftar as $s): ?>
                        <tr>
                            <td class="nowrap"><?= h((string) $s['nomor']) ?></td>
                            <td><strong><?= h($s['nama']) ?></strong><br><span class="muted"><?= h($s['nip']) ?></span></td>
                            <td><?= h((string) $s['jabatan']) ?><br><span class="muted"><?= h((string) $s['divisi']) ?></span></td>
                            <td class="nowrap"><?= h(bulan_indo((string) $s['periode'])) ?></td>
                            <td class="angka"><?= h(rp((float) $s['total_pendapatan'])) ?></td>
                            <td class="angka"><?= h(rp((float) $s['total_potongan'])) ?></td>
                            <td class="angka"><strong><?= h(rp((float) $s['take_home_pay'])) ?></strong></td>
                            <td><span class="lencana <?= $s['status'] === 'FINAL' ? 'lencana-hijau' : 'lencana-kuning' ?>"><?= h(ucfirst(strtolower((string) $s['status']))) ?></span></td>
                            <td class="nowrap">
                                <a class="btn btn-kecil" href="slip.php?id=<?= (int) $s['id'] ?>">Lihat</a>
                                <a class="btn btn-kecil" href="slip.php?id=<?= (int) $s['id'] ?>&cetak=1" target="_blank">Cetak</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="baris-total"><td colspan="6">Total</td><td class="angka"><?= h(rp($totalThp)) ?></td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="kosong">
                <strong>Belum ada slip untuk <?= h(bulan_indo($periode)) ?></strong>
                Buka <a href="payroll.php?periode=<?= h($periode) ?>">Penggajian</a> lalu tekan “Hitung Semua (Draft)”.
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php page_foot(!$cetak); ?>
