<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_pengaturan();

$role = sesi_role();
$tabDiminta = (string) ($_GET['tab'] ?? 'perusahaan');

/* ------------------------------------------------------------- aksi POST */
if (is_post()) {
    $aksi = post('aksi');
    if (!pengaturan_aksi_diizinkan($role, $aksi)) {
        redirect('pengaturan.php?tab=' . urlencode($tabDiminta) . '&err=' . rawurlencode('Tindakan itu hanya boleh dilakukan Superadmin.'));
    }

    if ($aksi === 'conf_simpan') {
        $kelompok = post('kelompok');
        $meta = conf_kelompok($kelompok);
        $simpan = 0;
        $galat = [];
        foreach ($meta as $kunci => $m) {
            $tipe = (string) ($m['tipe'] ?? 'teks');
            if ($tipe === 'bool') {
                $nilai = post_bool('cfg_' . $kunci) ? '1' : '0';
            } else {
                $nilai = trim((string) ($_POST['cfg_' . $kunci] ?? ''));
                if ($tipe === 'json' && $nilai !== '') {
                    $uji = json_decode($nilai, true);
                    if (!is_array($uji)) {
                        $galat[] = $m['label'] . ': format JSON tidak sah.';
                        continue;
                    }
                    /* Segmen jam kerja diperiksa lebih ketat: jam harus HH:MM dan durasi positif,
                       supaya salah tulis tidak diam-diam diabaikan saat menghitung gaji. */
                    if ($kunci === 'jam_kerja_segmen') {
                        $adaKerja = false;
                        $salah = '';
                        foreach ($uji as $i => $g) {
                            $m2 = jam_ke_menit((string) ($g['mulai'] ?? ''));
                            $k2 = jam_ke_menit((string) ($g['selesai'] ?? ''));
                            if ($m2 === null || $k2 === null) {
                                $salah = 'segmen ke-' . ($i + 1) . ' tidak sah: jam harus format HH:MM (24 jam)';
                                break;
                            }
                            if ($k2 <= $m2) {
                                $salah = 'segmen ke-' . ($i + 1) . ' tidak sah: jam selesai harus lebih besar dari jam mulai';
                                break;
                            }
                            if (empty($g['istirahat'])) {
                                $adaKerja = true;
                            }
                        }
                        if ($salah !== '') {
                            $galat[] = $m['label'] . ': ' . $salah . '.';
                            continue;
                        }
                        if (!$adaKerja) {
                            $galat[] = $m['label'] . ': tidak sah — minimal satu segmen harus berupa jam kerja (istirahat = false).';
                            continue;
                        }
                    }
                }
                if (($tipe === 'persen' || $tipe === 'angka') && $nilai !== '' && !is_numeric(str_replace(',', '.', $nilai))) {
                    $galat[] = $m['label'] . ': harus berupa angka.';
                    continue;
                }
            }
            conf_set($kunci, $nilai);
            $simpan++;
        }
        log_admin('konfigurasi_simpan',ucfirst($kelompok) . " — {$simpan} setelan");
        $url = 'pengaturan.php?tab=' . urlencode($tabDiminta);
        redirect($galat ? $url . '&err=' . rawurlencode(implode(' ', $galat)) : $url . '&ok=' . rawurlencode("{$simpan} setelan disimpan. Perubahan langsung dipakai pada perhitungan berikutnya."));
    } elseif ($aksi === 'logo_unggah' || $aksi === 'ttd_unggah') {
        $field = $aksi === 'logo_unggah' ? 'logo' : 'ttd';
        $hasil = unggah_gambar($field);
        if (empty($hasil['ok'])) {
            redirect('pengaturan.php?tab=' . urlencode($tabDiminta) . '&err=' . rawurlencode((string) $hasil['error']));
        }
        conf_set($aksi === 'logo_unggah' ? 'logo_url' : 'ttd_url', (string) $hasil['url']);
        if ($aksi === 'logo_unggah') {
            conf_set('logo_nama', (string) $hasil['nama']);
        } else {
            conf_set('ttd_tampil_gambar', '1');
        }
        log_admin($aksi, (string) $hasil['nama']);
        redirect('pengaturan.php?tab=' . urlencode($tabDiminta) . '&ok=' . rawurlencode('Berkas berhasil diunggah dan langsung dipakai.'));
    } elseif ($aksi === 'gambar_hapus') {
        $mana = post('mana') === 'ttd' ? 'ttd_url' : 'logo_url';
        conf_set($mana, '');
        log_admin('gambar_hapus', $mana);
        redirect('pengaturan.php?tab=' . urlencode($tabDiminta) . '&ok=' . rawurlencode('Gambar dihapus.'));
    } elseif ($aksi === 'ptkp_simpan') {
        $kode = strtoupper(trim(post('kode')));
        if ($kode === '') {
            redirect('pengaturan.php?tab=pajak&err=' . rawurlencode('Kode PTKP wajib diisi (contoh K/2).'));
        }
        db()->prepare('INSERT INTO ptkp (kode, keterangan, setahun, aktif, urutan) VALUES (?, ?, ?, ?, ?)
                       ON CONFLICT(kode) DO UPDATE SET keterangan = excluded.keterangan, setahun = excluded.setahun, aktif = excluded.aktif')
            ->execute([$kode, post('keterangan'), post_num('setahun'), post_bool('aktif') ? 1 : 0, post_int('urutan')]);
        log_admin('ptkp_simpan', $kode);
        redirect('pengaturan.php?tab=pajak&ok=' . rawurlencode('Status PTKP disimpan.'));
    } elseif ($aksi === 'ptkp_hapus') {
        db()->prepare('DELETE FROM ptkp WHERE kode = ?')->execute([post('kode')]);
        log_admin('ptkp_hapus', post('kode'));
        redirect('pengaturan.php?tab=pajak&ok=' . rawurlencode('Status PTKP dihapus.'));
    } elseif ($aksi === 'layer_simpan') {
        db()->prepare('INSERT INTO pph21_layers (urutan, batas_bawah, batas_atas, tarif_persen, keterangan) VALUES (?, ?, ?, ?, ?)')
            ->execute([post_int('urutan', 99), post_num('batas_bawah'), post_num('batas_atas'), post_num('tarif_persen'), post('keterangan')]);
        log_admin('layer_simpan', 'Lapisan tarif ' . post_num('tarif_persen') . '%');
        redirect('pengaturan.php?tab=pajak&ok=' . rawurlencode('Lapisan tarif ditambahkan.'));
    } elseif ($aksi === 'layer_hapus') {
        db()->prepare('DELETE FROM pph21_layers WHERE id = ?')->execute([post_int('id')]);
        log_admin('layer_hapus', 'Hapus lapisan tarif #' . post_int('id'));
        redirect('pengaturan.php?tab=pajak&ok=' . rawurlencode('Lapisan tarif dihapus.'));
    } elseif ($aksi === 'data_uji_bersih') {
        if (post('konfirmasi_bersih') !== 'BERSIH') {
            redirect('pengaturan.php?tab=datareset&err=' . rawurlencode('Ketik BERSIH pada kolom konfirmasi untuk melanjutkan.'));
        }
        $hasil = data_uji_bersihkan(post_bool('ikut_ubah'), !post_bool('tanpa_slip'), true, !post_bool('tanpa_tandai'));
        $pesan = 'Data uji dibersihkan: ' . $hasil['absensi'] . ' baris absensi dan ' . $hasil['slip'] . ' slip dihapus.';
        if (!empty($hasil['ditandai'])) {
            $pesan .= ' ' . $hasil['ditandai'] . ' baris hasil editan Anda ditandai sebagai data nyata sehingga tidak lagi dianggap data uji.';
        }
        if (!$hasil['absensi'] && !$hasil['slip'] && empty($hasil['ditandai'])) {
            $pesan = 'Tidak ada data uji yang perlu dihapus — database sudah bersih.';
        }
        redirect('pengaturan.php?tab=datareset&ok=' . rawurlencode($pesan));
    } elseif ($aksi === 'absensi_hapus_rentang') {
        if (post('konfirmasi_hapus_absen') !== 'HAPUS') {
            redirect('pengaturan.php?tab=datareset&err=' . rawurlencode('Ketik HAPUS pada kolom konfirmasi untuk menghapus absensi.'));
        }
        $jml = absensi_hapus_rentang(post('dari'), post('sampai'));
        redirect('pengaturan.php?tab=datareset&ok=' . rawurlencode($jml . ' baris absensi dihapus pada rentang yang dipilih.'));
    } elseif ($aksi === 'bonus_simpan') {
        $id = post_int('bonus_id');
        $nama = trim(post('bonus_nama'));
        if ($nama === '') {
            redirect('pengaturan.php?tab=bonus&err=' . rawurlencode('Nama bonus wajib diisi.'));
        }
        $kode = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim(post('bonus_kode'))));
        $kode = trim($kode, '_');
        if ($kode === '') {
            $kode = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $nama));
            $kode = trim($kode, '_');
        }
        $tipe = array_key_exists(post('bonus_tipe'), tipe_bonus()) ? post('bonus_tipe') : 'nominal';
        $data = [
            'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe, 'nilai' => post_num('bonus_nilai'),
            'kena_pajak' => post_bool('bonus_kena_pajak') ? 1 : 0,
            'berlaku_semua' => post_bool('bonus_berlaku_semua') ? 1 : 0,
            'syarat_alpa_nol' => post_bool('bonus_syarat_alpa') ? 1 : 0,
            'syarat_tanpa_terlambat' => post_bool('bonus_syarat_terlambat') ? 1 : 0,
            'aktif' => post_bool('bonus_aktif') ? 1 : 0,
            'urutan' => post_int('bonus_urutan'),
            'keterangan' => post('bonus_keterangan'),
        ];
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $par = array_values($data);
            $par[] = $id;
            db()->prepare("UPDATE bonus_parameter SET {$set} WHERE id = ?")->execute($par);
        } else {
            $st = db()->prepare('SELECT COUNT(*) FROM bonus_parameter WHERE kode = ?');
            $st->execute([$kode]);
            if ((int) $st->fetchColumn() > 0) {
                redirect('pengaturan.php?tab=bonus&err=' . rawurlencode('Kode bonus sudah dipakai. Ubah kode atau gunakan tombol Ubah pada baris yang ada.'));
            }
            db()->prepare('INSERT INTO bonus_parameter (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')')
                ->execute(array_values($data));
            $id = (int) db()->lastInsertId();
        }
        log_admin('bonus_simpan', ($id > 0 ? 'Simpan' : 'Tambah') . ' bonus "' . $nama . '"');
        redirect('pengaturan.php?tab=bonus&ok=' . rawurlencode('Parameter bonus disimpan.'));
    } elseif ($aksi === 'bonus_hapus') {
        $st = db()->prepare('SELECT nama FROM bonus_parameter WHERE id = ?');
        $st->execute([post_int('bonus_id')]);
        $nama = (string) $st->fetchColumn();
        db()->prepare('DELETE FROM bonus_parameter WHERE id = ?')->execute([post_int('bonus_id')]);
        log_admin('bonus_hapus', 'Hapus bonus "' . $nama . '"');
        redirect('pengaturan.php?tab=bonus&ok=' . rawurlencode('Parameter bonus dihapus.'));
    } elseif ($aksi === 'potongan_simpan') {
        foreach (conf_kelompok('potongan') as $kunci => $m) {
            conf_set($kunci, post_bool('cfg_' . $kunci) ? '1' : '0');
        }
        /* Saklar cepat untuk BPJS & PPh 21 yang pengaturan rincinya ada di tab masing-masing. */
        conf_set('bpjs_aktif', post_bool('cfg_bpjs_aktif') ? '1' : '0');
        conf_set('pajak_aktif', post_bool('cfg_pajak_aktif') ? '1' : '0');
        log_admin('potongan_simpan', 'Menyimpan saklar potongan');
        redirect('pengaturan.php?tab=potongan&ok=' . rawurlencode('Pengaturan potongan disimpan. Slip yang dihitung ulang akan mengikuti.'));
    } elseif ($aksi === 'libur_simpan') {
        $tgl = tanggal_valid(post('tanggal'));
        $st = db()->prepare('SELECT COUNT(*) FROM hari_libur WHERE tanggal = ?');
        $st->execute([$tgl]);
        if ((int) $st->fetchColumn() > 0) {
            redirect('pengaturan.php?tab=libur&err=' . rawurlencode('Tanggal itu sudah terdaftar sebagai hari libur.'));
        }
        db()->prepare('INSERT INTO hari_libur (tanggal, keterangan, jenis) VALUES (?, ?, ?)')
            ->execute([$tgl, post('keterangan'), post('jenis', 'libur_nasional')]);
        log_admin('libur_simpan', $tgl . ' — ' . post('keterangan'));
        redirect('pengaturan.php?tab=libur&ok=' . rawurlencode('Hari libur ditambahkan.'));
    } elseif ($aksi === 'libur_hapus') {
        db()->prepare('DELETE FROM hari_libur WHERE id = ?')->execute([post_int('id')]);
        log_admin('libur_hapus', 'Hapus hari libur #' . post_int('id'));
        redirect('pengaturan.php?tab=libur&ok=' . rawurlencode('Hari libur dihapus.'));
    } elseif ($aksi === 'user_simpan') {
        $id = post_int('user_id');
        if ($id > 0) {
            $hasil = user_ubah($id, post('nama'), post('role'), post_bool('aktif'), (string) ($_POST['sandi'] ?? ''));
        } else {
            $hasil = user_tambah(post('username'), post('nama'), (string) ($_POST['sandi'] ?? ''), post('role'), post_bool('aktif'));
        }
        if (empty($hasil['ok'])) {
            redirect('pengaturan.php?tab=akun&err=' . rawurlencode((string) $hasil['error']));
        }
        log_admin('user_simpan', 'Akun ' . post('username') . ' (' . post('role') . ')');
        redirect('pengaturan.php?tab=akun&ok=' . rawurlencode('Akun disimpan.'));
    } elseif ($aksi === 'user_hapus') {
        $hasil = user_hapus(post_int('user_id'));
        if (empty($hasil['ok'])) {
            redirect('pengaturan.php?tab=akun&err=' . rawurlencode((string) $hasil['error']));
        }
        log_admin('user_hapus', 'Akun #' . post_int('user_id'));
        redirect('pengaturan.php?tab=akun&ok=' . rawurlencode('Akun dihapus.'));
    } elseif ($aksi === 'sandi_saya') {
        $lama = (string) ($_POST['sandi_lama'] ?? '');
        $baru = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');
        $u = sesi_user();
        if (!$u || !password_verify($lama, (string) $u['password_hash'])) {
            redirect('pengaturan.php?tab=akun&err=' . rawurlencode('Kata sandi lama tidak sesuai.'));
        }
        if ($baru !== $ulang || strlen($baru) < 5) {
            redirect('pengaturan.php?tab=akun&err=' . rawurlencode('Kata sandi baru minimal 5 karakter dan harus sama pada kedua kolom.'));
        }
        user_set_sandi((int) $u['id'], $baru, false);
        log_admin('sandi_saya', 'Ganti kata sandi sendiri');
        redirect('pengaturan.php?tab=akun&ok=' . rawurlencode('Kata sandi berhasil diubah.'));
    }
}

/* ------------------------------------------------------------- tab aktif */
$tabSemua = [
    'perusahaan' => 'Identitas Perusahaan',
    'jamkerja'   => 'Jam Kerja',
    'penggajian' => 'Absensi & Dasar Upah',
    'lembur'     => 'Lembur',
    'bonus'      => 'Bonus',
    'potongan'   => 'Potongan',
    'bpjs'       => 'BPJS',
    'pajak'      => 'Pajak & PTKP',
    'slip'       => 'Desain Slip',
    'libur'      => 'Hari Kerja & Libur',
    'datareset'  => 'Data & Reset',
    'akun'       => 'Akun & Keamanan',
    'audit'      => 'Jejak Audit',
    'akun-hrd'   => 'Kata Sandi',
];
if (!pengaturan_tab_diizinkan($role, $tabDiminta)) {
    $tabDiminta = $role === 'superadmin' ? 'perusahaan' : 'perusahaan';
}
$tab = $tabDiminta;

/** Merender input sesuai tipe setelan. */
function input_setelan(string $kunci, array $m): void
{
    $tipe = (string) ($m['tipe'] ?? 'teks');
    $nilai = (string) ($m['nilai'] ?? '');
    $nama = 'cfg_' . $kunci;
    $id = 'cfg_' . $kunci;
    if ($tipe === 'bool') {
        $centang = in_array(strtolower($nilai), ['1', 'on', 'ya', 'true', 'aktif'], true);
        echo '<div class="saklar"><input type="checkbox" id="' . h($id) . '" name="' . h($nama) . '" value="1"'
            . ($centang ? ' checked' : '') . '><label for="' . h($id) . '">Aktifkan</label></div>';
        return;
    }
    if ($tipe === 'pilihan') {
        echo '<select id="' . h($id) . '" name="' . h($nama) . '">';
        foreach ((array) ($m['pilihan'] ?? []) as $v => $l) {
            echo '<option value="' . h((string) $v) . '"' . ($nilai === (string) $v ? ' selected' : '') . '>' . h((string) $l) . '</option>';
        }
        echo '</select>';
        return;
    }
    if ($tipe === 'alamat') {
        echo '<textarea id="' . h($id) . '" name="' . h($nama) . '" rows="3">' . h($nilai) . '</textarea>';
        return;
    }
    if ($tipe === 'json') {
        echo '<textarea id="' . h($id) . '" name="' . h($nama) . '" rows="3" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.8rem">' . h($nilai) . '</textarea>';
        return;
    }
    if ($tipe === 'zona') {
        $zona = ['Asia/Makassar', 'Asia/Jakarta', 'Asia/Jayapura', 'Asia/Pontianak', 'Asia/Singapore', 'UTC'];
        if (!in_array($nilai, $zona, true)) {
            $zona[] = $nilai;
        }
        echo '<select id="' . h($id) . '" name="' . h($nama) . '">';
        foreach ($zona as $z) {
            echo '<option value="' . h($z) . '"' . ($nilai === $z ? ' selected' : '') . '>' . h($z) . '</option>';
        }
        echo '</select>';
        return;
    }
    if ($tipe === 'waktu') {
        echo '<input type="time" id="' . h($id) . '" name="' . h($nama) . '" value="' . h($nilai) . '">';
        return;
    }
    if ($tipe === 'email') {
        echo '<input type="email" id="' . h($id) . '" name="' . h($nama) . '" value="' . h($nilai) . '">';
        return;
    }
    $langkah = ['persen' => '0.01', 'angka' => '0.01', 'jam' => '0.5', 'menit' => '1', 'uang' => '1'][$tipe] ?? null;
    if ($langkah !== null) {
        echo '<input type="number" step="' . $langkah . '" min="0" id="' . h($id) . '" name="' . h($nama) . '" value="' . h($nilai) . '">';
        return;
    }
    echo '<input type="text" id="' . h($id) . '" name="' . h($nama) . '" value="' . h($nilai) . '">';
}

function panel_setelan(string $judul, string $keterangan, string $kelompok, string $tab): void
{
    $meta = conf_kelompok($kelompok);
    if (!$meta) {
        return;
    }
    ?>
    <form method="post" class="kartu">
        <input type="hidden" name="aksi" value="conf_simpan">
        <input type="hidden" name="kelompok" value="<?= h($kelompok) ?>">
        <div class="kartu-kepala">
            <div>
                <h2><?= h($judul) ?></h2>
                <p class="muted"><?= h($keterangan) ?></p>
            </div>
        </div>
        <div class="form-baris dua">
            <?php foreach ($meta as $kunci => $m): ?>
                <div>
                    <label for="cfg_<?= h($kunci) ?>"><?= h((string) $m['label']) ?></label>
                    <?php input_setelan($kunci, $m); ?>
                    <?php if (!empty($m['keterangan'])): ?><div class="form-petunjuk"><?= h((string) $m['keterangan']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-aksi">
            <button class="btn btn-primer" type="submit">Simpan <?= h($judul) ?></button>
        </div>
    </form>
    <?php
}

page_head('Pengaturan', 'pengaturan.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Pengaturan</h1>
        <p class="muted">Semua nilai komputasi penggajian diubah dari halaman ini — tidak ada yang ditanam di kode.</p>
    </div>
    <div class="pita"><?= icon('gear', 15) ?> <?= h(label_role($role)) ?></div>
</div>

<?php flash(); ?>

<div class="tab-baris">
    <?php foreach ($tabSemua as $kunci => $label):
        $boleh = ($kunci === 'akun-hrd' && $role === 'hrd') || ($kunci !== 'akun-hrd' && pengaturan_tab_diizinkan($role, $kunci));
        if (!$boleh) {
            continue;
        } ?>
        <a class="tab<?= $tab === $kunci ? ' is-aktif' : '' ?>" href="pengaturan.php?tab=<?= h($kunci) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'perusahaan'): ?>
    <?php panel_setelan('Identitas Perusahaan', 'Nama, alamat, dan kontak yang tercetak di slip gaji serta header aplikasi.', 'perusahaan', $tab); ?>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('users', 17) ?> Logo Perusahaan</h2>
                <p class="muted">Format PNG/JPG (maks 5 MB). Logo dipakai di header aplikasi, halaman masuk, dan slip gaji.</p>
            </div>
        </div>
        <div class="kartu-grid grid-2">
            <div>
                <div class="slip-ttd-gambar" style="justify-content:flex-start">
                    <?php if (conf('logo_url') !== ''): ?>
                        <img id="pratinjau-logo" class="slip-logo" src="<?= h(conf('logo_url')) ?>" alt="Logo perusahaan">
                    <?php else: ?>
                        <img id="pratinjau-logo" class="slip-logo" src="" alt="Pratinjau logo" hidden>
                    <?php endif; ?>
                </div>
                <?php if (conf('logo_url') !== ''): ?>
                    <p class="muted">Berkas tersimpan: <?= h(conf('logo_nama') ?: '-') ?></p>
                    <form method="post" data-konfirmasi="Hapus logo perusahaan?">
                        <input type="hidden" name="aksi" value="gambar_hapus">
                        <input type="hidden" name="mana" value="logo">
                        <button class="btn btn-merah btn-kecil" type="submit">Hapus Logo</button>
                    </form>
                <?php else: ?>
                    <p class="muted">Belum ada logo. Sistem akan memakai ikon bawaan.</p>
                <?php endif; ?>
                <?php if (media_token() === ''): ?>
                    <div class="catatan" style="margin-top:12px">Unggah berkas aktif setelah aplikasi <strong>dipublikasikan</strong>. Di server lokal, isi kolom “Logo Perusahaan (URL)” dengan alamat gambar untuk uji coba.</div>
                <?php endif; ?>
            </div>
            <div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="aksi" value="logo_unggah">
                    <label for="logo">Pilih Berkas Logo</label>
                    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg" data-pratinjau="#pratinjau-logo" required>
                    <div class="form-aksi"><button class="btn btn-primer" type="submit">Unggah Logo</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Tanda Tangan Digital</h2>
                <p class="muted">Nama &amp; jabatan penanda tangan tercetak di slip gaji. Gambar tanda tangan bersifat opsional.</p>
            </div>
        </div>
        <div class="kartu-grid grid-2">
            <div>
                <?php if (conf('ttd_url') !== ''): ?>
                    <img id="pratinjau-ttd" src="<?= h(conf('ttd_url')) ?>" alt="Tanda tangan" style="max-height:80px;border:1px solid var(--garis);border-radius:8px;padding:4px">
                    <form method="post" style="margin-top:10px" data-konfirmasi="Hapus gambar tanda tangan?">
                        <input type="hidden" name="aksi" value="gambar_hapus">
                        <input type="hidden" name="mana" value="ttd">
                        <button class="btn btn-merah btn-kecil" type="submit">Hapus Gambar</button>
                    </form>
                <?php else: ?>
                    <img id="pratinjau-ttd" src="" alt="Pratinjau tanda tangan" style="max-height:80px;border:1px solid var(--garis);border-radius:8px;padding:4px" hidden>
                    <p class="muted">Belum ada gambar tanda tangan — slip akan memakai garis + nama + jabatan.</p>
                <?php endif; ?>
            </div>
            <div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="aksi" value="ttd_unggah">
                    <label for="ttd">Pilih Berkas Tanda Tangan</label>
                    <input type="file" id="ttd" name="ttd" accept="image/png,image/jpeg" data-pratinjau="#pratinjau-ttd" required>
                    <div class="form-aksi"><button class="btn btn-primer" type="submit">Unggah Tanda Tangan</button></div>
                </form>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'jamkerja'): ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('calendar', 17) ?> Jam Kerja Harian yang Berlaku</h2>
                <p class="muted">Ringkasan hasil pembacaan setelan di bawah — inilah yang dipakai absensi &amp; penggajian.</p>
            </div>
        </div>
        <div class="slip-rekap">
            <div class="slip-rekap-sel"><div class="l">Jam Kerja / Hari</div><div class="v"><?= h(angka(jam_kerja_per_hari_efektif(), jam_kerja_per_hari_efektif() == floor(jam_kerja_per_hari_efektif()) ? 0 : 1)) ?> jam</div></div>
            <div class="slip-rekap-sel"><div class="l">Jam Masuk</div><div class="v"><?= h(jam_masuk_standar()) ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Jam Pulang</div><div class="v"><?= h(jam_pulang_standar()) ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Istirahat / Hari</div><div class="v"><?= h(durasi_indo(menit_istirahat_harian())) ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Total di Tempat Kerja</div><div class="v"><?= h(angka((jam_kerja_per_hari_efektif() * 60 + menit_istirahat_harian()) / 60, 1)) ?> jam</div></div>
            <div class="slip-rekap-sel"><div class="l">Upah / Jam</div><div class="v"><?= h(angka(hitung_upah_contoh()['per_jam'])) ?></div></div>
        </div>
        <div class="tabel-bungkus" style="margin-top:14px">
            <table class="tabel dikit">
                <thead><tr><th>Segmen</th><th>Mulai</th><th>Selesai</th><th>Durasi</th><th>Sifat</th></tr></thead>
                <tbody>
                <?php foreach (jam_kerja_segmen() as $g): ?>
                    <tr>
                        <td><?= h($g['nama']) ?></td>
                        <td><?= h($g['mulai']) ?></td>
                        <td><?= h($g['selesai']) ?></td>
                        <td><?= h(durasi_indo((int) $g['menit'])) ?></td>
                        <td><?= $g['istirahat'] ? '<span class="lencana lencana-kuning">Istirahat — tidak dihitung &amp; tidak dibayar</span>' : '<span class="lencana lencana-hijau">Jam kerja — dibayar</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php panel_setelan('Bentuk & Segmen Jam Kerja', 'Ubah blok jam kerja harian di sini. Segmen dengan "istirahat": true tidak dihitung sebagai jam kerja sehingga tidak dibayar.', 'jam_kerja', $tab); ?>
    <div class="kartu">
        <h3><?= icon('gear', 17) ?> Contoh Cara Mengubah Segmen</h3>
        <p class="muted">Nilai pada kolom "Segmen Jam Kerja Harian" di atas berbentuk JSON. Contoh untuk aturan 08:00–11:00, istirahat 11:00–13:00, kerja 13:00–20:00:</p>
        <pre style="background:#0B1F3F;color:#E3EBFD;padding:14px 16px;border-radius:8px;overflow:auto;font-size:.8rem;line-height:1.5"><?= h(JAM_KERJA_SEGMEN_BAWAAN) ?></pre>
        <ul class="daftar-sederhana">
            <li><strong>nama</strong> — label segmen (bebas).</li>
            <li><strong>mulai</strong> / <strong>selesai</strong> — jam format <code>HH:MM</code> (24 jam).</li>
            <li><strong>istirahat</strong> — <code>true</code> bila tidak dibayar, <code>false</code> bila jam kerja.</li>
            <li>Durasi seluruh segmen dengan <code>istirahat: false</code> otomatis menjadi "Jam Kerja / Hari" (pembagi upah per jam).</li>
        </ul>
    </div>
    <?php panel_setelan('Dasar Perhitungan & Pembulatan', 'Zona waktu, hari kerja mingguan, basis hari kerja, dasar upah per jam, dan pembulatan nilai.', 'umum', $tab); ?>

<?php elseif ($tab === 'penggajian'): ?>
    <?php panel_setelan('Upah Harian & Kehadiran', 'Rumus upah perusahaan ini: Upah Harian = Gaji Pokok ÷ hari kerja (25), lalu dikalikan jumlah hari hadir.', 'upah', $tab); ?>
    <?php panel_setelan('Absensi & Keterlambatan', 'Toleransi keterlambatan, cara menghitung denda, serta potongan alpa/izin/sakit. Ingat: tiap potongan masih punya saklar hidup/mati di tab Potongan.', 'absensi', $tab); ?>

<?php elseif ($tab === 'lembur'): ?>
    <?php panel_setelan('Lembur Hari Kerja (Senin–Sabtu)', 'Berbasis jam: jam pertama dan jam berikutnya memakai faktor berbeda.', 'lembur', $tab); ?>
    <div class="kartu">
        <h3><?= icon('calendar', 17) ?> Aturan Hari Minggu yang Berlaku</h3>
        <p class="muted">Upah hari Minggu sengaja dibedakan dari hari biasa dan bisa diubah kapan saja di bawah ini.</p>
        <div class="slip-rekap">
            <div class="slip-rekap-sel"><div class="l">Mode</div><div class="v"><?= conf('minggu_mode', 'tarif_jam') === 'tarif_jam' ? 'Tarif per jam' : '2 upah' ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Tarif Minggu / Jam</div><div class="v"><?= h(rp(conf_num('minggu_tarif_jam', 0))) ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Contoh 10 Jam</div><div class="v"><?= h(rp(conf_num('minggu_tarif_jam', 0) * 10)) ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Batas Minimal Kerja</div><div class="v"><?= h(durasi_indo(conf_int('minggu_minimal_menit', 240))) ?></div></div>
        </div>
        <p class="muted" style="margin-top:10px">
            Mode <strong>tarif per jam</strong> (dipakai perusahaan ini): upah Minggu = jumlah jam kerja hari Minggu × tarif di atas —
            upah harian biasa <em>tidak</em> berlaku pada hari Minggu.
            Mode <strong>2 upah</strong> adalah aturan lama (upah harian + upah lembur minggu) yang tetap tersedia bila sewaktu-waktu diperlukan.
        </p>
    </div>
    <?php panel_setelan('Pengaturan Hari Minggu', 'Pilih cara hitung dan tarifnya. Contoh: tarif Rp20.000 dengan jam kerja 10 jam menghasilkan Rp200.000 per hari Minggu.', 'minggu', $tab); ?>
    <div class="kartu">
        <h3><?= icon('chart', 17) ?> Contoh Perhitungan Lembur</h3>
        <?php $contohLembur = hitung_upah_contoh(5000000); ?>
        <ul class="daftar-sederhana">
            <li>Contoh gaji pokok <?= h(rp(5000000)) ?> → upah lembur per jam = <?= h(rp(5000000)) ?> ÷ <?= h(angka(conf_num('lembur_pembagi', 173))) ?> = <strong><?= h(rp(round($contohLembur['per_jam_lembur']))) ?></strong>.</li>
            <li>Lembur 2 jam = 2 × <?= h(rp(round($contohLembur['per_jam_lembur']))) ?> = <strong><?= h(rp(round(2 * $contohLembur['per_jam_lembur']))) ?></strong><?= conf_bool('lembur_pakai_faktor', false) ? ' (dengan faktor pengali di atas)' : ' (tanpa faktor pengali)' ?>.</li>
            <li>Untuk pembanding: upah harian biasa = <?= h(rp(round($contohLembur['per_hari']))) ?> (pokok ÷ <?= h(angka($contohLembur['hari_kerja'])) ?> hari).</li>
        </ul>
        <p class="muted" style="margin-top:8px">Angka hanya berubah bila setelan di atas diubah — tidak ada nilai tetap di dalam aplikasi.</p>
    </div>

<?php elseif ($tab === 'bonus'): ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('wallet', 17) ?> Parameter Bonus</h2>
                <p class="muted">Semua bonus dihitung dari daftar ini. Tambah baris baru untuk membuat parameter bonus baru — otomatis langsung ikut dihitung pada slip berikutnya.</p>
            </div>
        </div>
        <?php $daftarBonus = db()->query('SELECT * FROM bonus_parameter ORDER BY urutan, nama')->fetchAll(); ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead><tr><th>#</th><th>Nama</th><th>Dasar Hitung</th><th class="angka">Nilai</th><th>Pajak</th><th>Penerima</th><th>Syarat</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($daftarBonus as $b):
                    $jml = (int) db()->query('SELECT COUNT(*) FROM karyawan_bonus WHERE bonus_id = ' . (int) $b['id'])->fetchColumn(); ?>
                    <tr>
                        <td><?= (int) $b['urutan'] ?></td>
                        <td>
                            <strong><?= h($b['nama']) ?></strong><br>
                            <span class="muted">kode: <?= h($b['kode']) ?></span>
                            <?php if ($b['keterangan'] !== ''): ?><br><span class="muted"><?= h($b['keterangan']) ?></span><?php endif; ?>
                        </td>
                        <td><?= h(tipe_bonus()[$b['tipe']] ?? $b['tipe']) ?></td>
                        <td class="angka">
                            <?= (int) $b['kena_pajak'] === 1 ? h(angka((float) $b['nilai'], 2)) : h(angka((float) $b['nilai'], 2)) ?>
                            <?= $b['tipe'] === 'persen_pokok' ? '%' : '' ?>
                        </td>
                        <td><?= (int) $b['kena_pajak'] === 1 ? '<span class="lencana lencana-biru">kena pajak</span>' : '<span class="lencana lencana-abu">bebas pajak</span>' ?></td>
                        <td>
                            <?php if ((int) $b['berlaku_semua'] === 1): ?><span class="lencana lencana-hijau">semua karyawan</span>
                            <?php else: ?><span class="lencana lencana-kuning">khusus (<?= $jml ?> karyawan)</span><?php endif; ?>
                        </td>
                        <td class="muted">
                            <?= (int) $b['syarat_alpa_nol'] === 1 ? 'tanpa alpa' : '' ?>
                            <?= (int) $b['syarat_alpa_nol'] === 1 && (int) $b['syarat_tanpa_terlambat'] === 1 ? ', ' : '' ?>
                            <?= (int) $b['syarat_tanpa_terlambat'] === 1 ? 'tanpa terlambat' : '' ?>
                            <?= (int) $b['syarat_alpa_nol'] === 0 && (int) $b['syarat_tanpa_terlambat'] === 0 ? '—' : '' ?>
                        </td>
                        <td><?= (int) $b['aktif'] === 1 ? '<span class="lencana lencana-hijau">aktif</span>' : '<span class="lencana lencana-abu">nonaktif</span>' ?></td>
                        <td class="kanan nowrap">
                            <a class="btn btn-kecil" href="pengaturan.php?tab=bonus&bonus=<?= (int) $b['id'] ?>#form-bonus">Ubah</a>
                            <form method="post" style="display:inline" data-konfirmasi="Hapus bonus &quot;<?= h($b['nama']) ?>&quot;? Penugasan bonus ke karyawan ikut terhapus.">
                                <input type="hidden" name="aksi" value="bonus_hapus">
                                <input type="hidden" name="bonus_id" value="<?= (int) $b['id'] ?>">
                                <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $ubahBonusId = (int) ($_GET['bonus'] ?? 0);
    $ubahBonus = null;
    if ($ubahBonusId > 0) {
        $st = db()->prepare('SELECT * FROM bonus_parameter WHERE id = ?');
        $st->execute([$ubahBonusId]);
        $ubahBonus = $st->fetch() ?: null;
    }
    $urutanBaru = count($daftarBonus) + 1;
    ?>
    <div class="kartu" id="form-bonus">
        <div class="kartu-kepala">
            <div>
                <h2><?= $ubahBonus ? 'Ubah Bonus: ' . h((string) $ubahBonus['nama']) : 'Tambah Parameter Bonus Baru' ?></h2>
                <p class="muted">Contoh bonus yang bisa ditambahkan: Bonus Kedisiplinan, Bonus Lembur Piket, Bonus Proyek, dst.</p>
            </div>
            <?php if ($ubahBonus): ?><a class="btn btn-kecil" href="pengaturan.php?tab=bonus">Batal Ubah</a><?php endif; ?>
        </div>
        <form method="post">
            <input type="hidden" name="aksi" value="bonus_simpan">
            <input type="hidden" name="bonus_id" value="<?= (int) ($ubahBonus['id'] ?? 0) ?>">
            <div class="form-baris dua">
                <div><label for="bonus_nama">Nama Bonus</label><input type="text" id="bonus_nama" name="bonus_nama" required value="<?= h((string) ($ubahBonus['nama'] ?? '')) ?>" placeholder="cth: Bonus Kedisiplinan"></div>
                <div><label for="bonus_kode">Kode <span class="opsional">(kosong = otomatis)</span></label><input type="text" id="bonus_kode" name="bonus_kode" value="<?= h((string) ($ubahBonus['kode'] ?? '')) ?>" placeholder="cth: bonus_kedisiplinan"></div>
                <div>
                    <label for="bonus_tipe">Dasar Hitung</label>
                    <select id="bonus_tipe" name="bonus_tipe">
                        <?php foreach (tipe_bonus() as $v => $l): ?>
                            <option value="<?= h($v) ?>" <?= ($ubahBonus['tipe'] ?? 'nominal') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-petunjuk">Menentukan nilai dikalikan apa. Lihat daftar tipe di bawah tabel.</div>
                </div>
                <div><label for="bonus_nilai">Nilai</label><input type="number" step="0.01" min="0" id="bonus_nilai" name="bonus_nilai" value="<?= h((string) (float) ($ubahBonus['nilai'] ?? 0)) ?>"></div>
                <div><label for="bonus_urutan">Urutan Tampil</label><input type="number" min="0" step="1" id="bonus_urutan" name="bonus_urutan" value="<?= (int) ($ubahBonus['urutan'] ?? $urutanBaru) ?>"></div>
                <div><label for="bonus_keterangan">Keterangan <span class="opsional">(opsional)</span></label><input type="text" id="bonus_keterangan" name="bonus_keterangan" value="<?= h((string) ($ubahBonus['keterangan'] ?? '')) ?>"></div>
            </div>
            <div class="form-baris tiga" style="margin-top:6px">
                <div class="saklar"><input type="checkbox" id="bonus_aktif" name="bonus_aktif" value="1" <?= (int) ($ubahBonus['aktif'] ?? 1) === 1 ? 'checked' : '' ?>><label for="bonus_aktif">Bonus aktif dihitung</label></div>
                <div class="saklar"><input type="checkbox" id="bonus_kena_pajak" name="bonus_kena_pajak" value="1" <?= (int) ($ubahBonus['kena_pajak'] ?? 1) === 1 ? 'checked' : '' ?>><label for="bonus_kena_pajak">Ikut dihitung sebagai penghasilan kena pajak</label></div>
                <div class="saklar"><input type="checkbox" id="bonus_berlaku_semua" name="bonus_berlaku_semua" value="1" <?= (int) ($ubahBonus['berlaku_semua'] ?? 1) === 1 ? 'checked' : '' ?>><label for="bonus_berlaku_semua">Berlaku untuk semua karyawan</label></div>
                <div class="saklar"><input type="checkbox" id="bonus_syarat_alpa" name="bonus_syarat_alpa" value="1" <?= (int) ($ubahBonus['syarat_alpa_nol'] ?? 0) === 1 ? 'checked' : '' ?>><label for="bonus_syarat_alpa">Hangus bila ada hari alpa</label></div>
                <div class="saklar"><input type="checkbox" id="bonus_syarat_terlambat" name="bonus_syarat_terlambat" value="1" <?= (int) ($ubahBonus['syarat_tanpa_terlambat'] ?? 0) === 1 ? 'checked' : '' ?>><label for="bonus_syarat_terlambat">Hangus bila ada keterlambatan</label></div>
            </div>
            <div class="form-aksi">
                <button class="btn btn-primer" type="submit"><?= $ubahBonus ? 'Simpan Perubahan' : 'Tambah Bonus' ?></button>
            </div>
        </form>
    </div>

    <div class="kartu">
        <h3>Arti Tiap Dasar Hitung</h3>
        <div class="tabel-bungkus">
            <table class="tabel dikit">
                <thead><tr><th>Dasar Hitung</th><th>Rumus</th></tr></thead>
                <tbody>
                <?php foreach (tipe_bonus() as $v => $l): ?>
                    <tr><td><strong><?= h($l) ?></strong><br><span class="muted"><?= h($v) ?></span></td><td><?= h(tipe_bonus_rumus($v)) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($tab === 'potongan'): ?>
    <div class="catatan catatan-info" style="margin-bottom:18px">
        Saat ini potongan yang aktif hanya <strong>Panjar</strong> — sesuai aturan perusahaan Anda.
        Semua potongan lain tetap tersedia dan bisa dinyalakan kapan saja dari saklar di bawah; perubahan langsung
        berlaku pada slip yang dihitung ulang.
        <br><span class="muted">Catatan jujur: PPh 21 dan BPJS pada umumnya wajib bagi perusahaan formal. Bila perusahaan Anda
        memang menanggungnya/berbeda kebijakan, silakan biarkan mati — tetapi bila nanti diwajibkan, tinggal nyalakan saklarnya.</span>
    </div>
    <form method="post" class="kartu">
        <input type="hidden" name="aksi" value="potongan_simpan">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('receipt', 17) ?> Saklar Potongan</h2>
                <p class="muted">Hidup/matikan tiap jenis potongan. Rincian nominalnya diatur di tab terkait.</p>
            </div>
        </div>
        <div class="form-baris dua">
            <?php foreach (conf_kelompok('potongan') as $kunci => $m): ?>
                <div>
                    <label><?= h((string) $m['label']) ?></label>
                    <div class="saklar" style="padding-top:0">
                        <input type="checkbox" id="cfg_<?= h($kunci) ?>" name="cfg_<?= h($kunci) ?>" value="1" <?= conf_bool($kunci) ? 'checked' : '' ?>>
                        <label for="cfg_<?= h($kunci) ?>">Aktif</label>
                    </div>
                    <?php if (!empty($m['keterangan'])): ?><div class="form-petunjuk"><?= h((string) $m['keterangan']) ?></div><?php endif; ?>
                    <?php
                    $tautan = ['potongan_denda_aktif' => ['tab' => 'penggajian', 'label' => 'Atur denda'],
                        'potongan_alpa_aktif' => ['tab' => 'penggajian', 'label' => 'Atur potongan alpa'],
                        'potongan_izin_aktif' => ['tab' => 'penggajian', 'label' => 'Atur potongan izin'],
                        'potongan_sakit_aktif' => ['tab' => 'penggajian', 'label' => 'Atur potongan sakit']][$kunci] ?? null;
                    ?>
                    <?php if ($tautan): ?><a class="muted" style="font-size:.78rem" href="pengaturan.php?tab=<?= h($tautan['tab']) ?>"><?= h($tautan['label']) ?> →</a><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div>
                <label>BPJS Ketenagakerjaan &amp; Kesehatan</label>
                <div class="saklar" style="padding-top:0">
                    <input type="checkbox" id="cfg_bpjs_aktif" name="cfg_bpjs_aktif" value="1" <?= conf_bool('bpjs_aktif') ? 'checked' : '' ?>>
                    <label for="cfg_bpjs_aktif">Aktif</label>
                </div>
                <div class="form-petunjuk">Bila aktif, iuran karyawan menjadi potongan dan iuran perusahaan muncul sebagai tunjangan. Persentase: <a href="pengaturan.php?tab=bpjs">tab BPJS</a>.</div>
            </div>
            <div>
                <label>PPh 21</label>
                <div class="saklar" style="padding-top:0">
                    <input type="checkbox" id="cfg_pajak_aktif" name="cfg_pajak_aktif" value="1" <?= conf_bool('pajak_aktif') ? 'checked' : '' ?>>
                    <label for="cfg_pajak_aktif">Aktif</label>
                </div>
                <div class="form-petunjuk">Tarif, PTKP, dan metode pajak per karyawan: <a href="pengaturan.php?tab=pajak">tab Pajak &amp; PTKP</a>.</div>
            </div>
        </div>
        <div class="form-aksi"><button class="btn btn-primer" type="submit">Simpan Saklar Potongan</button></div>
    </form>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('wallet', 17) ?> Daftar Panjar Berjalan</h2>
                <p class="muted">Panjar ditambahkan per karyawan di menu <a href="karyawan.php">Karyawan</a> → bagian Panjar. Sisa potongan dihitung dari riwayat slip.</p>
            </div>
        </div>
        <?php $panjarSemua = db()->query('SELECT p.*, k.nama, k.nip FROM panjar p JOIN karyawan k ON k.id = p.karyawan_id ORDER BY p.aktif DESC, k.nama')->fetchAll(); ?>
        <?php if ($panjarSemua): ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Karyawan</th><th>Keterangan</th><th class="angka">Jumlah Panjar</th><th class="angka">Cicilan / Bulan</th><th class="angka">Sudah Dipotong</th><th class="angka">Sisa</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php $tJumlah = 0; $tSisa = 0; foreach ($panjarSemua as $pj):
                        $sudah = 0.0;
                        $stS = db()->prepare('SELECT COALESCE(SUM(i.nilai),0) FROM slip_item i JOIN slip_gaji s ON s.id=i.slip_id WHERE i.sumber = ?');
                        $stS->execute(['panjar:' . $pj['id']]);
                        $sudah = (float) $stS->fetchColumn();
                        $sisa = max(0.0, (float) $pj['jumlah'] - $sudah);
                        $tJumlah += (float) $pj['jumlah'];
                        $tSisa += $sisa; ?>
                        <tr>
                            <td><strong><?= h($pj['nama']) ?></strong><br><span class="muted"><?= h($pj['nip']) ?></span></td>
                            <td><?= h($pj['keterangan']) ?><br><span class="muted"><?= h(tanggal_indo((string) $pj['tanggal'])) ?></span></td>
                            <td class="angka"><?= h(rp((float) $pj['jumlah'])) ?></td>
                            <td class="angka"><?= h(rp((float) $pj['cicilan_per_bulan'])) ?></td>
                            <td class="angka"><?= h(rp($sudah)) ?></td>
                            <td class="angka"><strong><?= h(rp($sisa)) ?></strong></td>
                            <td><?= $sisa <= 0 ? '<span class="lencana lencana-hijau">lunas</span>' : ((int) $pj['aktif'] === 1 ? '<span class="lencana lencana-kuning">berjalan</span>' : '<span class="lencana lencana-abu">nonaktif</span>') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="baris-total"><td colspan="2">Total</td><td class="angka"><?= h(rp($tJumlah)) ?></td><td class="angka"></td><td class="angka"></td><td class="angka"><?= h(rp($tSisa)) ?></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="kosong"><strong>Belum ada panjar</strong>Tambahkan dari halaman <a href="karyawan.php">Karyawan</a>.</div>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'bpjs'): ?>
    <div class="catatan catatan-info" style="margin-bottom:18px">
        Saklar utama BPJS ada di <a href="pengaturan.php?tab=potongan">tab Potongan</a> — status saat ini:
        <strong><?= conf_bool('bpjs_aktif') ? 'AKTIF' : 'MATI' ?></strong>.
        <?= conf_bool('bpjs_aktif') ? '' : 'Selama saklar mati, iuran di bawah tidak dipotong dan iuran perusahaan tidak muncul sebagai tunjangan.' ?>
    </div>
    <?php panel_setelan('BPJS Ketenagakerjaan & Kesehatan', 'Persentase yang ditanggung karyawan dan yang ditanggung perusahaan. Dipakai hanya bila saklar BPJS menyala.', 'bpjs', $tab); ?>

<?php elseif ($tab === 'pajak'): ?>
    <?php panel_setelan('Pengaturan PPh 21', 'Metode bawaan, biaya jabatan, dan pembulatan PKP. Metode pajak sendiri dipilih per karyawan di menu Karyawan.', 'pajak', $tab); ?>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Status PTKP (Penghasilan Tidak Kena Pajak)</h2>
                <p class="muted">Batas PTKP setahun per status — tambah atau ubah sesuai regulasi terbaru.</p>
            </div>
        </div>
        <div class="tabel-bungkus" style="margin-bottom:16px">
            <table class="tabel">
                <thead><tr><th>Kode</th><th>Keterangan</th><th class="angka">PTKP Setahun</th><th class="angka">PTKP per Bulan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach (daftar_ptkp(false) as $p): ?>
                    <tr>
                        <td><strong><?= h($p['kode']) ?></strong></td>
                        <td><?= h($p['keterangan']) ?></td>
                        <td class="angka"><?= h(rp((float) $p['setahun'])) ?></td>
                        <td class="angka"><?= h(rp((float) $p['setahun'] / 12)) ?></td>
                        <td><?= (int) $p['aktif'] === 1 ? '<span class="lencana lencana-hijau">aktif</span>' : '<span class="lencana lencana-abu">nonaktif</span>' ?></td>
                        <td class="kanan">
                            <form method="post" data-konfirmasi="Hapus status PTKP <?= h($p['kode']) ?>?">
                                <input type="hidden" name="aksi" value="ptkp_hapus">
                                <input type="hidden" name="kode" value="<?= h($p['kode']) ?>">
                                <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="ptkp_simpan">
            <div><label for="kode">Kode PTKP</label><input type="text" id="kode" name="kode" placeholder="cth: K/2" required></div>
            <div><label for="keterangan">Keterangan</label><input type="text" id="keterangan" name="keterangan" placeholder="Kawin, 2 tanggungan"></div>
            <div><label for="setahun">PTKP Setahun</label><input type="number" min="0" step="1" id="setahun" name="setahun" required></div>
            <div><label for="urutan">Urutan</label><input type="number" min="0" step="1" id="urutan" name="urutan" value="99"></div>
            <div class="saklar"><input type="checkbox" id="ptkp_aktif" name="aktif" value="1" checked><label for="ptkp_aktif">Aktif</label></div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Simpan PTKP</button></div>
        </form>
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Lapisan Tarif PPh 21 Progresif</h2>
                <p class="muted">Dihitung berlapis dari PKP. Batas atas 0 berarti tanpa batas (lapisan tertinggi).</p>
            </div>
        </div>
        <div class="tabel-bungkus" style="margin-bottom:16px">
            <table class="tabel">
                <thead><tr><th>#</th><th class="angka">Batas Bawah</th><th class="angka">Batas Atas</th><th class="angka">Tarif</th><th>Keterangan</th><th></th></tr></thead>
                <tbody>
                <?php foreach (pph21_layers() as $l): ?>
                    <tr>
                        <td><?= (int) $l['urutan'] ?></td>
                        <td class="angka"><?= h(rp((float) $l['batas_bawah'])) ?></td>
                        <td class="angka"><?= (float) $l['batas_atas'] > 0 ? h(rp((float) $l['batas_atas'])) : 'tanpa batas' ?></td>
                        <td class="angka"><?= h(angka((float) $l['tarif_persen'], 2)) ?>%</td>
                        <td class="muted"><?= h($l['keterangan']) ?></td>
                        <td class="kanan">
                            <form method="post" data-konfirmasi="Hapus lapisan tarif ini?">
                                <input type="hidden" name="aksi" value="layer_hapus">
                                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="layer_simpan">
            <div><label for="urutan2">Urutan</label><input type="number" min="1" step="1" id="urutan2" name="urutan" value="<?= count(pph21_layers()) + 1 ?>"></div>
            <div><label for="batas_bawah">Batas Bawah (PKP)</label><input type="number" min="0" step="1" id="batas_bawah" name="batas_bawah" value="0"></div>
            <div><label for="batas_atas">Batas Atas <span class="opsional">(0 = tanpa batas)</span></label><input type="number" min="0" step="1" id="batas_atas" name="batas_atas" value="0"></div>
            <div><label for="tarif_persen">Tarif (%)</label><input type="number" min="0" step="0.01" id="tarif_persen" name="tarif_persen" required></div>
            <div class="kolom-2"><label for="keterangan2">Keterangan</label><input type="text" id="keterangan2" name="keterangan"></div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Tambah Lapisan</button></div>
        </form>
    </div>

<?php elseif ($tab === 'slip'): ?>
    <?php panel_setelan('Tampilan Slip Gaji', 'Judul, catatan kaki, dan bagian mana saja yang dicetak pada slip.', 'slip', $tab); ?>
    <?php panel_setelan('Penanda Tangan', 'Nama, jabatan, dan gambar tanda tangan pada slip gaji.', 'ttd', $tab); ?>

    <div class="kartu">
        <h2>Contoh Terbilang</h2>
        <p class="muted">Take home pay dicetak otomatis dalam huruf. Contoh:</p>
        <div class="rincian-baris"><span><?= h(rp(1575000)) ?></span><span class="nilai"><?= h(terbilang_rupiah(1575000)) ?></span></div>
        <div class="rincian-baris"><span><?= h(rp(8250000)) ?></span><span class="nilai"><?= h(terbilang_rupiah(8250000)) ?></span></div>
        <div class="rincian-baris"><span><?= h(rp(12345678)) ?></span><span class="nilai"><?= h(terbilang_rupiah(12345678)) ?></span></div>
    </div>

<?php elseif ($tab === 'libur'): ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('calendar', 17) ?> Hari Kerja Mingguan</h2>
                <p class="muted">Diatur pada tab <a href="pengaturan.php?tab=penggajian">Komponen Penggajian → Dasar Perhitungan</a>. Nilai saat ini: <?= h(implode(', ', array_map(fn($n) => NAMA_HARI[$n], hari_kerja_mingguan()))) ?>.</p>
            </div>
        </div>
        <h3 style="margin-bottom:10px">Hari Libur Terdaftar</h3>
        <?php $libur = db()->query('SELECT * FROM hari_libur ORDER BY tanggal DESC')->fetchAll(); ?>
        <?php if ($libur): ?>
            <div class="tabel-bungkus" style="margin-bottom:16px">
                <table class="tabel">
                    <thead><tr><th>Tanggal</th><th>Hari</th><th>Keterangan</th><th>Jenis</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($libur as $l): ?>
                        <tr>
                            <td class="nowrap"><?= h(tanggal_indo((string) $l['tanggal'])) ?></td>
                            <td><?= h(NAMA_HARI[(int) date('N', strtotime((string) $l['tanggal']))]) ?></td>
                            <td><?= h($l['keterangan']) ?></td>
                            <td class="muted"><?= h(str_replace('_', ' ', (string) $l['jenis'])) ?></td>
                            <td class="kanan">
                                <form method="post" data-konfirmasi="Hapus hari libur ini?">
                                    <input type="hidden" name="aksi" value="libur_hapus">
                                    <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                    <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">Belum ada hari libur terdaftar.</p>
        <?php endif; ?>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="libur_simpan">
            <div><label for="tanggal_libur">Tanggal</label><?= input_tanggal('tanggal', date('Y-m-d'), ['id' => 'tanggal_libur', 'wajib' => true]) ?></div>
            <div><label for="ket_libur">Keterangan</label><input type="text" id="ket_libur" name="keterangan" placeholder="cth: Cuti bersama"></div>
            <div>
                <label for="jenis_libur">Jenis</label>
                <select id="jenis_libur" name="jenis">
                    <option value="libur_nasional">Libur Nasional</option>
                    <option value="cuti_bersama">Cuti Bersama</option>
                    <option value="libur_perusahaan">Libur Perusahaan</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Tambah Hari Libur</button></div>
        </form>
    </div>

<?php elseif ($tab === 'datareset'): ?>
    <?php $ringkas = data_uji_ringkas(); ?>
    <div class="catatan catatan-info" style="margin-bottom:18px">
        Tab ini untuk merapikan data. <strong>Data karyawan, divisi, bonus, panjar, dan seluruh setelan tidak pernah disentuh.</strong>
        Hanya baris yang <strong>bertanda uji</strong> (dibuat oleh data contoh/pengujian) yang bisa dihapus di sini.
        Setiap tindakan tercatat di Jejak Audit.
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('chart', 17) ?> Isi Database Saat Ini</h2>
                <p class="muted">Pratinjau sebelum menghapus — angka di bawah adalah jumlah baris yang akan terpengaruh.</p>
            </div>
        </div>
        <div class="slip-rekap">
            <div class="slip-rekap-sel"><div class="l">Absensi bertanda uji</div><div class="v"><?= (int) $ringkas['absensi_uji'] ?></div></div>
            <div class="slip-rekap-sel"><div class="l">…di antaranya sudah Anda ubah</div><div class="v"><?= (int) $ringkas['absensi_uji_diubah'] ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Slip bertanda uji</div><div class="v"><?= (int) $ringkas['slip_uji'] ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Absensi yang Anda input sendiri</div><div class="v"><?= (int) $ringkas['absensi_asli'] ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Slip yang Anda hitung sendiri</div><div class="v"><?= (int) $ringkas['slip_asli'] ?></div></div>
            <div class="slip-rekap-sel"><div class="l">Data contoh otomatis</div><div class="v"><?= $ringkas['demo_nonaktif'] ? 'dimatikan' : 'masih aktif' ?></div></div>
        </div>
        <?php if ($ringkas['sebaran']): ?>
            <h3 style="margin:18px 0 8px">Sebaran Absensi Bertanda Uji</h3>
            <div class="tabel-bungkus">
                <table class="tabel dikit">
                    <thead><tr><th>Karyawan</th><th class="angka">Baris</th><th>Tanggal Pertama</th><th>Tanggal Terakhir</th></tr></thead>
                    <tbody>
                    <?php foreach ($ringkas['sebaran'] as $r): ?>
                        <tr>
                            <td><?= h($r['nama']) ?></td>
                            <td class="angka"><?= (int) $r['jml'] ?></td>
                            <td class="nowrap"><?= h(tanggal_indo((string) $r['dari'])) ?></td>
                            <td class="nowrap"><?= h(tanggal_indo((string) $r['sampai'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top:10px">
                Periode yang terpengaruh:
                <?= h(implode(', ', array_map(fn($p) => bulan_indo((string) $p['bulan']) . ' (' . $p['jml'] . ' baris)', $ringkas['periode']))) ?>
            </p>
        <?php else: ?>
            <p class="muted" style="margin-top:12px">Tidak ada absensi bertanda uji — database sudah bersih.</p>
        <?php endif; ?>

        <?php if ($ringkas['absensi_uji_diubah_daftar']): ?>
            <h3 style="margin:20px 0 8px">Baris Uji yang Pernah Diubah (perlu keputusan Anda)</h3>
            <p class="muted" style="margin-bottom:10px">
                Baris di bawah berasal dari data contoh, tetapi pernah <strong>disimpan/diubah</strong> — kemungkinan sudah Anda
                jadikan data nyata. Secara bawaan baris ini <strong>dipertahankan</strong>; centang opsi
                “Ikut hapus baris uji yang sudah saya ubah” bila memang masih data contoh.
            </p>
            <div class="tabel-bungkus">
                <table class="tabel dikit">
                    <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Status</th><th class="angka">Terlambat</th><th class="angka">Lembur</th><th>Terakhir Diubah</th></tr></thead>
                    <tbody>
                    <?php foreach ($ringkas['absensi_uji_diubah_daftar'] as $r): ?>
                        <tr>
                            <td class="nowrap"><?= h(tanggal_indo((string) $r['tanggal'], true)) ?></td>
                            <td><?= h($r['nama']) ?></td>
                            <td><span class="lencana lencana-abu"><?= h(status_absensi()[$r['status']] ?? $r['status']) ?></span></td>
                            <td class="angka"><?= (int) $r['menit_terlambat'] ?> mnt</td>
                            <td class="angka"><?= (int) $r['menit_lembur'] ?> mnt</td>
                            <td class="muted nowrap"><?= h((string) $r['updated_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('undo', 17) ?> Bersihkan Data Uji</h2>
                <p class="muted">Menghapus absensi &amp; slip yang berasal dari data contoh/pengujian, lalu mematikan pembuatan data contoh untuk selamanya.</p>
            </div>
        </div>
        <form method="post" data-konfirmasi="Hapus data uji sekarang? Data karyawan, bonus, panjar, dan setelan TIDAK terhapus.">
            <input type="hidden" name="aksi" value="data_uji_bersih">
            <div class="saklar" style="padding-top:0">
                <input type="checkbox" id="ikut_ubah" name="ikut_ubah" value="1">
                <label for="ikut_ubah">Ikut hapus baris uji yang <strong>sudah saya ubah</strong>
                    (<?= (int) $ringkas['absensi_uji_diubah'] ?> baris) — centang bila baris itu memang masih data contoh</label>
            </div>
            <div class="form-petunjuk">Bila tidak dicentang, baris uji yang pernah Anda ubah akan DIPERTAHANKAN (dianggap sudah menjadi data nyata).</div>
            <div class="saklar">
                <input type="checkbox" id="tanpa_slip" name="tanpa_slip" value="1">
                <label for="tanpa_slip">Jangan hapus slip uji (<?= (int) $ringkas['slip_uji'] ?> slip) — biarkan slip hasil uji tetap ada</label>
            </div>
            <div class="saklar">
                <input type="checkbox" id="tanpa_tandai" name="tanpa_tandai" value="1">
                <label for="tanpa_tandai">Jangan tandai baris editan sebagai data nyata</label>
            </div>
            <div class="form-petunjuk">
                Bawaan (tidak dicentang): baris uji yang pernah Anda ubah akan <strong>ditandai sebagai data nyata</strong> —
                isinya tidak diubah, hanya penanda uji-nya dilepas supaya tidak lagi muncul sebagai data uji.
            </div>
            <div class="form-baris" style="margin-top:14px;max-width:320px">
                <div>
                    <label for="konfirmasi_bersih">Ketik <strong>BERSIH</strong> untuk konfirmasi</label>
                    <input type="text" id="konfirmasi_bersih" name="konfirmasi_bersih" placeholder="BERSIH" autocomplete="off">
                </div>
            </div>
            <div class="form-aksi">
                <button class="btn btn-merah" type="submit">Bersihkan Data Uji Sekarang</button>
            </div>
        </form>
    </div>

    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('calendar', 17) ?> Hapus Absensi per Rentang Tanggal</h2>
                <p class="muted">Untuk menghapus absensi periode tertentu (data lama/percobaan Anda sendiri). Tindakan ini juga tercatat di Jejak Audit.</p>
            </div>
        </div>
        <form method="post" data-konfirmasi="Hapus absensi pada rentang tanggal ini?">
            <input type="hidden" name="aksi" value="absensi_hapus_rentang">
            <div class="form-baris tiga">
                <div><label for="dari">Dari Tanggal</label><?= input_tanggal('dari', date('Y-m-01')) ?></div>
                <div><label for="sampai">Sampai Tanggal</label><?= input_tanggal('sampai', date('Y-m-t')) ?></div>
                <div>
                    <label for="konfirmasi_hapus_absen">Ketik <strong>HAPUS</strong></label>
                    <input type="text" id="konfirmasi_hapus_absen" name="konfirmasi_hapus_absen" placeholder="HAPUS" autocomplete="off">
                </div>
            </div>
            <div class="form-aksi"><button class="btn btn-merah" type="submit">Hapus Absensi Rentang Ini</button></div>
        </form>
    </div>

    <div class="kartu">
        <h3><?= icon('gear', 17) ?> Status Data Contoh</h3>
        <p class="muted">
            Data contoh (8 karyawan, absensi bulan lalu &amp; bulan ini, 16 slip) dibuat otomatis sekali saat aplikasi pertama dijalankan.
            <?php if ($ringkas['demo_nonaktif']): ?>
                Saat ini <strong>sudah dimatikan</strong> → tidak akan membuat data contoh lagi.
            <?php else: ?>
                Saat ini <strong>masih aktif</strong> → bila absensi &amp; slip contoh dihapus sementara flag belum dimatikan, data contoh bisa dibuat ulang.
                Tekan tombol di bawah untuk mematikannya.
            <?php endif; ?>
        </p>
        <?php if (!$ringkas['demo_nonaktif']): ?>
            <form method="post">
                <input type="hidden" name="aksi" value="data_uji_bersih">
                <input type="hidden" name="tanpa_slip" value="1">
                <input type="hidden" name="konfirmasi_bersih" value="BERSIH">
                <button class="btn" type="submit">Matikan Data Contoh</button>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($tab === 'akun' && $role === 'superadmin'): ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('users', 17) ?> Akun Pengguna</h2>
                <p class="muted">Superadmin mengelola semuanya; HRD mengelola data &amp; penggajian tanpa akses akun.</p>
            </div>
        </div>
        <div class="tabel-bungkus" style="margin-bottom:18px">
            <table class="tabel">
                <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Status</th><th>Terakhir Diubah</th><th></th></tr></thead>
                <tbody>
                <?php foreach (daftar_user() as $u): ?>
                    <tr>
                        <td><strong><?= h($u['username']) ?></strong></td>
                        <td><?= h($u['nama']) ?></td>
                        <td><span class="lencana lencana-biru"><?= h(label_role((string) $u['role'])) ?></span></td>
                        <td><?= (int) $u['aktif'] === 1 ? '<span class="lencana lencana-hijau">aktif</span>' : '<span class="lencana lencana-abu">nonaktif</span>' ?></td>
                        <td class="muted nowrap"><?= h((string) $u['updated_at']) ?></td>
                        <td class="kanan nowrap">
                            <form method="post" data-konfirmasi="Hapus akun <?= h($u['username']) ?>?">
                                <input type="hidden" name="aksi" value="user_hapus">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <h3 style="margin-bottom:10px">Tambah / Ubah Akun</h3>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="user_simpan">
            <div><label for="username">Username</label><input type="text" id="username" name="username" pattern="[A-Za-z0-9._\-]{3,24}" required></div>
            <div><label for="nama_user">Nama Tampilan</label><input type="text" id="nama_user" name="nama"></div>
            <div>
                <label for="role">Peran</label>
                <select id="role" name="role">
                    <?php foreach (daftar_peran() as $v => $l): ?><option value="<?= h($v) ?>"><?= h($l) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label for="sandi_user">Kata Sandi <span class="opsional">(min 5 karakter)</span></label><input type="password" id="sandi_user" name="sandi"></div>
            <div class="saklar"><input type="checkbox" id="user_aktif" name="aktif" value="1" checked><label for="user_aktif">Aktif</label></div>
            <div style="display:flex;align-items:flex-end"><button class="btn" type="submit">Simpan Akun</button></div>
        </form>
    </div>

    <div class="kartu">
        <h2>Ganti Kata Sandi Saya</h2>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="sandi_saya">
            <div><label for="sandi_lama">Kata Sandi Lama</label><input type="password" id="sandi_lama" name="sandi_lama" required></div>
            <div><label for="sandi_baru">Kata Sandi Baru</label><input type="password" id="sandi_baru" name="sandi_baru" required></div>
            <div><label for="sandi_ulang">Ulangi Kata Sandi Baru</label><input type="password" id="sandi_ulang" name="sandi_ulang" required></div>
            <div style="display:flex;align-items:flex-end"><button class="btn btn-primer" type="submit">Ubah Kata Sandi</button></div>
        </form>
    </div>

<?php elseif ($tab === 'akun-hrd' || ($tab === 'akun' && $role === 'hrd')): ?>
    <div class="kartu">
        <h2>Ganti Kata Sandi Saya</h2>
        <p class="muted">Pengelolaan akun pengguna lain hanya dapat dilakukan oleh Superadmin.</p>
        <form method="post" class="form-baris tiga">
            <input type="hidden" name="aksi" value="sandi_saya">
            <div><label for="sandi_lama2">Kata Sandi Lama</label><input type="password" id="sandi_lama2" name="sandi_lama" required></div>
            <div><label for="sandi_baru2">Kata Sandi Baru</label><input type="password" id="sandi_baru2" name="sandi_baru" required></div>
            <div><label for="sandi_ulang2">Ulangi Kata Sandi Baru</label><input type="password" id="sandi_ulang2" name="sandi_ulang" required></div>
            <div style="display:flex;align-items:flex-end"><button class="btn btn-primer" type="submit">Ubah Kata Sandi</button></div>
        </form>
    </div>

<?php elseif ($tab === 'audit'): ?>
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2>Jejak Audit</h2>
                <p class="muted">Catatan tindakan penting (perubahan konfigurasi, karyawan, absensi, slip) beserta pelakunya.</p>
            </div>
        </div>
        <?php $log = log_admin_terakhir(60); ?>
        <?php if ($log): ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead><tr><th>Waktu</th><th>Oleh</th><th>Aksi</th><th>Rincian</th></tr></thead>
                    <tbody>
                    <?php foreach ($log as $l): ?>
                        <tr>
                            <td class="nowrap muted"><?= h((string) $l['waktu']) ?></td>
                            <td><?= h((string) $l['oleh']) ?></td>
                            <td><span class="lencana lencana-biru"><?= h((string) $l['aksi']) ?></span></td>
                            <td><?= h((string) $l['rincian']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="kosong"><strong>Belum ada catatan</strong></div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="catatan">Tab ini tidak tersedia untuk peran Anda. Silakan pilih tab lain di atas.</div>
<?php endif; ?>
<?php page_foot(); ?>
