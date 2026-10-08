<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();
demo_siapkan();

/*
 * Menu KHUSUS untuk data yang melekat pada karyawan tetapi BUKAN identitasnya:
 *   1. Komponen gaji tambahan (tunjangan / potongan manual per karyawan)
 *   2. Bonus yang diterima (penugasan + nilai khusus per karyawan)
 *   3. Panjar (uang muka, dipotong mencicil tiap bulan)
 *
 * Dipisahkan dari form "Ubah Karyawan" supaya halaman data induk tetap ringkas dan
 * pengelolaan komponen/bonus/panjar bisa dilakukan tanpa membuka data karyawan.
 */

$pesan = '';
if (is_post()) {
    $aksi = post('aksi');

    if ($aksi === 'komponen_simpan') {
        $kid = post_int('karyawan_id');
        $nama = post('komponen_nama');
        if ($kid <= 0 || $nama === '') {
            $pesan = 'Nama komponen wajib diisi.';
        } else {
            db()->prepare('INSERT INTO komponen_gaji (karyawan_id, jenis, kategori, nama, tipe, nilai, berulang, periode, aktif, keterangan, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')
                ->execute([
                    $kid, post('komponen_jenis') === 'POTONGAN' ? 'POTONGAN' : 'PENDAPATAN',
                    post('komponen_kategori'), $nama,
                    post('komponen_tipe') === 'persen' ? 'persen' : 'nominal', post_num('komponen_nilai'),
                    post('komponen_berulang') === '1' ? 1 : 0, post('komponen_periode'),
                    post('komponen_keterangan'), now(),
                ]);
            log_admin('komponen_simpan', 'Komponen "' . $nama . '" untuk karyawan #' . $kid);
            redirect('komponen.php?karyawan=' . $kid . '&ok=' . rawurlencode('Komponen gaji ditambahkan.') . '#komponen');
        }
    } elseif ($aksi === 'komponen_hapus') {
        db()->prepare('DELETE FROM komponen_gaji WHERE id = ?')->execute([post_int('id')]);
        log_admin('komponen_hapus', 'Hapus komponen gaji #' . post_int('id'));
        redirect('komponen.php?karyawan=' . post_int('karyawan_id') . '&ok=' . rawurlencode('Komponen gaji dihapus.') . '#komponen');
    } elseif ($aksi === 'komponen_aktif') {
        $id = post_int('id');
        db()->prepare('UPDATE komponen_gaji SET aktif = CASE aktif WHEN 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$id]);
        log_admin('komponen_aktif', 'Ubah status komponen #' . $id);
        redirect('komponen.php?karyawan=' . post_int('karyawan_id') . '&ok=' . rawurlencode('Status komponen diubah.') . '#komponen');
    } elseif ($aksi === 'bonus_karyawan_simpan') {
        $kid = post_int('karyawan_id');
        if ($kid <= 0) {
            $pesan = 'Karyawan tidak ditemukan.';
        } else {
            $daftar = (array) ($_POST['bonus_aktif'] ?? []);
            $nilaiKhusus = (array) ($_POST['bonus_nilai'] ?? []);
            $pdo = db();
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                $pdo->prepare('DELETE FROM karyawan_bonus WHERE karyawan_id = ?')->execute([$kid]);
                $ins = $pdo->prepare('INSERT INTO karyawan_bonus (karyawan_id, bonus_id, aktif, nilai) VALUES (?, ?, ?, ?)');
                foreach (db()->query('SELECT id, nilai FROM bonus_parameter ORDER BY urutan')->fetchAll() as $b) {
                    $bid = (int) $b['id'];
                    $aktif = !empty($daftar[$bid]) ? 1 : 0;
                    $nilai = trim((string) ($nilaiKhusus[$bid] ?? ''));
                    $nilai = $nilai === '' ? -1.0 : (float) str_replace(['.', ','], ['', '.'], $nilai);
                    /* SELALU simpan barisnya (termasuk saat aktif = 0). Kalau barisnya tidak ada,
                       karyawan otomatis dianggap ikut bonus yang berlaku untuk semua orang —
                       sehingga pilihan "jangan ikutkan karyawan ini" tidak akan berfungsi. */
                    $ins->execute([$kid, $bid, $aktif, $nilai]);
                }
                $pdo->exec('COMMIT');
            } catch (Throwable $e) {
                $pdo->exec('ROLLBACK');
                throw $e;
            }
            log_admin('bonus_karyawan', 'Pengaturan bonus karyawan #' . $kid);
            redirect('komponen.php?karyawan=' . $kid . '&ok=' . rawurlencode('Pengaturan bonus karyawan disimpan.') . '#bonus');
        }
    } elseif ($aksi === 'panjar_simpan') {
        $kid = post_int('karyawan_id');
        $tanggalPanjar = tanggal_dari_teks(post('panjar_tanggal'));
        if ($tanggalPanjar === '') {
            $tanggalPanjar = date('Y-m-d');
        }
        if ($kid <= 0 || post_num('panjar_jumlah') <= 0 || post_num('panjar_cicilan') <= 0) {
            redirect('komponen.php?karyawan=' . $kid . '&err=' . rawurlencode('Jumlah panjar dan cicilan per bulan wajib lebih dari 0.') . '#panjar');
        }
        db()->prepare('INSERT INTO panjar (karyawan_id, tanggal, jumlah, cicilan_per_bulan, keterangan, aktif, created_at)
                       VALUES (?, ?, ?, ?, ?, 1, ?)')
            ->execute([$kid, $tanggalPanjar, post_num('panjar_jumlah'), post_num('panjar_cicilan'), post('panjar_keterangan'), now()]);
        log_admin('panjar_simpan', 'Panjar karyawan #' . $kid . ' sebesar ' . post_num('panjar_jumlah'));
        redirect('komponen.php?karyawan=' . $kid . '&ok=' . rawurlencode('Panjar ditambahkan; cicilannya akan dipotong pada slip berikutnya.') . '#panjar');
    } elseif ($aksi === 'panjar_hapus') {
        db()->prepare('DELETE FROM panjar WHERE id = ?')->execute([post_int('id')]);
        log_admin('panjar_hapus', 'Hapus panjar #' . post_int('id'));
        redirect('komponen.php?karyawan=' . post_int('karyawan_id') . '&ok=' . rawurlencode('Panjar dihapus.') . '#panjar');
    }
}

$cari = (string) ($_GET['cari'] ?? '');
$karyawanSemua = daftar_karyawan($cari, false);
$idDipilih = post_int('karyawan_id') ?: (int) ($_GET['karyawan'] ?? 0);
if ($idDipilih <= 0 && $karyawanSemua) {
    $idDipilih = (int) $karyawanSemua[0]['id'];
}
$kar = $idDipilih > 0 ? karyawan_by_id($idDipilih) : null;

page_head('Komponen Gaji & Bonus', 'komponen.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Komponen Gaji, Bonus &amp; Panjar</h1>
        <p class="muted">
            Data yang melekat pada karyawan tetapi bukan identitasnya. Data induk karyawan diubah di
            <a href="karyawan.php">menu Karyawan</a>, sedangkan pengaturan umum bonus (nama, dasar
            hitung, syarat, penerima) ada di <a href="pengaturan.php?tab=bonus">Pengaturan → Bonus</a>.
        </p>
    </div>
</div>

<?php flash(); ?>
<?php if ($pesan !== ''): ?><div class="flash flash-err"><?= h($pesan) ?></div><?php endif; ?>
<?php if (isset($_GET['err']) && (string) $_GET['err'] !== ''): ?><div class="flash flash-err"><?= h((string) $_GET['err']) ?></div><?php endif; ?>

<div class="kartu">
    <div class="kartu-kepala">
        <div>
            <h2><?= icon('users', 17) ?> Pilih Karyawan</h2>
            <p class="muted">Komponen, bonus, dan panjar di bawah berlaku untuk karyawan yang dipilih.</p>
        </div>
    </div>
    <form method="get" class="form-baris tiga">
        <div class="kolom-2">
            <label for="karyawan">Karyawan</label>
            <select id="karyawan" name="karyawan" onchange="this.form.submit()">
                <?php foreach ($karyawanSemua as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $idDipilih === (int) $k['id'] ? 'selected' : '' ?>>
                        <?= h($k['nama']) ?> — <?= h($k['nip']) ?><?= $k['jabatan'] !== '' ? ' (' . h($k['jabatan']) . ')' : '' ?><?= (int) $k['aktif'] === 1 ? '' : ' — NONAKTIF' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="cari">Cari karyawan</label>
            <input type="text" id="cari" name="cari" value="<?= h($cari) ?>" placeholder="nama / NIP">
        </div>
        <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Terapkan</button></div>
    </form>
</div>

<?php if (!$kar): ?>
    <div class="kosong"><strong>Belum ada karyawan</strong>Tambahkan karyawan dulu di <a href="karyawan.php">menu Karyawan</a>.</div>
<?php else: ?>

    <?php
    $kom = komponen_karyawan((int) $kar['id'], false);
    $daftarPanjar = panjar_karyawan((int) $kar['id'], false);
    $stB = db()->prepare('SELECT COUNT(*) FROM karyawan_bonus WHERE karyawan_id = ? AND aktif = 1');
    $stB->execute([(int) $kar['id']]);
    $jmlBonusIkut = (int) $stB->fetchColumn();
    $totalPanjarSisa = 0.0;
    $sisaPer = [];
    foreach ($daftarPanjar as $pj) {
        $stS = db()->prepare('SELECT COALESCE(SUM(i.nilai),0) FROM slip_item i JOIN slip_gaji s ON s.id = i.slip_id WHERE i.sumber = ?');
        $stS->execute(['panjar:' . $pj['id']]);
        $sudah = (float) $stS->fetchColumn();
        $sisaPer[(int) $pj['id']] = ['sudah' => $sudah, 'sisa' => max(0.0, (float) $pj['jumlah'] - $sudah)];
        $totalPanjarSisa += $sisaPer[(int) $pj['id']]['sisa'];
    }
    ?>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= h($kar['nama']) ?></h2>
                <p class="muted">
                    <?= h($kar['nip']) ?> · <?= h($kar['jabatan']) ?><?= $kar['divisi_nama'] ? ' · ' . h($kar['divisi_nama']) : '' ?>
                    · Gaji pokok <?= h(rp((float) $kar['gaji_pokok'])) ?>
                </p>
            </div>
            <a class="btn btn-kecil" href="karyawan.php?ubah=<?= (int) $kar['id'] ?>">Ubah Data Induk Karyawan</a>
        </div>
        <div class="slip-rekap">
            <div class="slip-rekap-sel"><div class="l">Komponen Tambahan</div><div class="v"><?= count($kom) ?> item</div></div>
            <div class="slip-rekap-sel"><div class="l">Bonus Diikuti</div><div class="v"><?= $jmlBonusIkut ?> bonus</div></div>
            <div class="slip-rekap-sel"><div class="l">Panjar Berjalan</div><div class="v"><?= count(array_filter($daftarPanjar, fn($p) => (int) $p['aktif'] === 1)) ?> catatan</div></div>
            <div class="slip-rekap-sel"><div class="l">Sisa Panjar</div><div class="v"><?= h(rp($totalPanjarSisa)) ?></div></div>
        </div>
        <div class="btn-baris" style="margin-top:14px">
            <a class="btn btn-kecil" href="#komponen">Komponen Gaji</a>
            <a class="btn btn-kecil" href="#bonus">Bonus</a>
            <a class="btn btn-kecil" href="#panjar">Panjar</a>
        </div>
    </div>

    <div class="kartu" id="komponen">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('wallet', 17) ?> 1. Komponen Gaji Tambahan</h2>
                <p class="muted">Tunjangan atau potongan rutin di luar gaji pokok: tunjangan transport, potongan koperasi, dll. Untuk potongan panjar pakai bagian Panjar di bawah.</p>
            </div>
        </div>
        <?php if ($kom): ?>
            <div class="tabel-bungkus" style="margin-bottom:16px">
                <table class="tabel dikit">
                    <thead><tr><th>Nama</th><th>Jenis</th><th>Kategori</th><th class="angka">Nilai</th><th>Periode</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($kom as $c): ?>
                        <tr>
                            <td><strong><?= h($c['nama']) ?></strong><?= $c['keterangan'] !== '' ? '<br><span class="muted">' . h($c['keterangan']) . '</span>' : '' ?></td>
                            <td><span class="lencana <?= $c['jenis'] === 'PENDAPATAN' ? 'lencana-hijau' : 'lencana-merah' ?>"><?= h($c['jenis'] === 'PENDAPATAN' ? 'Pendapatan' : 'Potongan') ?></span></td>
                            <td><?= h($c['kategori']) ?></td>
                            <td class="angka"><?= $c['tipe'] === 'persen' ? h(angka((float) $c['nilai'], 2)) . '%' : h(rp((float) $c['nilai'])) ?></td>
                            <td class="muted"><?= (int) $c['berulang'] === 1 ? 'Setiap bulan' : h((string) $c['periode']) ?></td>
                            <td><?= (int) $c['aktif'] === 1 ? '<span class="lencana lencana-hijau">aktif</span>' : '<span class="lencana lencana-abu">nonaktif</span>' ?></td>
                            <td class="kanan nowrap">
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="aksi" value="komponen_aktif">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="hidden" name="karyawan_id" value="<?= (int) $kar['id'] ?>">
                                    <button class="btn btn-kecil" type="submit"><?= (int) $c['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                                <form method="post" style="display:inline" data-konfirmasi="Hapus komponen ini?">
                                    <input type="hidden" name="aksi" value="komponen_hapus">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="hidden" name="karyawan_id" value="<?= (int) $kar['id'] ?>">
                                    <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Belum ada komponen tambahan untuk karyawan ini.</p>
        <?php endif; ?>

        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="komponen_simpan">
            <input type="hidden" name="karyawan_id" value="<?= (int) $kar['id'] ?>">
            <div><label for="komponen_nama">Nama Komponen</label><input type="text" id="komponen_nama" name="komponen_nama" required placeholder="cth: Tunjangan transport"></div>
            <div>
                <label for="komponen_jenis">Jenis</label>
                <select id="komponen_jenis" name="komponen_jenis">
                    <option value="PENDAPATAN">Pendapatan (menambah gaji)</option>
                    <option value="POTONGAN">Potongan (mengurangi gaji)</option>
                </select>
            </div>
            <div><label for="komponen_kategori">Kategori</label><input type="text" id="komponen_kategori" name="komponen_kategori" placeholder="cth: Tunjangan / Koperasi"></div>
            <div>
                <label for="komponen_tipe">Tipe Nilai</label>
                <select id="komponen_tipe" name="komponen_tipe">
                    <option value="nominal">Nominal (<?= h(conf('mata_uang_simbol')) ?>)</option>
                    <option value="persen">Persentase dari gaji pokok</option>
                </select>
            </div>
            <div><label for="komponen_nilai">Nilai</label><input type="number" step="0.01" min="0" id="komponen_nilai" name="komponen_nilai" value="0"></div>
            <div>
                <label for="komponen_berulang">Periode Berlaku</label>
                <select id="komponen_berulang" name="komponen_berulang">
                    <option value="1">Setiap bulan</option>
                    <option value="0">Satu kali (isi periode)</option>
                </select>
            </div>
            <div><label for="komponen_periode">Periode <span class="opsional">(YYYY-MM bila satu kali)</span></label><input type="month" id="komponen_periode" name="komponen_periode"></div>
            <div class="kolom-2"><label for="komponen_keterangan">Keterangan <span class="opsional">(opsional)</span></label><input type="text" id="komponen_keterangan" name="komponen_keterangan"></div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Tambah Komponen</button></div>
        </form>
    </div>

    <div class="kartu" id="bonus">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('wallet', 17) ?> 2. Bonus yang Diterima</h2>
                <p class="muted">
                    Daftar bonus beserta nilainya diatur di <a href="pengaturan.php?tab=bonus">Pengaturan → Bonus</a>.
                    Di sini Anda menentukan karyawan ini ikut bonus mana, dan boleh memberi <em>nilai khusus</em>
                    (mis. insentif minggu lebih besar untuk operator tertentu). Kosongkan = pakai nilai umum.
                </p>
            </div>
        </div>
        <?php
        $semuaBonus = db()->query('SELECT * FROM bonus_parameter ORDER BY urutan, nama')->fetchAll();
        $petaKB = [];
        $stKB = db()->prepare('SELECT * FROM karyawan_bonus WHERE karyawan_id = ?');
        $stKB->execute([(int) $kar['id']]);
        foreach ($stKB as $r) {
            $petaKB[(int) $r['bonus_id']] = $r;
        }
        ?>
        <?php if ($semuaBonus): ?>
            <form method="post">
                <input type="hidden" name="aksi" value="bonus_karyawan_simpan">
                <input type="hidden" name="karyawan_id" value="<?= (int) $kar['id'] ?>">
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead><tr><th>Ikut</th><th>Bonus</th><th>Dasar Hitung</th><th class="angka">Nilai Umum</th><th class="angka">Nilai Khusus Karyawan Ini</th></tr></thead>
                        <tbody>
                        <?php foreach ($semuaBonus as $b):
                            $kb = $petaKB[(int) $b['id']] ?? null;
                            $ikut = $kb ? (int) $kb['aktif'] === 1 : ((int) $b['berlaku_semua'] === 1);
                            $nilaiKb = $kb && (float) $kb['nilai'] >= 0 ? (float) $kb['nilai'] : ''; ?>
                            <tr>
                                <td class="tengah">
                                    <input type="checkbox" name="bonus_aktif[<?= (int) $b['id'] ?>]" value="1" <?= $ikut ? 'checked' : '' ?>
                                           style="width:17px;height:17px;accent-color:var(--biru-600)">
                                </td>
                                <td>
                                    <strong><?= h($b['nama']) ?></strong>
                                    <?php if ((int) $b['aktif'] !== 1): ?> <span class="lencana lencana-abu">nonaktif</span><?php endif; ?>
                                    <?php if ((int) $b['berlaku_semua'] !== 1): ?> <span class="lencana lencana-kuning">khusus</span><?php endif; ?>
                                    <?php if ((int) $b['syarat_alpa_nol'] === 1): ?> <span class="lencana lencana-abu">tanpa alpa</span><?php endif; ?>
                                </td>
                                <td class="muted"><?= h(tipe_bonus()[$b['tipe']] ?? $b['tipe']) ?></td>
                                <td class="angka"><?= h(angka((float) $b['nilai'], 0)) ?><?= $b['tipe'] === 'persen_pokok' ? '%' : '' ?></td>
                                <td class="angka"><input type="number" step="1" min="0" name="bonus_nilai[<?= (int) $b['id'] ?>]" value="<?= h((string) $nilaiKb) ?>" placeholder="—" style="max-width:130px"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-aksi"><button class="btn btn-primer" type="submit">Simpan Pengaturan Bonus</button></div>
            </form>
        <?php else: ?>
            <div class="kosong"><strong>Belum ada parameter bonus</strong>Tambahkan dulu di <a href="pengaturan.php?tab=bonus">Pengaturan → Bonus</a>.</div>
        <?php endif; ?>
    </div>

    <div class="kartu" id="panjar">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('receipt', 17) ?> 3. Panjar (Uang Muka)</h2>
                <p class="muted">Panjar dipotong mencicil tiap bulan sampai lunas. Sisa dihitung dari riwayat slip yang sudah dihitung — menghitung ulang slip tidak mengurangi panjar dua kali.</p>
            </div>
        </div>
        <?php if ($daftarPanjar): ?>
            <div class="tabel-bungkus" style="margin-bottom:16px">
                <table class="tabel dikit">
                    <thead><tr><th>Tanggal</th><th>Keterangan</th><th class="angka">Jumlah</th><th class="angka">Cicilan</th><th class="angka">Sudah</th><th class="angka">Sisa</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php $tJumlah = 0; $tSisa = 0; foreach ($daftarPanjar as $pj):
                        $info = $sisaPer[(int) $pj['id']] ?? ['sudah' => 0, 'sisa' => (float) $pj['jumlah']];
                        $tJumlah += (float) $pj['jumlah'];
                        $tSisa += $info['sisa']; ?>
                        <tr>
                            <td class="nowrap"><?= h(tanggal_indo((string) $pj['tanggal'])) ?></td>
                            <td><?= h($pj['keterangan'] ?: '—') ?></td>
                            <td class="angka"><?= h(rp((float) $pj['jumlah'])) ?></td>
                            <td class="angka"><?= h(rp((float) $pj['cicilan_per_bulan'])) ?></td>
                            <td class="angka"><?= h(rp($info['sudah'])) ?></td>
                            <td class="angka"><strong><?= h(rp($info['sisa'])) ?></strong></td>
                            <td><?= $info['sisa'] <= 0 ? '<span class="lencana lencana-hijau">lunas</span>' : ((int) $pj['aktif'] === 1 ? '<span class="lencana lencana-kuning">berjalan</span>' : '<span class="lencana lencana-abu">nonaktif</span>') ?></td>
                            <td class="kanan">
                                <form method="post" data-konfirmasi="Hapus panjar ini?">
                                    <input type="hidden" name="aksi" value="panjar_hapus">
                                    <input type="hidden" name="id" value="<?= (int) $pj['id'] ?>">
                                    <input type="hidden" name="karyawan_id" value="<?= (int) $kar['id'] ?>">
                                    <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="baris-total"><td colspan="2">Total</td><td class="angka"><?= h(rp($tJumlah)) ?></td><td class="angka"></td><td class="angka"></td><td class="angka"><?= h(rp($tSisa)) ?></td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Belum ada panjar untuk karyawan ini.</p>
        <?php endif; ?>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="panjar_simpan">
            <input type="hidden" name="karyawan_id" value="<?= (int) $kar['id'] ?>">
            <div><label for="panjar_tanggal">Tanggal Panjar</label><?= input_tanggal('panjar_tanggal', date('Y-m-d')) ?></div>
            <div><label for="panjar_jumlah">Jumlah Panjar</label><input type="number" min="1" step="1" id="panjar_jumlah" name="panjar_jumlah" required></div>
            <div><label for="panjar_cicilan">Cicilan per Bulan</label><input type="number" min="1" step="1" id="panjar_cicilan" name="panjar_cicilan" required></div>
            <div class="kolom-2"><label for="panjar_keterangan">Keterangan</label><input type="text" id="panjar_keterangan" name="panjar_keterangan" placeholder="cth: Panjar perbaikan sepeda motor"></div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Tambah Panjar</button></div>
        </form>
    </div>

<?php endif; ?>
<?php page_foot(); ?>
