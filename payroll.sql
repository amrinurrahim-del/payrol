PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE system_settings (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT "",
            tipe TEXT NOT NULL DEFAULT "teks",
            kelompok TEXT NOT NULL DEFAULT "umum",
            label TEXT NOT NULL DEFAULT "",
            keterangan TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        );
INSERT INTO system_settings VALUES('perusahaan_nama','DELTA PRINTING','teks','perusahaan','Nama Perusahaan','Tampil di header aplikasi & slip gaji.','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('perusahaan_alamat','Jl. Poros Masamba, Luwu Utara, Sulawesi Selatan 90245','alamat','perusahaan','Alamat Lengkap','Alamat kantor pusat, tampil di slip gaji.','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('perusahaan_telepon','(0411) 588-1234','telepon','perusahaan','Nomor Telepon','Contoh: (0411) 123456','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('perusahaan_email','hrd@delta.co.id','email','perusahaan','Email','Contoh: hrd@perusahaan.co.id','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('perusahaan_website','www.deltaprintingmsb.co.id','teks','perusahaan','Website','Contoh: www.perusahaan.co.id','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('perusahaan_npwp','01.234.567.8-901.000','teks','perusahaan','NPWP Perusahaan','Ditampilkan di slip gaji (opsional).','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('perusahaan_kota','Luwu Utara','teks','perusahaan','Kota (untuk tanggal tanda tangan)','Contoh: Makassar','2026-09-28 01:03:24');
INSERT INTO system_settings VALUES('logo_url','https://media.vibecoder.co.id/app-media/andidjemma/payroll/4264c329-974c-4d3e-9124-3b7d8501f7ee.png','teks','perusahaan','Logo Perusahaan (URL)','Diunggah lewat kartu Unggah Logo di tab Perusahaan.','2026-09-28 10:44:42');
INSERT INTO system_settings VALUES('logo_nama','ChatGPT_Image_28_Sep_2026__10.44.22.png','teks','perusahaan','Nama Berkas Logo','','2026-09-28 10:44:42');
INSERT INTO system_settings VALUES('ttd_nama','ASBAR, S.E.','teks','ttd','Nama Penanda Tangan','Direktur / HR Manager.','2026-09-28 01:01:04');
INSERT INTO system_settings VALUES('ttd_jabatan','Owner Delta Printing','teks','ttd','Jabatan Penanda Tangan','Contoh: HR Manager','2026-09-28 01:01:04');
INSERT INTO system_settings VALUES('ttd_url','https://media.vibecoder.co.id/app-media/andidjemma/payroll/4c64bba3-53c6-40ca-94be-fb7ab7cb7007.png','teks','ttd','Gambar Tanda Tangan (URL)','Opsional — unggah gambar tanda tangan (PNG transparan disarankan).','2026-09-28 01:06:49');
INSERT INTO system_settings VALUES('ttd_tampil_gambar','1','bool','ttd','Cetak gambar tanda tangan','Bila dimatikan, hanya nama & jabatan yang dicetak.','2026-09-28 01:06:49');
INSERT INTO system_settings VALUES('slip_judul','SLIP GAJI KARYAWAN','teks','slip','Judul Slip','Contoh: SLIP GAJI KARYAWAN','2026-10-08 15:15:08');
INSERT INTO system_settings VALUES('slip_catatan','Slip gaji ini bersifat RAHASIA dan hanya untuk karyawan yang bersangkutan. Bila terdapat ketidaksesuaian, silakan menghubungi Owner maksimal 7 hari kerja setelah slip diterima.','alamat','slip','Catatan Kaki Slip','Contoh: Slip ini bersifat rahasia.','2026-10-08 15:15:08');
INSERT INTO system_settings VALUES('slip_tampil_logo','1','bool','slip','Tampilkan logo di slip','','2026-10-08 15:15:08');
INSERT INTO system_settings VALUES('slip_tampil_rekap','1','bool','slip','Tampilkan rekap absensi & lembur','Rekap hari hadir, keterlambatan, dan jam lembur.','2026-10-08 15:15:08');
INSERT INTO system_settings VALUES('slip_tampil_terbilang','1','bool','slip','Tampilkan take home pay terbilang','','2026-10-08 15:15:08');
INSERT INTO system_settings VALUES('slip_kode_prefix','SLIP','teks','slip','Awalan Nomor Slip','Contoh: SLIP → SLIP/2026-01/0001','2026-10-08 15:15:08');
INSERT INTO system_settings VALUES('app_nama','PAYROLL DELTA PRINTING','teks','umum','Nama Aplikasi','Tampil di header aplikasi.','2026-09-28 00:44:35');
INSERT INTO system_settings VALUES('seed_selesai','1','teks','umum','','','2026-09-27 23:15:42');
CREATE TABLE payroll_configurations (
            kunci TEXT PRIMARY KEY,
            nilai TEXT NOT NULL DEFAULT "",
            tipe TEXT NOT NULL DEFAULT "teks",
            kelompok TEXT NOT NULL DEFAULT "umum",
            label TEXT NOT NULL DEFAULT "",
            keterangan TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT ""
        );
INSERT INTO payroll_configurations VALUES('zona_waktu','Asia/Makassar','zona','umum','Zona Waktu','Dipakai untuk jam absensi & tanggal slip.','2026-09-28 00:44:35');
INSERT INTO payroll_configurations VALUES('mata_uang_simbol','Rp','teks','umum','Simbol Mata Uang','Dicetak pada slip (mis. Rp, $, €).','2026-09-28 00:44:35');
INSERT INTO payroll_configurations VALUES('hari_kerja_mingguan','1,2,3,4,5,6','teks','umum','Hari Kerja Mingguan','1=Senin … 7=Minggu. Hanya hari ini yang dihitung sebagai hari kerja.','2026-09-28 00:44:35');
INSERT INTO payroll_configurations VALUES('basis_hari_kerja','otomatis','pilihan','umum','Basis Jumlah Hari Kerja','Dipakai sebagai pembagi upah harian.','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('hari_kerja_per_bulan','25','angka','umum','Hari Kerja per Bulan (tetap)','Hanya dipakai bila basis = angka tetap (banyak perusahaan memakai 25).','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('jam_kerja_per_hari','10','jam','umum','Jam Kerja per Hari','Pembagi upah per jam.','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('basis_upah_per_jam','gaji_pokok','pilihan','umum','Dasar Upah per Jam','Basis penghitungan upah lembur & potongan absensi.','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('pembulatan_gaji','bulat','pilihan','umum','Pembulatan Nilai Gaji','Dipakai untuk seluruh angka pendapatan & potongan.','2026-09-28 00:44:35');
INSERT INTO payroll_configurations VALUES('absensi_aktif','1','bool','absensi','Aktifkan potongan absensi','Bila dimatikan, keterlambatan & alpa tidak dipotong.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_toleransi_menit','10','menit','absensi','Toleransi Keterlambatan','Keterlambatan sampai sekian menit tidak dipotong.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_denda_mode','per_menit','pilihan','absensi','Cara Menghitung Denda','Per menit, per kejadian, atau bertingkat per rentang menit.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_denda_per_menit','250','uang','absensi','Denda per Menit','Dikalikan menit terlambat setelah toleransi.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_denda_per_kejadian','25000','uang','absensi','Denda per Kejadian','Dipakai bila cara = denda per kejadian.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_denda_bertingkat','[{"menit":30,"denda":10000},{"menit":60,"denda":25000},{"menit":90,"denda":50000},{"menit":0,"denda":75000}]','json','absensi','Rentang Denda Bertingkat','Format JSON [{menit,denda}]. "menit" = batas atas terlambat (menit ≤ batas). Isi 0 pada "menit" terakhir berarti "lebih dari batas sebelumnya".','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_maks_denda_bulan','0','uang','absensi','Batas Maksimum Denda / Bulan','0 = tanpa batas.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_alpa_mode','tidak_ada','pilihan','absensi','Potongan Alpa (tanpa keterangan)','Alpa = tidak hadir tanpa izin/sakit/cuti.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_alpa_faktor','1','angka','absensi','Pengali Upah Harian (Alpa)','Banyak perusahaan memakai 1 sampai 3 (sanksi).','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_alpa_nominal','100000','uang','absensi','Nominal Alpa per Hari','Dipakai bila cara = nominal.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_izin_mode','tidak_ada','pilihan','absensi','Potongan Izin','Izin dengan keterangan (mis. izin menikah/cuti).','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_izin_nominal','0','uang','absensi','Nominal Izin per Hari','','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_sakit_mode','tidak_ada','pilihan','absensi','Potongan Sakit','Biasanya tetap dibayar bila ada surat dokter.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_sakit_nominal','0','uang','absensi','Nominal Sakit per Hari','','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('absensi_jam_masuk_standar','08:00','waktu','absensi','Jam Masuk Standar','Dipakai untuk menghitung menit keterlambatan otomatis di halaman Absensi.','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('absensi_jam_pulang_standar','20:00','waktu','absensi','Jam Pulang Standar','Dipakai untuk menghitung menit lembur otomatis di halaman Absensi.','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('absensi_alpa_tanpa_catatan','0','bool','absensi','Hari kerja tanpa catatan dihitung Alpa','Bila menyala, hari kerja yang belum diisi absensinya (sampai hari ini) otomatis dihitung sebagai alpa pada perhitungan gaji. Biarkan mati agar gaji tidak terpotong sebelum absensi diisi.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('lembur_aktif','1','bool','lembur','Aktifkan perhitungan lembur','','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_faktor_pertama','1.5','angka','lembur','Faktor Jam Lembur Pertama','Regulasi Indonesia: 1,5× upah per jam pada jam pertama.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_faktor_berikutnya','2','angka','lembur','Faktor Jam Lembur Berikutnya','Regulasi Indonesia: 2× upah per jam pada jam berikutnya.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_faktor_hari_libur','2','angka','lembur','Faktor Lembur Hari Libur','Dipakai bila lembur jatuh pada hari libur/minggu.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_pembulatan','per_30menit','pilihan','lembur','Pembulatan Waktu Lembur','Menentukan cara membaca menit lembur dari absensi.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_maks_jam_bulan','0','jam','lembur','Maksimum Jam Lembur / Bulan','0 = tanpa batas.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_wajib_disetujui','0','bool','lembur','Lembur wajib disetujui','Bila aktif, hanya lembur yang dicentang "disetujui" yang dibayar.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('bpjs_aktif','0','bool','bpjs','Aktifkan potongan BPJS','','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('bpjs_dasar','gaji_pokok','pilihan','bpjs','Dasar Perhitungan BPJS','Upah yang dijadikan dasar iuran.','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('bpjs_maks_dasar','0','uang','bpjs','Batas Atas Dasar BPJS','0 = tanpa batas. Sesuaikan dengan plafon upah terbaru.','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('bpjs_tk_karyawan_persen','3','persen','bpjs','BPJS Ketenagakerjaan — Karyawan (%)','Umumnya JHT 2% + JP 1%.','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('bpjs_kes_karyawan_persen','1','persen','bpjs','BPJS Kesehatan — Karyawan (%)','','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('bpjs_tk_perusahaan_persen','6.24','persen','bpjs','BPJS Ketenagakerjaan — Perusahaan (%)','Ditanggung perusahaan, tampil sebagai tunjangan.','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('bpjs_kes_perusahaan_persen','4','persen','bpjs','BPJS Kesehatan — Perusahaan (%)','','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('bpjs_tampil_tunjangan_perusahaan','1','bool','bpjs','Tampilkan iuran perusahaan sebagai pendapatan','Direkomendasikan menyala agar slip menunjukkan total kompensasi.','2026-10-03 16:12:57');
INSERT INTO payroll_configurations VALUES('pajak_aktif','0','bool','pajak','Aktifkan perhitungan PPh 21','Bila dimatikan, tidak ada baris potongan pajak.','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('pajak_metode_default','progresif','pilihan','pajak','Metode Pajak Bawaan','Dipakai untuk karyawan baru; tiap karyawan tetap bisa memilih metodenya sendiri.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_tunggal_persen','5','persen','pajak','Pajak — Persentase Tunggal (%)','Dipakai untuk karyawan dengan metode "persentase tunggal".','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_tunggal_dasar','bruto','pilihan','pajak','Dasar Persentase Tunggal','Dasar pengenaan sebelum/sesudah potongan BPJS.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_bulat_pkp','1000','uang','pajak','Pembulatan PKP ke Bawah','PKP dibulatkan ke bawah dalam kelipatan nilai ini (lazim Rp1.000).','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_biaya_jabatan_persen','5','persen','pajak','Biaya Jabatan (% bruto)','Pengurang bruto setahun.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_biaya_jabatan_maks_bulan','500000','uang','pajak','Batas Biaya Jabatan / Bulan','Lazim Rp500.000 per bulan (Rp6.000.000 per tahun).','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_tanpa_npwp_tambahan_persen','20','persen','pajak','Tambahan Pajak Tanpa NPWP (%)','Kenaikan tarif bila karyawan belum punya NPWP. Isi 0 bila tidak ingin diterapkan.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_bulan_setahun','12','angka','pajak','Jumlah Bulan dalam Setahun','Dipakai untuk menyetahunkan penghasilan tetap.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_bpjs_pengurang','0','bool','pajak','Iuran BPJS karyawan sebagai pengurang','Iuran JHT/JP/pensiun milik karyawan mengurangi penghasilan bruto.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('pajak_bpjs_perusahaan_kena_pajak','0','bool','pajak','Iuran BPJS perusahaan kena pajak','Bila menyala, tunjangan BPJS dari perusahaan ikut dihitung sebagai penghasilan bruto.','2026-09-28 00:59:05');
INSERT INTO payroll_configurations VALUES('demo_absensi_dibuat','1','teks','umum','','','2026-09-28 01:42:03');
INSERT INTO payroll_configurations VALUES('demo_slip_dibuat','1','teks','umum','','','2026-09-28 01:42:03');
INSERT INTO payroll_configurations VALUES('jam_kerja_mode','segmen','pilihan','jam_kerja','Bentuk Jam Kerja Harian','Segmen = jam kerja dibagi beberapa blok dengan jam istirahat (mis. 08:00–11:00 dan 13:00–20:00).','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('jam_kerja_segmen','[{"nama":"Sesi pagi","mulai":"08:00","selesai":"11:00","istirahat":false},{"nama":"Istirahat","mulai":"11:00","selesai":"13:00","istirahat":true},{"nama":"Sesi sore","mulai":"13:00","selesai":"20:00","istirahat":false}]','json','jam_kerja','Segmen Jam Kerja Harian','Format JSON [{"nama","mulai","selesai","istirahat"}]. Segmen dengan "istirahat": true TIDAK dihitung sebagai jam kerja dan tidak dibayar.','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('minggu_aktif','1','bool','minggu','Aktifkan aturan kerja hari Minggu','Bila aktif, karyawan yang bekerja pada hari Minggu mendapat upah harian + upah lembur minggu.','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_upah_harian_faktor','1','angka','minggu','Upah Harian Minggu (× upah harian)','Bagian pertama dari "2 upah". Nilai 1 = dibayar 1× upah harian untuk setiap hari Minggu yang dikerjakan.','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_lembur_mode','per_jam_faktor','pilihan','minggu','Cara Menghitung Upah Lembur Minggu','Bagian kedua dari "2 upah".','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_lembur_faktor_harian','1','angka','minggu','Faktor Upah Harian (lembur Minggu)','Dipakai bila cara = "sekian kali upah harian". Nilai 1 → total 2 upah harian per hari Minggu (upah harian + lembur minggu).','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_lembur_nominal','0','uang','minggu','Nominal Lembur Minggu per Hari','Dipakai bila cara = "nominal rupiah per hari".','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_lembur_faktor_jam','1','angka','minggu','Faktor Upah per Jam (lembur Minggu)','Dipakai bila cara = "sekian kali upah per jam"; dikalikan jam kerja aktual hari Minggu dari absensi.','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_minimal_menit','0','menit','minggu','Minimal Menit Kerja Hari Minggu','Catatan absensi hari Minggu dengan jam kerja kurang dari ini TIDAK dihitung sebagai kerja hari Minggu (mencegah salah input). 0 = semua dihitung.','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('potongan_denda_aktif','1','bool','potongan','Denda Keterlambatan','Pemilik dapat mematikannya; pengaturan denda lengkap ada di tab Absensi.','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('potongan_alpa_aktif','0','bool','potongan','Potongan Alpa','Bila mati, hari tanpa keterangan tidak dipotong.','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('potongan_izin_aktif','0','bool','potongan','Potongan Izin','','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('potongan_sakit_aktif','0','bool','potongan','Potongan Sakit','','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('potongan_panjar_aktif','1','bool','potongan','Potongan Panjar (cicilan)','Potongan utama yang dipakai perusahaan ini: cicilan panjar yang diberikan ke karyawan.','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('potongan_lain_aktif','1','bool','potongan','Potongan Tambahan per Karyawan','Potongan yang ditambahkan manual pada data karyawan (kasbon, koperasi, dsb.).','2026-10-08 15:16:06');
INSERT INTO payroll_configurations VALUES('versi_aturan','5','teks','umum','Versi aturan','Penanda migrasi aturan perusahaan','2026-10-03 11:29:04');
INSERT INTO payroll_configurations VALUES('demo_nonaktif','1','teks','umum','','','2026-09-28 01:42:03');
INSERT INTO payroll_configurations VALUES('upah_mode','proporsional_hadir','pilihan','upah','Cara Membayar Upah Bulanan','Menentukan bentuk pendapatan upah pada slip.','2026-10-03 12:07:32');
INSERT INTO payroll_configurations VALUES('tunjangan_ikut_kehadiran','1','bool','upah','Tunjangan tetap & tidak tetap ikut dihitung proporsional kehadiran','Bila mati (bawaan), tunjangan dibayar penuh dan tidak dipengaruhi jumlah kehadiran.','2026-10-03 12:07:32');
INSERT INTO payroll_configurations VALUES('alpa_potong_saat_proporsional','0','bool','absensi','Tetap potong alpa walau upah sudah proporsional','Bila “Cara Membayar Upah Bulanan” = proporsional kehadiran, hari alpa otomatis tidak dibayar. Biarkan mati agar tidak terhitung dua kali; nyalakan hanya bila memang ingin menambah sanksi.','2026-10-08 11:20:25');
INSERT INTO payroll_configurations VALUES('lembur_pembagi','173','angka','lembur','Pembagi Upah Lembur per Jam','Rumus perusahaan ini: Upah Lembur per Jam = Gaji Pokok ÷ 173 (rumus resmi ketenagakerjaan: 1/173 × upah sebulan).','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('lembur_pakai_faktor','0','bool','lembur','Pakai faktor pengali (1,5× / 2×)','Mati (bawaan) = upah lembur = jam × (Gaji Pokok ÷ 173), sesuai kebijakan perusahaan ini. Nyalakan bila ingin memakai aturan 1,5× jam pertama dan 2× jam berikutnya.','2026-10-03 15:56:20');
INSERT INTO payroll_configurations VALUES('minggu_mode','tarif_jam','pilihan','minggu','Cara Menghitung Upah Hari Minggu','Perusahaan ini memakai tarif per jam khusus Minggu.','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('minggu_tarif_jam','25000','uang','minggu','Tarif Upah Minggu per Jam','Berbeda dari upah hari biasa. Contoh: Rp20.000 → kerja 10 jam = Rp200.000.','2026-10-03 15:55:14');
INSERT INTO payroll_configurations VALUES('hari_kerja_kurangi_libur','1','teks','umum','','','2026-10-03 11:29:04');
CREATE TABLE user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL DEFAULT "",
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            aktif INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT NOT NULL DEFAULT "",
            failed_count INTEGER NOT NULL DEFAULT 0,
            locked_until TEXT NULL
        );
INSERT INTO user VALUES(1,'superadmin','Superadmin Sistem','$2y$10$rUgc0CobMOBKSaVSGULz6uY7hjLVOzRT7xQMz5188fgKipBPZkkdq','superadmin',1,'2026-10-03 16:19:53',0,NULL);
INSERT INTO user VALUES(3,'delta','LISA SUSANTI','$2y$10$n.598Y3Fi51fIW5FVaeyjOXXfUvMTnfe/FExohK2tjrCqXRmFiVk6','hrd',1,'2026-10-03 16:21:04',0,NULL);
CREATE TABLE sesi (
            token_hash TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT "",
            expires_at TEXT NOT NULL DEFAULT "",
            last_seen TEXT NOT NULL DEFAULT ""
        );
INSERT INTO sesi VALUES('c8fd895c95e0a5ee92d535252b61784ebb1fec70e5d4cd2056b18ae09245a3ec',3,'2026-10-08 15:20:18','2026-10-09 01:23:52','2026-10-08 15:23:52');
CREATE TABLE divisi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL UNIQUE,
            keterangan TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1
        );
INSERT INTO divisi VALUES(1,'Manajemen','Direksi, HRD, dan administrasi pusat',1);
INSERT INTO divisi VALUES(2,'Keuangan','Akuntansi, pajak, dan kasir',1);
INSERT INTO divisi VALUES(3,'Operasional','Produksi dan gudang',1);
INSERT INTO divisi VALUES(4,'Pemasaran','Penjualan dan hubungan pelanggan',1);
INSERT INTO divisi VALUES(5,'Teknologi Informasi','Infrastruktur dan pengembangan sistem',1);
CREATE TABLE karyawan (
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
        );
INSERT INTO karyawan VALUES(1,'NSS-001','FARIS','Supervisor Produksi',3,'2023-02-24','tetap','tidak_ada','TK/0','01.234.567.8-901.000',3500000.0,0.0,0.0,'Bank Mandiri','1300099887766','faris@delta.co.id','0811-2345-678',1,'2026-09-27 23:15:42','2026-09-28 00:25:04');
INSERT INTO karyawan VALUES(2,'NSS-002','AHDAN','Designer',3,'2023-06-21','tetap','tidak_ada','TK/0','02.345.678.9-012.000',3000000.0,0.0,0.0,'Bank Mandiri','1300099887711','ahdan@delta.co.id','0812-3456-789',1,'2026-09-27 23:15:42','2026-09-28 00:26:55');
INSERT INTO karyawan VALUES(3,'NSS-003','ALAMSYAH','Designer',3,'2023-09-20','tetap','tidak_ada','TK/0','03.456.789.0-123.000',3000000.0,0.0,0.0,'Bank BCA','7640123456','alamsyah@delta.co.id','0813-4567-890',1,'2026-09-27 23:15:42','2026-09-28 00:29:38');
INSERT INTO karyawan VALUES(4,'NSS-004','VOVI','Designer',3,'2021-03-15','tetap','tidak_ada','TK/0','',2750000.0,0.0,0.0,'Bank BNI','0987654321','vovi@delta.co.id','0814-5678-901',1,'2026-09-27 23:15:42','2026-09-28 00:32:07');
INSERT INTO karyawan VALUES(5,'NSS-005','PUTRA','Designer',3,'2023-01-19','tetap','tidak_ada','TK/0','05.678.901.2-345.000',2000000.0,0.0,0.0,'Bank BRI','334455667788','putra@delta.co.id','0815-6789-012',1,'2026-09-27 23:15:42','2026-09-28 00:35:53');
INSERT INTO karyawan VALUES(6,'NSS-006','SETIAWAN','Designer',3,'2022-07-18','tetap','tidak_ada','TK/0','',2000000.0,0.0,0.0,'Bank BRI','334455661122','setiawan@delta.co.id','0816-7890-123',1,'2026-09-27 23:15:42','2026-10-03 13:19:49');
INSERT INTO karyawan VALUES(7,'NSS-007','MASKAL','Operator Produksi',3,'2023-02-06','tetap','tidak_ada','TK/0','',2000000.0,0.0,0.0,'Bank BCA','7640987654','maskal@delta.co.id','0817-8901-234',1,'2026-09-27 23:15:42','2026-09-28 00:40:40');
INSERT INTO karyawan VALUES(8,'NSS-008','RISKAL','Operator Produksi',3,'2024-01-18','tetap','tidak_ada','TK/0','06.789.012.3-456.000',2000000.0,0.0,0.0,'Bank Mandiri','1300099887999','riskal@delta.co.id','0818-9012-345',1,'2026-09-27 23:15:42','2026-09-28 00:38:54');
INSERT INTO karyawan VALUES(9,'NSS-0009','MUKHLIS','Operator Produksi',3,'2025-09-09','tetap','tidak_ada','TK/0','8878978399393',1700000.0,0.0,0.0,'BNI','787879888883','mukhlis@delta.co.id','08773999300',1,'2026-09-28 00:42:22','2026-09-28 01:23:23');
INSERT INTO karyawan VALUES(10,'NSS-010','JOKOWI','Designer',3,'2026-06-02','tetap','tidak_ada','TK/0','9829.3.22.23.2323.32',1500000.0,0.0,0.0,'BRI','82309920003302','jokowi@delta.com','087399589992',1,'2026-10-03 16:25:01','2026-10-03 16:25:01');
CREATE TABLE komponen_gaji (
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
        );
CREATE TABLE absensi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            tanggal TEXT NOT NULL,
            jam_masuk TEXT NOT NULL DEFAULT "",
            jam_pulang TEXT NOT NULL DEFAULT "",
            status TEXT NOT NULL DEFAULT "HADIR",
            menit_terlambat INTEGER NOT NULL DEFAULT 0,
            menit_lembur INTEGER NOT NULL DEFAULT 0,
            lembur_disetujui INTEGER NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT "",
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT "", menit_minggu INTEGER NOT NULL DEFAULT 0,
            UNIQUE (karyawan_id, tanggal)
        );
INSERT INTO absensi VALUES(2501,2,'2026-10-08','09:03','20:00','HADIR',63,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2502,3,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2503,1,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2504,10,'2026-10-08','','','ALPA',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2505,7,'2026-10-08','','','CUTI',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2506,9,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2507,5,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2508,8,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2509,6,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
INSERT INTO absensi VALUES(2510,4,'2026-10-08','08:00','20:00','HADIR',0,0,0,'Diisi cepat oleh delta','superadmin','2026-10-08 11:13:59','2026-10-08 15:16:30',0);
CREATE TABLE hari_libur (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal TEXT NOT NULL UNIQUE,
            keterangan TEXT NOT NULL DEFAULT "",
            jenis TEXT NOT NULL DEFAULT "libur_nasional"
        );
INSERT INTO hari_libur VALUES(1,'2026-01-01','Tahun Baru Masehi','libur_nasional');
INSERT INTO hari_libur VALUES(2,'2026-05-01','Hari Buruh Internasional','libur_nasional');
INSERT INTO hari_libur VALUES(3,'2026-06-01','Hari Lahir Pancasila','libur_nasional');
INSERT INTO hari_libur VALUES(4,'2026-08-17','Hari Kemerdekaan RI','libur_nasional');
INSERT INTO hari_libur VALUES(5,'2026-12-25','Hari Raya Natal','libur_nasional');
CREATE TABLE ptkp (
            kode TEXT PRIMARY KEY,
            keterangan TEXT NOT NULL DEFAULT "",
            setahun REAL NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            urutan INTEGER NOT NULL DEFAULT 0
        );
INSERT INTO ptkp VALUES('TK/0','Tidak kawin, 0 tanggungan',54000000.0,1,1);
INSERT INTO ptkp VALUES('TK/1','Tidak kawin, 1 tanggungan',58500000.0,1,2);
INSERT INTO ptkp VALUES('TK/2','Tidak kawin, 2 tanggungan',63000000.0,1,3);
INSERT INTO ptkp VALUES('TK/3','Tidak kawin, 3 tanggungan',67500000.0,1,4);
INSERT INTO ptkp VALUES('K/0','Kawin, 0 tanggungan',58500000.0,1,5);
INSERT INTO ptkp VALUES('K/1','Kawin, 1 tanggungan',63000000.0,1,6);
INSERT INTO ptkp VALUES('K/2','Kawin, 2 tanggungan',67500000.0,1,7);
INSERT INTO ptkp VALUES('K/3','Kawin, 3 tanggungan',72000000.0,1,8);
CREATE TABLE pph21_layers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            urutan INTEGER NOT NULL,
            batas_bawah REAL NOT NULL DEFAULT 0,
            batas_atas REAL NOT NULL DEFAULT 0,
            tarif_persen REAL NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT ""
        );
INSERT INTO pph21_layers VALUES(1,1,0.0,60000000.0,5.0,'Sampai dengan Rp60 juta');
INSERT INTO pph21_layers VALUES(2,2,60000000.0,250000000.0,15.0,'Di atas Rp60 juta s.d. Rp250 juta');
INSERT INTO pph21_layers VALUES(3,3,250000000.0,500000000.0,25.0,'Di atas Rp250 juta s.d. Rp500 juta');
INSERT INTO pph21_layers VALUES(4,4,500000000.0,5000000000.0,30.0,'Di atas Rp500 juta s.d. Rp5 miliar');
INSERT INTO pph21_layers VALUES(5,5,5000000000.0,0.0,35.0,'Di atas Rp5 miliar (batas_atas 0 = tanpa batas)');
CREATE TABLE slip_gaji (
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
            upah_per_jam REAL NOT NULL DEFAULT 0,
            total_pendapatan REAL NOT NULL DEFAULT 0,
            total_potongan REAL NOT NULL DEFAULT 0,
            take_home_pay REAL NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "DRAFT",
            catatan TEXT NOT NULL DEFAULT "",
            dibuat_oleh TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT "",
            updated_at TEXT NOT NULL DEFAULT "", hari_minggu REAL NOT NULL DEFAULT 0, upah_minggu REAL NOT NULL DEFAULT 0, total_bonus REAL NOT NULL DEFAULT 0, total_panjar REAL NOT NULL DEFAULT 0, upah_harian REAL NOT NULL DEFAULT 0, menit_minggu INTEGER NOT NULL DEFAULT 0,
            UNIQUE (karyawan_id, periode)
        );
INSERT INTO slip_gaji VALUES(57,1,'2026-09','SLIP/2026-09/0001','2026-10-03','Supervisor Produksi','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,20231.2138728319987,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,134615.384615379996,0);
INSERT INTO slip_gaji VALUES(58,2,'2026-09','SLIP/2026-09/0002','2026-10-03','Designer','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,17341.0404624280017,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,115384.615384620003,0);
INSERT INTO slip_gaji VALUES(59,3,'2026-09','SLIP/2026-09/0003','2026-10-03','Designer','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,17341.0404624280017,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,115384.615384620003,0);
INSERT INTO slip_gaji VALUES(60,4,'2026-09','SLIP/2026-09/0004','2026-10-03','Designer','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,15895.9537572249992,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,105769.230769229994,0);
INSERT INTO slip_gaji VALUES(61,5,'2026-09','SLIP/2026-09/0005','2026-10-03','Designer','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,76923.0769230769947,0);
INSERT INTO slip_gaji VALUES(62,6,'2026-09','SLIP/2026-09/0006','2026-10-03','Designer','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,76923.0769230769947,0);
INSERT INTO slip_gaji VALUES(63,7,'2026-09','SLIP/2026-09/0007','2026-10-03','Operator Produksi','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,76923.0769230769947,0);
INSERT INTO slip_gaji VALUES(64,8,'2026-09','SLIP/2026-09/0008','2026-10-03','Operator Produksi','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 00:08:20','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,76923.0769230769947,0);
INSERT INTO slip_gaji VALUES(65,9,'2026-09','SLIP/2026-09/0009','2026-10-03','Operator Produksi','Operasional','tidak_ada',26.0,0.0,26.0,0.0,0.0,0.0,0,0,9826.58959537570081,0.0,0.0,0.0,'DRAFT','','superadmin','2026-09-28 01:08:34','2026-10-03 14:25:07',0.0,0.0,0.0,0.0,65384.6153846149973,0);
INSERT INTO slip_gaji VALUES(67,3,'2026-10','SLIP/2026-10/0002','2026-10-08','Designer','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,17341.0404624280017,111111.0,0.0,111111.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,111111.111111110003,0);
INSERT INTO slip_gaji VALUES(68,1,'2026-10','SLIP/2026-10/0003','2026-10-08','Supervisor Produksi','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,20231.2138728319987,129630.0,0.0,129630.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,129629.629629629998,0);
INSERT INTO slip_gaji VALUES(69,7,'2026-10','SLIP/2026-10/0004','2026-10-08','Operator Produksi','Operasional','tidak_ada',27.0,0.0,0.0,0.0,0.0,1.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,74074.0740740740002,0);
INSERT INTO slip_gaji VALUES(70,9,'2026-10','SLIP/2026-10/0005','2026-10-08','Operator Produksi','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,9826.58959537570081,62963.0,0.0,62963.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,62962.9629629629998,0);
INSERT INTO slip_gaji VALUES(71,5,'2026-10','SLIP/2026-10/0006','2026-10-08','Designer','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,11560.6936416179996,74074.0,0.0,74074.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,74074.0740740740002,0);
INSERT INTO slip_gaji VALUES(72,8,'2026-10','SLIP/2026-10/0007','2026-10-08','Operator Produksi','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,11560.6936416179996,74074.0,0.0,74074.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,74074.0740740740002,0);
INSERT INTO slip_gaji VALUES(73,6,'2026-10','SLIP/2026-10/0008','2026-10-08','Designer','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,11560.6936416179996,74074.0,0.0,74074.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,74074.0740740740002,0);
INSERT INTO slip_gaji VALUES(74,4,'2026-10','SLIP/2026-10/0009','2026-10-08','Designer','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,0,0,15895.9537572249992,101852.0,0.0,101852.0,'DRAFT','','superadmin','2026-10-03 11:21:37','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,101851.851851850005,0);
INSERT INTO slip_gaji VALUES(75,2,'2026-08','SLIP/2026-08/0001','2026-10-03','Designer','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,17341.0404624280017,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,120000.0,0);
INSERT INTO slip_gaji VALUES(76,3,'2026-08','SLIP/2026-08/0002','2026-10-03','Designer','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,17341.0404624280017,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,120000.0,0);
INSERT INTO slip_gaji VALUES(77,1,'2026-08','SLIP/2026-08/0003','2026-10-03','Supervisor Produksi','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,20231.2138728319987,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,140000.0,0);
INSERT INTO slip_gaji VALUES(78,7,'2026-08','SLIP/2026-08/0004','2026-10-03','Operator Produksi','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,80000.0,0);
INSERT INTO slip_gaji VALUES(79,9,'2026-08','SLIP/2026-08/0005','2026-10-03','Operator Produksi','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,9826.58959537570081,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,68000.0,0);
INSERT INTO slip_gaji VALUES(80,5,'2026-08','SLIP/2026-08/0006','2026-10-03','Designer','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,80000.0,0);
INSERT INTO slip_gaji VALUES(81,8,'2026-08','SLIP/2026-08/0007','2026-10-03','Operator Produksi','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,80000.0,0);
INSERT INTO slip_gaji VALUES(82,6,'2026-08','SLIP/2026-08/0008','2026-10-03','Designer','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,11560.6936416179996,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,80000.0,0);
INSERT INTO slip_gaji VALUES(83,4,'2026-08','SLIP/2026-08/0009','2026-10-03','Designer','Operasional','tidak_ada',25.0,0.0,25.0,0.0,0.0,0.0,0,0,15895.9537572249992,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 14:25:24','2026-10-03 14:25:24',0.0,0.0,0.0,0.0,110000.0,0);
INSERT INTO slip_gaji VALUES(84,2,'2026-10','SLIP/2026-10/0010','2026-10-08','Designer','Operasional','tidak_ada',27.0,1.0,0.0,0.0,0.0,0.0,63,0,17341.0404624280017,111111.0,13250.0,97861.0,'DRAFT','','superadmin','2026-10-03 14:37:01','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,111111.111111110003,0);
INSERT INTO slip_gaji VALUES(85,10,'2026-10','SLIP/2026-10/0011','2026-10-08','Designer','Operasional','tidak_ada',27.0,0.0,1.0,0.0,0.0,0.0,0,0,8670.52023121390084,0.0,0.0,0.0,'DRAFT','','superadmin','2026-10-03 16:25:35','2026-10-08 15:16:37',0.0,0.0,0.0,0.0,55555.5555555559985,0);
CREATE TABLE slip_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slip_id INTEGER NOT NULL REFERENCES slip_gaji(id) ON DELETE CASCADE,
            jenis TEXT NOT NULL,
            kategori TEXT NOT NULL DEFAULT "",
            nama TEXT NOT NULL,
            nilai REAL NOT NULL DEFAULT 0,
            sumber TEXT NOT NULL DEFAULT "",
            urutan INTEGER NOT NULL DEFAULT 0
        );
INSERT INTO slip_item VALUES(5859,58,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 115.385)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5860,59,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 115.385)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5861,57,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 134.615)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5862,63,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 76.923)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5863,65,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 65.385)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5864,61,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 76.923)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5865,64,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 76.923)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5866,62,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 76.923)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5867,60,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 105.769)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5868,75,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 120.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5869,76,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 120.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5870,77,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 140.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5871,78,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 80.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5872,79,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 68.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5873,80,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 80.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5874,81,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 80.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5875,82,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 80.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(5876,83,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 110.000)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6077,84,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 111.111)',111111.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6078,84,'POTONGAN','Absensi','Denda Keterlambatan',13250.0,'absensi',1);
INSERT INTO slip_item VALUES(6079,67,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 111.111)',111111.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6080,68,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 129.630)',129630.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6081,85,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 55.556)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6082,69,'PENDAPATAN','Tetap','Upah Harian (0 hari × Rp 74.074)',0.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6083,70,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 62.963)',62963.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6084,71,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 74.074)',74074.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6085,72,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 74.074)',74074.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6086,73,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 74.074)',74074.0,'master_karyawan',1);
INSERT INTO slip_item VALUES(6087,74,'PENDAPATAN','Tetap','Upah Harian (1 hari × Rp 101.852)',101852.0,'master_karyawan',1);
CREATE TABLE log_admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            waktu TEXT NOT NULL DEFAULT "",
            oleh TEXT NOT NULL DEFAULT "",
            aksi TEXT NOT NULL DEFAULT "",
            rincian TEXT NOT NULL DEFAULT ""
        );
INSERT INTO log_admin VALUES(1,'2026-09-27 23:16:42','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(2,'2026-09-27 23:16:43','superadmin','konfigurasi_simpan','Absensi — 17 setelan');
INSERT INTO log_admin VALUES(3,'2026-09-27 23:16:47','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(4,'2026-09-27 23:16:47','superadmin','konfigurasi_simpan','Absensi — 17 setelan');
INSERT INTO log_admin VALUES(5,'2026-09-27 23:17:43','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(6,'2026-09-27 23:17:44','superadmin','konfigurasi_simpan','Absensi — 17 setelan');
INSERT INTO log_admin VALUES(7,'2026-09-27 23:25:54','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(8,'2026-09-28 00:07:15','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(9,'2026-09-28 00:07:16','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(10,'2026-09-28 00:07:21','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(11,'2026-09-28 00:07:21','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(12,'2026-09-28 00:07:25','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(13,'2026-09-28 00:07:38','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(14,'2026-09-28 00:08:20','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(15,'2026-09-28 00:08:21','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(16,'2026-09-28 00:09:59','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(17,'2026-09-28 00:10:00','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(18,'2026-09-28 00:10:00','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(19,'2026-09-28 00:10:11','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(20,'2026-09-28 00:10:18','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(21,'2026-09-28 00:14:36','superadmin','absensi_harian','Simpan absensi 2026-09-28 (8 baris)');
INSERT INTO log_admin VALUES(22,'2026-09-28 00:15:31','superadmin','absensi_harian','Simpan absensi 2026-09-28 (8 baris)');
INSERT INTO log_admin VALUES(23,'2026-09-28 00:16:40','superadmin','absensi_harian','Simpan absensi 2026-09-28 (8 baris)');
INSERT INTO log_admin VALUES(24,'2026-09-28 00:25:04','superadmin','karyawan_simpan','Ubah karyawan #1 (FARIS)');
INSERT INTO log_admin VALUES(25,'2026-09-28 00:26:55','superadmin','karyawan_simpan','Ubah karyawan #2 (AHDAN)');
INSERT INTO log_admin VALUES(26,'2026-09-28 00:29:38','superadmin','karyawan_simpan','Ubah karyawan #3 (ALAMSYAH)');
INSERT INTO log_admin VALUES(27,'2026-09-28 00:32:07','superadmin','karyawan_simpan','Ubah karyawan #4 (VOVI)');
INSERT INTO log_admin VALUES(28,'2026-09-28 00:33:19','superadmin','panjar_simpan','Panjar karyawan #4 sebesar 200000');
INSERT INTO log_admin VALUES(29,'2026-09-28 00:35:53','superadmin','karyawan_simpan','Ubah karyawan #5 (PUTRA)');
INSERT INTO log_admin VALUES(30,'2026-09-28 00:37:22','superadmin','karyawan_simpan','Ubah karyawan #6 (SETIAWAN)');
INSERT INTO log_admin VALUES(31,'2026-09-28 00:38:54','superadmin','karyawan_simpan','Ubah karyawan #8 (RISKAL)');
INSERT INTO log_admin VALUES(32,'2026-09-28 00:40:40','superadmin','karyawan_simpan','Ubah karyawan #7 (MASKAL)');
INSERT INTO log_admin VALUES(33,'2026-09-28 00:42:22','superadmin','karyawan_simpan','Tambah karyawan #9 (MUKHLIS)');
INSERT INTO log_admin VALUES(34,'2026-09-28 00:44:35','superadmin','konfigurasi_simpan','Umum — 8 setelan');
INSERT INTO log_admin VALUES(35,'2026-09-28 00:47:25','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(36,'2026-09-28 00:48:34','superadmin','konfigurasi_simpan','Lembur — 7 setelan');
INSERT INTO log_admin VALUES(37,'2026-09-28 00:49:43','superadmin','konfigurasi_simpan','Minggu — 7 setelan');
INSERT INTO log_admin VALUES(38,'2026-09-28 00:52:05','superadmin','bonus_simpan','Simpan bonus "Bonus Target"');
INSERT INTO log_admin VALUES(39,'2026-09-28 00:52:56','superadmin','bonus_simpan','Simpan bonus "Bonus Produksi"');
INSERT INTO log_admin VALUES(40,'2026-09-28 00:53:18','superadmin','bonus_simpan','Simpan bonus "Bonus Target"');
INSERT INTO log_admin VALUES(41,'2026-09-28 00:54:47','superadmin','bonus_simpan','Simpan bonus "Bonus Kehadiran"');
INSERT INTO log_admin VALUES(42,'2026-09-28 00:55:32','superadmin','bonus_simpan','Simpan bonus "Bonus SOP"');
INSERT INTO log_admin VALUES(43,'2026-09-28 00:55:44','superadmin','bonus_simpan','Simpan bonus "Bonus SOP"');
INSERT INTO log_admin VALUES(44,'2026-09-28 00:56:01','superadmin','bonus_simpan','Simpan bonus "Bonus Kreativitas & Inovasi"');
INSERT INTO log_admin VALUES(45,'2026-09-28 00:56:18','superadmin','bonus_simpan','Simpan bonus "Bonus Kualitas / Zero Error"');
INSERT INTO log_admin VALUES(46,'2026-09-28 00:57:15','superadmin','bonus_simpan','Simpan bonus "Bonus Insentif Minggu"');
INSERT INTO log_admin VALUES(47,'2026-09-28 00:57:28','superadmin','bonus_simpan','Simpan bonus "Bonus Insentif Minggu"');
INSERT INTO log_admin VALUES(48,'2026-09-28 00:58:10','superadmin','potongan_simpan','Menyimpan saklar potongan');
INSERT INTO log_admin VALUES(49,'2026-09-28 00:59:05','superadmin','konfigurasi_simpan','Pajak — 11 setelan');
INSERT INTO log_admin VALUES(50,'2026-09-28 01:00:34','superadmin','konfigurasi_simpan','Slip — 6 setelan');
INSERT INTO log_admin VALUES(51,'2026-09-28 01:01:04','superadmin','konfigurasi_simpan','Ttd — 4 setelan');
INSERT INTO log_admin VALUES(52,'2026-09-28 01:03:24','superadmin','konfigurasi_simpan','Perusahaan — 9 setelan');
INSERT INTO log_admin VALUES(53,'2026-09-28 01:05:00','superadmin','logo_unggah','Salinan_Locksmithmachine_2.png');
INSERT INTO log_admin VALUES(54,'2026-09-28 01:06:49','superadmin','ttd_unggah','IMG_20260928_010631.png');
INSERT INTO log_admin VALUES(55,'2026-09-28 01:08:24','superadmin','slip_hitung','Karyawan #2 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(56,'2026-09-28 01:08:26','superadmin','slip_hitung','Karyawan #3 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(57,'2026-09-28 01:08:28','superadmin','slip_hitung','Karyawan #1 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(58,'2026-09-28 01:08:30','superadmin','slip_hitung','Karyawan #7 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(59,'2026-09-28 01:08:34','superadmin','slip_hitung','Karyawan #9 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(60,'2026-09-28 01:08:41','superadmin','slip_hitung','Karyawan #5 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(61,'2026-09-28 01:08:45','superadmin','slip_hitung','Karyawan #8 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(62,'2026-09-28 01:08:48','superadmin','slip_hitung','Karyawan #6 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(63,'2026-09-28 01:08:56','superadmin','slip_hitung','Karyawan #4 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(64,'2026-09-28 01:10:36','superadmin','slip_hitung','Karyawan #9 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(65,'2026-09-28 01:11:37','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(66,'2026-09-28 01:11:43','superadmin','slip_hitung','Karyawan #9 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(67,'2026-09-28 01:13:51','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(68,'2026-09-28 01:14:23','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(69,'2026-09-28 01:14:29','superadmin','slip_hitung','Karyawan #2 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(70,'2026-09-28 01:14:34','superadmin','slip_hitung','Karyawan #4 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(71,'2026-09-28 01:14:40','superadmin','slip_hitung','Karyawan #9 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(72,'2026-09-28 01:14:48','superadmin','slip_hitung','Karyawan #5 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(73,'2026-09-28 01:14:50','superadmin','slip_hitung','Karyawan #2 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(74,'2026-09-28 01:14:51','superadmin','slip_hitung','Karyawan #3 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(75,'2026-09-28 01:14:53','superadmin','slip_hitung','Karyawan #1 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(76,'2026-09-28 01:14:55','superadmin','slip_hitung','Karyawan #7 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(77,'2026-09-28 01:14:58','superadmin','slip_hitung','Karyawan #9 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(78,'2026-09-28 01:15:00','superadmin','slip_hitung','Karyawan #5 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(79,'2026-09-28 01:17:00','superadmin','panjar_simpan','Panjar karyawan #2 sebesar 300000');
INSERT INTO log_admin VALUES(80,'2026-09-28 01:17:13','superadmin','slip_hitung','Karyawan #2 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(81,'2026-09-28 01:17:53','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(82,'2026-09-28 01:21:47','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(83,'2026-09-28 01:23:23','superadmin','karyawan_simpan','Ubah karyawan #9 (MUKHLIS)');
INSERT INTO log_admin VALUES(84,'2026-09-28 01:29:00','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(85,'2026-09-28 01:37:51','superadmin','slip_hitung','Karyawan #5 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(86,'2026-09-28 01:38:19','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(87,'2026-09-28 01:38:59','superadmin','slip_hitung','Karyawan #5 periode 2026-09 → DRAFT');
INSERT INTO log_admin VALUES(88,'2026-09-28 01:40:44','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(89,'2026-09-28 01:40:45','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(90,'2026-09-28 01:40:49','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(91,'2026-09-28 01:40:50','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(92,'2026-09-28 01:40:56','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(93,'2026-09-28 01:41:03','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(94,'2026-09-28 01:41:07','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(95,'2026-09-28 01:41:43','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(96,'2026-09-28 01:41:44','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(97,'2026-09-28 01:41:49','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(98,'2026-09-28 01:42:03','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(99,'2026-09-28 01:42:03','superadmin','data_uji_bersih','Hapus 421 baris absensi uji & 8 slip uji (baris uji yang sudah diubah dipertahankan)');
INSERT INTO log_admin VALUES(100,'2026-09-28 01:42:07','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(101,'2026-09-28 01:42:14','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(102,'2026-09-28 01:42:58','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(103,'2026-09-28 01:42:58','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(104,'2026-09-28 01:42:59','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(105,'2026-09-28 01:47:41','superadmin','absensi_harian','Simpan absensi 2026-09-30 (9 baris)');
INSERT INTO log_admin VALUES(106,'2026-09-28 01:49:47','superadmin','absensi_hapus_rentang','Hapus 18 baris absensi 2026-09-01 s/d 2026-09-30');
INSERT INTO log_admin VALUES(107,'2026-09-28 01:50:47','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-28 (9 karyawan)');
INSERT INTO log_admin VALUES(108,'2026-09-28 01:52:26','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(109,'2026-09-28 01:52:52','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(110,'2026-09-28 01:54:34','superadmin','panjar_hapus','Hapus panjar #5');
INSERT INTO log_admin VALUES(111,'2026-09-28 01:55:03','superadmin','panjar_hapus','Hapus panjar #1');
INSERT INTO log_admin VALUES(112,'2026-09-28 01:55:40','superadmin','panjar_hapus','Hapus panjar #3');
INSERT INTO log_admin VALUES(113,'2026-09-28 01:56:41','superadmin','panjar_hapus','Hapus panjar #2');
INSERT INTO log_admin VALUES(114,'2026-09-28 01:56:50','superadmin','panjar_hapus','Hapus panjar #4');
INSERT INTO log_admin VALUES(115,'2026-09-28 01:57:03','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(116,'2026-09-28 05:25:10','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(117,'2026-09-28 05:27:34','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-01 (9 karyawan)');
INSERT INTO log_admin VALUES(118,'2026-09-28 05:27:41','superadmin','absensi_harian','Simpan absensi 2026-09-01 (9 baris)');
INSERT INTO log_admin VALUES(119,'2026-09-28 05:27:53','superadmin','absensi_harian','Simpan absensi 2026-09-02 (0 baris)');
INSERT INTO log_admin VALUES(120,'2026-09-28 05:28:00','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-02 (9 karyawan)');
INSERT INTO log_admin VALUES(121,'2026-09-28 05:28:02','superadmin','absensi_harian','Simpan absensi 2026-09-02 (9 baris)');
INSERT INTO log_admin VALUES(122,'2026-09-28 05:28:15','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(123,'2026-09-28 05:28:49','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-03 (9 karyawan)');
INSERT INTO log_admin VALUES(124,'2026-09-28 05:28:51','superadmin','absensi_harian','Simpan absensi 2026-09-03 (9 baris)');
INSERT INTO log_admin VALUES(125,'2026-09-28 05:28:56','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-04 (9 karyawan)');
INSERT INTO log_admin VALUES(126,'2026-09-28 05:28:59','superadmin','absensi_harian','Simpan absensi 2026-09-04 (9 baris)');
INSERT INTO log_admin VALUES(127,'2026-09-28 05:29:04','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-05 (9 karyawan)');
INSERT INTO log_admin VALUES(128,'2026-09-28 05:29:06','superadmin','absensi_harian','Simpan absensi 2026-09-05 (9 baris)');
INSERT INTO log_admin VALUES(129,'2026-09-28 05:29:10','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-06 (9 karyawan)');
INSERT INTO log_admin VALUES(130,'2026-09-28 05:29:25','superadmin','absensi_harian','Simpan absensi 2026-09-06 (9 baris)');
INSERT INTO log_admin VALUES(131,'2026-09-28 05:29:35','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(132,'2026-09-28 05:29:36','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(133,'2026-09-28 05:30:42','superadmin','absensi_harian','Simpan absensi 2026-09-06 (9 baris)');
INSERT INTO log_admin VALUES(134,'2026-09-28 05:30:46','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(135,'2026-09-28 05:35:15','superadmin','konfigurasi_simpan','Minggu — 7 setelan');
INSERT INTO log_admin VALUES(136,'2026-09-28 05:35:46','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(137,'2026-09-28 05:37:03','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-07 (9 karyawan)');
INSERT INTO log_admin VALUES(138,'2026-09-28 05:37:05','superadmin','absensi_harian','Simpan absensi 2026-09-07 (9 baris)');
INSERT INTO log_admin VALUES(139,'2026-09-28 05:37:14','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-08 (9 karyawan)');
INSERT INTO log_admin VALUES(140,'2026-09-28 05:37:15','superadmin','absensi_harian','Simpan absensi 2026-09-08 (9 baris)');
INSERT INTO log_admin VALUES(141,'2026-09-28 05:37:22','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-09 (9 karyawan)');
INSERT INTO log_admin VALUES(142,'2026-09-28 05:37:24','superadmin','absensi_harian','Simpan absensi 2026-09-09 (9 baris)');
INSERT INTO log_admin VALUES(143,'2026-09-28 05:37:32','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-10 (9 karyawan)');
INSERT INTO log_admin VALUES(144,'2026-09-28 05:37:34','superadmin','absensi_harian','Simpan absensi 2026-09-10 (9 baris)');
INSERT INTO log_admin VALUES(145,'2026-09-28 05:37:39','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-11 (9 karyawan)');
INSERT INTO log_admin VALUES(146,'2026-09-28 05:37:41','superadmin','absensi_harian','Simpan absensi 2026-09-11 (9 baris)');
INSERT INTO log_admin VALUES(147,'2026-09-28 05:37:45','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-12 (9 karyawan)');
INSERT INTO log_admin VALUES(148,'2026-09-28 05:37:47','superadmin','absensi_harian','Simpan absensi 2026-09-12 (9 baris)');
INSERT INTO log_admin VALUES(149,'2026-09-28 05:37:57','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-13 (9 karyawan)');
INSERT INTO log_admin VALUES(150,'2026-09-28 05:37:59','superadmin','absensi_harian','Simpan absensi 2026-09-13 (9 baris)');
INSERT INTO log_admin VALUES(151,'2026-09-28 05:38:07','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(152,'2026-09-28 05:39:18','superadmin','absensi_harian','Simpan absensi 2026-09-13 (9 baris)');
INSERT INTO log_admin VALUES(153,'2026-09-28 05:39:22','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(154,'2026-09-28 05:40:43','superadmin','absensi_harian','Simpan absensi 2026-09-13 (9 baris)');
INSERT INTO log_admin VALUES(155,'2026-09-28 05:40:51','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(156,'2026-09-28 05:41:21','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(157,'2026-09-28 05:43:40','superadmin','absensi_harian','Simpan absensi 2026-09-13 (9 baris)');
INSERT INTO log_admin VALUES(158,'2026-09-28 05:43:45','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(159,'2026-09-28 05:44:51','superadmin','absensi_harian','Simpan absensi 2026-09-13 (9 baris)');
INSERT INTO log_admin VALUES(160,'2026-09-28 05:44:54','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(161,'2026-09-28 05:46:05','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-14 (9 karyawan)');
INSERT INTO log_admin VALUES(162,'2026-09-28 05:46:08','superadmin','absensi_harian','Simpan absensi 2026-09-14 (9 baris)');
INSERT INTO log_admin VALUES(163,'2026-09-28 05:46:13','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-15 (9 karyawan)');
INSERT INTO log_admin VALUES(164,'2026-09-28 05:46:15','superadmin','absensi_harian','Simpan absensi 2026-09-15 (9 baris)');
INSERT INTO log_admin VALUES(165,'2026-09-28 05:46:22','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-16 (9 karyawan)');
INSERT INTO log_admin VALUES(166,'2026-09-28 05:46:23','superadmin','absensi_harian','Simpan absensi 2026-09-16 (9 baris)');
INSERT INTO log_admin VALUES(167,'2026-09-28 05:46:28','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-17 (9 karyawan)');
INSERT INTO log_admin VALUES(168,'2026-09-28 05:46:29','superadmin','absensi_harian','Simpan absensi 2026-09-17 (9 baris)');
INSERT INTO log_admin VALUES(169,'2026-09-28 05:46:38','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-18 (9 karyawan)');
INSERT INTO log_admin VALUES(170,'2026-09-28 05:46:41','superadmin','absensi_harian','Simpan absensi 2026-09-18 (9 baris)');
INSERT INTO log_admin VALUES(171,'2026-09-28 05:46:45','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-19 (9 karyawan)');
INSERT INTO log_admin VALUES(172,'2026-09-28 05:46:47','superadmin','absensi_harian','Simpan absensi 2026-09-19 (9 baris)');
INSERT INTO log_admin VALUES(173,'2026-09-28 05:46:59','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-21 (9 karyawan)');
INSERT INTO log_admin VALUES(174,'2026-09-28 05:47:01','superadmin','absensi_harian','Simpan absensi 2026-09-21 (9 baris)');
INSERT INTO log_admin VALUES(175,'2026-09-28 05:47:06','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-22 (9 karyawan)');
INSERT INTO log_admin VALUES(176,'2026-09-28 05:47:08','superadmin','absensi_harian','Simpan absensi 2026-09-22 (9 baris)');
INSERT INTO log_admin VALUES(177,'2026-09-28 05:47:12','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-23 (9 karyawan)');
INSERT INTO log_admin VALUES(178,'2026-09-28 05:47:14','superadmin','absensi_harian','Simpan absensi 2026-09-23 (9 baris)');
INSERT INTO log_admin VALUES(179,'2026-09-28 05:47:18','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-24 (9 karyawan)');
INSERT INTO log_admin VALUES(180,'2026-09-28 05:47:20','superadmin','absensi_harian','Simpan absensi 2026-09-24 (9 baris)');
INSERT INTO log_admin VALUES(181,'2026-09-28 05:47:24','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-25 (9 karyawan)');
INSERT INTO log_admin VALUES(182,'2026-09-28 05:47:25','superadmin','absensi_harian','Simpan absensi 2026-09-25 (9 baris)');
INSERT INTO log_admin VALUES(183,'2026-09-28 05:47:32','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-26 (9 karyawan)');
INSERT INTO log_admin VALUES(184,'2026-09-28 05:47:33','superadmin','absensi_harian','Simpan absensi 2026-09-26 (9 baris)');
INSERT INTO log_admin VALUES(185,'2026-09-28 05:48:01','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-29 (9 karyawan)');
INSERT INTO log_admin VALUES(186,'2026-09-28 05:48:03','superadmin','absensi_harian','Simpan absensi 2026-09-29 (9 baris)');
INSERT INTO log_admin VALUES(187,'2026-09-28 05:48:08','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(188,'2026-09-28 05:48:10','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(189,'2026-09-28 05:48:12','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(190,'2026-09-28 05:50:00','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(191,'2026-09-28 05:51:23','superadmin','absensi_cepat','Isi cepat Hadir 2026-09-30 (9 karyawan)');
INSERT INTO log_admin VALUES(192,'2026-09-28 05:51:25','superadmin','absensi_harian','Simpan absensi 2026-09-30 (9 baris)');
INSERT INTO log_admin VALUES(193,'2026-09-28 05:51:36','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(194,'2026-09-28 05:52:51','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(195,'2026-09-28 06:00:53','superadmin','konfigurasi_simpan','Minggu — 7 setelan');
INSERT INTO log_admin VALUES(196,'2026-09-28 06:00:59','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(197,'2026-09-28 06:02:58','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(198,'2026-09-28 06:03:03','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(199,'2026-09-28 06:06:03','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(200,'2026-09-28 06:07:23','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(201,'2026-09-28 06:09:56','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(202,'2026-09-28 06:10:01','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(203,'2026-09-28 06:11:00','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(204,'2026-09-28 06:11:18','superadmin','slip_finalkan_semua','Periode 2026-09, 9 slip → FINAL');
INSERT INTO log_admin VALUES(205,'2026-09-28 06:15:29','superadmin','panjar_simpan','Panjar karyawan #6 sebesar 200000');
INSERT INTO log_admin VALUES(206,'2026-09-28 06:16:14','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(207,'2026-09-28 10:18:27','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(208,'2026-09-28 10:44:33','superadmin','gambar_hapus','logo_url');
INSERT INTO log_admin VALUES(209,'2026-09-28 10:44:42','superadmin','logo_unggah','ChatGPT_Image_28_Sep_2026__10.44.22.png');
INSERT INTO log_admin VALUES(210,'2026-09-28 10:46:30','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(211,'2026-09-28 11:50:07','superadmin','konfigurasi_simpan','Absensi — 15 setelan');
INSERT INTO log_admin VALUES(212,'2026-09-28 11:50:16','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(213,'2026-09-28 11:50:17','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(214,'2026-09-28 11:51:05','superadmin','absensi_harian','Simpan absensi 2026-09-28 (9 baris)');
INSERT INTO log_admin VALUES(215,'2026-09-28 11:51:09','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(216,'2026-09-28 12:21:23','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(217,'2026-10-03 10:45:54','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(218,'2026-10-03 11:14:29','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(219,'2026-10-03 11:15:04','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(220,'2026-10-03 11:15:41','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(221,'2026-10-03 11:15:42','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(222,'2026-10-03 11:15:51','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(223,'2026-10-03 11:16:02','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(224,'2026-10-03 11:16:09','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(225,'2026-10-03 11:17:10','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(226,'2026-10-03 11:17:11','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(227,'2026-10-03 11:21:37','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(228,'2026-10-03 11:24:15','superadmin','absensi_harian','Simpan absensi 2026-10-03 (9 baris)');
INSERT INTO log_admin VALUES(229,'2026-10-03 11:24:42','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(230,'2026-10-03 11:25:30','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(231,'2026-10-03 11:31:24','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(232,'2026-10-03 11:31:25','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(233,'2026-10-03 11:31:30','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(234,'2026-10-03 11:31:31','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(235,'2026-10-03 11:31:42','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(236,'2026-10-03 11:31:43','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(237,'2026-10-03 11:31:50','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(238,'2026-10-03 11:32:30','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(239,'2026-10-03 11:32:30','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(240,'2026-10-03 11:32:40','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(241,'2026-10-03 11:32:45','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(242,'2026-10-03 11:57:07','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(243,'2026-10-03 11:57:09','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(244,'2026-10-03 12:00:33','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(245,'2026-10-03 12:00:36','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(246,'2026-10-03 12:02:32','superadmin','bonus_karyawan','Pengaturan bonus karyawan #7');
INSERT INTO log_admin VALUES(247,'2026-10-03 12:03:05','superadmin','panjar_simpan','Panjar karyawan #7 sebesar 300000');
INSERT INTO log_admin VALUES(248,'2026-10-03 12:03:33','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(249,'2026-10-03 12:04:52','superadmin','panjar_hapus','Hapus panjar #6');
INSERT INTO log_admin VALUES(250,'2026-10-03 12:04:59','superadmin','bonus_karyawan','Pengaturan bonus karyawan #6');
INSERT INTO log_admin VALUES(251,'2026-10-03 12:05:08','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(252,'2026-10-03 12:07:32','superadmin','konfigurasi_simpan','Upah — 2 setelan');
INSERT INTO log_admin VALUES(253,'2026-10-03 12:07:42','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(254,'2026-10-03 12:10:50','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(255,'2026-10-03 12:13:58','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(256,'2026-10-03 12:15:55','superadmin','slip_hitung','Karyawan #2 periode 2026-10 → DRAFT');
INSERT INTO log_admin VALUES(257,'2026-10-03 12:17:21','superadmin','absensi_harian','Simpan absensi 2026-10-01 (1 baris)');
INSERT INTO log_admin VALUES(258,'2026-10-03 12:17:25','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-01 (9 karyawan)');
INSERT INTO log_admin VALUES(259,'2026-10-03 12:17:33','superadmin','absensi_harian','Simpan absensi 2026-10-01 (9 baris)');
INSERT INTO log_admin VALUES(260,'2026-10-03 12:17:40','superadmin','absensi_harian','Simpan absensi 2026-10-02 (0 baris)');
INSERT INTO log_admin VALUES(261,'2026-10-03 12:17:43','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-02 (9 karyawan)');
INSERT INTO log_admin VALUES(262,'2026-10-03 12:17:45','superadmin','absensi_harian','Simpan absensi 2026-10-02 (9 baris)');
INSERT INTO log_admin VALUES(263,'2026-10-03 12:18:38','superadmin','absensi_harian','Simpan absensi 2026-10-04 (9 baris)');
INSERT INTO log_admin VALUES(264,'2026-10-03 12:18:50','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(265,'2026-10-03 12:19:16','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(266,'2026-10-03 12:25:14','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-05 (9 karyawan)');
INSERT INTO log_admin VALUES(267,'2026-10-03 12:25:22','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-06 (9 karyawan)');
INSERT INTO log_admin VALUES(268,'2026-10-03 12:25:25','superadmin','absensi_harian','Simpan absensi 2026-10-06 (9 baris)');
INSERT INTO log_admin VALUES(269,'2026-10-03 12:25:31','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(270,'2026-10-03 12:26:27','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-07 (9 karyawan)');
INSERT INTO log_admin VALUES(271,'2026-10-03 12:26:29','superadmin','absensi_harian','Simpan absensi 2026-10-07 (9 baris)');
INSERT INTO log_admin VALUES(272,'2026-10-03 12:26:34','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-08 (9 karyawan)');
INSERT INTO log_admin VALUES(273,'2026-10-03 12:26:36','superadmin','absensi_harian','Simpan absensi 2026-10-08 (9 baris)');
INSERT INTO log_admin VALUES(274,'2026-10-03 12:26:41','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-09 (9 karyawan)');
INSERT INTO log_admin VALUES(275,'2026-10-03 12:26:44','superadmin','absensi_harian','Simpan absensi 2026-10-09 (9 baris)');
INSERT INTO log_admin VALUES(276,'2026-10-03 12:26:48','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-10 (9 karyawan)');
INSERT INTO log_admin VALUES(277,'2026-10-03 12:26:50','superadmin','absensi_harian','Simpan absensi 2026-10-10 (9 baris)');
INSERT INTO log_admin VALUES(278,'2026-10-03 12:26:53','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(279,'2026-10-03 12:28:26','superadmin','absensi_cepat','Isi cepat Hadir 2026-10-11 (9 karyawan)');
INSERT INTO log_admin VALUES(280,'2026-10-03 12:30:17','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(281,'2026-10-03 12:30:52','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(282,'2026-10-03 12:30:56','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(283,'2026-10-03 12:31:56','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(284,'2026-10-03 12:32:04','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(285,'2026-10-03 12:32:52','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(286,'2026-10-03 12:32:58','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(287,'2026-10-03 12:35:12','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(288,'2026-10-03 12:35:34','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(289,'2026-10-03 12:36:29','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(290,'2026-10-03 12:37:00','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(291,'2026-10-03 12:38:35','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(292,'2026-10-03 12:38:48','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(293,'2026-10-03 12:41:02','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(294,'2026-10-03 12:42:38','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(295,'2026-10-03 12:42:43','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(296,'2026-10-03 12:42:45','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(297,'2026-10-03 13:07:11','superadmin','absensi_harian','Simpan absensi 2026-10-11 (9 baris)');
INSERT INTO log_admin VALUES(298,'2026-10-03 13:07:20','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(299,'2026-10-03 13:08:20','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(300,'2026-10-03 13:08:25','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(301,'2026-10-03 13:10:09','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(302,'2026-10-03 13:10:15','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(303,'2026-10-03 13:11:59','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(304,'2026-10-03 13:13:06','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(305,'2026-10-03 13:14:51','superadmin','absensi_hapus_bulan','Hapus seluruh absensi periode 2026-10 (99 baris)');
INSERT INTO log_admin VALUES(306,'2026-10-03 13:15:34','superadmin','bonus_karyawan','Pengaturan bonus karyawan #2');
INSERT INTO log_admin VALUES(307,'2026-10-03 13:15:42','superadmin','bonus_karyawan','Pengaturan bonus karyawan #3');
INSERT INTO log_admin VALUES(308,'2026-10-03 13:15:51','superadmin','bonus_karyawan','Pengaturan bonus karyawan #1');
INSERT INTO log_admin VALUES(309,'2026-10-03 13:15:59','superadmin','panjar_hapus','Hapus panjar #7');
INSERT INTO log_admin VALUES(310,'2026-10-03 13:16:05','superadmin','bonus_karyawan','Pengaturan bonus karyawan #7');
INSERT INTO log_admin VALUES(311,'2026-10-03 13:16:15','superadmin','bonus_karyawan','Pengaturan bonus karyawan #7');
INSERT INTO log_admin VALUES(312,'2026-10-03 13:16:48','superadmin','bonus_karyawan','Pengaturan bonus karyawan #5');
INSERT INTO log_admin VALUES(313,'2026-10-03 13:17:01','superadmin','bonus_karyawan','Pengaturan bonus karyawan #8');
INSERT INTO log_admin VALUES(314,'2026-10-03 13:17:14','superadmin','bonus_karyawan','Pengaturan bonus karyawan #6');
INSERT INTO log_admin VALUES(315,'2026-10-03 13:17:25','superadmin','bonus_karyawan','Pengaturan bonus karyawan #4');
INSERT INTO log_admin VALUES(316,'2026-10-03 13:17:51','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(317,'2026-10-03 13:18:14','superadmin','bonus_karyawan','Pengaturan bonus karyawan #9');
INSERT INTO log_admin VALUES(318,'2026-10-03 13:18:16','superadmin','bonus_karyawan','Pengaturan bonus karyawan #9');
INSERT INTO log_admin VALUES(319,'2026-10-03 13:18:54','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(320,'2026-10-03 13:19:49','superadmin','karyawan_simpan','Ubah karyawan #6 (SETIAWAN)');
INSERT INTO log_admin VALUES(321,'2026-10-03 13:19:52','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(322,'2026-10-03 13:20:29','superadmin','komponen_hapus','Hapus komponen gaji #3');
INSERT INTO log_admin VALUES(323,'2026-10-03 13:20:39','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(324,'2026-10-03 13:40:16','superadmin','absensi_hapus_bulan','Hapus seluruh absensi periode 2026-09 (252 baris)');
INSERT INTO log_admin VALUES(325,'2026-10-03 13:57:08','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(326,'2026-10-03 14:07:57','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(327,'2026-10-03 14:08:15','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(328,'2026-10-03 14:08:16','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(329,'2026-10-03 14:24:26','superadmin','absensi_hapus_bulan','Hapus seluruh absensi periode 2026-09 (0 baris)');
INSERT INTO log_admin VALUES(330,'2026-10-03 14:25:02','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(331,'2026-10-03 14:25:07','superadmin','slip_hitung_semua','Periode 2026-09, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(332,'2026-10-03 14:25:24','superadmin','slip_hitung_semua','Periode 2026-08, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(333,'2026-10-03 14:31:58','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(334,'2026-10-03 14:33:43','superadmin','bonus_karyawan','Pengaturan bonus karyawan #2');
INSERT INTO log_admin VALUES(335,'2026-10-03 14:34:40','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(336,'2026-10-03 14:34:43','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(337,'2026-10-03 14:34:55','superadmin','bonus_karyawan','Pengaturan bonus karyawan #2');
INSERT INTO log_admin VALUES(338,'2026-10-03 14:35:00','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(339,'2026-10-03 14:36:56','superadmin','slip_hapus','Hapus slip #66 periode 2026-10');
INSERT INTO log_admin VALUES(340,'2026-10-03 14:37:01','superadmin','slip_hitung','Karyawan #2 periode 2026-10 → DRAFT');
INSERT INTO log_admin VALUES(341,'2026-10-03 14:37:18','superadmin','bonus_karyawan','Pengaturan bonus karyawan #2');
INSERT INTO log_admin VALUES(342,'2026-10-03 14:37:23','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(343,'2026-10-03 15:46:58','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(344,'2026-10-03 15:55:14','superadmin','konfigurasi_simpan','Minggu — 9 setelan');
INSERT INTO log_admin VALUES(345,'2026-10-03 15:55:45','superadmin','konfigurasi_simpan','Lembur — 9 setelan');
INSERT INTO log_admin VALUES(346,'2026-10-03 15:56:20','superadmin','konfigurasi_simpan','Lembur — 9 setelan');
INSERT INTO log_admin VALUES(347,'2026-10-03 15:58:57','superadmin','absensi_harian','Simpan absensi 2026-10-04 (1 baris)');
INSERT INTO log_admin VALUES(348,'2026-10-03 15:59:04','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(349,'2026-10-03 16:00:57','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(350,'2026-10-03 16:01:05','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(351,'2026-10-03 16:02:19','superadmin','absensi_harian','Simpan absensi 2026-10-04 (1 baris)');
INSERT INTO log_admin VALUES(352,'2026-10-03 16:02:26','superadmin','absensi_harian','Simpan absensi 2026-10-04 (0 baris)');
INSERT INTO log_admin VALUES(353,'2026-10-03 16:02:31','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(354,'2026-10-03 16:12:29','superadmin','potongan_simpan','Menyimpan saklar potongan');
INSERT INTO log_admin VALUES(355,'2026-10-03 16:12:57','superadmin','konfigurasi_simpan','Bpjs — 8 setelan');
INSERT INTO log_admin VALUES(356,'2026-10-03 16:15:21','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(357,'2026-10-03 16:17:30','superadmin','potongan_simpan','Menyimpan saklar potongan');
INSERT INTO log_admin VALUES(358,'2026-10-03 16:17:38','superadmin','slip_hitung_semua','Periode 2026-10, 9 slip → DRAFT');
INSERT INTO log_admin VALUES(359,'2026-10-03 16:19:53','superadmin','sandi_saya','Ganti kata sandi sendiri');
INSERT INTO log_admin VALUES(360,'2026-10-03 16:20:10','superadmin','user_hapus','Akun #2');
INSERT INTO log_admin VALUES(361,'2026-10-03 16:21:04','superadmin','user_simpan','Akun delta (hrd)');
INSERT INTO log_admin VALUES(362,'2026-10-03 16:21:19','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(363,'2026-10-03 16:25:01','delta','karyawan_simpan','Tambah karyawan #10 (JOKOWI)');
INSERT INTO log_admin VALUES(364,'2026-10-03 16:25:35','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(365,'2026-10-03 16:25:56','delta','bonus_karyawan','Pengaturan bonus karyawan #10');
INSERT INTO log_admin VALUES(366,'2026-10-03 16:26:02','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(367,'2026-10-03 16:30:25','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(368,'2026-10-03 16:31:50','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(369,'2026-10-03 16:31:55','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(370,'2026-10-03 16:32:15','delta','absensi_harian','Simpan absensi 2026-10-03 (1 baris)');
INSERT INTO log_admin VALUES(371,'2026-10-03 16:32:21','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(372,'2026-10-03 16:38:53','delta','absensi_harian','Simpan absensi 2026-10-03 (0 baris)');
INSERT INTO log_admin VALUES(373,'2026-10-03 16:38:58','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(374,'2026-10-03 16:39:46','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(375,'2026-10-04 10:41:01','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(376,'2026-10-05 08:50:50','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(377,'2026-10-05 09:01:03','superadmin','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(378,'2026-10-08 11:12:01','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(379,'2026-10-08 11:13:59','delta','absensi_cepat','Isi cepat Hadir 2026-10-08 (10 karyawan)');
INSERT INTO log_admin VALUES(380,'2026-10-08 11:14:12','delta','absensi_harian','Simpan absensi 2026-10-08 (10 baris)');
INSERT INTO log_admin VALUES(381,'2026-10-08 11:14:30','delta','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(382,'2026-10-08 11:19:05','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(383,'2026-10-08 11:20:25','superadmin','konfigurasi_simpan','Absensi — 16 setelan');
INSERT INTO log_admin VALUES(384,'2026-10-08 15:07:37','','login','Berhasil masuk');
INSERT INTO log_admin VALUES(385,'2026-10-08 15:11:58','superadmin','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(386,'2026-10-08 15:15:08','superadmin','konfigurasi_simpan','Slip — 6 setelan');
INSERT INTO log_admin VALUES(387,'2026-10-08 15:16:06','superadmin','potongan_simpan','Menyimpan saklar potongan');
INSERT INTO log_admin VALUES(388,'2026-10-08 15:16:30','superadmin','absensi_harian','Simpan absensi 2026-10-08 (10 baris)');
INSERT INTO log_admin VALUES(389,'2026-10-08 15:16:37','superadmin','slip_hitung_semua','Periode 2026-10, 10 slip → DRAFT');
INSERT INTO log_admin VALUES(390,'2026-10-08 15:20:18','','login','Berhasil masuk');
CREATE TABLE bonus_parameter (
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
        );
INSERT INTO bonus_parameter VALUES(1,'bonus_target','Bonus Target','nominal',100000.0,0,1,0,0,1,1,'Dibayar bila target kerja tercapai (syarat: tanpa hari alpa).');
INSERT INTO bonus_parameter VALUES(2,'bonus_produksi','Bonus Produksi','nominal',100000.0,0,1,0,0,1,2,'Bonus hasil produksi bulanan.');
INSERT INTO bonus_parameter VALUES(3,'bonus_kehadiran','Bonus Kehadiran','per_hari_hadir',3333.0,0,1,0,0,1,3,'Dibayarkan per hari hadir; hangus bila ada hari alpa (tanpa keterangan).');
INSERT INTO bonus_parameter VALUES(4,'bonus_sop','Bonus SOP','nominal',100000.0,0,1,0,0,1,4,'Kepatuhan menjalankan standar operasional.');
INSERT INTO bonus_parameter VALUES(5,'bonus_kreativitas','Bonus Kreativitas & Inovasi','nominal',100000.0,0,1,0,0,1,5,'Ide perbaikan / inovasi yang diterapkan.');
INSERT INTO bonus_parameter VALUES(6,'bonus_kualitas','Bonus Kualitas / Zero Error','nominal',100000.0,0,1,0,0,1,6,'Tidak ada kesalahan produksi sepanjang periode.');
INSERT INTO bonus_parameter VALUES(7,'bonus_insentif_minggu','Bonus Insentif Minggu','per_minggu_kerja',100000.0,0,1,0,0,1,7,'Insentif tiap hari Minggu yang dikerjakan. Contoh: hanya diberikan kepada karyawan tertentu dengan nilai berbeda.');
CREATE TABLE karyawan_bonus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            bonus_id INTEGER NOT NULL REFERENCES bonus_parameter(id) ON DELETE CASCADE,
            aktif INTEGER NOT NULL DEFAULT 1,
            nilai REAL NOT NULL DEFAULT -1,
            UNIQUE (karyawan_id, bonus_id)
        );
INSERT INTO karyawan_bonus VALUES(24,3,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(25,3,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(26,3,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(27,3,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(28,3,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(29,3,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(30,3,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(31,1,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(32,1,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(33,1,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(34,1,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(35,1,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(36,1,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(37,1,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(45,7,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(46,7,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(47,7,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(48,7,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(49,7,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(50,7,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(51,7,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(52,5,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(53,5,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(54,5,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(55,5,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(56,5,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(57,5,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(58,5,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(59,8,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(60,8,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(61,8,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(62,8,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(63,8,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(64,8,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(65,8,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(66,6,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(67,6,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(68,6,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(69,6,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(70,6,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(71,6,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(72,6,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(73,4,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(74,4,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(75,4,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(76,4,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(77,4,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(78,4,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(79,4,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(87,9,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(88,9,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(89,9,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(90,9,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(91,9,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(92,9,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(93,9,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(108,2,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(109,2,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(110,2,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(111,2,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(112,2,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(113,2,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(114,2,7,0,-1.0);
INSERT INTO karyawan_bonus VALUES(115,10,1,0,-1.0);
INSERT INTO karyawan_bonus VALUES(116,10,2,0,-1.0);
INSERT INTO karyawan_bonus VALUES(117,10,3,0,-1.0);
INSERT INTO karyawan_bonus VALUES(118,10,4,0,-1.0);
INSERT INTO karyawan_bonus VALUES(119,10,5,0,-1.0);
INSERT INTO karyawan_bonus VALUES(120,10,6,0,-1.0);
INSERT INTO karyawan_bonus VALUES(121,10,7,0,-1.0);
CREATE TABLE panjar (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
            tanggal TEXT NOT NULL DEFAULT "",
            jumlah REAL NOT NULL DEFAULT 0,
            cicilan_per_bulan REAL NOT NULL DEFAULT 0,
            keterangan TEXT NOT NULL DEFAULT "",
            aktif INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT ""
        );
DELETE FROM sqlite_sequence;
INSERT INTO sqlite_sequence VALUES('user',3);
INSERT INTO sqlite_sequence VALUES('pph21_layers',5);
INSERT INTO sqlite_sequence VALUES('divisi',5);
INSERT INTO sqlite_sequence VALUES('karyawan',10);
INSERT INTO sqlite_sequence VALUES('komponen_gaji',3);
INSERT INTO sqlite_sequence VALUES('hari_libur',5);
INSERT INTO sqlite_sequence VALUES('absensi',2530);
INSERT INTO sqlite_sequence VALUES('slip_gaji',85);
INSERT INTO sqlite_sequence VALUES('slip_item',6087);
INSERT INTO sqlite_sequence VALUES('log_admin',390);
INSERT INTO sqlite_sequence VALUES('bonus_parameter',7);
INSERT INTO sqlite_sequence VALUES('karyawan_bonus',121);
INSERT INTO sqlite_sequence VALUES('panjar',7);
CREATE INDEX idx_absensi_tanggal ON absensi (tanggal);
COMMIT;
