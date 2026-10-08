/* ============================================================================
   HR & Payroll — skrip halaman (tab, konfirmasi, hitung menit, pratinjau berkas)
   ========================================================================== */
(function () {
    'use strict';

    /* ---------------------------------------------------------- tab halaman */
    function pasangTab() {
        var tombol = document.querySelectorAll('[data-tab]');
        if (!tombol.length) { return; }
        var awal = document.body.getAttribute('data-tab-awal') || (tombol[0] && tombol[0].getAttribute('data-tab'));
        function tampil(nama) {
            tombol.forEach(function (t) { t.classList.toggle('is-aktif', t.getAttribute('data-tab') === nama); });
            document.querySelectorAll('[data-panel]').forEach(function (p) {
                p.classList.toggle('is-aktif', p.getAttribute('data-panel') === nama);
            });
            if (history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', nama);
                url.hash = '';
                history.replaceState(null, '', url.toString());
            }
        }
        tombol.forEach(function (t) {
            t.addEventListener('click', function () { tampil(t.getAttribute('data-tab')); });
        });
        var dariUrl = new URL(window.location.href).searchParams.get('tab');
        tampil(dariUrl && document.querySelector('[data-panel="' + dariUrl + '"]') ? dariUrl : awal);
    }

    /* ------------------------------------------------------------------
       PEMILIH TANGGAL format dd/mm/yyyy
       Browser selalu menampilkan <input type="date"> menurut locale perangkat
       (bisa MM/DD/YYYY), jadi kolom tanggal dibuat sendiri: teks dd/mm/yyyy +
       kalender kecil. Hari kerja akhir pekan tetap bisa dipilih (mis. kerja Minggu).
       ------------------------------------------------------------------ */
    var NAMA_BULAN_TGL = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var NAMA_HARI_TGL = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    var kalenderAktif = null;

    function duaAngka(n) { return (n < 10 ? '0' : '') + n; }

    function tglKeTeks(d) { return duaAngka(d.getDate()) + '/' + duaAngka(d.getMonth() + 1) + '/' + d.getFullYear(); }

    /* "1/2/2026" / "01/02/2026" / "2026-02-01" → objek Date, atau null.
       Tanggal yang tidak ada (mis. 31/02/2026) HARUS ditolak: objek Date JavaScript
       menormalkannya menjadi 3 Maret, sehingga hasilnya tampak sah padahal salah. */
    function teksKeTgl(teks) {
        var t = String(teks || '').trim();
        var hari, bulan, tahun, d;
        var m = t.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/);
        if (m) {
            hari = Number(m[1]); bulan = Number(m[2]); tahun = Number(m[3]);
        } else {
            m = t.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
            if (!m) { return null; }
            tahun = Number(m[1]); bulan = Number(m[2]); hari = Number(m[3]);
        }
        if (bulan < 1 || bulan > 12 || hari < 1 || hari > 31) { return null; }
        d = new Date(tahun, bulan - 1, hari);
        if (d.getFullYear() !== tahun || d.getMonth() !== bulan - 1 || d.getDate() !== hari) {
            return null;   /* tanggal tidak ada di kalender */
        }
        return d;
    }

    function tglSah(d) {
        return d instanceof Date && !isNaN(d.getTime());
    }

    function tutupKalender() {
        if (kalenderAktif) {
            kalenderAktif.el.remove();
            document.querySelectorAll('.tgl-input.is-terbuka').forEach(function (i) { i.classList.remove('is-terbuka'); });
            kalenderAktif = null;
        }
    }

    function bukaKalender(inp) {
        tutupKalender();
        var kotak = document.createElement('div');
        kotak.className = 'tgl-kalender';
        var dipilih = teksKeTgl(inp.value);
        var acuan = tglSah(dipilih) ? new Date(dipilih.getTime()) : new Date();
        var bulanTampil = acuan.getMonth();
        var tahunTampil = acuan.getFullYear();
        var nilaiAwal = tglSah(dipilih) ? tglKeTeks(dipilih) : '';

        function gambar() {
            var pertama = new Date(tahunTampil, bulanTampil, 1);
            var geser = (pertama.getDay() + 6) % 7;              /* Senin = 0 */
            var jumlahHari = new Date(tahunTampil, bulanTampil + 1, 0).getDate();
            var hariIni = new Date();
            var html = '<div class="tgl-kepala">'
                + '<button type="button" class="tgl-nav" data-geser="-1" aria-label="Bulan sebelumnya">&#8249;</button>'
                + '<span class="tgl-judul">' + NAMA_BULAN_TGL[bulanTampil] + ' ' + tahunTampil + '</span>'
                + '<button type="button" class="tgl-nav" data-geser="1" aria-label="Bulan berikutnya">&#8250;</button>'
                + '</div><div class="tgl-hari-nama">';
            NAMA_HARI_TGL.forEach(function (n) { html += '<span>' + n + '</span>'; });
            html += '</div><div class="tgl-grid">';
            for (var i = 0; i < geser; i++) { html += '<span class="tgl-kosong"></span>'; }
            for (var d = 1; d <= jumlahHari; d++) {
                var t = new Date(tahunTampil, bulanTampil, d);
                var kelas = 'tgl-hari';
                if (tglKeTeks(t) === nilaiAwal) { kelas += ' is-pilih'; }
                if (tglKeTeks(t) === tglKeTeks(hariIni)) { kelas += ' is-hari-ini'; }
                html += '<button type="button" class="' + kelas + '" data-hari="' + d + '">' + d + '</button>';
            }
            html += '</div><div class="tgl-kaki">'
                + '<button type="button" class="tgl-aksi" data-aksi="hari-ini">Hari ini</button>'
                + '<button type="button" class="tgl-aksi" data-aksi="bersih">Kosongkan</button>'
                + '</div>';
            kotak.innerHTML = html;
            kotak.querySelectorAll('.tgl-nav').forEach(function (b) {
                b.addEventListener('click', function () {
                    var g = Number(b.getAttribute('data-geser'));
                    bulanTampil += g;
                    if (bulanTampil < 0) { bulanTampil = 11; tahunTampil--; }
                    if (bulanTampil > 11) { bulanTampil = 0; tahunTampil++; }
                    gambar();
                });
            });
            kotak.querySelectorAll('.tgl-hari').forEach(function (b) {
                b.addEventListener('click', function () {
                    var t = new Date(tahunTampil, bulanTampil, Number(b.getAttribute('data-hari')));
                    inp.value = tglKeTeks(t);
                    inp.classList.remove('is-salah');
                    inp.dispatchEvent(new Event('change', { bubbles: true }));
                    tutupKalender();
                    inp.focus();
                });
            });
            kotak.querySelector('[data-aksi=hari-ini]').addEventListener('click', function () {
                inp.value = tglKeTeks(new Date());
                inp.classList.remove('is-salah');
                inp.dispatchEvent(new Event('change', { bubbles: true }));
                tutupKalender();
            });
            kotak.querySelector('[data-aksi=bersih]').addEventListener('click', function () {
                inp.value = '';
                inp.dispatchEvent(new Event('change', { bubbles: true }));
                tutupKalender();
                inp.focus();
            });
        }
        gambar();
        document.body.appendChild(kotak);
        kalenderAktif = { el: kotak, inp: inp };
        posisiKalender();
        inp.classList.add('is-terbuka');
    }

    /* Menempatkan kalender di bawah kolom; digeser bila melewati tepi layar.
       Dipanggil ulang saat halaman digulir/diubah ukurannya supaya kalender
       tetap menempel pada kolomnya (bukan menutup seperti sebelumnya). */
    function posisiKalender() {
        if (!kalenderAktif) { return; }
        var kotak = kalenderAktif.el;
        var r = kalenderAktif.inp.getBoundingClientRect();
        var lebar = kotak.offsetWidth;
        var tinggi = kotak.offsetHeight;
        var kiri = Math.min(r.left, window.innerWidth - lebar - 8);
        var atas = r.bottom + 6;
        if (atas + tinggi > window.innerHeight - 8) { atas = Math.max(8, r.top - tinggi - 6); }
        kotak.style.left = Math.max(8, kiri) + 'px';
        kotak.style.top = atas + 'px';
    }

    function pasangTanggal() {
        document.querySelectorAll('input.tgl-input').forEach(function (inp) {
            var tombol = inp.parentElement ? inp.parentElement.querySelector('.tgl-tombol') : null;
            if (tombol) {
                tombol.addEventListener('mousedown', function (e) { e.preventDefault(); });
                tombol.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (kalenderAktif && kalenderAktif.inp === inp) { tutupKalender(); } else { bukaKalender(inp); }
                });
            }
            inp.addEventListener('focus', function () { bukaKalender(inp); });
            inp.addEventListener('click', function () { if (!kalenderAktif || kalenderAktif.inp !== inp) { bukaKalender(inp); } });
            /* Bila pengguna mengetik, format dirapikan & diperiksa saat keluar dari kolom. */
            inp.addEventListener('blur', function () {
                var t = inp.value.trim();
                if (t === '') { inp.classList.remove('is-salah'); return; }
                var d = teksKeTgl(t);
                if (!tglSah(d)) { inp.classList.add('is-salah'); return; }
                inp.value = tglKeTeks(d);
                inp.classList.remove('is-salah');
            });
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { tutupKalender(); }
                if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
            });
        });
        /* Kolom yang ditandai data-perubahan-tanggal="kirim" mengirim formulirnya sendiri
           saat tanggal berubah (pengganti onchange milik <input type=date>). */
        document.querySelectorAll('input.tgl-input[data-perubahan-tanggal=kirim]').forEach(function (inp) {
            inp.addEventListener('change', function () {
                if (inp.value.trim() === '') { return; }
                if (inp.form) { inp.form.submit(); }
            });
        });

        /* Klik di luar kalender menutupnya. */
        document.addEventListener('mousedown', function (e) {
            if (!kalenderAktif) { return; }
            if (kalenderAktif.el.contains(e.target) || e.target === kalenderAktif.inp
                || e.target.classList.contains('tgl-tombol') || e.target.closest('.tgl-tombol')) { return; }
            tutupKalender();
        });
        window.addEventListener('resize', posisiKalender);
        window.addEventListener('scroll', posisiKalender, true);
    }

    /* ------------------------------------------------------- konfirmasi aksi */
    function pasangKonfirmasi() {
        document.querySelectorAll('form[data-konfirmasi]').forEach(function (f) {
            f.addEventListener('submit', function (e) {
                if (!window.confirm(f.getAttribute('data-konfirmasi'))) { e.preventDefault(); }
            });
        });
        document.querySelectorAll('[data-konfirmasi-klik]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                if (!window.confirm(a.getAttribute('data-konfirmasi-klik'))) { e.preventDefault(); }
            });
        });
    }

    /* ------------------------------------------------------------------
       ABSENSI HARIAN
       - Jam masuk & jam pulang hanya aktif bila status HADIR. Status lain
         (Izin/Sakit/Cuti/Alpa/Libur/tidak dicatat) otomatis dinonaktifkan.
       - Menit terlambat dihitung otomatis dari jam masuk terhadap jam masuk standar.
       - Menit lembur dihitung otomatis dari jam pulang terhadap jam pulang standar,
         TETAPI hanya bila centang "lembur disetujui" aktif.
       Nilai tetap bisa dikoreksi manual; begitu diketik manual, kolom itu berhenti
       dihitung otomatis (ditandai lewat kolom hidden auto_*).
       ------------------------------------------------------------------ */
    function keMenit(nilai) {
        if (!nilai || String(nilai).indexOf(':') < 0) { return null; }
        var b = String(nilai).split(':');
        var jam = parseInt(b[0], 10);
        var men = parseInt(b[1], 10);
        if (isNaN(jam) || isNaN(men)) { return null; }
        return jam * 60 + men;
    }

    function pasangBarisAbsen(tr) {
        var sel = tr.querySelector('.sel-status');
        var masuk = tr.querySelector('.jam-masuk');
        var pulang = tr.querySelector('.jam-pulang');
        var terlambat = tr.querySelector('.menit-terlambat');
        var lembur = tr.querySelector('.menit-lembur');
        var setuju = tr.querySelector('.chk-setuju');
        var autoTerlambat = tr.querySelector('.auto-terlambat');
        var autoLembur = tr.querySelector('.auto-lembur');
        if (!sel || !masuk || !pulang || !terlambat || !lembur) { return; }

        var standarMasuk = keMenit(tr.getAttribute('data-masuk-standar'));
        var standarPulang = keMenit(tr.getAttribute('data-pulang-standar'));
        if (standarMasuk === null) { standarMasuk = 8 * 60; }
        if (standarPulang === null) { standarPulang = 20 * 60; }

        function hadir() { return sel.value === 'HADIR'; }

        function kunciKolom() {
            var boleh = hadir();
            [masuk, pulang, terlambat, lembur, setuju].forEach(function (el) {
                if (!el) { return; }
                el.disabled = !boleh;
            });
            if (!boleh) {
                if (autoTerlambat) { autoTerlambat.value = '1'; }
                if (autoLembur) { autoLembur.value = '1'; }
                terlambat.value = 0;
                lembur.value = 0;
                if (setuju) { setuju.checked = false; }
            }
        }

        function hitung(pakaiStandar) {
            if (!hadir()) { return; }
            if (pakaiStandar) {
                if (!masuk.value) { masuk.value = tr.getAttribute('data-masuk-standar') || '08:00'; }
                if (!pulang.value) { pulang.value = tr.getAttribute('data-pulang-standar') || '20:00'; }
            }
            if (!autoTerlambat || autoTerlambat.value === '1') {
                var m = keMenit(masuk.value);
                terlambat.value = (m === null) ? 0 : Math.max(0, m - standarMasuk);
            }
            if (!setuju || !setuju.checked) {
                /* Lembur hanya dihitung bila disetujui. */
                if (!autoLembur || autoLembur.value === '1') { lembur.value = 0; }
            } else if (!autoLembur || autoLembur.value === '1') {
                var k = keMenit(pulang.value);
                lembur.value = (k === null) ? 0 : Math.max(0, k - standarPulang);
            }
        }

        sel.addEventListener('change', function () {
            kunciKolom();
            hitung(true);
        });
        masuk.addEventListener('change', function () { hitung(false); });
        masuk.addEventListener('blur', function () { hitung(false); });
        pulang.addEventListener('change', function () { hitung(false); });
        pulang.addEventListener('blur', function () { hitung(false); });
        if (setuju) {
            setuju.addEventListener('change', function () { hitung(false); });
        }
        /* Koreksi manual: kolom itu berhenti dihitung otomatis. */
        if (autoTerlambat) {
            terlambat.addEventListener('input', function () { autoTerlambat.value = '0'; });
        }
        if (autoLembur) {
            lembur.addEventListener('input', function () { autoLembur.value = '0'; });
        }

        /* Keadaan awal mengikuti data tersimpan & status yang sudah dipilih. */
        kunciKolom();
        if (hadir()) {
            if (!masuk.value) { masuk.value = tr.getAttribute('data-masuk-standar') || '08:00'; }
            if (!pulang.value) { pulang.value = tr.getAttribute('data-pulang-standar') || '20:00'; }
        }
    }

    /* ------------------------------------------------------------------
       HARI MINGGU: status hanya Hadir/Libur dan satu-satunya isian adalah
       JUMLAH JAM KERJA. Kolom jam masuk/pulang & lembur tidak ada di baris ini.
       ------------------------------------------------------------------ */
    function pasangBarisMinggu(tr) {
        var sel = tr.querySelector('.sel-status-minggu');
        var jam = tr.querySelector('.jam-kerja-minggu');
        if (!sel || !jam) { return; }

        function terapkan() {
            var hadir = sel.value === 'HADIR';
            jam.disabled = !hadir;
            if (!hadir) { jam.value = ''; }
        }
        sel.addEventListener('change', function () {
            terapkan();
            if (sel.value === 'HADIR' && jam.value.trim() === '') { jam.focus(); }
        });
        /* Rapikan angka: terima koma atau titik, batasi 0–24 jam. */
        jam.addEventListener('blur', function () {
            var t = jam.value.trim().replace(',', '.');
            if (t === '') { return; }
            var n = parseFloat(t);
            if (isNaN(n) || n < 0) { jam.value = ''; return; }
            if (n > 24) { n = 24; }
            /* Ditampilkan dengan koma (gaya Indonesia) agar konsisten dengan angka lain di aplikasi. */
            jam.value = (Math.round(n * 100) / 100).toString().replace('.', ',');
        });
        jam.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); jam.blur(); }
        });
        terapkan();
    }

    function pasangHitungMenit() {
        document.querySelectorAll('tr.baris-absen:not(.baris-minggu)').forEach(pasangBarisAbsen);
        document.querySelectorAll('tr.baris-minggu').forEach(pasangBarisMinggu);
    }

    /* ------------------------------------------- pratinjau gambar terunggah */
    function pasangPratinjauBerkas() {
        document.querySelectorAll('input[type=file][data-pratinjau]').forEach(function (inp) {
            var target = document.querySelector(inp.getAttribute('data-pratinjau'));
            if (!target) { return; }
            inp.addEventListener('change', function () {
                var f = inp.files && inp.files[0];
                if (!f) { return; }
                var url = URL.createObjectURL(f);
                if (target.tagName === 'IMG') { target.src = url; target.hidden = false; }
                else { target.innerHTML = '<img src="' + url + '" alt="Pratinjau" class="slip-logo">'; }
            });
        });
    }

    /* ------------------------------------------------------------- cetak */
    function pasangCetak() {
        document.querySelectorAll('[data-cetak]').forEach(function (b) {
            b.addEventListener('click', function (e) {
                e.preventDefault();
                window.print();
            });
        });
    }

    /* --------------------------------------------- salin ke papan klip */
    function pasangSalin() {
        document.querySelectorAll('[data-salin]').forEach(function (b) {
            b.addEventListener('click', function (e) {
                e.preventDefault();
                var el = document.querySelector(b.getAttribute('data-salin'));
                if (!el) { return; }
                var teks = el.value !== undefined ? el.value : el.textContent;
                var selesai = function () {
                    var asli = b.textContent;
                    b.textContent = 'Tersalin';
                    setTimeout(function () { b.textContent = asli; }, 1500);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(teks).then(selesai, function () {});
                } else {
                    el.select && el.select();
                    document.execCommand('copy');
                    selesai();
                }
            });
        });
    }

    /* -------------------------------------------------- tampilkan/sembunyikan */
    function pasangSaklar() {
        document.querySelectorAll('[data-saklar]').forEach(function (inp) {
            var target = document.querySelectorAll(inp.getAttribute('data-saklar'));
            function terapkan() {
                target.forEach(function (t) { t.hidden = !inp.checked; });
            }
            inp.addEventListener('change', terapkan);
            terapkan();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        pasangTab();
        pasangKonfirmasi();
        pasangHitungMenit();
        pasangTanggal();
        pasangPratinjauBerkas();
        pasangCetak();
        pasangSalin();
        pasangSaklar();
    });
})();
