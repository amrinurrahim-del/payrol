<?php
declare(strict_types=1);

/*
 * ============================================================================
 *  PAYROLL & HR MANAGEMENT SYSTEM
 *  Koneksi database (SQLite via PDO) + pembuatan skema otomatis.
 * ============================================================================
 *
 *  Semua nilai komputasi (toleransi keterlambatan, denda, faktor lembur,
 *  persentase BPJS, PTKP, lapisan tarif PPh 21) DISIMPAN DI DATABASE pada tabel
 *  key-value:
 *      - system_settings        : identitas perusahaan / tanda tangan / format slip
 *      - payroll_configurations : seluruh variabel hukum & ketenagakerjaan
 *  Ditambah tabel relasional murni untuk data master & transaksi:
 *      - divisi, karyawan, komponen_gaji, absensi, hari_libur,
 *        ptkp, pph21_layers, slip_gaji, slip_item, user, sesi, log_admin
 *
 *  Catatan lingkungan: MongoDB TIDAK tersedia di server ini, sedangkan model
 *  datanya (payroll) memang sangat relasional (satu karyawan → banyak absensi →
 *  satu slip → banyak item). Karena itu dipakai SQLite/PDO dengan foreign key
 *  hidup, dan variabel yang harus fleksibel ditaruh sebagai key-value/JSON.
 */

date_default_timezone_set('Asia/Makassar');

/* ------------------------------------------------------------------ */
/* Metadata seluruh variabel dinamis (satu sumber kebenaran)           */
/* ------------------------------------------------------------------ */

/**
 * Definisi kolom identitas perusahaan & tampilan slip (tabel system_settings).
 * 'tipe' dipakai halaman Pengaturan untuk memilih jenis input:
 *   teks | alamat | email | telepon | angka | uang | persen | menit | jam | bool | json | pilihan
 */
function daftar_system_setting(): array
{
    return [
        'perusahaan_nama'        => ['tipe' => 'teks',   'kelompok' => 'perusahaan', 'label' => 'Nama Perusahaan',            'keterangan' => 'Tampil di header aplikasi & slip gaji.'],
        'perusahaan_alamat'      => ['tipe' => 'alamat', 'kelompok' => 'perusahaan', 'label' => 'Alamat Lengkap',             'keterangan' => 'Alamat kantor pusat, tampil di slip gaji.'],
        'perusahaan_telepon'     => ['tipe' => 'telepon','kelompok' => 'perusahaan', 'label' => 'Nomor Telepon',              'keterangan' => 'Contoh: (0411) 123456'],
        'perusahaan_email'       => ['tipe' => 'email',  'kelompok' => 'perusahaan', 'label' => 'Email',                      'keterangan' => 'Contoh: hrd@perusahaan.co.id'],
        'perusahaan_website'     => ['tipe' => 'teks',   'kelompok' => 'perusahaan', 'label' => 'Website',                    'keterangan' => 'Contoh: www.perusahaan.co.id'],
        'perusahaan_npwp'        => ['tipe' => 'teks',   'kelompok' => 'perusahaan', 'label' => 'NPWP Perusahaan',            'keterangan' => 'Ditampilkan di slip gaji (opsional).'],
        'perusahaan_kota'        => ['tipe' => 'teks',   'kelompok' => 'perusahaan', 'label' => 'Kota (untuk tanggal tanda tangan)', 'keterangan' => 'Contoh: Makassar'],
        'logo_url'               => ['tipe' => 'teks',   'kelompok' => 'perusahaan', 'label' => 'Logo Perusahaan (URL)',      'keterangan' => 'Diunggah lewat kartu Unggah Logo di tab Perusahaan.'],
        'logo_nama'              => ['tipe' => 'teks',   'kelompok' => 'perusahaan', 'label' => 'Nama Berkas Logo',           'keterangan' => ''],
        'ttd_nama'               => ['tipe' => 'teks',   'kelompok' => 'ttd',        'label' => 'Nama Penanda Tangan',        'keterangan' => 'Direktur / HR Manager.'],
        'ttd_jabatan'            => ['tipe' => 'teks',   'kelompok' => 'ttd',        'label' => 'Jabatan Penanda Tangan',     'keterangan' => 'Contoh: HR Manager'],
        'ttd_url'                => ['tipe' => 'teks',   'kelompok' => 'ttd',        'label' => 'Gambar Tanda Tangan (URL)',  'keterangan' => 'Opsional — unggah gambar tanda tangan (PNG transparan disarankan).'],
        'ttd_tampil_gambar'      => ['tipe' => 'bool',   'kelompok' => 'ttd',        'label' => 'Cetak gambar tanda tangan',  'keterangan' => 'Bila dimatikan, hanya nama & jabatan yang dicetak.'],
        'slip_judul'             => ['tipe' => 'teks',   'kelompok' => 'slip',       'label' => 'Judul Slip',                  'keterangan' => 'Contoh: SLIP GAJI KARYAWAN'],
        'slip_catatan'           => ['tipe' => 'alamat', 'kelompok' => 'slip',       'label' => 'Catatan Kaki Slip',           'keterangan' => 'Contoh: Slip ini bersifat rahasia.'],
        'slip_tampil_logo'       => ['tipe' => 'bool',   'kelompok' => 'slip',       'label' => 'Tampilkan logo di slip',      'keterangan' => ''],
        'slip_tampil_rekap'      => ['tipe' => 'bool',   'kelompok' => 'slip',       'label' => 'Tampilkan rekap absensi & lembur', 'keterangan' => 'Rekap hari hadir, keterlambatan, dan jam lembur.'],
        'slip_tampil_terbilang'  => ['tipe' => 'bool',   'kelompok' => 'slip',       'label' => 'Tampilkan take home pay terbilang', 'keterangan' => ''],
        'slip_kode_prefix'       => ['tipe' => 'teks',   'kelompok' => 'slip',       'label' => 'Awalan Nomor Slip',           'keterangan' => 'Contoh: SLIP → SLIP/2026-01/0001'],
        'app_nama'               => ['tipe' => 'teks',   'kelompok' => 'umum',       'label' => 'Nama Aplikasi',               'keterangan' => 'Tampil di header aplikasi.'],
    ];
}

/**
 * Segmen jam kerja harian bawaan (aturan perusahaan ini):
 *   08:00–11:00  kerja (3 jam)
 *   11:00–13:00  istirahat (tidak dibayar)
 *   13:00–20:00  kerja (7 jam)
 * Total 10 jam kerja per hari.
 */
const JAM_KERJA_SEGMEN_BAWAAN = '[{"nama":"Sesi pagi","mulai":"08:00","selesai":"11:00","istirahat":false},{"nama":"Istirahat","mulai":"11:00","selesai":"13:00","istirahat":true},{"nama":"Sesi sore","mulai":"13:00","selesai":"20:00","istirahat":false}]';

/**
 * Definisi seluruh variabel ketenagakerjaan (tabel payroll_configurations).
 * 'tipe' dipakai untuk input & validasi; 'pilihan' untuk daftar nilai tetap.
 */
function daftar_payroll_config(): array
{
    return [
        /* ---------- Umum / dasar perhitungan ---------- */
        'zona_waktu'              => ['tipe' => 'zona',   'kelompok' => 'umum',   'label' => 'Zona Waktu',                 'nilai' => 'Asia/Makassar', 'keterangan' => 'Dipakai untuk jam absensi & tanggal slip.'],
        'mata_uang_simbol'        => ['tipe' => 'teks',   'kelompok' => 'umum',   'label' => 'Simbol Mata Uang',           'nilai' => 'Rp',            'keterangan' => 'Dicetak pada slip (mis. Rp, $, €).'],
        'hari_kerja_mingguan'     => ['tipe' => 'teks',   'kelompok' => 'umum',   'label' => 'Hari Kerja Mingguan',        'nilai' => '1,2,3,4,5,6',   'keterangan' => '1=Senin … 7=Minggu. Hanya hari ini yang dihitung sebagai hari kerja.'],
        'basis_hari_kerja'        => ['tipe' => 'pilihan','kelompok' => 'umum',   'label' => 'Basis Jumlah Hari Kerja (pembagi upah harian)', 'nilai' => 'otomatis', 'keterangan' => 'Pembagi upah harian. Perusahaan ini: OTOMATIS dihitung dari kalender, hanya Senin–Sabtu — hari Minggu tidak pernah masuk pembagi.', 'pilihan' => ['otomatis' => 'Otomatis: hitung hari Senin–Sabtu pada periode itu (dipakai perusahaan ini)', 'tetap' => 'Angka tetap di bawah (mis. 25)']],
        'hari_kerja_kurangi_libur' => ['tipe' => 'bool','kelompok' => 'umum',   'label' => 'Hari libur nasional mengurangi jumlah hari kerja', 'nilai' => '1', 'keterangan' => 'Aktif (bawaan): tanggal yang terdaftar di tab Hari Kerja & Libur dikurangi dari pembagi. Matikan bila ingin menghitung murni Senin–Sabtu saja.'],
        'hari_kerja_per_bulan'    => ['tipe' => 'angka',  'kelompok' => 'umum',   'label' => 'Hari Kerja per Bulan (mode angka tetap)', 'nilai' => '25', 'keterangan' => 'Hanya dipakai bila basis = angka tetap. Pada mode otomatis, angka ini diabaikan.'],
        'jam_kerja_per_hari'      => ['tipe' => 'jam',    'kelompok' => 'jam_kerja','label' => 'Jam Kerja per Hari (mode sederhana)', 'nilai' => '10',  'keterangan' => 'Hanya dipakai bila bentuk jam kerja = "satu blok". Pada mode segmen, angka ini dihitung otomatis dari segmen di bawah.'],
        'jam_kerja_mode'          => ['tipe' => 'pilihan','kelompok' => 'jam_kerja','label' => 'Bentuk Jam Kerja Harian',    'nilai' => 'segmen',        'keterangan' => 'Segmen = jam kerja dibagi beberapa blok dengan jam istirahat (mis. 08:00–11:00 dan 13:00–20:00).', 'pilihan' => ['segmen' => 'Beberapa segmen (ada jam istirahat)', 'sederhana' => 'Satu blok kerja (jam masuk–jam pulang langsung)']],
        'jam_kerja_segmen'        => ['tipe' => 'json',   'kelompok' => 'jam_kerja','label' => 'Segmen Jam Kerja Harian',    'nilai' => JAM_KERJA_SEGMEN_BAWAAN, 'keterangan' => 'Format JSON [{"nama","mulai","selesai","istirahat"}]. Segmen dengan "istirahat": true TIDAK dihitung sebagai jam kerja dan tidak dibayar.'],
        'absensi_jam_masuk_standar' => ['tipe' => 'waktu','kelompok' => 'jam_kerja','label' => 'Jam Masuk Standar',         'nilai' => '08:00',         'keterangan' => 'Dipakai untuk menghitung menit keterlambatan otomatis di halaman Absensi. Kosongkan/ubah bila jam masuk Anda berbeda.'],
        'absensi_jam_pulang_standar' => ['tipe' => 'waktu','kelompok' => 'jam_kerja','label' => 'Jam Pulang Standar → mulai lembur','nilai' => '20:00',  'keterangan' => 'Jam normal terakhir; kelebihan jam setelah ini dihitung sebagai lembur hari kerja.'],
        'basis_upah_per_jam'      => ['tipe' => 'pilihan','kelompok' => 'umum',   'label' => 'Dasar Upah (harian, lembur, potongan)', 'nilai' => 'gaji_pokok', 'keterangan' => 'Angka yang dibagi untuk menghitung upah harian & upah lembur per jam. Perusahaan ini memakai Gaji Pokok saja.', 'pilihan' => ['gaji_pokok' => 'Gaji Pokok saja', 'gaji_pokok_tunjangan' => 'Gaji Pokok + Tunjangan Tetap']],

        /* ---------- Upah harian & kehadiran ---------- */
        'upah_mode'               => ['tipe' => 'pilihan','kelompok' => 'upah',  'label' => 'Cara Membayar Upah Bulanan', 'nilai' => 'proporsional_hadir', 'keterangan' => 'Menentukan bentuk pendapatan upah pada slip.', 'pilihan' => ['proporsional_hadir' => 'Upah harian × jumlah hari hadir (Gaji Pokok ÷ hari kerja) — dipakai perusahaan ini', 'penuh' => 'Gaji pokok dibayar penuh (potongan absensi dihitung terpisah)']],
        'tunjangan_ikut_kehadiran' => ['tipe' => 'bool','kelompok' => 'upah',  'label' => 'Tunjangan tetap & tidak tetap ikut dihitung proporsional kehadiran', 'nilai' => '0', 'keterangan' => 'Bila mati (bawaan), tunjangan dibayar penuh dan tidak dipengaruhi jumlah kehadiran.'],
        'pembulatan_gaji'         => ['tipe' => 'pilihan','kelompok' => 'umum',   'label' => 'Pembulatan Nilai Gaji',      'nilai' => 'bulat',         'keterangan' => 'Dipakai untuk seluruh angka pendapatan & potongan.', 'pilihan' => ['bulat' => 'Bulatkan ke rupiah terdekat', 'ribuan' => 'Bulatkan ke ribuan terdekat', 'none' => 'Tanpa pembulatan']],

        /* ---------- Absensi & keterlambatan ---------- */
        'absensi_aktif'           => ['tipe' => 'bool',   'kelompok' => 'absensi','label' => 'Aktifkan potongan absensi',  'nilai' => '1',             'keterangan' => 'Bila dimatikan, keterlambatan & alpa tidak dipotong.'],
        'absensi_toleransi_menit' => ['tipe' => 'menit',  'kelompok' => 'absensi','label' => 'Toleransi Keterlambatan',    'nilai' => '15',            'keterangan' => 'Keterlambatan sampai sekian menit tidak dipotong.'],
        'absensi_denda_mode'      => ['tipe' => 'pilihan','kelompok' => 'absensi','label' => 'Cara Menghitung Denda',      'nilai' => 'bertingkat',    'keterangan' => 'Per menit, per kejadian, atau bertingkat per rentang menit.', 'pilihan' => ['tidak_ada' => 'Tanpa denda', 'per_menit' => 'Denda per menit', 'per_kejadian' => 'Denda per kejadian (flat)', 'bertingkat' => 'Bertingkat per rentang menit']],
        'absensi_denda_per_menit' => ['tipe' => 'uang',   'kelompok' => 'absensi','label' => 'Denda per Menit',            'nilai' => '1000',          'keterangan' => 'Dikalikan menit terlambat setelah toleransi.'],
        'absensi_denda_per_kejadian' => ['tipe' => 'uang','kelompok' => 'absensi','label' => 'Denda per Kejadian',         'nilai' => '25000',         'keterangan' => 'Dipakai bila cara = denda per kejadian.'],
        'absensi_denda_bertingkat' => ['tipe' => 'json',  'kelompok' => 'absensi','label' => 'Rentang Denda Bertingkat',   'nilai' => '[{"menit":30,"denda":10000},{"menit":60,"denda":25000},{"menit":90,"denda":50000},{"menit":0,"denda":75000}]', 'keterangan' => 'Format JSON [{menit,denda}]. "menit" = batas atas terlambat (menit ≤ batas). Isi 0 pada "menit" terakhir berarti "lebih dari batas sebelumnya".'],
        'absensi_maks_denda_bulan' => ['tipe' => 'uang',  'kelompok' => 'absensi','label' => 'Batas Maksimum Denda / Bulan','nilai' => '0',            'keterangan' => '0 = tanpa batas.'],
        'absensi_alpa_mode'       => ['tipe' => 'pilihan','kelompok' => 'absensi','label' => 'Potongan Alpa (tanpa keterangan)', 'nilai' => 'upah_harian', 'keterangan' => 'Alpa = tidak hadir tanpa izin/sakit/cuti.', 'pilihan' => ['tidak_ada' => 'Tanpa potongan', 'upah_harian' => 'Potong sekian kali upah harian', 'nominal' => 'Potong nominal per hari']],
        'absensi_alpa_faktor'     => ['tipe' => 'angka',  'kelompok' => 'absensi','label' => 'Pengali Upah Harian (Alpa)','nilai' => '1',             'keterangan' => 'Banyak perusahaan memakai 1 sampai 3 (sanksi).'],
        'absensi_alpa_nominal'    => ['tipe' => 'uang',   'kelompok' => 'absensi','label' => 'Nominal Alpa per Hari',      'nilai' => '0',             'keterangan' => 'Dipakai bila cara = nominal.'],
        'absensi_izin_mode'       => ['tipe' => 'pilihan','kelompok' => 'absensi','label' => 'Potongan Izin',              'nilai' => 'tidak_ada',     'keterangan' => 'Izin dengan keterangan (mis. izin menikah/cuti).', 'pilihan' => ['tidak_ada' => 'Tanpa potongan', 'upah_harian' => 'Potong upah harian', 'nominal' => 'Potong nominal per hari']],
        'absensi_izin_nominal'    => ['tipe' => 'uang',   'kelompok' => 'absensi','label' => 'Nominal Izin per Hari',      'nilai' => '0',             'keterangan' => ''],
        'absensi_sakit_mode'      => ['tipe' => 'pilihan','kelompok' => 'absensi','label' => 'Potongan Sakit',             'nilai' => 'tidak_ada',     'keterangan' => 'Biasanya tetap dibayar bila ada surat dokter.', 'pilihan' => ['tidak_ada' => 'Tanpa potongan', 'upah_harian' => 'Potong upah harian', 'nominal' => 'Potong nominal per hari']],
        'absensi_sakit_nominal'   => ['tipe' => 'uang',   'kelompok' => 'absensi','label' => 'Nominal Sakit per Hari',     'nilai' => '0',             'keterangan' => ''],
        'alpa_potong_saat_proporsional' => ['tipe' => 'bool','kelompok' => 'absensi','label' => 'Tetap potong alpa walau upah sudah proporsional', 'nilai' => '0', 'keterangan' => 'Bila “Cara Membayar Upah Bulanan” = proporsional kehadiran, hari alpa otomatis tidak dibayar. Biarkan mati agar tidak terhitung dua kali; nyalakan hanya bila memang ingin menambah sanksi.'],
        'absensi_alpa_tanpa_catatan' => ['tipe' => 'bool','kelompok' => 'absensi','label' => 'Hari kerja tanpa catatan dihitung Alpa', 'nilai' => '0',  'keterangan' => 'Bila menyala, hari kerja yang belum diisi absensinya (sampai hari ini) otomatis dihitung sebagai alpa pada perhitungan gaji. Biarkan mati agar gaji tidak terpotong sebelum absensi diisi.'],

        /* ---------- Lembur ---------- */
        'lembur_aktif'            => ['tipe' => 'bool',   'kelompok' => 'lembur','label' => 'Aktifkan perhitungan lembur', 'nilai' => '1',             'keterangan' => ''],
        'lembur_pembagi'          => ['tipe' => 'angka',  'kelompok' => 'lembur','label' => 'Pembagi Upah Lembur per Jam', 'nilai' => '173',           'keterangan' => 'Rumus perusahaan ini: Upah Lembur per Jam = Gaji Pokok ÷ 173 (rumus resmi ketenagakerjaan: 1/173 × upah sebulan).'],
        'lembur_pakai_faktor'     => ['tipe' => 'bool',   'kelompok' => 'lembur','label' => 'Pakai faktor pengali (1,5× / 2×)', 'nilai' => '0',          'keterangan' => 'Mati (bawaan) = upah lembur = jam × (Gaji Pokok ÷ 173), sesuai kebijakan perusahaan ini. Nyalakan bila ingin memakai aturan 1,5× jam pertama dan 2× jam berikutnya.'],
        'lembur_faktor_pertama'   => ['tipe' => 'angka',  'kelompok' => 'lembur','label' => 'Faktor Jam Lembur Pertama',   'nilai' => '1.5',           'keterangan' => 'Hanya dipakai bila “Pakai faktor pengali” menyala. Regulasi: 1,5× pada jam pertama.'],
        'lembur_faktor_berikutnya'=> ['tipe' => 'angka',  'kelompok' => 'lembur','label' => 'Faktor Jam Lembur Berikutnya','nilai' => '2',             'keterangan' => 'Regulasi Indonesia: 2× upah per jam pada jam berikutnya.'],
        'lembur_faktor_hari_libur'=> ['tipe' => 'angka',  'kelompok' => 'lembur','label' => 'Faktor Lembur Hari Libur Nasional', 'nilai' => '2', 'keterangan' => 'Dipakai bila lembur jatuh pada hari libur nasional/cuti bersama (BUKAN hari Minggu — Minggu punya aturan sendiri di bawah).'],
        'lembur_pembulatan'       => ['tipe' => 'pilihan','kelompok' => 'lembur','label' => 'Pembulatan Waktu Lembur',     'nilai' => 'per_menit',     'keterangan' => 'Menentukan cara membaca menit lembur dari absensi.', 'pilihan' => ['per_menit' => 'Perhitungan per menit (paling tepat)', 'per_15menit' => 'Dibulatkan ke 15 menit', 'per_30menit' => 'Dibulatkan ke 30 menit', 'per_jam' => 'Dibulatkan ke jam penuh']],
        'lembur_maks_jam_bulan'   => ['tipe' => 'jam',    'kelompok' => 'lembur','label' => 'Maksimum Jam Lembur / Bulan', 'nilai' => '0',             'keterangan' => '0 = tanpa batas.'],
        'lembur_wajib_disetujui'  => ['tipe' => 'bool',   'kelompok' => 'lembur','label' => 'Lembur wajib disetujui',      'nilai' => '1',             'keterangan' => 'Bila aktif, hanya lembur yang dicentang "disetujui" yang dibayar.'],

        /* ---------- Lembur hari MINGGU (aturan khusus: 2 upah) ---------- */
        'minggu_aktif'            => ['tipe' => 'bool',   'kelompok' => 'minggu', 'label' => 'Aktifkan aturan kerja hari Minggu', 'nilai' => '1',      'keterangan' => 'Bila aktif, karyawan yang bekerja pada hari Minggu dibayar menurut aturan khusus hari Minggu di bawah.'],
        'minggu_mode'             => ['tipe' => 'pilihan','kelompok' => 'minggu', 'label' => 'Cara Menghitung Upah Hari Minggu', 'nilai' => 'tarif_jam', 'keterangan' => 'Perusahaan ini memakai tarif per jam khusus Minggu.', 'pilihan' => ['tarif_jam' => 'Jam kerja Minggu × tarif per jam khusus Minggu (dipakai perusahaan ini)', 'dua_upah' => 'Upah harian + upah lembur minggu (aturan lama)']],
        'minggu_tarif_jam'        => ['tipe' => 'uang',   'kelompok' => 'minggu', 'label' => 'Tarif Upah Minggu per Jam',   'nilai' => '20000',         'keterangan' => 'Berbeda dari upah hari biasa. Contoh: Rp20.000 → kerja 10 jam = Rp200.000.'],
        'minggu_upah_harian_faktor' => ['tipe' => 'angka','kelompok' => 'minggu', 'label' => 'Upah Harian Minggu (× upah harian)', 'nilai' => '1',    'keterangan' => 'Bagian pertama dari "2 upah". Nilai 1 = dibayar 1× upah harian untuk setiap hari Minggu yang dikerjakan.'],
        'minggu_lembur_mode'      => ['tipe' => 'pilihan','kelompok' => 'minggu', 'label' => 'Cara Menghitung Upah Lembur Minggu', 'nilai' => 'faktor_upah_harian', 'keterangan' => 'Bagian kedua dari "2 upah".', 'pilihan' => ['faktor_upah_harian' => 'Sekian kali upah harian (per hari)', 'nominal_per_hari' => 'Nominal rupiah per hari', 'per_jam_faktor' => 'Sekian kali upah per jam (× jam kerja aktual)']],
        'minggu_lembur_faktor_harian' => ['tipe' => 'angka','kelompok' => 'minggu','label' => 'Faktor Upah Harian (lembur Minggu)', 'nilai' => '1', 'keterangan' => 'Dipakai bila cara = "sekian kali upah harian". Nilai 1 → total 2 upah harian per hari Minggu (upah harian + lembur minggu).'],
        'minggu_lembur_nominal'   => ['tipe' => 'uang',   'kelompok' => 'minggu', 'label' => 'Nominal Lembur Minggu per Hari', 'nilai' => '0',         'keterangan' => 'Dipakai bila cara = "nominal rupiah per hari".'],
        'minggu_lembur_faktor_jam' => ['tipe' => 'angka', 'kelompok' => 'minggu', 'label' => 'Faktor Upah per Jam (lembur Minggu)', 'nilai' => '2',    'keterangan' => 'Dipakai bila cara = "sekian kali upah per jam"; dikalikan jam kerja aktual hari Minggu dari absensi.'],
        'minggu_minimal_menit'    => ['tipe' => 'menit',  'kelompok' => 'minggu', 'label' => 'Minimal Menit Kerja Hari Minggu', 'nilai' => '240',      'keterangan' => 'Catatan absensi hari Minggu dengan jam kerja kurang dari ini TIDAK dihitung sebagai kerja hari Minggu (mencegah salah input). 0 = semua dihitung.'],

        /* ---------- Potongan (semua opsional, bisa dimatikan) ---------- */
        'potongan_denda_aktif'    => ['tipe' => 'bool',   'kelompok' => 'potongan','label' => 'Denda Keterlambatan',       'nilai' => '0',             'keterangan' => 'Pemilik dapat mematikannya; pengaturan denda lengkap ada di tab Absensi.'],
        'potongan_alpa_aktif'     => ['tipe' => 'bool',   'kelompok' => 'potongan','label' => 'Potongan Alpa',             'nilai' => '0',             'keterangan' => 'Bila mati, hari tanpa keterangan tidak dipotong.'],
        'potongan_izin_aktif'     => ['tipe' => 'bool',   'kelompok' => 'potongan','label' => 'Potongan Izin',             'nilai' => '0',             'keterangan' => ''],
        'potongan_sakit_aktif'    => ['tipe' => 'bool',   'kelompok' => 'potongan','label' => 'Potongan Sakit',            'nilai' => '0',             'keterangan' => ''],
        'potongan_panjar_aktif'   => ['tipe' => 'bool',   'kelompok' => 'potongan','label' => 'Potongan Panjar (cicilan)', 'nilai' => '1',             'keterangan' => 'Potongan utama yang dipakai perusahaan ini: cicilan panjar yang diberikan ke karyawan.'],
        'potongan_lain_aktif'     => ['tipe' => 'bool',   'kelompok' => 'potongan','label' => 'Potongan Tambahan per Karyawan','nilai' => '1',          'keterangan' => 'Potongan yang ditambahkan manual pada data karyawan (kasbon, koperasi, dsb.).'],

        /* ---------- BPJS ---------- */
        'bpjs_dasar'              => ['tipe' => 'pilihan','kelompok' => 'bpjs',  'label' => 'Dasar Perhitungan BPJS',      'nilai' => 'gaji_pokok',    'keterangan' => 'Upah yang dijadikan dasar iuran.', 'pilihan' => ['gaji_pokok' => 'Gaji Pokok saja', 'gaji_pokok_tunjangan' => 'Gaji Pokok + Tunjangan Tetap']],
        'bpjs_maks_dasar'         => ['tipe' => 'uang',   'kelompok' => 'bpjs',  'label' => 'Batas Atas Dasar BPJS',       'nilai' => '0',             'keterangan' => '0 = tanpa batas. Sesuaikan dengan plafon upah terbaru.'],
        'bpjs_tk_karyawan_persen' => ['tipe' => 'persen', 'kelompok' => 'bpjs',  'label' => 'BPJS Ketenagakerjaan — Karyawan (%)', 'nilai' => '3',     'keterangan' => 'Umumnya JHT 2% + JP 1%.'],
        'bpjs_kes_karyawan_persen'=> ['tipe' => 'persen', 'kelompok' => 'bpjs',  'label' => 'BPJS Kesehatan — Karyawan (%)', 'nilai' => '1',           'keterangan' => ''],
        'bpjs_tk_perusahaan_persen' => ['tipe' => 'persen','kelompok' => 'bpjs', 'label' => 'BPJS Ketenagakerjaan — Perusahaan (%)', 'nilai' => '6.24', 'keterangan' => 'Ditanggung perusahaan, tampil sebagai tunjangan.'],
        'bpjs_kes_perusahaan_persen' => ['tipe' => 'persen','kelompok' => 'bpjs','label' => 'BPJS Kesehatan — Perusahaan (%)', 'nilai' => '4',      'keterangan' => ''],
        'bpjs_tampil_tunjangan_perusahaan' => ['tipe' => 'bool','kelompok' => 'bpjs','label' => 'Tampilkan iuran perusahaan sebagai pendapatan', 'nilai' => '1', 'keterangan' => 'Direkomendasikan menyala agar slip menunjukkan total kompensasi.'],
        'bpjs_aktif'              => ['tipe' => 'bool',   'kelompok' => 'bpjs',  'label' => 'Aktifkan potongan BPJS',      'nilai' => '0',             'keterangan' => 'Bila mati: tidak ada potongan BPJS dan tidak ada tunjangan BPJS perusahaan. Saklar cepat tersedia di tab Potongan.'],

        /* ---------- Pajak PPh 21 ---------- */
        'pajak_aktif'             => ['tipe' => 'bool',   'kelompok' => 'pajak', 'label' => 'Aktifkan perhitungan PPh 21',  'nilai' => '0',            'keterangan' => 'Bila dimatikan, tidak ada baris potongan pajak. Saklar cepat juga tersedia di tab Potongan.'],
        'pajak_metode_default'    => ['tipe' => 'pilihan','kelompok' => 'pajak', 'label' => 'Metode Pajak Bawaan',          'nilai' => 'progresif',    'keterangan' => 'Dipakai untuk karyawan baru; tiap karyawan tetap bisa memilih metodenya sendiri.', 'pilihan' => ['progresif' => 'Progresif berlapis (PTKP + tarif berlapis)', 'tunggal' => 'Persentase tunggal', 'tidak_ada' => 'Tanpa pajak']],
        'pajak_tunggal_persen'    => ['tipe' => 'persen', 'kelompok' => 'pajak', 'label' => 'Pajak — Persentase Tunggal (%)','nilai' => '5',           'keterangan' => 'Dipakai untuk karyawan dengan metode "persentase tunggal".'],
        'pajak_tunggal_dasar'     => ['tipe' => 'pilihan','kelompok' => 'pajak', 'label' => 'Dasar Persentase Tunggal',     'nilai' => 'bruto',        'keterangan' => 'Dasar pengenaan sebelum/sesudah potongan BPJS.', 'pilihan' => ['bruto' => 'Penghasilan bruto', 'bruto_setelah_bpjs' => 'Bruto dikurangi potongan BPJS karyawan']],
        'pajak_bulat_pkp'         => ['tipe' => 'uang',   'kelompok' => 'pajak', 'label' => 'Pembulatan PKP ke Bawah',      'nilai' => '1000',         'keterangan' => 'PKP dibulatkan ke bawah dalam kelipatan nilai ini (lazim Rp1.000).'],
        'pajak_biaya_jabatan_persen' => ['tipe' => 'persen','kelompok' => 'pajak','label' => 'Biaya Jabatan (% bruto)',     'nilai' => '5',            'keterangan' => 'Pengurang bruto setahun.'],
        'pajak_biaya_jabatan_maks_bulan' => ['tipe' => 'uang','kelompok' => 'pajak','label' => 'Batas Biaya Jabatan / Bulan', 'nilai' => '500000',    'keterangan' => 'Lazim Rp500.000 per bulan (Rp6.000.000 per tahun).'],
        'pajak_tanpa_npwp_tambahan_persen' => ['tipe' => 'persen','kelompok' => 'pajak','label' => 'Tambahan Pajak Tanpa NPWP (%)','nilai' => '20',   'keterangan' => 'Kenaikan tarif bila karyawan belum punya NPWP. Isi 0 bila tidak ingin diterapkan.'],
        'pajak_bulan_setahun'     => ['tipe' => 'angka',  'kelompok' => 'pajak', 'label' => 'Jumlah Bulan dalam Setahun',   'nilai' => '12',           'keterangan' => 'Dipakai untuk menyetahunkan penghasilan tetap.'],
        'pajak_bpjs_pengurang'    => ['tipe' => 'bool',   'kelompok' => 'pajak', 'label' => 'Iuran BPJS karyawan sebagai pengurang', 'nilai' => '1',   'keterangan' => 'Iuran JHT/JP/pensiun milik karyawan mengurangi penghasilan bruto.'],
        'pajak_bpjs_perusahaan_kena_pajak' => ['tipe' => 'bool','kelompok' => 'pajak','label' => 'Iuran BPJS perusahaan kena pajak', 'nilai' => '1', 'keterangan' => 'Bila menyala, tunjangan BPJS dari perusahaan ikut dihitung sebagai penghasilan bruto.'],
    ];
}

/** Daftar status absensi yang sah beserta labelnya. */
function status_absensi(): array
{
    return [
        'HADIR' => 'Hadir',
        'IZIN'  => 'Izin',
        'SAKIT' => 'Sakit',
        'CUTI'  => 'Cuti',
        'ALPA'  => 'Alpa (tanpa keterangan)',
        'LIBUR' => 'Libur',
    ];
}

/** Status absensi yang boleh dipilih pada HARI MINGGU: hanya Hadir dan Libur. */
function status_absensi_minggu(): array
{
    return [
        'HADIR' => 'Hadir (kerja hari Minggu)',
        'LIBUR' => 'Libur',
    ];
}

/** Daftar status kepegawaian. */
function status_karyawan(): array
{
    return [
        'tetap'   => 'Karyawan Tetap',
        'kontrak' => 'Kontrak',
        'harian'  => 'Harian / Lepas',
        'magang'  => 'Magang / Percobaan',
    ];
}

/** Metode pajak per karyawan. */
function metode_pajak(): array
{
    return [
        'progresif' => 'Progresif (PTKP + tarif berlapis)',
        'tunggal'   => 'Persentase tunggal',
        'tidak_ada' => 'Tanpa PPh 21',
    ];
}

/* ------------------------------------------------------------------ */
/* Koneksi                                                             */
/* ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!class_exists('PDO')) {
        http_response_code(500);
        exit('Ekstensi database (PDO SQLite) tidak tersedia di server ini.');
    }

    /* PAYROLL_DB hanya untuk pengujian; produksi memakai data/payroll.sqlite. */
    $path = getenv('PAYROLL_DB') ?: (__DIR__ . '/data/payroll.sqlite');
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    /* Urutan penting: busy_timeout -> WAL -> synchronous. */
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    schema_ensure($pdo);

    $tz = (string) $pdo->query('SELECT nilai FROM payroll_configurations WHERE kunci = "zona_waktu"')->fetchColumn();
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
    return $pdo;
}

/* ------------------------------------------------------------------ */
/* Skema + data awal                                                   */
/* ------------------------------------------------------------------ */

/**
 * Membuat seluruh tabel dan mengisi data awal.
 * SATU transaksi BEGIN IMMEDIATE: mencegah balapan antar request ketika dua
 * pengguna membuka halaman bersamaan, dan mencegah satu fsync per statement
 * (fungsi ini dipanggil pada setiap permintaan).
 */
function schema_ensure(PDO $pdo): void
{
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        /* ---------- Key-value: identitas perusahaan ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS system_settings (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT "",
            tipe TEXT NOT NULL DEFAULT "teks",
            kelompok TEXT NOT NULL DEFAULT "umum",
            label TEXT NOT NULL DEFAULT "",
            keterangan TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        )');

        /* ---------- Key-value: variabel ketenagakerjaan ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS payroll_configurations (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT "",
            tipe TEXT NOT NULL DEFAULT "teks",
            kelompok TEXT NOT NULL DEFAULT "umum",
            label TEXT NOT NULL DEFAULT "",
            keterangan TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        )');

        /* ---------- Akun & sesi ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL DEFAULT "",
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            aktif INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT NOT NULL DEFAULT "",
            failed_count INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS sesi (
            token_hash TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT "",
            expires_at TEXT NOT NULL DEFAULT "",
            last_seen TEXT NOT NULL DEFAULT ""
        )');

        /* ---------- Master organisasi ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS divisi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL UNIQUE,
            keterangan TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS karyawan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nip TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL,
            jabatan TEXT NOT NULL DEFAULT "",
            divisi_id INTEGER NULL REFERENCES divisi(id) ON DELETE SET NULL,
            tanggal_masuk TEXT NOT NULL DEFAULT "",
            status_kepegawaian TEXT NOT NULL DEFAULT "tetap",
            metode_pajak TEXT NOT NULL DEFAULT "progresif",
            ptkp_kode TEXT NOT NULL DEFAULT "TK/0",
            npwp TEXT NOT NULL DEFAULT "",
            gaji_pokok REAL NOT NULL DEFAULT 0,
            tunjangan_tetap REAL NOT NULL DEFAULT 0,
            tunjangan_tidak_tetap REAL NOT NULL DEFAULT 0,
            bank TEXT NOT NULL DEFAULT "",
            no_rekening TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL DEFAULT "",
            telepon TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        )');

        /* Komponen gaji tambahan per karyawan (tunjangan & potongan rutin, termasuk kasbon) */
        $pdo->exec('CREATE TABLE IF NOT EXISTS komponen_gaji (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            jenis TEXT NOT NULL DEFAULT "PENDAPATAN",
            kategori TEXT NOT NULL DEFAULT "",
            nama TEXT NOT NULL,
            tipe TEXT NOT NULL DEFAULT "nominal",
            nilai REAL NOT NULL DEFAULT 0,
            berulang INTEGER NOT NULL DEFAULT 1,
            periode TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1,
            keterangan TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT ""
        )');

        /* ---------- Absensi & lembur ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS absensi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            tanggal TEXT NOT NULL,
            jam_masuk TEXT NOT NULL DEFAULT "",
            jam_pulang TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "HADIR",
            menit_terlambat INTEGER NOT NULL DEFAULT 0,
            menit_lembur INTEGER NOT NULL DEFAULT 0,
            /* Jam kerja HARI MINGGU (menit) — diisi langsung oleh petugas, bukan dari jam masuk/pulang.
               Dipakai sebagai "lembur minggu": jam kerja x tarif per jam khusus Minggu. */
            menit_minggu INTEGER NOT NULL DEFAULT 0,
            lembur_disetujui INTEGER NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT "",
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT "",
            UNIQUE (karyawan_id, tanggal)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absensi_tanggal ON absensi (tanggal)');

        /* Migrasi aman: pastikan kolom menit_minggu ada pada database yang sudah berjalan. */
        $adaMenitMinggu = false;
        foreach ($pdo->query('PRAGMA table_info(absensi)') as $c) {
            if ($c['name'] === 'menit_minggu') {
                $adaMenitMinggu = true;
                break;
            }
        }
        if (!$adaMenitMinggu) {
            $pdo->exec('ALTER TABLE absensi ADD COLUMN menit_minggu INTEGER NOT NULL DEFAULT 0');
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS hari_libur (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL UNIQUE,
            keterangan TEXT NOT NULL DEFAULT "",
            jenis TEXT NOT NULL DEFAULT "libur_nasional"
        )');

        /* ---------- Parameter pajak ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS ptkp (
            kode TEXT PRIMARY KEY,
            keterangan TEXT NOT NULL DEFAULT "",
            setahun REAL NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS pph21_layers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            urutan INTEGER NOT NULL,
            batas_bawah REAL NOT NULL DEFAULT 0,
            batas_atas REAL NOT NULL DEFAULT 0,
            tarif_persen REAL NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT ""
        )');

        /* ---------- Slip gaji ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS slip_gaji (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            periode TEXT NOT NULL,
            nomor TEXT NOT NULL DEFAULT "",
            tanggal_proses TEXT NOT NULL DEFAULT "",
            jabatan TEXT NOT NULL DEFAULT "",
            divisi TEXT NOT NULL DEFAULT "",
            metode_pajak TEXT NOT NULL DEFAULT "",
            hari_kerja REAL NOT NULL DEFAULT 0,
            hari_hadir REAL NOT NULL DEFAULT 0,
            hari_alpa REAL NOT NULL DEFAULT 0,
            hari_izin REAL NOT NULL DEFAULT 0,
            hari_sakit REAL NOT NULL DEFAULT 0,
            hari_cuti REAL NOT NULL DEFAULT 0,
            total_menit_terlambat INTEGER NOT NULL DEFAULT 0,
            total_menit_lembur INTEGER NOT NULL DEFAULT 0,
            menit_minggu INTEGER NOT NULL DEFAULT 0,          -- jam kerja HARI MINGGU (menit)
            upah_per_jam REAL NOT NULL DEFAULT 0,
            total_pendapatan REAL NOT NULL DEFAULT 0,
            total_potongan REAL NOT NULL DEFAULT 0,
            take_home_pay REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "DRAFT",
            catatan TEXT NOT NULL DEFAULT "",
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT "",
            UNIQUE (karyawan_id, periode)
        )');

        /* Kolom tambahan slip (migrasi aman: hanya ditambah bila belum ada). */
        foreach ([
            'hari_minggu'  => 'REAL NOT NULL DEFAULT 0',
            'upah_minggu'  => 'REAL NOT NULL DEFAULT 0',
            'total_bonus'  => 'REAL NOT NULL DEFAULT 0',
            'total_panjar' => 'REAL NOT NULL DEFAULT 0',
            'upah_harian'  => 'REAL NOT NULL DEFAULT 0',
            'menit_minggu' => 'INTEGER NOT NULL DEFAULT 0',
        ] as $kolom => $ddl) {
            $ada = false;
            foreach ($pdo->query('PRAGMA table_info(slip_gaji)') as $c) {
                if ($c['name'] === $kolom) {
                    $ada = true;
                    break;
                }
            }
            if (!$ada) {
                $pdo->exec("ALTER TABLE slip_gaji ADD COLUMN {$kolom} {$ddl}");
            }
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS slip_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slip_id INTEGER NOT NULL REFERENCES slip_gaji(id) ON DELETE CASCADE,
            jenis TEXT NOT NULL,
            kategori TEXT NOT NULL DEFAULT "",
            nama TEXT NOT NULL,
            nilai REAL NOT NULL DEFAULT 0,
            sumber TEXT NOT NULL DEFAULT "",
            urutan INTEGER NOT NULL DEFAULT 0
        )');

        /* ---------- Bonus (parameter dinamis, bisa ditambah admin) ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS bonus_parameter (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL,
            tipe TEXT NOT NULL DEFAULT "nominal",
            nilai REAL NOT NULL DEFAULT 0,
            kena_pajak INTEGER NOT NULL DEFAULT 1,
            berlaku_semua INTEGER NOT NULL DEFAULT 1,
            syarat_alpa_nol INTEGER NOT NULL DEFAULT 0,
            syarat_tanpa_terlambat INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT ""
        )');

        /* Penerima bonus per karyawan (kosong = ikut saklar "berlaku untuk semua karyawan") */
        $pdo->exec('CREATE TABLE IF NOT EXISTS karyawan_bonus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            bonus_id INTEGER NOT NULL REFERENCES bonus_parameter(id) ON DELETE CASCADE,
            aktif INTEGER NOT NULL DEFAULT 1,
            nilai REAL NOT NULL DEFAULT -1,
            UNIQUE (karyawan_id, bonus_id)
        )');

        /* ---------- Panjar (uang muka; satu-satunya potongan utama) ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS panjar (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            tanggal TEXT NOT NULL DEFAULT "",
            jumlah REAL NOT NULL DEFAULT 0,
            cicilan_per_bulan REAL NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT ""
        )');

        /* ---------- Jejak audit ---------- */
        $pdo->exec('CREATE TABLE IF NOT EXISTS log_admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            waktu TEXT NOT NULL DEFAULT "",
            oleh TEXT NOT NULL DEFAULT "",
            aksi TEXT NOT NULL DEFAULT "",
            rincian TEXT NOT NULL DEFAULT ""
        )');

        /* ================= Isi data awal (hanya sekali) ================= */
        $seed = (string) $pdo->query('SELECT nilai FROM system_settings WHERE kunci = "seed_selesai"')->fetchColumn();
        $insSet = $pdo->prepare('INSERT OR IGNORE INTO system_settings (kunci, nilai, tipe, kelompok, label, keterangan, updated_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insCfg = $pdo->prepare('INSERT OR IGNORE INTO payroll_configurations (kunci, nilai, tipe, kelompok, label, keterangan, updated_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)');
        $now = date('Y-m-d H:i:s');

        $bawaanSet = [
            'app_nama'            => 'HR & Payroll Management',
            'perusahaan_nama'     => 'PT Nusantara Sejahtera Mandiri',
            'perusahaan_alamat'   => "Jl. Perintis Kemerdekaan No. 45, Lantai 3\nKec. Tamalanrea, Kota Makassar, Sulawesi Selatan 90245",
            'perusahaan_telepon'  => '(0411) 588-1234',
            'perusahaan_email'    => 'hrd@nusantarasejahtera.co.id',
            'perusahaan_website'  => 'www.nusantarasejahtera.co.id',
            'perusahaan_npwp'     => '01.234.567.8-901.000',
            'perusahaan_kota'     => 'Makassar',
            'logo_url'            => '',
            'logo_nama'           => '',
            'ttd_nama'            => 'Andi Rahmat Hidayat, S.E.',
            'ttd_jabatan'         => 'Direktur Utama',
            'ttd_url'             => '',
            'ttd_tampil_gambar'   => '0',
            'slip_judul'          => 'SLIP GAJI KARYAWAN',
            'slip_catatan'        => 'Slip gaji ini bersifat RAHASIA dan hanya untuk karyawan yang bersangkutan. Bila terdapat ketidaksesuaian, silakan menghubungi HRD maksimal 7 hari kerja setelah slip diterima.',
            'slip_tampil_logo'    => '1',
            'slip_tampil_rekap'   => '1',
            'slip_tampil_terbilang' => '1',
            'slip_kode_prefix'    => 'SLIP',
        ];
        foreach (daftar_system_setting() as $kunci => $meta) {
            $insSet->execute([$kunci, $bawaanSet[$kunci] ?? '', $meta['tipe'], $meta['kelompok'], $meta['label'], $meta['keterangan'] ?? '', $now]);
        }
        foreach (daftar_payroll_config() as $kunci => $meta) {
            $insCfg->execute([$kunci, (string) ($meta['nilai'] ?? ''), $meta['tipe'], $meta['kelompok'], $meta['label'], $meta['keterangan'] ?? '', $now]);
        }
        /* Nilai awal dari seed demo (dipakai oleh mesin payroll sebagai contoh). */
        $pdo->prepare('INSERT OR IGNORE INTO system_settings (kunci, nilai, tipe, kelompok, label, keterangan, updated_at) VALUES ("seed_selesai", "1", "teks", "umum", "", "", ?)')
            ->execute([$now]);
        $pdo->prepare('INSERT OR IGNORE INTO payroll_configurations (kunci, nilai, tipe, kelompok, label, keterangan, updated_at) VALUES ("demo_absensi_dibuat", "0", "teks", "umum", "", "", ?)')
            ->execute([$now]);
        $pdo->prepare('INSERT OR IGNORE INTO payroll_configurations (kunci, nilai, tipe, kelompok, label, keterangan, updated_at) VALUES ("demo_slip_dibuat", "0", "teks", "umum", "", "", ?)')
            ->execute([$now]);
        $pdo->prepare('INSERT OR IGNORE INTO payroll_configurations (kunci, nilai, tipe, kelompok, label, keterangan, updated_at) VALUES ("demo_nonaktif", "0", "teks", "umum", "", "", ?)')
            ->execute([$now]);

        /* ---------- Akun bawaan ---------- */
        if ($seed === '' || $seed === false || $seed === null) {
            $insUser = $pdo->prepare('INSERT OR IGNORE INTO user (username, nama, password_hash, role, aktif, updated_at)
                                      VALUES (?, ?, ?, ?, 1, ?)');
            $insUser->execute(['superadmin', 'Superadmin Sistem', password_hash('superadmin', PASSWORD_DEFAULT), 'superadmin', $now]);
            $insUser->execute(['hrd', 'HRD / Admin Payroll', password_hash('hrd', PASSWORD_DEFAULT), 'hrd', $now]);
        } else {
            /* Pastikan akun tetap ada walau tabel user pernah dikosongkan. */
            $jmlUser = (int) $pdo->query('SELECT COUNT(*) FROM user')->fetchColumn();
            if ($jmlUser === 0) {
                $insUser = $pdo->prepare('INSERT OR IGNORE INTO user (username, nama, password_hash, role, aktif, updated_at)
                                          VALUES (?, ?, ?, ?, 1, ?)');
                $insUser->execute(['superadmin', 'Superadmin Sistem', password_hash('superadmin', PASSWORD_DEFAULT), 'superadmin', $now]);
                $insUser->execute(['hrd', 'HRD / Admin Payroll', password_hash('hrd', PASSWORD_DEFAULT), 'hrd', $now]);
            }
        }

        /* ---------- PTKP (bisa ditambah/diubah admin) ---------- */
        $jmlPtkp = (int) $pdo->query('SELECT COUNT(*) FROM ptkp')->fetchColumn();
        if ($jmlPtkp === 0) {
            $insPtkp = $pdo->prepare('INSERT INTO ptkp (kode, keterangan, setahun, aktif, urutan) VALUES (?, ?, ?, 1, ?)');
            $ptkp = [
                ['TK/0', 'Tidak kawin, 0 tanggungan', 54000000],
                ['TK/1', 'Tidak kawin, 1 tanggungan', 58500000],
                ['TK/2', 'Tidak kawin, 2 tanggungan', 63000000],
                ['TK/3', 'Tidak kawin, 3 tanggungan', 67500000],
                ['K/0',  'Kawin, 0 tanggungan', 58500000],
                ['K/1',  'Kawin, 1 tanggungan', 63000000],
                ['K/2',  'Kawin, 2 tanggungan', 67500000],
                ['K/3',  'Kawin, 3 tanggungan', 72000000],
            ];
            $u = 1;
            foreach ($ptkp as $p) {
                $insPtkp->execute([$p[0], $p[1], $p[2], $u++]);
            }
        }

        /* ---------- Lapisan tarif PPh 21 progresif ---------- */
        $jmlLayer = (int) $pdo->query('SELECT COUNT(*) FROM pph21_layers')->fetchColumn();
        if ($jmlLayer === 0) {
            $insLayer = $pdo->prepare('INSERT INTO pph21_layers (urutan, batas_bawah, batas_atas, tarif_persen, keterangan) VALUES (?, ?, ?, ?, ?)');
            $layers = [
                [1, 0,          60000000,   5,  'Sampai dengan Rp60 juta'],
                [2, 60000000,   250000000,  15, 'Di atas Rp60 juta s.d. Rp250 juta'],
                [3, 250000000,  500000000,  25, 'Di atas Rp250 juta s.d. Rp500 juta'],
                [4, 500000000,  5000000000, 30, 'Di atas Rp500 juta s.d. Rp5 miliar'],
                [5, 5000000000, 0,          35, 'Di atas Rp5 miliar (batas_atas 0 = tanpa batas)'],
            ];
            foreach ($layers as $l) {
                $insLayer->execute($l);
            }
        }

        /* ---------- Parameter bonus (7 bonus perusahaan + bisa ditambah) ---------- */
        $jmlBonus = (int) $pdo->query('SELECT COUNT(*) FROM bonus_parameter')->fetchColumn();
        if ($jmlBonus === 0) {
            $insBonus = $pdo->prepare('INSERT INTO bonus_parameter
                (kode, nama, tipe, nilai, kena_pajak, berlaku_semua, syarat_alpa_nol, syarat_tanpa_terlambat, aktif, urutan, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
            $bonus = [
                ['bonus_target', 'Bonus Target', 'nominal', 750000, 1, 1, 1, 0, 1, 'Dibayar bila target kerja tercapai (syarat: tanpa hari alpa).'],
                ['bonus_produksi', 'Bonus Produksi', 'nominal', 500000, 1, 1, 0, 0, 2, 'Bonus hasil produksi bulanan.'],
                ['bonus_kehadiran', 'Bonus Kehadiran', 'per_hari_hadir', 25000, 1, 1, 1, 0, 3, 'Dibayarkan per hari hadir; hangus bila ada hari alpa (tanpa keterangan).'],
                ['bonus_sop', 'Bonus SOP', 'nominal', 250000, 1, 1, 0, 0, 4, 'Kepatuhan menjalankan standar operasional.'],
                ['bonus_kreativitas', 'Bonus Kreativitas & Inovasi', 'nominal', 300000, 1, 1, 0, 0, 5, 'Ide perbaikan / inovasi yang diterapkan.'],
                ['bonus_kualitas', 'Bonus Kualitas / Zero Error', 'nominal', 400000, 1, 1, 0, 0, 6, 'Tidak ada kesalahan produksi sepanjang periode.'],
                ['bonus_insentif_minggu', 'Bonus Insentif Minggu', 'per_minggu_kerja', 200000, 1, 0, 0, 0, 7, 'Insentif tiap hari Minggu yang dikerjakan. Contoh: hanya diberikan kepada karyawan tertentu dengan nilai berbeda.'],
            ];
            foreach ($bonus as $b) {
                $insBonus->execute($b);
            }
        }

        /* ---------- Data contoh (perusahaan, karyawan, absensi) ---------- */
        $jmlDivisi = (int) $pdo->query('SELECT COUNT(*) FROM divisi')->fetchColumn();
        $jmlKaryawan = (int) $pdo->query('SELECT COUNT(*) FROM karyawan')->fetchColumn();
        if ($seed === '' && $jmlDivisi === 0 && $jmlKaryawan === 0) {
            $insDiv = $pdo->prepare('INSERT INTO divisi (nama, keterangan, aktif) VALUES (?, ?, 1)');
            $divisi = [
                ['Manajemen', 'Direksi, HRD, dan administrasi pusat'],
                ['Keuangan', 'Akuntansi, pajak, dan kasir'],
                ['Operasional', 'Produksi dan gudang'],
                ['Pemasaran', 'Penjualan dan hubungan pelanggan'],
                ['Teknologi Informasi', 'Infrastruktur dan pengembangan sistem'],
            ];
            foreach ($divisi as $d) {
                $insDiv->execute($d);
            }
            $petaDiv = [];
            foreach ($pdo->query('SELECT id, nama FROM divisi') as $r) {
                $petaDiv[$r['nama']] = (int) $r['id'];
            }

            $insKar = $pdo->prepare('INSERT INTO karyawan
                (nip, nama, jabatan, divisi_id, tanggal_masuk, status_kepegawaian, metode_pajak, ptkp_kode, npwp,
                 gaji_pokok, tunjangan_tetap, tunjangan_tidak_tetap, bank, no_rekening, email, telepon, aktif, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
            $contoh = [
                // nip, nama, jabatan, divisi, tgl masuk, status, metode pajak, ptkp, npwp, pokok, tunj tetap, tunj tidak tetap, bank, rekening, email, telepon
                ['NSS-001', 'Andi Rahmat Hidayat',   'Direktur Utama',        'Manajemen',             '2015-02-02', 'tetap',   'progresif', 'K/2', '01.234.567.8-901.000', 22000000, 5500000, 0,       'Bank Mandiri', '1300099887766', 'andi@nusantarasejahtera.co.id',  '0811-2345-678'],
                ['NSS-002', 'Siti Aminah Yusuf',     'HR Manager',            'Manajemen',             '2017-06-12', 'tetap',   'progresif', 'K/1', '02.345.678.9-012.000', 14500000, 3000000, 750000,  'Bank Mandiri', '1300099887711', 'siti@nusantarasejahtera.co.id',  '0812-3456-789'],
                ['NSS-003', 'Budi Santoso',          'Staf Akuntansi',        'Keuangan',              '2019-09-01', 'tetap',   'progresif', 'K/0', '03.456.789.0-123.000',  7500000, 1200000, 500000,  'Bank BCA',     '7640123456',    'budi@nusantarasejahtera.co.id',  '0813-4567-890'],
                ['NSS-004', 'Dewi Kartika Sari',     'Kasir',                 'Keuangan',              '2021-03-15', 'kontrak', 'tunggal',   'TK/0','',                     5200000, 600000,  300000,  'Bank BNI',     '0987654321',    'dewi@nusantarasejahtera.co.id',  '0814-5678-901'],
                ['NSS-005', 'Muhammad Fajar',        'Supervisor Produksi',   'Operasional',           '2018-01-08', 'tetap',   'progresif', 'K/3', '05.678.901.2-345.000',  9500000, 1800000, 900000,  'Bank BRI',     '334455667788',  'fajar@nusantarasejahtera.co.id', '0815-6789-012'],
                ['NSS-006', 'Nurhaliza Putri',       'Operator Produksi',     'Operasional',           '2022-07-18', 'kontrak', 'tunggal',   'TK/0','',                     4500000, 400000,  250000,  'Bank BRI',     '334455661122',  'nurhaliza@nusantarasejahtera.co.id', '0816-7890-123'],
                ['NSS-007', 'Rangga Pratama',        'Staf Pemasaran',        'Pemasaran',             '2023-02-06', 'kontrak', 'tunggal',   'K/0', '',                      4800000, 500000,  1500000, 'Bank BCA',     '7640987654',    'rangga@nusantarasejahtera.co.id','0817-8901-234'],
                ['NSS-008', 'Indah Permata Sari',    'Programmer',            'Teknologi Informasi',   '2021-11-01', 'tetap',   'progresif', 'TK/0','06.789.012.3-456.000',  10500000, 1500000, 600000,  'Bank Mandiri', '1300099887999', 'indah@nusantarasejahtera.co.id', '0818-9012-345'],
            ];
            foreach ($contoh as $c) {
                $insKar->execute([
                    $c[0], $c[1], $c[2], $petaDiv[$c[3]] ?? null, $c[4], $c[5], $c[6], $c[7], $c[8],
                    $c[9], $c[10], $c[11], $c[12], $c[13], $c[14], $c[15], $now, $now,
                ]);
            }

            /* Komponen gaji contoh: hanya pendapatan tambahan.
               Sesuai aturan perusahaan, potongan rutin memakai mekanisme PANJAR. */
            $idKaryawan = [];
            foreach ($pdo->query('SELECT id, nip FROM karyawan') as $r) {
                $idKaryawan[$r['nip']] = (int) $r['id'];
            }
            $insKom = $pdo->prepare('INSERT INTO komponen_gaji
                (karyawan_id, jenis, kategori, nama, tipe, nilai, berulang, periode, aktif, keterangan, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, "", 1, ?, ?)');
            $komponen = [
                ['NSS-006', 'PENDAPATAN', 'Tunjangan', 'Tunjangan transport harian', 'nominal', 400000, 1, 'Ditambahkan tiap bulan'],
            ];
            foreach ($komponen as $k) {
                $insKom->execute([$idKaryawan[$k[0]], $k[1], $k[2], $k[3], $k[4], $k[5], $k[6], $k[7], $now]);
            }

            /* Penugasan bonus per karyawan & contoh PANJAR diisi oleh demo_pelengkap()
               (lib.php) supaya satu sumber kebenaran untuk database baru maupun lama. */

            /* Hari libur nasional bertanggal tetap (tidak berubah tiap tahun). */
            $insLibur = $pdo->prepare('INSERT OR IGNORE INTO hari_libur (tanggal, keterangan, jenis) VALUES (?, ?, "libur_nasional")');
            $insLibur->execute([date('Y') . '-01-01', 'Tahun Baru Masehi']);
            $insLibur->execute([date('Y') . '-05-01', 'Hari Buruh Internasional']);
            $insLibur->execute([date('Y') . '-06-01', 'Hari Lahir Pancasila']);
            $insLibur->execute([date('Y') . '-08-17', 'Hari Kemerdekaan RI']);
            $insLibur->execute([date('Y') . '-12-25', 'Hari Raya Natal']);
        }

        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}
