<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();
demo_siapkan();

$periode = periode_valid((string) ($_GET['periode'] ?? date('Y-m')));
$jenis   = in_array((string) ($_GET['jenis'] ?? 'gaji'), ['gaji', 'absensi', 'komponen'], true) ? (string) $_GET['jenis'] : 'gaji';
$filterDivisi = (string) ($_GET['divisi'] ?? '');

/* Bangun data: pakai slip tersimpan bila ada, jika belum dihitung pakai pratinjau mesin payroll. */
$karyawan = daftar_karyawan('', true, $filterDivisi);
$tersimpan = [];
foreach (slip_daftar($periode, $filterDivisi) as $s) {
    $tersimpan[(int) $s['karyawan_id']] = $s;
}
$rows = [];
foreach ($karyawan as $k) {
    $s = $tersimpan[(int) $k['id']] ?? null;
    if ($s) {
        $rows[] = [
            'id' => (int) $k['id'],
            'nip' => (string) $k['nip'], 'nama' => (string) $k['nama'], 'jabatan' => (string) $k['jabatan'],
            'divisi' => (string) ($k['divisi_nama'] ?? ''), 'hadir' => (float) $s['hari_hadir'],
            'alpa' => (float) $s['hari_alpa'], 'izin' => (float) $s['hari_izin'], 'sakit' => (float) $s['hari_sakit'],
            'cuti' => (float) $s['hari_cuti'], 'terlambat' => (int) $s['total_menit_terlambat'],
            'minggu' => (float) ($s['hari_minggu'] ?? 0),
            'bonus' => (float) ($s['total_bonus'] ?? 0), 'panjar' => (float) ($s['total_panjar'] ?? 0),
            'lembur' => (int) $s['total_menit_lembur'], 'pendapatan' => (float) $s['total_pendapatan'],
            'potongan' => (float) $s['total_potongan'], 'thp' => (float) $s['take_home_pay'],
            'status' => (string) $s['status'], 'slip_id' => (int) $s['id'], 'metode' => (string) $s['metode_pajak'],
            /* ptkp diambil dari data karyawan ($k) — slip_daftar() tidak ikut memilih kolom ini. */
            'ptkp' => (string) ($k['ptkp_kode'] ?? ''),
        ];
        continue;
    }
    $h = hitung_slip((int) $k['id'], $periode);
    if (empty($h['ok'])) {
        continue;
    }
    $rows[] = [
        'id' => (int) $k['id'],
        'nip' => (string) $k['nip'], 'nama' => (string) $k['nama'], 'jabatan' => (string) $k['jabatan'],
        'divisi' => (string) ($k['divisi_nama'] ?? ''), 'hadir' => (float) $h['rekap']['hadir'],
        'alpa' => (float) $h['rekap']['alpa'], 'izin' => (float) $h['rekap']['izin'], 'sakit' => (float) $h['rekap']['sakit'],
        'cuti' => (float) $h['rekap']['cuti'], 'terlambat' => (int) $h['rekap']['menit_terlambat'],
        'minggu' => (float) $h['rekap']['hadir_minggu'],
        'bonus' => (float) $h['total_bonus'], 'panjar' => (float) $h['panjar']['total'],
        'lembur' => (int) ($h['lembur']['menit'] + $h['lembur_libur']['menit']), 'pendapatan' => (float) $h['total_pendapatan'],
        'potongan' => (float) $h['total_potongan'], 'thp' => (float) $h['take_home_pay'],
        'status' => 'PRATINJAU', 'slip_id' => 0, 'metode' => (string) $k['metode_pajak'],
        'ptkp' => (string) $k['ptkp_kode'],
    ];
}

/* Rekap per divisi */
$perDivisi = [];
foreach ($rows as $r) {
    $d = $r['divisi'] !== '' ? $r['divisi'] : 'Tanpa Divisi';
    $perDivisi[$d] = $perDivisi[$d] ?? ['jml' => 0, 'pendapatan' => 0.0, 'potongan' => 0.0, 'thp' => 0.0];
    $perDivisi[$d]['jml']++;
    $perDivisi[$d]['pendapatan'] += $r['pendapatan'];
    $perDivisi[$d]['potongan'] += $r['potongan'];
    $perDivisi[$d]['thp'] += $r['thp'];
}

/* Rekap komponen (dari item slip tersimpan) */
$komponen = [];
foreach ($tersimpan as $s) {
    foreach (slip_item((int) $s['id']) as $i) {
        $key = $i['jenis'] . '|' . $i['nama'];
        $komponen[$key] = $komponen[$key] ?? ['jenis' => $i['jenis'], 'nama' => (string) $i['nama'], 'kategori' => (string) $i['kategori'], 'jml' => 0, 'nilai' => 0.0];
        $komponen[$key]['jml']++;
        $komponen[$key]['nilai'] += (float) $i['nilai'];
    }
}
ksort($komponen);

/* ---------------------------------------------------------- unduh CSV */
if (($tok = (string) ($_GET['unduh'] ?? '')) !== '') {
    $nama = 'rekap-' . $jenis . '-' . $periode . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar rapi dibuka di Excel
    if ($jenis === 'gaji') {
        fputcsv($out, ['NIP', 'Nama', 'Jabatan', 'Divisi', 'Hadir', 'Hadir Minggu', 'Alpa', 'Izin', 'Sakit', 'Cuti', 'Terlambat (menit)', 'Lembur (menit)', 'Bonus', 'Potongan Panjar', 'Pendapatan', 'Potongan', 'Take Home Pay', 'Status'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [$r['nip'], $r['nama'], $r['jabatan'], $r['divisi'], $r['hadir'], $r['minggu'], $r['alpa'], $r['izin'], $r['sakit'], $r['cuti'], $r['terlambat'], $r['lembur'], $r['bonus'], $r['panjar'], $r['pendapatan'], $r['potongan'], $r['thp'], $r['status']], ';');
        }
    } elseif ($jenis === 'absensi') {
        $kal = hari_kerja_bulan($periode);
        $hariLibur = $kal['libur'];
        $header = ['NIP', 'Nama', 'Jabatan', 'Divisi'];
        foreach ($kal['tanggal'] as $t) {
            $header[] = date('d/m', strtotime($t));
        }
        $header[] = 'Hadir';
        $header[] = 'Alpa';
        $header[] = 'Lembur (menit)';
        fputcsv($out, $header, ';');
        foreach ($karyawan as $k) {
            $peta = [];
            $st = db()->prepare('SELECT tanggal, status FROM absensi WHERE karyawan_id = ? AND tanggal LIKE ?');
            $st->execute([(int) $k['id'], $periode . '%']);
            foreach ($st as $a) {
                $peta[$a['tanggal']] = $a['status'];
            }
            $rek = absensi_rekap((int) $k['id'], $periode);
            $baris = [$k['nip'], $k['nama'], $k['jabatan'], (string) ($k['divisi_nama'] ?? '')];
            foreach ($kal['tanggal'] as $t) {
                $s = $peta[$t] ?? '';
                $baris[] = $s === '' ? '-' : substr($s, 0, 1);
            }
            $baris[] = $rek['hadir'];
            $baris[] = $rek['alpa'];
            $baris[] = $rek['menit_lembur'];
            fputcsv($out, $baris, ';');
        }
    } else {
        fputcsv($out, ['Jenis', 'Nama Komponen', 'Kategori', 'Jumlah Slip', 'Total Nilai'], ';');
        foreach ($komponen as $c) {
            fputcsv($out, [$c['jenis'], $c['nama'], $c['kategori'], $c['jml'], $c['nilai']], ';');
        }
    }
    fclose($out);
    exit;
}

$kal = hari_kerja_bulan($periode);
$liburMap = $kal['libur'];
$simbol = conf('mata_uang_simbol');
$totalGaji = array_sum(array_column($rows, 'thp'));
$totalPotongan = array_sum(array_column($rows, 'potongan'));
$totalPendapatan = array_sum(array_column($rows, 'pendapatan'));

page_head('Laporan', 'laporan.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Laporan &amp; Rekapitulasi</h1>
        <p class="muted">Periode <?= h(bulan_indo($periode)) ?> · <?= count($rows) ?> karyawan · total gaji bersih <strong><?= h(rp($totalGaji)) ?></strong></p>
    </div>
    <form method="get" class="btn-baris">
        <input type="hidden" name="jenis" value="<?= h($jenis) ?>">
        <input type="month" name="periode" value="<?= h($periode) ?>" onchange="this.form.submit()">
        <select name="divisi" onchange="this.form.submit()">
            <option value="">Semua divisi</option>
            <?php foreach (daftar_divisi() as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= $filterDivisi === (string) $d['id'] ? 'selected' : '' ?>><?= h($d['nama']) ?></option>
            <?php endforeach; ?>
        </select>
        <a class="btn" href="laporan.php?jenis=<?= h($jenis) ?>&periode=<?= h($periode) ?>&divisi=<?= h($filterDivisi) ?>&unduh=csv">Unduh CSV</a>
                </form>
</div>

<?php flash(); ?>

<?php if (array_filter($rows, fn($r) => $r['status'] === 'PRATINJAU')): ?>
    <div class="catatan catatan-info" style="margin-bottom:18px">
        Sebagian karyawan belum dihitung slipnya untuk periode ini, sehingga angkanya masih berupa
        <strong>pratinjau</strong> dari mesin payroll. Hitung di <a href="payroll.php?periode=<?= h($periode) ?>">Penggajian</a> agar tersimpan sebagai riwayat.
    </div>
<?php endif; ?>

<div class="tab-baris">
    <a class="tab<?= $jenis === 'gaji' ? ' is-aktif' : '' ?>" href="laporan.php?jenis=gaji&periode=<?= h($periode) ?>&divisi=<?= h($filterDivisi) ?>">Rekap Gaji</a>
    <a class="tab<?= $jenis === 'absensi' ? ' is-aktif' : '' ?>" href="laporan.php?jenis=absensi&periode=<?= h($periode) ?>&divisi=<?= h($filterDivisi) ?>">Rekap Absensi</a>
    <a class="tab<?= $jenis === 'komponen' ? ' is-aktif' : '' ?>" href="laporan.php?jenis=komponen&periode=<?= h($periode) ?>&divisi=<?= h($filterDivisi) ?>">Rekap Komponen</a>
</div>

<?php if ($jenis === 'gaji'): ?>
    <div class="kartu-grid grid-3" style="margin-bottom:22px">
        <div class="stat is-hijau"><span class="stat-ikon"><?= icon('wallet') ?></span><span class="stat-label">Total Pendapatan</span><span class="stat-nilai kecil"><?= h(rp($totalPendapatan)) ?></span></div>
        <div class="stat is-merah"><span class="stat-ikon"><?= icon('receipt') ?></span><span class="stat-label">Total Potongan</span><span class="stat-nilai kecil"><?= h(rp($totalPotongan)) ?></span></div>
        <div class="stat"><span class="stat-ikon"><?= icon('chart') ?></span><span class="stat-label">Take Home Pay</span><span class="stat-nilai kecil"><?= h(rp($totalGaji)) ?></span></div>
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('users', 17) ?> Rincian Gaji per Karyawan</h2>
                <p class="muted">Terbilang total: <em><?= h(terbilang_rupiah($totalGaji)) ?></em></p>
            </div>
        </div>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr><th>NIP</th><th>Nama</th><th>Jabatan / Divisi</th><th>PTKP</th>
                        <th class="angka">Hadir</th><th class="angka">Minggu</th><th class="angka">Alpa</th><th class="angka">Terlambat</th><th class="angka">Lembur</th>
                        <th class="angka">Bonus</th><th class="angka">Panjar</th>
                        <th class="angka">Pendapatan</th><th class="angka">Potongan</th><th class="angka">Take Home Pay</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="nowrap"><?= h($r['nip']) ?></td>
                        <td><strong><?= h($r['nama']) ?></strong></td>
                        <td><?= h($r['jabatan']) ?><br><span class="muted"><?= h($r['divisi'] ?: '-') ?></span></td>
                        <td class="nowrap"><?= h($r['ptkp']) ?></td>
                        <td class="angka"><?= h(angka($r['hadir'])) ?></td>
                        <td class="angka"><?= h(angka($r['minggu'])) ?></td>
                        <td class="angka"><?= h(angka($r['alpa'])) ?></td>
                        <td class="angka"><?= (int) $r['terlambat'] ?> mnt</td>
                        <td class="angka"><?= h(angka($r['lembur'] / 60, 2)) ?> jam</td>
                        <td class="angka"><?= h(angka($r['bonus'])) ?></td>
                        <td class="angka"><?= h(angka($r['panjar'])) ?></td>
                        <td class="angka"><?= h(angka($r['pendapatan'])) ?></td>
                        <td class="angka"><?= h(angka($r['potongan'])) ?></td>
                        <td class="angka"><strong><?= h(angka($r['thp'])) ?></strong></td>
                        <td class="nowrap">
                            <?php if ($r['slip_id'] > 0): ?>
                                <a class="btn btn-kecil" href="slip.php?id=<?= (int) $r['slip_id'] ?>">Slip</a>
                            <?php else: ?>
                                <a class="btn btn-kecil" href="slip.php?karyawan=<?= (int) $r['id'] ?>&periode=<?= h($periode) ?>">Rincian</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="baris-total">
                        <td colspan="10">Total</td>
                        <td class="angka"><?= h(angka($totalPendapatan)) ?></td>
                        <td class="angka"><?= h(angka($totalPotongan)) ?></td>
                        <td class="angka"><?= h(angka($totalGaji)) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="kartu">
        <h2><?= icon('chart', 17) ?> Rekap per Divisi</h2>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead><tr><th>Divisi</th><th class="angka">Karyawan</th><th class="angka">Pendapatan</th><th class="angka">Potongan</th><th class="angka">Take Home Pay</th></tr></thead>
                <tbody>
                <?php ksort($perDivisi); foreach ($perDivisi as $nama => $d): ?>
                    <tr>
                        <td><?= h($nama) ?></td>
                        <td class="angka"><?= $d['jml'] ?></td>
                        <td class="angka"><?= h(angka($d['pendapatan'])) ?></td>
                        <td class="angka"><?= h(angka($d['potongan'])) ?></td>
                        <td class="angka"><strong><?= h(angka($d['thp'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted" style="margin-top:12px">Nilai ditampilkan dalam <?= h($simbol) ?> (tanpa simbol untuk memudahkan pembacaan tabel).</p>
    </div>

<?php elseif ($jenis === 'absensi'): ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('calendar', 17) ?> Daftar Hadir <?= h(bulan_indo($periode)) ?></h2>
                <p class="muted">
                    Kode: <strong>H</strong> hadir · <strong>I</strong> izin · <strong>S</strong> sakit · <strong>C</strong> cuti ·
                    <strong>A</strong> alpa · <strong>L</strong> libur · <strong>-</strong> belum dicatat.
                    Tanggal merah pada kepala kolom = hari libur / akhir pekan.
                </p>
            </div>
        </div>
        <div class="tabel-bungkus">
            <table class="tabel dikit">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <?php foreach ($kal['tanggal'] as $t): ?><th class="tengah"><?= h(date('d', strtotime($t))) ?></th><?php endforeach; ?>
                        <th class="angka">Hadir</th><th class="angka">Hadir Minggu</th><th class="angka">Alpa</th><th class="angka">Lembur</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($karyawan as $k):
                    $peta = [];
                    $st = db()->prepare('SELECT tanggal, status FROM absensi WHERE karyawan_id = ? AND tanggal LIKE ?');
                    $st->execute([(int) $k['id'], $periode . '%']);
                    foreach ($st as $a) {
                        $peta[$a['tanggal']] = $a['status'];
                    }
                    $rek = absensi_rekap((int) $k['id'], $periode); ?>
                    <tr>
                        <td><strong><?= h($k['nama']) ?></strong><br><span class="muted"><?= h($k['nip']) ?></span></td>
                        <?php foreach ($kal['tanggal'] as $t):
                            $s = $peta[$t] ?? '';
                            $warna = ['H' => 'lencana-hijau', 'I' => 'lencana-biru', 'S' => 'lencana-kuning', 'C' => 'lencana-ungu', 'A' => 'lencana-merah', 'L' => 'lencana-abu'][$s !== '' ? substr($s, 0, 1) : '-'] ?? 'lencana-abu'; ?>
                            <td class="tengah"><span class="lencana <?= $warna ?>" style="padding:1px 7px"><?= h($s !== '' ? substr($s, 0, 1) : '-') ?></span></td>
                        <?php endforeach; ?>
                        <td class="angka"><?= (int) $rek['hadir'] ?></td>
                        <td class="angka"><?= (int) $rek['hadir_minggu'] ?></td>
                        <td class="angka"><?= (int) $rek['alpa'] ?></td>
                        <td class="angka"><?= h(angka($rek['menit_lembur'] / 60, 2)) ?> jam</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('receipt', 17) ?> Rekap Komponen Slip</h2>
                <p class="muted">Dihitung dari slip yang sudah tersimpan pada periode ini.</p>
            </div>
        </div>
        <?php if ($komponen): ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Jenis</th><th>Nama Komponen</th><th>Kategori</th><th class="angka">Jumlah Slip</th><th class="angka">Total Nilai</th></tr></thead>
                    <tbody>
                    <?php foreach ($komponen as $c): ?>
                        <tr>
                            <td><span class="lencana <?= $c['jenis'] === 'PENDAPATAN' ? 'lencana-hijau' : 'lencana-merah' ?>"><?= h($c['jenis']) ?></span></td>
                            <td><?= h($c['nama']) ?></td>
                            <td class="muted"><?= h($c['kategori']) ?></td>
                            <td class="angka"><?= $c['jml'] ?></td>
                            <td class="angka"><?= h(rp($c['nilai'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="kosong"><strong>Belum ada slip tersimpan</strong>Hitung penggajian periode ini terlebih dahulu.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php page_foot(); ?>
