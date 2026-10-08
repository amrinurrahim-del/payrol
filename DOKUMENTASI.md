# HR & Payroll Management — Dokumentasi Arsitektur

Aplikasi penggajian karyawan berbasis web. **Semua nilai komputasi** (jam kerja & istirahat,
toleransi keterlambatan, denda, faktor lembur hari kerja, upah lembur hari Minggu, daftar bonus,
persentase BPJS, PTKP, lapisan tarif PPh 21, dan saklar tiap potongan) **diambil dari halaman
Pengaturan** — tidak ada satu pun tarif yang ditanam di dalam kode.

### Aturan kerja yang sedang berlaku (perusahaan ini)
| Hal | Aturan | Diatur di |
| --- | --- | --- |
| Jam kerja harian | **10 jam bersegmen**: 08:00–11:00 dan 13:00–20:00 | Pengaturan → Jam Kerja |
| Istirahat | 11:00–13:00 (2 jam) — **tidak dihitung & tidak dibayar** | Pengaturan → Jam Kerja |
| Jumlah hari kerja (pembagi) | **Otomatis**: dihitung dari kalender, **hanya Senin–Sabtu**; **Minggu tidak pernah masuk pembagi** | Pengaturan → Jam Kerja → Dasar Perhitungan |
| Upah harian (Senin–Sabtu) | **Gaji Pokok ÷ jumlah hari kerja** (otomatis, Senin–Sabtu) lalu **× jumlah hari hadir** | Pengaturan → Absensi & Dasar Upah |
| Minggu (berdiri sendiri) | **jam kerja Minggu × tarif per jam custom (Rp20.000)** — disebut **lembur minggu**; upah harian biasa tidak berlaku & Minggu tidak ikut pembagi | Pengaturan → Lembur |
| Lembur (Senin–Sabtu) | **(Gaji Pokok ÷ 173) × jam lembur** — tanpa faktor pengali | Pengaturan → Lembur |
| Lembur hari libur nasional | jam × (Gaji Pokok ÷ 173), atau × faktor bila faktor dinyalakan | Pengaturan → Lembur |
| Bonus | 7 parameter (target, produksi, kehadiran, SOP, kreativitas, kualitas/zero error, insentif minggu) — bisa ditambah | Pengaturan → Bonus |
| Potongan | berlaku **Panjar** (+ denda/alpa/izin/sakit/BPJS/PPh 21 yang bisa dinyalakan) | Pengaturan → Potongan |

---

## 1. Arsitektur & tumpukan teknologi

| Bagian | Pilihan | Alasan |
| --- | --- | --- |
| Bahasa | PHP 8.2 | runtime platform |
| Database | **SQLite via PDO** (`data/payroll.sqlite`) | relasional penuh (foreign key aktif), satu berkas, tanpa server DB terpisah. **MongoDB tidak tersedia** di server ini dan model datanya memang relasional (karyawan → banyak absensi → slip → banyak item). |
| Konfigurasi | tabel key-value `system_settings` + `payroll_configurations`, tabel `ptkp`, `pph21_layers` | memenuhi syarat "semua variabel kustom tersimpan di database" |
| Sesi | cookie acak + tabel `sesi` (hash sha256) | tidak bergantung folder session server |
| Tampilan | PHP + CSS korporat biru/putih + JS kecil (`assets/app.js`) | ramah cetak, tanpa build step |

Skema lengkap: **`skema.sql`** (acuan) — dibuat otomatis oleh `db.php` → `schema_ensure()`
dalam satu transaksi `BEGIN IMMEDIATE`, dengan `PRAGMA busy_timeout=5000`, `journal_mode=WAL`,
`synchronous=NORMAL`.

### Relasi utama

```
divisi ──1:N── karyawan ──1:N── absensi        (unik per karyawan+tanggal)
                    │
                    ├──1:N── komponen_gaji   (kasbon, koperasi, tunjangan tambahan)
                    │
                    └──1:N── slip_gaji ──1:N── slip_item   (pendapatan/potongan itemized)

karyawan ──1:N── bonus_parameter  (lewat karyawan_bonus: penugasan + nilai khusus)
karyawan ──1:N── panjar           (uang muka, dipotong mencicil; sisa dari riwayat slip)
ptkp ◄── karyawan.ptkp_kode          pph21_layers  (lapisan tarif progresif)
system_settings / payroll_configurations + bonus_parameter → dibaca mesin payroll setiap perhitungan
user ──1:N── sesi                     log_admin (jejak audit tindakan penting)
```

`slip_gaji` + `slip_item` adalah **snapshot**: angka slip yang sudah disimpan tidak berubah
walaupun setelan Pengaturan atau gaji pokok diubah setelahnya (riwayat tetap utuh).

---

## 2. Pseudocode mesin penggajian (`hitung_slip()`)

```
FUNGSI hitung_slip(karyawan, periode):            # periode = "YYYY-MM"
  K ← data karyawan;  C ← seluruh nilai payroll_configurations

  # 1. JAM KERJA BERSERMEN (bisa diubah di Pengaturan → Jam Kerja)
  segmen  ← baca C.jam_kerja_segmen            # [{"mulai","selesai","istirahat"}]
  jam_hari ← Σ durasi segmen yang istirahat = false        # 08:00-11:00 + 13:00-20:00 = 10 jam
  # 2. Kalender kerja — Minggu SELALU dikecualikan dari pembagi
  hari_tersedia ← [tanggal dalam bulan] yang:
        hari ≠ MINGGU                     # dikecualikan mutlak, apa pun setelan hari kerja mingguan
        AND dow ∈ C.hari_kerja_mingguan
        AND (libur nasional TIDAK dihitung bila C.hari_kerja_kurangi_libur)
  jumlah_minggu ← |tanggal MINGGU dalam bulan|          # hanya untuk info & bonus insentif minggu
  IF C.basis_hari_kerja = "tetap" THEN hari_kerja ← C.hari_kerja_per_bulan
                                 ELSE hari_kerja ← |hari_tersedia|    # ← dipakai perusahaan ini

  # 3. Absensi (Modul 3)
  rekap ← { hadir, hadir_kerja, hadir_minggu, menit_minggu, izin, sakit, cuti, alpa,
            menit_terlambat, rincian_lembur[] }        # tiap baris lembur diberi tanda dow & minggu

  # 4. Upah dasar (rumus perusahaan ini)
  basis        ← (C.basis_upah_per_jam = "pokok_tunjangan" ? K.gaji_pokok + K.tunjangan_tetap : K.gaji_pokok)
  hari_pembagi ← C.basis_hari_kerja = "otomatis" ? |hari kerja Senin–Sabtu| : C.hari_kerja_per_bulan
  upah_hari    ← basis ÷ hari_pembagi
  upah_lembur_jam ← basis ÷ C.lembur_pembagi                                                     # ÷ 173

  # 5. PENDAPATAN
  IF C.upah_mode = "proporsional_hadir":                    # dipakai perusahaan ini
      pendapatan += ["Upah Harian (N hari × upah_hari)", upah_hari × rekap.hadir_kerja]
  ELSE
      pendapatan += ["Gaji Pokok", K.gaji_pokok]            # dibayar penuh
  pendapatan += Tunjangan Tetap / Tunjangan Tidak Tetap  (× faktor kehadiran bila C.tunjangan_ikut_kehadiran)

  # 5a. HARI MINGGU — BERDIRI SENDIRI: hanya lembur minggu dengan tarif per jam custom
  IF C.minggu_aktif AND |rekap.rincian_minggu| > 0 THEN
      jamM ← rekap.menit_minggu ÷ 60                        # absensi Minggu dengan jam kerja ≥ C.minggu_minimal_menit
      IF C.minggu_mode = "tarif_jam" THEN                   # dipakai perusahaan ini
          pendapatan += ["Upah Lembur Minggu (jamM jam × C.minggu_tarif_jam)", jamM × C.minggu_tarif_jam]
      ELSE  # mode lama "2 upah": upah harian + upah lembur minggu (lihat minggu_lembur_mode)
          pendapatan += ["Upah Harian Hari Minggu", upah_hari × C.minggu_upah_harian_faktor × hariM]
          pendapatan += ["Upah Lembur Minggu", …]

  # 5b. LEMBUR HARI KERJA (Senin–Sabtu) & HARI LIBUR NASIONAL — berbasis jam
  FOR baris IN rekap.rincian_lembur WHERE baris.minggu = false:
      IF C.lembur_wajib_disetujui AND NOT disetujui THEN SKIP
      menit ← bulatkan(menit, C.lembur_pembulatan)         # per_menit | 15 | 30 | jam
      IF C.lembur_maks_jam_bulan > 0 THEN batasi menit ke sisa kuota bulan
      jam ← menit ÷ 60
      IF C.lembur_pakai_faktor = 0 THEN                    # dipakai perusahaan ini
          nilai ← jam × upah_lembur_jam
      ELSE
          IF baris.hari_libur THEN nilai ← jam × C.lembur_faktor_hari_libur × upah_lembur_jam
          ELSE nilai ← (min(1,jam) × C.lembur_faktor_pertama + max(0,jam−1) × C.lembur_faktor_berikutnya) × upah_lembur_jam
  pendapatan += ["Upah Lembur Hari Kerja" / "Upah Lembur Hari Libur", totalnya]

  # 5c. BONUS — daftar parameter dari tabel bonus_parameter (bisa ditambah admin)
  FOR b IN bonus_parameter WHERE aktif = 1 ORDER BY urutan:
      kb ← karyawan_bonus(K.id, b.id)
      IF kb ADA   THEN IF kb.aktif = 0 THEN SKIP (tidak diikutkan)
                       nilai ← (kb.nilai ≥ 0 ? kb.nilai : b.nilai)
      ELSE          IF b.berlaku_semua = 1 THEN nilai ← b.nilai  ELSE SKIP
      IF b.syarat_alpa_nol = 1 AND rekap.alpa > 0 THEN SKIP (dicatat alasannya di slip)
      IF b.syarat_tanpa_terlambat = 1 AND rekap.menit_terlambat > 0 THEN SKIP
      CASE b.tipe:
        "nominal"          → nilai
        "persen_pokok"     → nilai% × K.gaji_pokok
        "per_hari_hadir"   → nilai × rekap.hadir
        "per_minggu"       → nilai × jumlah hari Minggu dalam periode
        "per_minggu_kerja" → nilai × rekap.hadir_minggu
      pendapatan += [b.nama, hasil, kena_pajak = b.kena_pajak]

  pendapatan += komponen_gaji(jenis=PENDAPATAN)          # tunjangan manual per karyawan
  IF C.bpjs_aktif THEN pendapatan += iuran BPJS perusahaan (tunjangan)

  # 6. POTONGAN — semuanya punya saklar di Pengaturan → Potongan
  IF C.potongan_denda_aktif THEN potongan += denda keterlambatan (per_menit | per_kejadian | bertingkat)
  IF C.potongan_alpa_aktif AND NOT (C.upah_mode = "proporsional_hadir" AND C.alpa_potong_saat_proporsional = 0)
      THEN potongan += potongan_status("alpa", alpa)   # alpa sudah otomatis tidak dibayar pada mode proporsional
  IF C.potongan_izin_aktif  THEN potongan += potongan_status("izin",  izin)
  IF C.potongan_sakit_aktif THEN potongan += potongan_status("sakit", sakit)
  IF C.potongan_lain_aktif  THEN potongan += komponen_gaji(jenis=POTONGAN)

  IF C.potongan_panjar_aktif THEN                          # PANJAR: satu-satunya potongan utama
      FOR p IN panjar WHERE karyawan_id = K.id AND aktif = 1:
          sudah ← Σ slip_item.nilai WHERE sumber = "panjar:"+p.id AND slip.periode < periode
          sisa  ← max(0, p.jumlah − sudah)
          IF sisa > 0 THEN potongan += ["Potongan Panjar", min(p.cicilan_per_bulan, sisa)]

  IF C.bpjs_aktif THEN potongan += iuran BPJS karyawan

  # 7. PPh 21 — metode per karyawan (progresif / persentase tunggal / tanpa pajak)
  bruto_pajak ← Σ pendapatan WHERE kena_pajak ≠ false     # bonus bebas pajak dikecualikan
  IF C.pajak_aktif AND K.metode_pajak ≠ "tidak_ada" THEN
      IF "tunggal"   → bruto_pajak(_setelah_bpjs?) × C.pajak_tunggal_persen
      IF "progresif" → PKP ← bulat_bawah(bruto_setahun − biaya_jabatan − BPJS karyawan − PTKP(K.ptkp_kode))
                       pajak ← Σ lapis pph21_layers ; ÷ C.pajak_bulan_setahun
                       IF K.npwp kosong → × (1 + C.pajak_tanpa_npwp_tambahan_persen ÷ 100)
      potongan += ["PPh 21", pajak]

  # 8. Hasil
  take_home_pay ← max(0, Σ pendapatan − Σ potongan)
  RETURN { pendapatan[], potongan[], total_*, take_home_pay, catatan[], rincian_rumus[] }
```

## 2b. Absensi harian: perhitungan otomatis & penguncian kolom

```
HALAMAN absensi.php (input harian)
  untuk setiap karyawan → satu baris <tr class="baris-absen"
        data-masuk-standar="08:00" data-pulang-standar="20:00">

  JS (assets/app.js → pasangBarisAbsen):
    status berubah  → kunciKolom():
        HADIR  : jam masuk, jam pulang, menit terlambat, menit lembur, centang setujui = AKTIF
        lainnya: semua kolom di atas NONAKTIF, menit di-nol-kan, centang dilepas
        serta jam masuk/pulang diisi otomatis jam standar dari segmen
    jam masuk berubah   → menit terlambat = max(0, jam masuk − jam masuk standar)
    jam pulang berubah  → menit lembur   = max(0, jam pulang − jam pulang standar)
                          HANYA bila centang "lembur disetujui" aktif (kalau tidak: 0)
    nilai diketik manual → penanda auto_* = 0, kolom itu berhenti dihitung otomatis

SERVER (absensi_simpan) — pengaman bila JavaScript mati:
    status ≠ HADIR  → jam masuk/pulang dikosongkan, menit terlambat & lembur = 0,
                      persetujuan lembur = 0
    status = HADIR  → bila penanda auto_* dikirim (formulir selalu mengirimnya, berupa
                      kolom tersembunyi) menit dihitung ulang di server memakai
                      jam_masuk_standar()/jam_pulang_standar()
                      bila penanda TIDAK ada (impor manual / skrip) nilai dikirim dipakai apa adanya
```

## 3. Variabel dinamis (semua diubah dari Pengaturan)

| Kelompok | Contoh kunci |
| --- | --- |
| Jam kerja | `jam_kerja_mode` (segmen/sederhana), `jam_kerja_segmen` (JSON — jam masuk, jam istirahat, jam pulang), `jam_kerja_per_hari`, `absensi_jam_masuk_standar`, `absensi_jam_pulang_standar` (~juga dipakai menghitung menit terlambat & lembur secara otomatis) |
| Umum | `hari_kerja_mingguan`, `basis_hari_kerja`, `hari_kerja_per_bulan`, `basis_upah_per_jam`, `pembulatan_gaji`, `mata_uang_simbol`, `zona_waktu` |
| Upah & kehadiran | `upah_mode`, `tunjangan_ikut_kehadiran`, `basis_hari_kerja` (otomatis), `hari_kerja_kurangi_libur`, `hari_kerja_per_bulan` (hanya mode angka tetap), `basis_upah_per_jam`, `alpa_potong_saat_proporsional` |
| Lembur | `lembur_pembagi` (173), `lembur_pakai_faktor`, `lembur_faktor_pertama/_berikutnya/_hari_libur`, `lembur_pembulatan`, `lembur_maks_jam_bulan`, `lembur_wajib_disetujui` |
| Lembur Minggu | `minggu_aktif`, `minggu_mode` (tarif_jam / dua_upah), `minggu_tarif_jam` (20.000), `minggu_minimal_menit`, + setelan mode lama (`minggu_upah_harian_faktor`, `minggu_lembur_mode`, …) |
| Bonus | tabel `bonus_parameter` (nama, dasar hitung, nilai, kena pajak, penerima, syarat) + `karyawan_bonus` (penugasan & nilai khusus per karyawan) |
| Potongan | `potongan_denda_aktif`, `potongan_alpa_aktif`, `potongan_izin_aktif`, `potongan_sakit_aktif`, `potongan_panjar_aktif`, `potongan_lain_aktif`, `bpjs_aktif`, `pajak_aktif` |
| Absensi | `absensi_toleransi_menit`, `absensi_denda_mode`, `absensi_denda_per_menit`, `absensi_denda_per_kejadian`, `absensi_denda_bertingkat` (JSON), `absensi_maks_denda_bulan`, `absensi_alpa_mode/_faktor/_nominal`, `absensi_izin_*`, `absensi_sakit_*`, `absensi_jam_masuk_standar` |
| Lembur | `lembur_faktor_pertama`, `lembur_faktor_berikutnya`, `lembur_faktor_hari_libur`, `lembur_pembulatan`, `lembur_maks_jam_bulan`, `lembur_wajib_disetujui` |
| BPJS | `bpjs_dasar`, `bpjs_maks_dasar`, `bpjs_tk_karyawan_persen`, `bpjs_kes_karyawan_persen`, `bpjs_tk_perusahaan_persen`, `bpjs_kes_perusahaan_persen`, `bpjs_tampil_tunjangan_perusahaan` |
| Pajak | `pajak_metode_default`, `pajak_tunggal_persen`, `pajak_tunggal_dasar`, `pajak_biaya_jabatan_persen`, `pajak_biaya_jabatan_maks_bulan`, `pajak_bulat_pkp`, `pajak_tanpa_npwp_tambahan_persen`, `pajak_bulan_setahun`, `pajak_bpjs_pengurang`, + tabel `ptkp` & `pph21_layers` |
| Identitas & slip | `perusahaan_*`, `logo_url`, `ttd_nama/jabatan/url`, `slip_judul/catatan/tampil_*`, `slip_kode_prefix` |

Menambah variabel baru = tambahkan satu entri di `daftar_payroll_config()` (`db.php`); formulir
Pengaturan, penyimpanan, dan validasi otomatis mengikuti tanpa mengubah kode halaman.

---

## 4. Halaman

| Berkas | Fungsi |
| --- | --- |
| `login.php` | masuk (Superadmin / HRD), keluar |
| `index.php` | dashboard: karyawan aktif, total THP periode, lembur/keterlambatan, rekap per divisi, status slip, pengingat absensi harian |
| `karyawan.php` | master karyawan & divisi (data induk saja) |
| `komponen.php` | **menu terpisah**: 1) komponen gaji tambahan per karyawan, 2) bonus yang diterima (ikut/tidak + nilai khusus), 3) panjar (uang muka + cicilan). Dipisahkan dari form Ubah Karyawan supaya data induk tetap ringkas |
| `absensi.php` | input harian (jam masuk/pulang → menit terlambat & lembur otomatis), daftar absensi, rekap bulanan |
| `payroll.php` | mesin penggajian: hitung semua/per orang, draft ↔ final, cetak massal |
| `slip.php` | daftar slip, slip siap cetak (itemized + terbilang + tanda tangan), cetak semua |
| `laporan.php` | rekap gaji, daftar hadir bulanan, rekap komponen, unduh CSV |
### Format tanggal: dd/mm/yyyy

`<input type="date">` **tidak dipakai** karena format tampilannya mengikuti locale perangkat
(bisa tampil MM/DD/YYYY) dan tidak bisa dipaksa. Gantinya:

- `input_tanggal($name, $ymd)` (lib.php) → kolom teks `dd/mm/yyyy` + tombol kalender.
- `tanggal_dari_teks($teks)` → membaca `dd/mm/yyyy` (pemisah `/`, `.`, `-`) maupun `yyyy-mm-dd`
  (data lama/impor); `''` bila kosong/tidak sah. `tanggal_valid()` memakai fungsi ini sehingga
  **seluruh aplikasi** menerima kedua format tanpa perlu diubah satu per satu.
- `tanggal_teks($ymd)` → `dd/mm/yyyy` untuk ditampilkan.
- Kalender kecil ada di `assets/app.js` (`pasangTanggal()`, `.tgl-kalender`): pilih hari,
  tombol "Hari ini"/"Kosongkan", validasi **tanggal yang tidak ada ditolak** (mis. 31/02/2026),
  ketikan dirapikan saat keluar kolom (`1/2/2026` → `01/02/2026`), dan kalender **mengikuti posisi
  kolom saat halaman digulir** (bukan menutup).

### Pengaturan → **Data & Reset** | pratinjau jumlah data uji, bersihkan data uji (absensi & slip bertanda uji), hapus absensi per rentang tanggal, status data contoh |
| `pengaturan.php` | Identitas Perusahaan, **Jam Kerja** (segmen + istirahat), Absensi & Dasar Upah, **Lembur** (hari kerja & aturan Minggu "2 upah"), **Bonus** (tambah/ubah parameter bonus), **Potongan** (saklar semua potongan + daftar panjar), BPJS, Pajak & PTKP, Desain Slip, Hari Kerja & Libur, Akun & Keamanan, Jejak Audit |

---

## 4b. Data uji & pembersihannya (Pengaturan → Data & Reset)

Setiap baris absensi menyimpan kolom `dibuat_oleh`. Nilai `demo` / `uji` / `test` / `tes`
menandai baris **data contoh/pengujian** (`SUMBER_UJI` di `lib.php`). Baris yang diisi lewat
aplikasi memakai username akun, sehingga tidak pernah dianggap data uji.

- `data_uji_ringkas()` → pratinjau: jumlah baris uji, berapa di antaranya pernah **diubah**
  (`updated_at <> created_at`), jumlah slip uji, sebaran per karyawan & periode, plus daftar
  lengkap baris uji yang pernah diubah.
- `data_uji_bersihkan($ikutDiubah, $hapusSlip, $matikanDemo)` → menghapus baris bertanda uji
  dalam satu transaksi, lalu menyalakan `demo_nonaktif = 1` sehingga `demo_siapkan()` berhenti
  membuat data contoh. **Data karyawan, divisi, bonus, panjar, dan setelan tidak disentuh.**
- Opsi **“ikut hapus baris uji yang sudah diubah”** sengaja dimatikan sebagai bawaan: baris uji
  yang pernah disimpan pemilik kemungkinan besar sudah berisi data nyata, jadi dipertahankan
  sampai pemilik memilih sebaliknya.
- `absensi_hapus_rentang($dari, $sampai)` → hapus absensi per rentang tanggal.
- Tindakan merusak (`data_uji_bersih`, `absensi_hapus_rentang`, `absensi_hapus_bulan`) hanya
  boleh **superadmin** (`pengaturan_aksi_diizinkan()` + pemeriksaan di `absensi.php`).

## 5. Pengujian

| Skrip | Cakupan |
| --- | --- |
| `_uji/uji-payroll.php` | 142 asersi logika: terbilang, kalender kerja, upah/jam, lembur 1,5×/2×, denda (per menit/bertingkat/batas maksimum), alpa, BPJS (termasuk plafon), PPh 21 progresif berlapis & persentase tunggal, tarif tanpa NPWP, komponen kasbon, **snapshot slip**, pemisahan periode |
| `_uji/uji-payroll-ui.js` | 100+ pemeriksaan alur: login, dashboard, karyawan, absensi harian & rekap, hitung slip, isi slip, mode cetak, laporan, perubahan setelan dinamis (termasuk penolakan JSON tidak sah), hak akses HRD |
| `_uji/uji-payroll-cetak.js` | tata letak 7 halaman × 3 lebar layar, jarak kartu, slip dua kolom, **PDF 1 slip = 1 halaman A4**, isi cetak bersih dari menu/tombol, tampilan ponsel |
| `_uji/cek-payroll-live.js` | verifikasi di alamat publik (aset, dashboard, slip, cetak, simpan setelan, laporan, absensi otomatis & penguncian kolom, tab Data & Reset). **Tidak pernah menuntut keadaan saklar potongan tertentu** — saklar itu hak pemilik dan bebas diubah kapan saja. |
