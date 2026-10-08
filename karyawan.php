<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();

$pesan = '';
if (is_post()) {
    $aksi = post('aksi');
    if ($aksi === 'karyawan_simpan') {
        $id = post_int('id') ?: null;
        $hasil = karyawan_simpan($id, [
            'nip' => post('nip'),
            'nama' => post('nama'),
            'jabatan' => post('jabatan'),
            'divisi_id' => post_int('divisi_id'),
            'tanggal_masuk' => post('tanggal_masuk'),
            'status_kepegawaian' => post('status_kepegawaian'),
            'metode_pajak' => post('metode_pajak'),
            'ptkp_kode' => post('ptkp_kode'),
            'npwp' => post('npwp'),
            'gaji_pokok' => post_num('gaji_pokok'),
            'tunjangan_tetap' => post_num('tunjangan_tetap'),
            'tunjangan_tidak_tetap' => post_num('tunjangan_tidak_tetap'),
            'bank' => post('bank'),
            'no_rekening' => post('no_rekening'),
            'email' => post('email'),
            'telepon' => post('telepon'),
            'aktif' => isset($_POST['aktif']),
        ]);
        if ($hasil['ok']) {
            log_admin('karyawan_simpan', ($id ? 'Ubah' : 'Tambah') . ' karyawan #' . $hasil['id'] . ' (' . post('nama') . ')');
            redirect('karyawan.php?ok=' . rawurlencode('Data karyawan disimpan.') . '&ubah=' . (int) $hasil['id']);
        }
        $pesan = $hasil['error'];
    } elseif ($aksi === 'karyawan_hapus') {
        $hasil = karyawan_hapus(post_int('id'));
        log_admin('karyawan_hapus', 'Hapus/nonaktifkan karyawan #' . post_int('id'));
        redirect('karyawan.php?ok=' . rawurlencode($hasil['nonaktif'] ? 'Karyawan dinonaktifkan (riwayat slip tetap disimpan).' : 'Karyawan dihapus.'));
    } elseif ($aksi === 'divisi_simpan') {
        $nama = post('divisi_nama');
        if ($nama !== '') {
            db()->prepare('INSERT OR IGNORE INTO divisi (nama, keterangan, aktif) VALUES (?, ?, 1)')
                ->execute([$nama, post('divisi_keterangan')]);
            log_admin('divisi_simpan', 'Divisi "' . $nama . '"');
        }
        redirect('karyawan.php?ok=' . rawurlencode('Divisi disimpan.'));
    } elseif ($aksi === 'divisi_hapus') {
        db()->prepare('DELETE FROM divisi WHERE id = ?')->execute([post_int('divisi_id')]);
        log_admin('divisi_hapus', 'Hapus divisi #' . post_int('divisi_id'));
        redirect('karyawan.php?ok=' . rawurlencode('Divisi dihapus (karyawan menjadi tanpa divisi).'));
    }
}

$cari = (string) ($_GET['cari'] ?? '');
$filterDivisi = (string) ($_GET['divisi'] ?? '');
$ubah = post_int('ubah') ?: (int) ($_GET['ubah'] ?? 0);
$edit = $ubah > 0 ? karyawan_by_id($ubah) : null;
$daftar = daftar_karyawan($cari, false, $filterDivisi);
$ptkpList = daftar_ptkp();
$divisiList = daftar_divisi(false);

page_head('Karyawan', 'karyawan.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Master Karyawan</h1>
        <p class="muted">Data karyawan, upah tetap, status PTKP, dan komponen gaji/potongan tambahan.</p>
    </div>
    <div class="btn-baris">
        <a class="btn btn-primer" href="karyawan.php?baru=1"><?= icon('plus', 16) ?> Karyawan Baru</a>
    </div>
</div>

<?php flash(); ?>
<?php if ($pesan !== ''): ?><div class="flash flash-err"><?= h($pesan) ?></div><?php endif; ?>
<?php if (isset($_GET['err']) && $_GET['err'] !== ''): ?><div class="flash flash-err"><?= h((string) $_GET['err']) ?></div><?php endif; ?>

<div class="kartu-grid <?= $edit || isset($_GET['baru']) ? 'grid-2-1' : '' ?>">
    <div>
        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2><?= icon('users', 17) ?> Daftar Karyawan</h2>
                    <p class="muted"><?= count($daftar) ?> data ditampilkan (termasuk yang nonaktif).</p>
                </div>
            </div>
            <form method="get" class="filter-baris">
                <div>
                    <label for="cari">Cari</label>
                    <input type="text" id="cari" name="cari" value="<?= h($cari) ?>" placeholder="Nama / NIP / jabatan">
                </div>
                <div>
                    <label for="divisi">Divisi</label>
                    <select id="divisi" name="divisi">
                        <option value="">Semua divisi</option>
                        <?php foreach ($divisiList as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= $filterDivisi === (string) $d['id'] ? 'selected' : '' ?>><?= h($d['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit">Terapkan</button>
                <?php if ($cari !== '' || $filterDivisi !== ''): ?><a class="btn btn-ghost" style="background:transparent;color:var(--ink-2);border-color:var(--garis-2)" href="karyawan.php">Reset</a><?php endif; ?>
            </form>

            <?php if ($daftar): ?>
                <div class="tabel-bungkus">
                    <table class="tabel">
                        <thead>
                            <tr>
                                <th>Nama / NIP</th><th>Jabatan</th><th>Divisi</th><th>Masuk</th>
                                <th class="angka">Gaji Pokok</th><th class="angka">Tunjangan</th><th>Pajak</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($daftar as $k): ?>
                            <tr>
                                <td>
                                    <strong><?= h($k['nama']) ?></strong><br>
                                    <span class="muted"><?= h($k['nip']) ?></span>
                                    <?php if ((int) $k['aktif'] !== 1): ?> <span class="lencana lencana-abu">Nonaktif</span><?php endif; ?>
                                </td>
                                <td><?= h($k['jabatan']) ?><br><span class="muted"><?= h(status_karyawan()[$k['status_kepegawaian']] ?? '') ?></span></td>
                                <td><?= h((string) ($k['divisi_nama'] ?? '-')) ?></td>
                                <td class="nowrap"><?= h($k['tanggal_masuk'] !== '' ? tanggal_pendek($k['tanggal_masuk']) : '-') ?></td>
                                <td class="angka"><?= h(rp((float) $k['gaji_pokok'])) ?></td>
                                <td class="angka"><?= h(rp((float) $k['tunjangan_tetap'])) ?></td>
                                <td class="nowrap"><span class="lencana lencana-biru"><?= h($k['ptkp_kode']) ?></span></td>
                                <td class="kanan nowrap">
                                    <a class="btn btn-kecil" href="karyawan.php?ubah=<?= (int) $k['id'] ?>">Ubah</a>
                                    <a class="btn btn-kecil" href="komponen.php?karyawan=<?= (int) $k['id'] ?>" title="Komponen gaji tambahan, bonus &amp; panjar">Komponen/Bonus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="kosong"><strong>Tidak ada karyawan</strong>Ubah kata kunci pencarian atau tambah karyawan baru.</div>
            <?php endif; ?>
        </div>

        <div class="kartu">
            <h2><?= icon('grid', 17) ?> Divisi</h2>
            <p class="muted">Divisi dipakai pada slip gaji dan rekap laporan.</p>
            <div class="tabel-bungkus" style="margin-bottom:14px">
                <table class="tabel dikit">
                    <thead><tr><th>Nama</th><th>Keterangan</th><th class="angka">Karyawan</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($divisiList as $d):
                        $st = db()->prepare('SELECT COUNT(*) FROM karyawan WHERE divisi_id = ?');
                        $st->execute([(int) $d['id']]);
                        $jml = (int) $st->fetchColumn(); ?>
                        <tr>
                            <td><?= h($d['nama']) ?><?= (int) $d['aktif'] === 1 ? '' : ' <span class="lencana lencana-abu">nonaktif</span>' ?></td>
                            <td class="muted"><?= h($d['keterangan']) ?></td>
                            <td class="angka"><?= $jml ?></td>
                            <td class="kanan">
                                <form method="post" style="display:inline" data-konfirmasi="Hapus divisi <?= h($d['nama']) ?>?">
                                    <input type="hidden" name="aksi" value="divisi_hapus">
                                    <input type="hidden" name="divisi_id" value="<?= (int) $d['id'] ?>">
                                    <button class="btn btn-kecil btn-merah" type="submit" <?= $jml > 0 ? 'disabled title="Masih dipakai karyawan"' : '' ?>>Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="form-baris tiga">
                <input type="hidden" name="aksi" value="divisi_simpan">
                <div><label for="divisi_nama">Nama Divisi</label><input type="text" id="divisi_nama" name="divisi_nama" required></div>
                <div><label for="divisi_keterangan">Keterangan <span class="opsional">(opsional)</span></label><input type="text" id="divisi_keterangan" name="divisi_keterangan"></div>
                <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Tambah Divisi</button></div>
            </form>
        </div>
    </div>

    <?php if ($edit || isset($_GET['baru'])): ?>
    <div>
        <div class="kartu">
            <div class="kartu-kepala">
                <div>
                    <h2><?= $edit ? 'Ubah Karyawan' : 'Karyawan Baru' ?></h2>
                    <p class="muted"><?= $edit ? h($edit['nama']) . ' · ' . h($edit['nip']) : 'Isi data kepegawaian & upah tetap.' ?></p>
                </div>
                <a class="btn btn-kecil" href="karyawan.php">Tutup</a>
            </div>
            <form method="post">
                <input type="hidden" name="aksi" value="karyawan_simpan">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="form-baris dua">
                    <div><label for="nama">Nama Lengkap</label><input type="text" id="nama" name="nama" required value="<?= h((string) ($edit['nama'] ?? '')) ?>"></div>
                    <div><label for="nip">NIP <span class="opsional">(kosongkan = otomatis)</span></label><input type="text" id="nip" name="nip" value="<?= h((string) ($edit['nip'] ?? '')) ?>"></div>
                    <div><label for="jabatan">Jabatan</label><input type="text" id="jabatan" name="jabatan" value="<?= h((string) ($edit['jabatan'] ?? '')) ?>"></div>
                    <div>
                        <label for="divisi_id">Divisi</label>
                        <select id="divisi_id" name="divisi_id">
                            <option value="0">— tanpa divisi —</option>
                            <?php foreach ($divisiList as $d): ?>
                                <option value="<?= (int) $d['id'] ?>" <?= (int) ($edit['divisi_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label for="tanggal_masuk">Tanggal Masuk</label><?= input_tanggal('tanggal_masuk', (string) ($edit['tanggal_masuk'] ?? date('Y-m-d'))) ?></div>
                    <div>
                        <label for="status_kepegawaian">Status Kepegawaian</label>
                        <select id="status_kepegawaian" name="status_kepegawaian">
                            <?php foreach (status_karyawan() as $v => $l): ?>
                                <option value="<?= h($v) ?>" <?= ($edit['status_kepegawaian'] ?? 'tetap') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h3 style="margin:20px 0 10px">Upah Tetap</h3>
                <div class="form-baris dua">
                    <div><label for="gaji_pokok">Gaji Pokok (<?= h(conf('mata_uang_simbol')) ?>)</label><input type="number" step="1" min="0" id="gaji_pokok" name="gaji_pokok" value="<?= h((string) (float) ($edit['gaji_pokok'] ?? 0)) ?>"></div>
                    <div><label for="tunjangan_tetap">Tunjangan Tetap</label><input type="number" step="1" min="0" id="tunjangan_tetap" name="tunjangan_tetap" value="<?= h((string) (float) ($edit['tunjangan_tetap'] ?? 0)) ?>"></div>
                    <div><label for="tunjangan_tidak_tetap">Tunjangan Tidak Tetap <span class="opsional">(dasar)</span></label><input type="number" step="1" min="0" id="tunjangan_tidak_tetap" name="tunjangan_tidak_tetap" value="<?= h((string) (float) ($edit['tunjangan_tidak_tetap'] ?? 0)) ?>"></div>
                    <div>
                        <label for="metode_pajak">Metode PPh 21</label>
                        <select id="metode_pajak" name="metode_pajak">
                            <?php foreach (metode_pajak() as $v => $l): ?>
                                <option value="<?= h($v) ?>" <?= ($edit['metode_pajak'] ?? conf('pajak_metode_default', 'progresif')) === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-petunjuk">Metode pajak bisa berbeda tiap karyawan — sesuai permintaan Anda.</div>
                    </div>
                    <div>
                        <label for="ptkp_kode">Status PTKP</label>
                        <select id="ptkp_kode" name="ptkp_kode">
                            <?php foreach ($ptkpList as $p): ?>
                                <option value="<?= h($p['kode']) ?>" <?= ($edit['ptkp_kode'] ?? 'TK/0') === $p['kode'] ? 'selected' : '' ?>>
                                    <?= h($p['kode']) ?> — <?= h($p['keterangan']) ?> (<?= h(rp((float) $p['setahun'])) ?>/tahun)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label for="npwp">NPWP <span class="opsional">(kosong = tarif tanpa NPWP)</span></label><input type="text" id="npwp" name="npwp" value="<?= h((string) ($edit['npwp'] ?? '')) ?>"></div>
                </div>

                <h3 style="margin:20px 0 10px">Data Pendukung</h3>
                <div class="form-baris dua">
                    <div><label for="bank">Bank</label><input type="text" id="bank" name="bank" value="<?= h((string) ($edit['bank'] ?? '')) ?>"></div>
                    <div><label for="no_rekening">No. Rekening</label><input type="text" id="no_rekening" name="no_rekening" value="<?= h((string) ($edit['no_rekening'] ?? '')) ?>"></div>
                    <div><label for="email">Email</label><input type="email" id="email" name="email" value="<?= h((string) ($edit['email'] ?? '')) ?>"></div>
                    <div><label for="telepon">Telepon</label><input type="text" id="telepon" name="telepon" value="<?= h((string) ($edit['telepon'] ?? '')) ?>"></div>
                </div>
                <div class="saklar">
                    <input type="checkbox" id="aktif" name="aktif" value="1" <?= (int) ($edit['aktif'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label for="aktif">Karyawan aktif (ikut dihitung pada penggajian)</label>
                </div>
                <div class="form-aksi">
                    <button class="btn btn-primer" type="submit">Simpan Data Karyawan</button>
                    <?php if ($edit): ?>
                        <button class="btn btn-merah" type="submit" form="form-hapus-karyawan">Hapus / Nonaktifkan</button>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($edit): ?>
                <form id="form-hapus-karyawan" method="post" data-konfirmasi="Hapus karyawan ini? Bila sudah pernah digaji, karyawan hanya akan dinonaktifkan agar riwayat slip tetap utuh.">
                    <input type="hidden" name="aksi" value="karyawan_hapus">
                    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                </form>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; ?>
</div>
<?php page_foot(); ?>
