-- ============================================================================
--  SKEMA DATABASE — HR & PAYROLL MANAGEMENT
--  (acuan/dokumentasi; skema asli dibuat otomatis oleh db.php: schema_ensure())
--  Database: SQLite via PDO (file: data/payroll.sqlite)
--
--  Prinsip: SEMUA variabel komputasi (toleransi terlambat, denda, faktor lembur,
--  persentase BPJS, PTKP, lapisan tarif PPh 21) disimpan sebagai DATA pada tabel
--  key-value (system_settings / payroll_configurations) + tabel parameter
--  (ptkp, pph21_layers). Tidak ada tarif yang ditanam di dalam kode program.
-- ============================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------- konfigurasi
-- Identitas perusahaan, logo, tanda tangan, dan tampilan slip.
CREATE TABLE system_settings (
    kunci       TEXT PRIMARY KEY,
    nilai       TEXT NOT NULL DEFAULT '',
    tipe        TEXT NOT NULL DEFAULT 'teks',   -- teks|alamat|email|angka|uang|persen|menit|jam|waktu|bool|json|pilihan|zona
    kelompok    TEXT NOT NULL DEFAULT 'umum',   -- perusahaan|ttd|slip|umum
    label       TEXT NOT NULL DEFAULT '',
    keterangan  TEXT NOT NULL DEFAULT '',
    updated_at  TEXT NOT NULL DEFAULT ''
);

-- Variabel hukum & ketenagakerjaan (Modul 2).
CREATE TABLE payroll_configurations (
    kunci       TEXT PRIMARY KEY,
    nilai       TEXT NOT NULL DEFAULT '',       -- angka/persentase/JSON sebagai teks
    tipe        TEXT NOT NULL DEFAULT 'teks',
    kelompok    TEXT NOT NULL DEFAULT 'umum',   -- umum|absensi|lembur|bpjs|pajak
    label       TEXT NOT NULL DEFAULT '',
    keterangan  TEXT NOT NULL DEFAULT '',
    updated_at  TEXT NOT NULL DEFAULT ''
);

-- --------------------------------------------------------- parameter pajak
-- PTKP dinamis: TK/0, K/0, K/1, ... (bisa ditambah/diubah admin)
CREATE TABLE ptkp (
    kode       TEXT PRIMARY KEY,               -- 'TK/0', 'K/2', ...
    keterangan TEXT NOT NULL DEFAULT '',
    setahun    REAL NOT NULL DEFAULT 0,        -- batas PTKP setahun
    aktif      INTEGER NOT NULL DEFAULT 1,
    urutan     INTEGER NOT NULL DEFAULT 0
);

-- Lapisan tarif PPh 21 progresif (batas_atas = 0 berarti tanpa batas)
CREATE TABLE pph21_layers (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    urutan       INTEGER NOT NULL,
    batas_bawah  REAL NOT NULL DEFAULT 0,
    batas_atas   REAL NOT NULL DEFAULT 0,
    tarif_persen REAL NOT NULL DEFAULT 0,
    keterangan   TEXT NOT NULL DEFAULT ''
);

-- ------------------------------------------------------------ master data
CREATE TABLE divisi (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    nama       TEXT NOT NULL UNIQUE,
    keterangan TEXT NOT NULL DEFAULT '',
    aktif      INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE karyawan (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    nip                 TEXT NOT NULL UNIQUE,
    nama                TEXT NOT NULL,
    jabatan             TEXT NOT NULL DEFAULT '',
    divisi_id           INTEGER REFERENCES divisi(id) ON DELETE SET NULL,
    tanggal_masuk       TEXT NOT NULL DEFAULT '',
    status_kepegawaian  TEXT NOT NULL DEFAULT 'tetap',      -- tetap|kontrak|harian|magang
    metode_pajak        TEXT NOT NULL DEFAULT 'progresif',  -- progresif|tunggal|tidak_ada (per karyawan)
    ptkp_kode           TEXT NOT NULL DEFAULT 'TK/0' REFERENCES ptkp(kode),
    npwp                TEXT NOT NULL DEFAULT '',
    gaji_pokok          REAL NOT NULL DEFAULT 0,
    tunjangan_tetap     REAL NOT NULL DEFAULT 0,
    tunjangan_tidak_tetap REAL NOT NULL DEFAULT 0,
    bank                TEXT NOT NULL DEFAULT '',
    no_rekening         TEXT NOT NULL DEFAULT '',
    email               TEXT NOT NULL DEFAULT '',
    telepon             TEXT NOT NULL DEFAULT '',
    aktif               INTEGER NOT NULL DEFAULT 1,
    created_at          TEXT NOT NULL DEFAULT '',
    updated_at          TEXT NOT NULL DEFAULT ''
);

-- Tunjangan/potongan tambahan per karyawan (kasbon, koperasi, transport, dll.)
CREATE TABLE komponen_gaji (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
    jenis       TEXT NOT NULL DEFAULT 'PENDAPATAN',  -- PENDAPATAN | POTONGAN
    kategori    TEXT NOT NULL DEFAULT '',
    nama        TEXT NOT NULL,
    tipe        TEXT NOT NULL DEFAULT 'nominal',     -- nominal | persen (dari gaji pokok)
    nilai       REAL NOT NULL DEFAULT 0,
    berulang    INTEGER NOT NULL DEFAULT 1,          -- 1 = setiap bulan
    periode     TEXT NOT NULL DEFAULT '',            -- YYYY-MM bila satu kali
    aktif       INTEGER NOT NULL DEFAULT 1,
    keterangan  TEXT NOT NULL DEFAULT '',
    created_at  TEXT NOT NULL DEFAULT ''
);

-- --------------------------------------------------------------- bonus
-- Parameter bonus DINAMIS: admin dapat menambah/mengubah/menghapus dari
-- Pengaturan → Bonus. Tipe nilai: nominal | persen_pokok | per_hari_hadir |
-- per_minggu | per_minggu_kerja.
CREATE TABLE bonus_parameter (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    kode       TEXT NOT NULL UNIQUE,          -- mis. bonus_target
    nama       TEXT NOT NULL,
    tipe       TEXT NOT NULL DEFAULT 'nominal',
    nilai      REAL NOT NULL DEFAULT 0,
    kena_pajak INTEGER NOT NULL DEFAULT 1,    -- hanya yang 1 masuk penghasilan bruto PPh 21
    berlaku_semua INTEGER NOT NULL DEFAULT 1, -- 0 = hanya karyawan yang diikutkan
    syarat_alpa_nol       INTEGER NOT NULL DEFAULT 0,  -- hangus bila ada hari alpa
    syarat_tanpa_terlambat INTEGER NOT NULL DEFAULT 0, -- hangus bila ada keterlambatan
    aktif      INTEGER NOT NULL DEFAULT 1,
    urutan     INTEGER NOT NULL DEFAULT 0,
    keterangan TEXT NOT NULL DEFAULT ''
);

-- Penerima bonus per karyawan. Baris TANPA data = ikut saklar berlaku_semua.
-- aktif = 0 berarti karyawan ini sengaja TIDAK diikutkan bonus tersebut.
CREATE TABLE karyawan_bonus (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    karyawan_id INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
    bonus_id    INTEGER NOT NULL REFERENCES bonus_parameter(id) ON DELETE CASCADE,
    aktif       INTEGER NOT NULL DEFAULT 1,
    nilai       REAL NOT NULL DEFAULT -1,     -- -1 = pakai nilai umum di Pengaturan
    UNIQUE (karyawan_id, bonus_id)
);

-- --------------------------------------------------------------- panjar
-- Panjar (uang muka karyawan) — satu-satunya potongan utama pada aturan ini.
-- Sisa tidak disimpan sebagai kolom: dihitung dari riwayat slip_item (sumber
-- 'panjar:<id>') agar perhitungan ulang tidak mengurangi panjar dua kali.
CREATE TABLE panjar (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    karyawan_id        INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
    tanggal            TEXT NOT NULL DEFAULT '',
    jumlah             REAL NOT NULL DEFAULT 0,
    cicilan_per_bulan  REAL NOT NULL DEFAULT 0,
    keterangan         TEXT NOT NULL DEFAULT '',
    aktif              INTEGER NOT NULL DEFAULT 1,
    created_at         TEXT NOT NULL DEFAULT ''
);

-- ---------------------------------------------------------------- absensi
CREATE TABLE absensi (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    karyawan_id      INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
    tanggal          TEXT NOT NULL,
    jam_masuk        TEXT NOT NULL DEFAULT '',
    jam_pulang       TEXT NOT NULL DEFAULT '',
    status           TEXT NOT NULL DEFAULT 'HADIR',  -- HADIR|IZIN|SAKIT|CUTI|ALPA|LIBUR
    menit_terlambat  INTEGER NOT NULL DEFAULT 0,
    menit_lembur     INTEGER NOT NULL DEFAULT 0,
    lembur_disetujui INTEGER NOT NULL DEFAULT 0,
    keterangan       TEXT NOT NULL DEFAULT '',
    dibuat_oleh      TEXT NOT NULL DEFAULT '',
    created_at       TEXT NOT NULL DEFAULT '',
    updated_at       TEXT NOT NULL DEFAULT '',
    UNIQUE (karyawan_id, tanggal)
);
CREATE INDEX idx_absensi_tanggal ON absensi (tanggal);

CREATE TABLE hari_libur (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal    TEXT NOT NULL UNIQUE,
    keterangan TEXT NOT NULL DEFAULT '',
    jenis      TEXT NOT NULL DEFAULT 'libur_nasional'
);

-- ------------------------------------------------------------- slip gaji
-- Ringkasan slip (satu baris per karyawan per periode).
CREATE TABLE slip_gaji (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    karyawan_id     INTEGER NOT NULL REFERENCES karyawan(id) ON DELETE CASCADE,
    periode         TEXT NOT NULL,                 -- YYYY-MM
    nomor           TEXT NOT NULL DEFAULT '',      -- PREFIX/YYYY-MM/0001
    tanggal_proses  TEXT NOT NULL DEFAULT '',
    jabatan         TEXT NOT NULL DEFAULT '',      -- disalin saat proses (snapshot)
    divisi          TEXT NOT NULL DEFAULT '',
    metode_pajak    TEXT NOT NULL DEFAULT '',
    hari_kerja      REAL NOT NULL DEFAULT 0,
    hari_hadir      REAL NOT NULL DEFAULT 0,
    hari_alpa       REAL NOT NULL DEFAULT 0,
    hari_izin       REAL NOT NULL DEFAULT 0,
    hari_sakit      REAL NOT NULL DEFAULT 0,
    hari_cuti       REAL NOT NULL DEFAULT 0,
    total_menit_terlambat INTEGER NOT NULL DEFAULT 0,
    total_menit_lembur    INTEGER NOT NULL DEFAULT 0,
    upah_per_jam    REAL NOT NULL DEFAULT 0,
    total_pendapatan REAL NOT NULL DEFAULT 0,
    total_potongan   REAL NOT NULL DEFAULT 0,
    take_home_pay    REAL NOT NULL DEFAULT 0,
    hari_minggu     REAL NOT NULL DEFAULT 0,       -- jumlah hari Minggu yang dikerjakan
    upah_minggu     REAL NOT NULL DEFAULT 0,       -- upah harian + upah lembur Minggu ("2 upah")
    total_bonus     REAL NOT NULL DEFAULT 0,
    total_panjar    REAL NOT NULL DEFAULT 0,
    status          TEXT NOT NULL DEFAULT 'DRAFT', -- DRAFT | FINAL
    catatan         TEXT NOT NULL DEFAULT '',
    dibuat_oleh     TEXT NOT NULL DEFAULT '',
    created_at      TEXT NOT NULL DEFAULT '',
    updated_at      TEXT NOT NULL DEFAULT '',
    UNIQUE (karyawan_id, periode)
);

-- Rincian item slip (itemized) — SNAPSHOT: tidak berubah walau setelan diubah.
CREATE TABLE slip_item (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    slip_id  INTEGER NOT NULL REFERENCES slip_gaji(id) ON DELETE CASCADE,
    jenis    TEXT NOT NULL,                        -- PENDAPATAN | POTONGAN
    kategori TEXT NOT NULL DEFAULT '',             -- Tetap|Lembur|BPJS|Absensi|Pajak|Kasbon|...
    nama     TEXT NOT NULL,
    nilai    REAL NOT NULL DEFAULT 0,
    sumber   TEXT NOT NULL DEFAULT '',             -- master_karyawan|absensi|bpjs|pajak|komponen
    urutan   INTEGER NOT NULL DEFAULT 0
);

-- -------------------------------------------------------- akun & audit
CREATE TABLE user (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT NOT NULL UNIQUE,
    nama          TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL,                   -- superadmin | hrd
    aktif         INTEGER NOT NULL DEFAULT 1,
    updated_at    TEXT NOT NULL DEFAULT '',
    failed_count  INTEGER NOT NULL DEFAULT 0,
    locked_until  TEXT NULL
);

CREATE TABLE sesi (
    token_hash TEXT PRIMARY KEY,                   -- sha256 cookie acak (bukan session PHP)
    user_id    INTEGER NOT NULL REFERENCES user(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT '',
    expires_at TEXT NOT NULL DEFAULT '',
    last_seen  TEXT NOT NULL DEFAULT ''
);

CREATE TABLE log_admin (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    waktu   TEXT NOT NULL DEFAULT '',
    oleh    TEXT NOT NULL DEFAULT '',
    aksi    TEXT NOT NULL DEFAULT '',
    rincian TEXT NOT NULL DEFAULT ''
);
