<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';
require_login();
demo_siapkan();

$periode = periode_valid((string) ($_GET['periode'] ?? date('Y-m')));
$tanggal = tanggal_valid((string) ($_GET['tanggal'] ?? date('Y-m-d')), date('Y-m-d'));
if (substr($tanggal, 0, 7) !== $periode && !isset($_GET['periode'])) {
    $periode = substr($tanggal, 0, 7);
}

if (is_post()) {
    $aksi = post('aksi');
    if ($aksi === 'harian_simpan') {
        $tgl = tanggal_valid(post('tanggal'));
        $statusArr = (array) ($_POST['status'] ?? []);
        $simpan = 0;
        foreach ($statusArr as $kid => $status) {
            $kid = (int) $kid;
            $masuk = (string) ($_POST['masuk'][$kid] ?? '');
            $ket = trim((string) ($_POST['ket'][$kid] ?? ''));
            $jamMinggu = trim((string) ($_POST['jam_minggu'][$kid] ?? ''));
            if ($status === 'KOSONG') {
                /* Baris dikosongkan → hapus catatan tanggal itu (bila ada). */
                db()->prepare('DELETE FROM absensi WHERE karyawan_id = ? AND tanggal = ?')->execute([$kid, $tgl]);
                continue;
            }
            if ($status === '' && $masuk === '' && $jamMinggu === '' && $ket === '') {
                db()->prepare('DELETE FROM absensi WHERE karyawan_id = ? AND tanggal = ?')->execute([$kid, $tgl]);
                continue;
            }
            $hasil = absensi_simpan([
                'karyawan_id' => $kid,
                'tanggal' => $tgl,
                'status' => $status,
                'jam_masuk' => $masuk,
                'jam_pulang' => (string) ($_POST['pulang'][$kid] ?? ''),
                'menit_terlambat' => (int) ($_POST['terlambat'][$kid] ?? 0),
                'menit_lembur' => (int) ($_POST['lembur'][$kid] ?? 0),
                'auto_terlambat' => !empty($_POST['auto_terlambat'][$kid]),
                'auto_lembur' => !empty($_POST['auto_lembur'][$kid]),
                'lembur_disetujui' => !empty($_POST['setuju'][$kid]),
                /* Hari Minggu: jumlah jam kerja (jam, boleh desimal) → dihitung menjadi menit di lib.php */
                'jam_minggu' => (string) ($_POST['jam_minggu'][$kid] ?? ''),
                'keterangan' => $ket,
            ], sesi_username());
            if (!empty($hasil['ok'])) {
                $simpan++;
            }
        }
        log_admin('absensi_harian', "Simpan absensi {$tgl} ({$simpan} baris)");
        redirect('absensi.php?tanggal=' . $tgl . '&periode=' . substr($tgl, 0, 7) . '&ok=' . rawurlencode("Absensi {$simpan} karyawan disimpan."));
    } elseif ($aksi === 'isi_cepat') {
        /* Semua karyawan aktif ditandai Hadir dengan jam kerja standar. */
        $tgl = tanggal_valid(post('tanggal'));
        $masuk = post('jam_masuk_cepat', '08:00');
        $pulang = post('jam_pulang_cepat', '17:00');
        $jml = 0;
        foreach (daftar_karyawan('', true) as $k) {
            absensi_simpan([
                'karyawan_id' => (int) $k['id'], 'tanggal' => $tgl, 'status' => 'HADIR',
                'jam_masuk' => $masuk, 'jam_pulang' => $pulang, 'menit_terlambat' => 0, 'menit_lembur' => 0,
                'lembur_disetujui' => 0, 'keterangan' => 'Diisi cepat oleh ' . sesi_username(),
            ], sesi_username());
            $jml++;
        }
        log_admin('absensi_cepat', "Isi cepat Hadir {$tgl} ({$jml} karyawan)");
        redirect('absensi.php?tanggal=' . $tgl . '&periode=' . substr($tgl, 0, 7) . '&ok=' . rawurlencode("Semua karyawan aktif ditandai Hadir untuk {$tgl}."));
    } elseif ($aksi === 'hapus') {
        absensi_hapus(post_int('id'));
        log_admin('absensi_hapus', 'Hapus absensi #' . post_int('id'));
        redirect('absensi.php?periode=' . $periode . '&ok=' . rawurlencode('Catatan absensi dihapus.'));
    } elseif ($aksi === 'hapus_bulan') {
        /* Menghapus absensi satu bulan sekaligus (tindakan merusak) → hanya superadmin. */
        if (sesi_role() !== 'superadmin') {
            redirect('absensi.php?periode=' . $periode . '&err=' . rawurlencode('Hanya Superadmin yang boleh menghapus absensi satu periode.'));
        }
        $jml = (int) db()->query('SELECT COUNT(*) FROM absensi WHERE tanggal LIKE "' . $periode . '%"')->fetchColumn();
        db()->prepare('DELETE FROM absensi WHERE tanggal LIKE ?')->execute([$periode . '%']);
        log_admin('absensi_hapus_bulan', "Hapus seluruh absensi periode {$periode} ({$jml} baris)");
        redirect('absensi.php?periode=' . $periode . '&ok=' . rawurlencode("Seluruh absensi periode " . bulan_indo($periode) . " ({$jml} baris) dihapus."));
    }
}

$karyawan = daftar_karyawan('', true);
$liburMap = hari_libur_map($periode);
$hariKerjaIni = tanggal_hari_kerja($tanggal, $liburMap);
$absensiTanggal = [];
foreach (absensi_rentang(substr($tanggal, 0, 7), 0) as $a) {
    if ($a['tanggal'] === $tanggal) {
        $absensiTanggal[(int) $a['karyawan_id']] = $a;
    }
}
$jamStandarMasuk = jam_masuk_standar();
$jamStandarPulang = jam_pulang_standar();
$segmenKerja = jam_kerja_segmen();
/* Hari MINGGU punya bentuk input sendiri: status Hadir/Libur + jumlah jam kerja. */
$minggu = (int) date('N', strtotime($tanggal)) === 7;

page_head('Absensi & Lembur', 'absensi.php');
?>
<div class="judul-halaman">
    <div>
        <h1>Absensi &amp; Lembur</h1>
        <p class="muted">Pencatatan kehadiran harian dan rekapitulasi otomatis per periode penggajian.</p>
    </div>
    <form method="get" class="btn-baris">
        <input type="month" name="periode" value="<?= h($periode) ?>" onchange="this.form.submit()">
    </form>
</div>

<?php flash(); ?>

<div class="tab-baris">
    <button class="tab" type="button" data-tab="harian">Input Harian</button>
    <button class="tab" type="button" data-tab="daftar">Daftar Absensi</button>
    <button class="tab" type="button" data-tab="rekap">Rekap Bulanan</button>
</div>

<div class="panel" data-panel="harian">
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('calendar', 17) ?> Absensi <?= h(tanggal_indo($tanggal, true)) ?></h2>
                <p class="muted">
                    <?= $hariKerjaIni ? 'Hari kerja' : 'Bukan hari kerja (akhir pekan/libur)' ?> ·
                    Kosongkan baris bila karyawan tidak perlu dicatat (data tanggal itu akan dihapus).
                </p>
            </div>
            <form method="get" class="btn-baris">
                <input type="hidden" name="periode" value="<?= h($periode) ?>">
                <a class="btn btn-kecil" href="absensi.php?tanggal=<?= h(date('Y-m-d', strtotime($tanggal . ' -1 day'))) ?>&periode=<?= h($periode) ?>">‹ Sebelumnya</a>
                <?= input_tanggal('tanggal', $tanggal, ['data_tanggal' => 'kirim']) ?>
                <a class="btn btn-kecil" href="absensi.php?tanggal=<?= h(date('Y-m-d', strtotime($tanggal . ' +1 day'))) ?>&periode=<?= h($periode) ?>">Berikutnya ›</a>
                <a class="btn btn-kecil" href="absensi.php?tanggal=<?= h(date('Y-m-d')) ?>&periode=<?= h(date('Y-m')) ?>">Hari ini</a>
            </form>
        </div>

        <div class="catatan catatan-info" style="margin-bottom:16px">
            <strong>Jam kerja berlaku: <?= h(jam_kerja_teks()) ?></strong>.
            <strong>Jam masuk</strong> dan <strong>jam pulang</strong> hanya aktif bila status <strong>Hadir</strong> — status lain
            otomatis dinonaktifkan. Menit <strong>terlambat</strong> dihitung otomatis dari jam masuk (standar
            <strong><?= h($jamStandarMasuk) ?></strong>), dan menit <strong>lembur</strong> dihitung dari jam pulang
            (di atas <strong><?= h($jamStandarPulang) ?></strong>) <em>hanya bila centang “Lembur disetujui” aktif</em>.
            Semua nilai bisa dikoreksi manual; setelah diketik manual, kolom itu tidak lagi dihitung otomatis.
            <div style="margin-top:6px">
                <?php foreach ($segmenKerja as $g): ?>
                    <span class="lencana <?= $g['istirahat'] ? 'lencana-kuning' : 'lencana-hijau' ?>">
                        <?= h($g['nama']) ?> <?= h($g['mulai']) ?>–<?= h($g['selesai']) ?><?= $g['istirahat'] ? ' (istirahat, tidak dibayar)' : '' ?>
                    </span>
                <?php endforeach; ?>
                <span class="lencana lencana-biru">Hari Minggu: status hanya Hadir/Libur, cukup isi jumlah jam kerja → dibayar <?= h(rp(conf_num('minggu_tarif_jam', 0))) ?>/jam</span>
            </div>
        </div>

        <form method="post" id="absensi-harian">
            <input type="hidden" name="aksi" value="harian_simpan">
            <input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <?php if ($minggu): ?>
                            <tr>
                                <th>Karyawan</th><th>Status</th>
                                <th class="angka">Jam Kerja</th>
                                <th class="angka">Upah Minggu</th>
                                <th>Keterangan</th>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th>Karyawan</th><th>Status</th><th>Masuk</th><th>Pulang</th>
                                <th class="angka">Terlambat<br>(menit)</th><th class="angka">Lembur<br>(menit)</th>
                                <th class="tengah">Lembur<br>disetujui</th><th>Keterangan</th>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                    <?php foreach ($karyawan as $k):
                        $a = $absensiTanggal[(int) $k['id']] ?? null;
                        $id = (int) $k['id'];
                        $statusK = (string) ($a['status'] ?? 'KOSONG');
                        $hadir = $statusK === 'HADIR'; ?>
                        <?php if ($minggu):
                            /* ---------- HARI MINGGU: status Hadir/Libur + isian jumlah jam kerja ---------- */
                            $menitMingguAda = (int) ($a['menit_minggu'] ?? 0);
                            $jamMingguAda = $menitMingguAda > 0 ? rtrim(rtrim(number_format($menitMingguAda / 60, 2, ',', ''), '0'), ',') : ''; ?>
                            <tr class="baris-absen baris-minggu">
                                <td>
                                    <strong><?= h($k['nama']) ?></strong><br>
                                    <span class="muted"><?= h($k['nip']) ?> · <?= h($k['jabatan']) ?></span>
                                    <br><span class="lencana lencana-ungu">Hari Minggu</span>
                                </td>
                                <td>
                                    <select class="sel-status-minggu" name="status[<?= $id ?>]">
                                        <option value="KOSONG">— tidak dicatat —</option>
                                        <?php foreach (status_absensi_minggu() as $v => $l): ?>
                                            <option value="<?= h($v) ?>" <?= $statusK === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="kanan">
                                    <div class="jam-kerja-minggu-bungkus">
                                        <input class="jam-kerja-minggu" type="text" inputmode="decimal"
                                               name="jam_minggu[<?= $id ?>]" value="<?= h($jamMingguAda) ?>"
                                               style="max-width:92px;text-align:right"
                                               title="Jumlah jam kerja hari Minggu (mis. 8 atau 7,5)"
                                               <?= $hadir ? '' : 'disabled' ?>> <span class="muted">jam</span>
                                    </div>
                                </td>
                                <td class="angka upah-minggu">
                                    <?php if ($hadir && $menitMingguAda > 0): ?>
                                        <strong><?= h(rp(payroll_bulat(($menitMingguAda / 60) * conf_num('minggu_tarif_jam', 0)))) ?></strong>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><input type="text" name="ket[<?= $id ?>]" value="<?= h((string) ($a['keterangan'] ?? '')) ?>" placeholder="opsional"></td>
                            </tr>
                        <?php else: ?>
                        <tr class="baris-absen"
                            data-masuk-standar="<?= h($jamStandarMasuk) ?>"
                            data-pulang-standar="<?= h($jamStandarPulang) ?>">
                            <td>
                                <strong><?= h($k['nama']) ?></strong><br>
                                <span class="muted"><?= h($k['nip']) ?> · <?= h($k['jabatan']) ?></span>
                            </td>
                            <td>
                                <select class="sel-status" name="status[<?= $id ?>]">
                                    <option value="KOSONG">— tidak dicatat —</option>
                                    <?php foreach (status_absensi() as $v => $l): ?>
                                        <option value="<?= h($v) ?>" <?= $statusK === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input class="jam-masuk" type="time" name="masuk[<?= $id ?>]"
                                       value="<?= h((string) ($a['jam_masuk'] ?? '')) ?>" style="min-width:110px"
                                       title="Jam masuk (aktif bila status Hadir)" <?= $hadir ? '' : 'disabled' ?>>
                            </td>
                            <td>
                                <input class="jam-pulang" type="time" name="pulang[<?= $id ?>]"
                                       value="<?= h((string) ($a['jam_pulang'] ?? '')) ?>" style="min-width:110px"
                                       title="Jam pulang (aktif bila status Hadir)" <?= $hadir ? '' : 'disabled' ?>>
                            </td>
                            <td>
                                <input class="menit-terlambat" type="number" min="0" step="1" name="terlambat[<?= $id ?>]"
                                       value="<?= (int) ($a['menit_terlambat'] ?? 0) ?>" style="min-width:80px"
                                       title="Dihitung otomatis dari jam masuk; boleh dikoreksi manual"
                                       <?= $hadir ? '' : 'disabled' ?>>
                                <input type="hidden" class="auto-terlambat" name="auto_terlambat[<?= $id ?>]" value="1">
                            </td>
                            <td>
                                <input class="menit-lembur" type="number" min="0" step="1" name="lembur[<?= $id ?>]"
                                       value="<?= (int) ($a['menit_lembur'] ?? 0) ?>" style="min-width:80px"
                                       title="Dihitung otomatis dari jam pulang bila lembur disetujui"
                                       <?= $hadir ? '' : 'disabled' ?>>
                                <input type="hidden" class="auto-lembur" name="auto_lembur[<?= $id ?>]" value="1">
                            </td>
                            <td class="tengah">
                                <input class="chk-setuju" type="checkbox" name="setuju[<?= $id ?>]" value="1"
                                       <?= (int) ($a['lembur_disetujui'] ?? 0) === 1 ? 'checked' : '' ?>
                                       title="Centang agar menit lembur dihitung & dibayar"
                                       style="width:17px;height:17px;accent-color:var(--biru-600)" <?= $hadir ? '' : 'disabled' ?>>
                            </td>
                            <td><input type="text" name="ket[<?= $id ?>]" value="<?= h((string) ($a['keterangan'] ?? '')) ?>" placeholder="opsional"></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-aksi">
                <button class="btn btn-primer" type="submit">Simpan Absensi Hari Ini</button>
            </div>
        </form>

        <form method="post" class="btn-baris" style="margin-top:16px"
              data-konfirmasi="Semua karyawan aktif akan ditandai Hadir dengan jam standar. Catatan tanggal ini akan ditimpa. Lanjutkan?">
            <input type="hidden" name="aksi" value="isi_cepat">
            <input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
            <input type="time" name="jam_masuk_cepat" value="<?= h($jamStandarMasuk) ?>">
            <input type="time" name="jam_pulang_cepat" value="<?= h($jamStandarPulang) ?>">
            <button class="btn" type="submit">Isi Cepat: Semua Hadir</button>
        </form>
    </div>
</div>

<div class="panel" data-panel="daftar">
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('calendar', 17) ?> Catatan Absensi <?= h(bulan_indo($periode)) ?></h2>
                <p class="muted">Semua catatan absensi pada periode ini.</p>
            </div>
            <form method="post" data-konfirmasi="Hapus SELURUH catatan absensi periode <?= h(bulan_indo($periode)) ?>? Tindakan ini tercatat di jejak audit.">
                <input type="hidden" name="aksi" value="hapus_bulan">
                <button class="btn btn-merah btn-kecil" type="submit">Hapus Semua Periode Ini</button>
            </form>
        </div>
        <?php $rows = absensi_rentang($periode); ?>
        <?php if ($rows): ?>
            <div class="tabel-bungkus">
                <table class="tabel">
                    <thead>
                        <tr><th>Tanggal</th><th>Karyawan</th><th>Status</th><th>Masuk</th><th>Pulang</th>
                            <th class="angka">Terlambat</th><th class="angka">Lembur</th><th>Keterangan</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $a): ?>
                        <tr>
                            <td class="nowrap"><?= h(tanggal_indo((string) $a['tanggal'], true)) ?></td>
                            <td><?= h($a['nama']) ?><br><span class="muted"><?= h($a['nip']) ?></span></td>
                            <td>
                                <?php $kelas = ['HADIR' => 'lencana-hijau', 'IZIN' => 'lencana-biru', 'SAKIT' => 'lencana-kuning', 'CUTI' => 'lencana-ungu', 'ALPA' => 'lencana-merah', 'LIBUR' => 'lencana-abu'][$a['status']] ?? 'lencana-abu'; ?>
                                <span class="lencana <?= $kelas ?>"><?= h(status_absensi()[$a['status']] ?? $a['status']) ?></span>
                            </td>
                            <td><?= h((string) $a['jam_masuk'] ?: '-') ?></td>
                            <td><?= h((string) $a['jam_pulang'] ?: '-') ?></td>
                            <td class="angka"><?= (int) $a['menit_terlambat'] ? h(durasi_indo((int) $a['menit_terlambat'])) : '-' ?></td>
                            <td class="angka"><?= (int) $a['menit_lembur'] ? h(durasi_indo((int) $a['menit_lembur'])) . ((int) $a['lembur_disetujui'] === 1 ? ' ✓' : '') : '-' ?></td>
                            <td class="muted"><?= h((string) $a['keterangan']) ?></td>
                            <td class="kanan">
                                <form method="post" data-konfirmasi="Hapus catatan absensi ini?">
                                    <input type="hidden" name="aksi" value="hapus">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button class="btn btn-kecil btn-merah" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="kosong"><strong>Belum ada catatan</strong>Gunakan tab Input Harian, atau tombol “Isi Cepat: Semua Hadir”.</div>
        <?php endif; ?>
    </div>
</div>

<div class="panel" data-panel="rekap">
    <div class="kartu">
        <div class="kartu-kepala">
            <div>
                <h2><?= icon('chart', 17) ?> Rekapitulasi Otomatis <?= h(bulan_indo($periode)) ?></h2>
                <p class="muted">Dipakai langsung oleh mesin penggajian: hari hadir, total keterlambatan, total jam lembur.</p>
            </div>
            <a class="btn btn-kecil" href="laporan.php?periode=<?= h($periode) ?>&jenis=absensi">Lihat di Laporan</a>
        </div>
        <?php $kal = hari_kerja_bulan($periode); ?>
        <div class="tabel-bungkus">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th class="angka">Hari Kerja</th><th class="angka">Hadir</th><th class="angka">Hadir Minggu</th><th class="angka">Izin</th>
                        <th class="angka">Sakit</th><th class="angka">Cuti</th><th class="angka">Alpa</th>
                        <th class="angka">Kali Terlambat</th><th class="angka">Total Terlambat</th>
                        <th class="angka">Jam Lembur</th><th class="angka">Lembur (jam)</th>
                    </tr>
                </thead>
                <tbody>
                <?php $tLembur = 0; $tTerlambat = 0; foreach ($karyawan as $k): $r = absensi_rekap((int) $k['id'], $periode); $tLembur += $r['menit_lembur']; $tTerlambat += $r['menit_terlambat']; ?>
                    <tr>
                        <td><strong><?= h($k['nama']) ?></strong><br><span class="muted"><?= h($k['nip']) ?></span></td>
                        <td class="angka"><?= (int) $kal['jumlah'] ?></td>
                        <td class="angka"><?= (int) $r['hadir'] ?></td>
                        <td class="angka"><?= (int) $r['hadir_minggu'] ?></td>
                        <td class="angka"><?= (int) $r['izin'] ?></td>
                        <td class="angka"><?= (int) $r['sakit'] ?></td>
                        <td class="angka"><?= (int) $r['cuti'] ?></td>
                        <td class="angka"><?= (int) $r['alpa'] ?></td>
                        <td class="angka"><?= (int) $r['kali_terlambat'] ?></td>
                        <td class="angka"><?= h(durasi_indo((int) $r['menit_terlambat'])) ?></td>
                        <td class="angka"><?= (int) $r['menit_lembur'] ?> menit</td>
                        <td class="angka"><?= h(angka($r['menit_lembur'] / 60, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="baris-total">
                        <td>Total</td><td class="angka"></td><td class="angka"></td><td class="angka"></td><td class="angka"></td><td class="angka"></td>
                        <td class="angka"></td><td class="angka"></td><td class="angka"></td>
                        <td class="angka"><?= h(durasi_indo($tTerlambat)) ?></td>
                        <td class="angka"><?= (int) $tLembur ?> menit</td>
                        <td class="angka"><?= h(angka($tLembur / 60, 2)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php page_foot(); ?>
