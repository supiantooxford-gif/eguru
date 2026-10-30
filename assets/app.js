/* ===== e-GURU — interaksi halaman & grafik ===== */
(function () {
  'use strict';

  /* ---------- Sidebar (mobile) ---------- */
  function initNav() {
    var t = document.getElementById('navToggle');
    var s = document.getElementById('sidebar');
    var sc = document.getElementById('scrim');
    if (!t || !s) return;
    function close() { s.classList.remove('open'); if (sc) sc.classList.remove('on'); }
    t.addEventListener('click', function () {
      s.classList.toggle('open');
      if (sc) sc.classList.toggle('on', s.classList.contains('open'));
    });
    if (sc) sc.addEventListener('click', close);
  }

  /* ---------- Konfirmasi hapus ---------- */
  function initConfirm() {
    document.addEventListener('submit', function (ev) {
      var f = ev.target;
      if (f.matches('form[data-confirm]') && !window.confirm(f.getAttribute('data-confirm'))) ev.preventDefault();
    });
    document.addEventListener('click', function (ev) {
      var a = ev.target.closest('a[data-confirm]');
      if (a && !window.confirm(a.getAttribute('data-confirm'))) ev.preventDefault();
    });
  }

  /* ---------- Jurnal Harian: Uraian Kegiatan + Kelas/Tempat (permintaan pemilik, v1.37.0) ----------
     Dua bantuan saja, keduanya juga DITEGAKKAN di server (jurnal.php + jurnal_formatif… lihat
     jurnal_uraian_sah()/validasi jurnal.php):
       1. "Uraian Kegiatan" — bila bukan kegiatan mengajar, Mata Pelajaran & Materi/Topik TIDAK wajib
          diisi (tanda * disembunyikan). Tanpa JavaScript pun tetap bekerja karena atribut required
          tidak pernah dicetak oleh server.
       2. "Kelas / Tempat"  — pilih kelas ATAU isi tempat (cukup salah satu): memilih kelas
          mengosongkan isian tempat, dan mengetik tempat membatalkan pilihan kelas. Kedua isian
          tetap AKTIF (tidak dinonaktifkan) supaya guru tidak terjebak — penjagaan "tidak boleh
          keduanya" tetap ditegakkan server, dan tanpa JavaScript pun formulir tetap terkirim. */
  function initJurnalForm() {
    var form = document.getElementById('formJurnal');
    if (!form) return;
    var uraian = document.getElementById('uraian');
    var wajib = form.querySelectorAll('[data-wajib-jurnal]');
    var tanda = form.querySelectorAll('[data-wajib-mengajar]');
    var kelas = document.getElementById('kelas_id');
    var tempat = document.getElementById('tempat');

    function mengajar() {
      var v = uraian ? String(uraian.value || '').trim().toLowerCase() : '';
      return v === '' || v === 'mengajar';
    }
    function aturWajib() {
      var ya = mengajar();
      wajib.forEach(function (i) { i.required = ya; });
      tanda.forEach(function (t) { t.hidden = !ya; });
    }
    function aturKelasTempat() {
      if (!kelas || !tempat) return;
      // Cukup salah satu: begitu kelas dipilih, isian tempat dikosongkan (isian tetap aktif
      // supaya guru dapat langsung menggantinya bila ingin menulis tempat).
      if (String(kelas.value || '') !== '') {
        tempat.value = '';
      }
    }

    if (uraian) {
      uraian.addEventListener('input', aturWajib);
      uraian.addEventListener('change', aturWajib);
    }
    if (kelas) {
      kelas.addEventListener('change', aturKelasTempat);
    }
    if (tempat) {
      // Mengetik tempat = membatalkan pilihan kelas (tidak boleh dua-duanya).
      tempat.addEventListener('input', function () {
        if (String(tempat.value || '').trim() !== '' && kelas) kelas.value = '';
      });
      tempat.addEventListener('change', function () {
        if (String(tempat.value || '').trim() !== '' && kelas) kelas.value = '';
        aturKelasTempat();
      });
    }

    // Perapian sekali di awal (mis. mode Ubah dengan salah satu sudah terisi).
    if (tempat && String(tempat.value || '').trim() !== '' && kelas) kelas.value = '';
    aturWajib();
    aturKelasTempat();
  }

  /* ---------- Saring tabel cepat ---------- */
  function initTableSearch() {
    var inp = document.getElementById('tblSearch');
    if (!inp) return;
    var tbl = document.getElementById(inp.dataset.target || '');
    if (!tbl) return;
    inp.addEventListener('input', function () {
      var v = inp.value.toLowerCase().trim();
      var shown = 0;
      tbl.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.dataset.nofilter === '1') return;
        var hit = tr.textContent.toLowerCase().indexOf(v) !== -1;
        tr.style.display = hit ? '' : 'none';
        if (hit) shown++;
      });
      var c = document.getElementById('tblCount');
      if (c) c.textContent = shown;
    });
  }

  /* ---------- Filter otomatis (select dengan data-auto) ---------- */
  function initAutoFilter() {
    document.querySelectorAll('select[data-auto]').forEach(function (s) {
      s.addEventListener('change', function () {
        var f = s.closest('form');
        if (f) f.submit();
      });
    });
  }

  /* ---------- Nilai: hitung Nilai Akhir & predikat secara hidup ---------- */
  function angka(inp) {
    var v = parseFloat(String(inp.value).replace(',', '.'));
    return isNaN(v) ? null : Math.max(0, Math.min(100, v));
  }

  function initNilaiGrid() {
    var form = document.getElementById('formNilai');
    if (!form) return;
    var B = (typeof window.EG_BOBOT === 'number') ? window.EG_BOBOT : 50;
    var TH = window.EG_TH || { a: 90, b: 80, c: 70 };

    function predikat(n) {
      if (n >= TH.a) return 'A';
      if (n >= TH.b) return 'B';
      if (n >= TH.c) return 'C';
      return 'D';
    }

    function hitung(tr) {
      var sum = [], asas = null;
      tr.querySelectorAll('input[data-jenis]').forEach(function (inp) {
        var v = angka(inp);
        if (inp.dataset.jenis === 'sumatif' && v !== null) sum.push(v);
        if (inp.dataset.jenis === 'asas') asas = v;
      });
      var rataS = sum.length ? sum.reduce(function (a, b) { return a + b; }, 0) / sum.length : null;
      var akhir = null;
      if (rataS !== null && asas !== null) akhir = rataS * (100 - B) / 100 + asas * B / 100;
      else if (rataS !== null) akhir = rataS;
      else if (asas !== null) akhir = asas;

      var tdNa = tr.querySelector('td.na');
      var tdPr = tr.querySelector('td.predikat');
      if (tdNa) {
        if (akhir === null) {
          tdNa.textContent = '—';
          tdNa.classList.add('belum');
        } else {
          tdNa.textContent = akhir.toFixed(2).replace('.', ',');
          tdNa.classList.remove('belum');
        }
      }
      if (tdPr) {
        if (akhir === null) {
          tdPr.innerHTML = '<span class="mut xs">-</span>';
        } else {
          var p = predikat(akhir);
          tdPr.innerHTML = '<span class="pill pill-' + p + '">' + p + '</span>';
        }
      }
      return { akhir: akhir, rataS: rataS };
    }

    function refresh() {
      var rows = form.querySelectorAll('tbody tr[data-siswa]');
      var jumlah = 0, total = 0;
      rows.forEach(function (tr) {
        var h = hitung(tr);
        if (h.akhir !== null) { jumlah++; total += h.akhir; }
      });
      var out = document.getElementById('nilaiStat');
      if (out) {
        out.textContent = jumlah > 0
          ? jumlah + ' dari ' + rows.length + ' siswa memiliki nilai akhir · rata-rata kelas ' + (total / jumlah).toFixed(2).replace('.', ',')
          : 'Belum ada nilai yang terisi.';
      }
    }

    /* Kolom FORMATIF boleh berisi angka, huruf, atau tanda centang (permintaan pemilik). Saat
       pengguna selesai mengisi (change/blur), isinya dirapikan dengan ATURAN YANG SAMA dengan
       server (nilai_formatif_sah() di inc/helpers.php): ejaan centang apa pun → '✓', huruf
       dijadikan huruf besar & dipotong 8 karakter, angka dibiarkan. */
    var CENTANG = ['v', 'vh', 'cek', 'check', 'centang', '✓', '✔', '√'];
    function rapikanFormatif(inp) {
      var s = String(inp.value || '').trim();
      if (s === '') { inp.value = ''; return; }
      if (/^[0-9]+([.,][0-9]+)?$/.test(s)) return;             // angka → biarkan
      var kunci = s.toLowerCase().replace(/[\s.\-]+/g, '');
      if (CENTANG.indexOf(kunci) >= 0) { inp.value = '✓'; return; }
      inp.value = s.toUpperCase().slice(0, 8);
    }

    form.addEventListener('input', function (ev) {
      if (ev.target.matches('input[data-jenis]')) {
        var v = angka(ev.target);
        if (v !== null && v !== parseFloat(String(ev.target.value).replace(',', '.'))) ev.target.value = v;
        refresh();
      }
    });
    form.addEventListener('change', function (ev) {
      if (!ev.target.matches('input[data-jenis]')) return;
      if (ev.target.dataset.jenis === 'formatif') rapikanFormatif(ev.target);
      refresh();
    });
    refresh();
  }

  /* ---------- Daftar hadir: status H/S/I/A ---------- */
  function initHadirGrid() {
    // Tombol H/S/I/A (.st) dipakai DUA tempat: menu "Daftar Hadir Siswa" (#formHadir) dan
    // "Absensi Kelas" pada menu Wali Kelas (#formAbsensiWaliKirim).
    // Dulu fungsi ini hanya terikat ke #formHadir, sehingga di Absensi Kelas klik S/I/A tidak
    // memindahkan kelas "sel" (penanda hijau) — tombol H tetap hijau dan tombol lain tampak
    // "tidak bisa dipilih" (dilaporkan pemilik). Sekarang penandanya digeneralisasi.
    if (!document.querySelector('.st')) return;

    function tandai(lb) {
      if (!lb) return;
      var grup = lb.parentNode;
      // Hanya kelas "sel" yang ditukar — kelas dasar (sel-H/sel-S/sel-I/sel-A) tetap
      // dipertahankan pada setiap label sebagai penanda statusnya.
      grup.querySelectorAll('label').forEach(function (x) {
        x.classList.remove('sel');
        var i = x.querySelector('input');
        if (i) { x.classList.add('sel-' + i.value); }
      });
      lb.classList.add('sel');
    }

    function segarkanRingkas(form) {
      if (!form) return;
      // Angka ringkasan (jmlH/jmlS/jmlI/jmlA) hanya ada di halaman Daftar Hadir Siswa; di halaman
      // lain elemennya tidak ditemukan sehingga bagian ini otomatis dilewati.
      var c = { H: 0, S: 0, I: 0, A: 0 };
      form.querySelectorAll('input[type=radio][data-st]:checked').forEach(function (i) {
        if (c[i.value] !== undefined) c[i.value]++;
      });
      ['H', 'S', 'I', 'A'].forEach(function (k) {
        var el = document.getElementById('jml' + k);
        if (el) el.textContent = c[k];
      });
    }

    // Delegasi di document: radio ber-atribut data-st di halaman mana pun ikut ditandai.
    document.addEventListener('change', function (ev) {
      var t = ev.target;
      if (t && t.matches && t.matches('input[type=radio][data-st]') && t.checked) {
        tandai(t.closest('label'));
        segarkanRingkas(t.form);
      }
    });

    // Tombol "Tandai Semua Hadir" memakai id berbeda di tiap halaman.
    [
      { form: 'formHadir', tombol: 'btnSemuaHadir' },                 // Daftar Hadir Siswa
      { form: 'formAbsensiWaliKirim', tombol: 'btnSemuaHadirWali' }    // Absensi Kelas (Wali Kelas)
    ].forEach(function (p) {
      var form = document.getElementById(p.form);
      if (!form) return;
      var semua = document.getElementById(p.tombol);
      if (semua) {
        semua.addEventListener('click', function () {
          form.querySelectorAll('tr[data-siswa]').forEach(function (tr) {
            var inp = tr.querySelector('input[type=radio][data-st][value="H"]');
            if (inp) { inp.checked = true; tandai(inp.closest('label')); }
          });
          segarkanRingkas(form);
        });
      }
      segarkanRingkas(form);
    });
  }

  /* ---------- Foto profil: pratinjau & pemeriksaan ukuran ---------- */
  function initFoto() {
    var inp = document.getElementById('foto');
    if (!inp) return;
    var wrap = document.getElementById('fotoPreviewWrap');
    var prev = document.getElementById('fotoPreview');
    var info = document.getElementById('fotoUkuran');
    var hint = document.getElementById('fotoHint');
    var btn = document.getElementById('btnFoto');
    var form = document.getElementById('formFoto');
    var status = document.getElementById('fotoStatus');
    var MAKS = 2 * 1024 * 1024;   // batas per berkas 2 MB (UNGGAH_MAKS_BYTE di inc/config.php)
    var OK = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    function mb(b) { return (b / 1048576).toFixed(b < 1048576 ? 2 : 1).replace('.', ',') + ' MB'; }

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f) return;
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (OK.indexOf(ext) === -1) {
        hint.innerHTML = '<b style="color:#b91c1c">Format .' + ext + ' tidak didukung.</b> Pakai JPG, PNG, GIF, WEBP, atau SVG.';
        if (wrap) wrap.hidden = true;
        if (btn) btn.disabled = true;
        return;
      }
      if (f.size > MAKS) {
        hint.innerHTML = '<b style="color:#b91c1c">Terlalu besar (' + mb(f.size) + ').</b> Maksimal 2 MB — perkecil dulu fotonya.';
        if (wrap) wrap.hidden = true;
        if (btn) btn.disabled = true;
        return;
      }
      if (btn) btn.disabled = false;
      hint.innerHTML = 'Berkas siap diunggah: <b>' + f.name.replace(/[<>&"]/g, '') + '</b>';
      if (info) info.textContent = mb(f.size);
      if (ext === 'svg') {
        if (wrap) wrap.hidden = true;
        return;
      }
      var fr = new FileReader();
      fr.onload = function () {
        if (prev) prev.innerHTML = '<img src="' + fr.result + '" alt="Pratinjau foto">';
        if (wrap) wrap.hidden = false;
      };
      fr.readAsDataURL(f);
    });

    if (form) {
      form.addEventListener('submit', function () {
        if (btn) { btn.disabled = true; btn.textContent = 'Mengunggah...'; }
        if (status) status.textContent = 'Mohon tunggu, foto sedang diunggah.';
      });
    }
  }

  /* ---------- Pas foto siswa 3x4: pratinjau & pemeriksaan ukuran (form tambah/ubah siswa) ----------
     PENTING: memakai id "fotoSiswa" (BUKAN "foto") agar tidak bertabrakan dengan input foto PROFIL
     di halaman Akun Saya yang ditangani initFoto(). */
  function initPasFotoSiswa() {
    var inp = document.getElementById('fotoSiswa');
    if (!inp) return;
    var kotak = document.getElementById('pasfotoKotak');
    var hint = document.getElementById('pasfotoHint');
    var status = document.getElementById('pasfotoStatus');
    var btn = document.getElementById('btnSiswa');
    var hapus = document.getElementById('hapusFoto');
    var form = document.getElementById('formSiswa');
    var MAKS = 2 * 1024 * 1024;   // batas per berkas 2 MB (UNGGAH_MAKS_BYTE di inc/config.php)
    var OK = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    function mb(b) { return (b / 1048576).toFixed(b < 1048576 ? 2 : 1).replace('.', ',') + ' MB'; }

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f) return;
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (OK.indexOf(ext) === -1) {
        if (hint) hint.innerHTML = '<b style="color:#b91c1c">Format .' + ext + ' tidak didukung.</b> Pakai JPG, PNG, GIF, WEBP, atau SVG.';
        inp.value = '';
        return;
      }
      if (f.size > MAKS) {
        if (hint) hint.innerHTML = '<b style="color:#b91c1c">Terlalu besar (' + mb(f.size) + ').</b> Maksimal 2 MB &mdash; perkecil dulu fotonya.';
        inp.value = '';
        return;
      }
      // Mengunggah foto baru membatalkan pilihan "Hapus pas foto".
      if (hapus) { hapus.checked = false; hapus.disabled = true; }
      if (hint) hint.innerHTML = 'Siap diunggah: <b>' + f.name.replace(/[<>&"]/g, '') + '</b> (' + mb(f.size) + ') &mdash; disimpan bersama data siswa.';
      if (status) status.textContent = 'Foto akan diunggah saat tombol Simpan ditekan.';
      if (ext === 'svg') return;
      var fr = new FileReader();
      fr.onload = function () {
        var img = new Image();
        img.onload = function () {
          var w = img.naturalWidth, h = img.naturalHeight;
          if (w && h && hint) {
            var rasio = w / h;
            if (h <= w) {
              hint.innerHTML += '<br><b style="color:#b45309">Catatan:</b> foto ini mendatar; pas foto 3 x 4 sebaiknya tegak.';
            } else if (Math.abs(rasio - 0.75) > 0.08) {
              hint.innerHTML += '<br><b style="color:#b45309">Catatan:</b> rasio ' + w + ':' + h + ' bukan 3:4 &mdash; pada kartu pelajar foto akan dipotong otomatis.';
            }
            if (w < 240) {
              hint.innerHTML += '<br><b style="color:#b45309">Catatan:</b> resolusi ' + w + ' x ' + h + ' px cukup kecil untuk dicetak.';
            }
          }
        };
        img.src = fr.result;
        if (kotak) {
          kotak.innerHTML = '<img src="' + fr.result + '" alt="Pratinjau pas foto">';
        }
      };
      fr.readAsDataURL(f);
    });

    if (form) {
      form.addEventListener('submit', function () {
        if (inp.files && inp.files.length && btn) {
          btn.disabled = true;
          btn.textContent = 'Menyimpan & mengunggah foto...';
          if (status) status.textContent = 'Mohon tunggu, pas foto sedang diunggah.';
        }
      });
    }
  }

  /* ---------- "Tentang Aplikasi" (tentang.php): pratinjau nama & foto ----------
     Permintaan pemilik: "Pada halaman depan di bagian tentang aplikasi tambahkan tempat foto, nama
     dan keterangan dengan posisi atau letak dibuat sama dengan aplikasi supervisi" — halaman
     pengisiannya (tentang.php) memakai pratinjau hidup seperti aplikasi Supervisi: nama yang
     diketik langsung tampil di bawah kotak foto, dan berkas yang dipilih langsung tampil di
     kotaknya. Skrip ini hanya PELENGKAP: tanpa JavaScript pun formulirnya tetap bisa disimpan. */
  function initTentang() {
    var form = document.getElementById('formTentang');
    if (!form) return;
    var btn = document.getElementById('btnTentang');
    var status = document.getElementById('tentangStatus');
    var MAKS = 2 * 1024 * 1024;   // batas per berkas 2 MB (UNGGAH_MAKS_BYTE di inc/config.php)
    var OK = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    function mb(b) { return (b / 1048576).toFixed(b < 1048576 ? 2 : 1).replace('.', ',') + ' MB'; }

    // Nama: tampil langsung di bawah kotak fotonya.
    Array.prototype.forEach.call(document.querySelectorAll('[data-pratinjau-nama]'), function (inp) {
      var tgt = document.getElementById(inp.getAttribute('data-pratinjau-nama'));
      if (!tgt) return;
      inp.addEventListener('input', function () {
        var v = inp.value.trim();
        tgt.innerHTML = v === '' ? '<span class="mut">(nama belum diisi)</span>' : '';
        if (v !== '') tgt.textContent = v;
      });
    });

    // Foto: pratinjau berkas terpilih + pemeriksaan format & ukuran.
    Array.prototype.forEach.call(document.querySelectorAll('[data-pratinjau-foto]'), function (inp) {
      var bingkai = document.getElementById(inp.getAttribute('data-pratinjau-foto'));
      var hint = document.getElementById('hintFoto' + (inp.id.split('_').pop() || ''));
      inp.addEventListener('change', function () {
        var f = inp.files && inp.files[0];
        if (!f) return;
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        if (OK.indexOf(ext) === -1) {
          if (hint) hint.innerHTML = '<b style="color:#b91c1c">Format .' + ext + ' tidak didukung.</b> Pakai PNG, JPG, GIF, WEBP, atau SVG.';
          if (btn) btn.disabled = true;
          return;
        }
        if (f.size > MAKS) {
          if (hint) hint.innerHTML = '<b style="color:#b91c1c">Terlalu besar (' + mb(f.size) + ').</b> Maksimal 2 MB.';
          if (btn) btn.disabled = true;
          return;
        }
        if (btn) btn.disabled = false;
        if (hint) hint.innerHTML = 'Berkas siap diunggah: <b>' + f.name.replace(/[<>&"]/g, '') + '</b> (' + mb(f.size) + ')';
        if (!bingkai || ext === 'svg') return;
        var fr = new FileReader();
        fr.onload = function () {
          bingkai.innerHTML = '<img src="' + fr.result + '" alt="Pratinjau foto">';
        };
        fr.readAsDataURL(f);
      });
    });

    form.addEventListener('submit', function () {
      if (btn) btn.disabled = true;
      if (status) status.textContent = 'Mohon tunggu, isian sedang disimpan…';
    });
  }

  /* ---------- Logo sekolah: pratinjau & pemeriksaan ukuran ---------- */
  function initLogo() {
    var inp = document.getElementById('logo');
    if (!inp) return;
    var wrap = document.getElementById('logoPreviewWrap');
    var prev = document.getElementById('logoPreview');
    var info = document.getElementById('logoUkuran');
    var hint = document.getElementById('logoHint');
    var btn = document.getElementById('btnLogo');
    var status = document.getElementById('logoStatus');
    var MAKS = 2 * 1024 * 1024;   // batas per berkas 2 MB (UNGGAH_MAKS_BYTE di inc/config.php)
    var OK = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    function mb(b) { return (b / 1048576).toFixed(b < 1048576 ? 2 : 1).replace('.', ',') + ' MB'; }

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f) return;
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (OK.indexOf(ext) === -1) {
        hint.innerHTML = '<b style="color:#b91c1c">Format .' + ext + ' tidak didukung.</b> Pakai PNG, JPG, GIF, WEBP, atau SVG.';
        if (wrap) wrap.hidden = true;
        if (btn) btn.disabled = true;
        return;
      }
      if (f.size > MAKS) {
        hint.innerHTML = '<b style="color:#b91c1c">Terlalu besar (' + mb(f.size) + ').</b> Maksimal 2 MB — perkecil dulu berkasnya.';
        if (wrap) wrap.hidden = true;
        if (btn) btn.disabled = true;
        return;
      }
      if (btn) btn.disabled = false;
      hint.innerHTML = 'Berkas siap diunggah: <b>' + f.name.replace(/[<>&"]/g, '') + '</b>';
      if (info) info.textContent = mb(f.size);
      if (ext === 'svg') {
        if (wrap) wrap.hidden = true;
        return;
      }
      var fr = new FileReader();
      fr.onload = function () {
        if (prev) prev.innerHTML = '<img src="' + fr.result + '" alt="Pratinjau logo">';
        if (wrap) wrap.hidden = false;
      };
      fr.readAsDataURL(f);
    });

    var form = document.getElementById('formLogo');
    if (form) {
      form.addEventListener('submit', function () {
        if (btn) btn.disabled = true;
        if (status) status.textContent = 'Mohon tunggu, logo sedang diunggah.';
      });
    }
  }

  /* ---------- Jenis sekolah: tandai perubahan yang belum disimpan ---------- */
  function initJenis() {
    var sel = document.getElementById('jenisSekolah');
    if (!sel) return;
    var simpan = document.getElementById('btnSimpanJenis');
    var catatan = document.getElementById('jenisCatatan');
    function cek() {
      var beda = sel.value !== (sel.dataset.tersimpan || 'umum');
      if (simpan) simpan.classList.toggle('btn-primary', beda);
      if (catatan) catatan.textContent = beda ? 'Pilihan berubah — klik "Simpan Jenis" untuk menerapkan.' : 'Istilah yang dipakai saat ini.';
    }
    sel.addEventListener('change', cek);
    cek();
  }

  /* ---------- Pilihan JENJANG mengikuti JENIS LEMBAGA (skema v49) ----------
     Permintaan pemilik: Sekolah Umum → jenjang SD/SMP/SMA/SMK · Madrasah → MI/MTs/MA/MAK.
     Pilihan awal sudah dicetak SERVER (jadi tetap benar tanpa JavaScript); skrip ini hanya menyusun
     ulang daftar pilihan ketika pilihan jenis diubah di peramban. Server tetap memeriksa keabsahan
     nilai yang dikirim, jadi pilihan yang tidak cocok tidak akan tersimpan. */
  function initJenisJenjang() {
    var pasangan = [
      { jenis: 'jenisSekolah', jenjang: 'jenjangSekolah' },   // Identitas & Pengaturan
      { jenis: 'jenis', jenjang: 'jenjang' },                 // halaman Pendaftaran
      { jenis: 'jenis', jenjang: 'jenjang' },                 // Kelola Sekolah (form Tambah)
      { jenis: 'ejenis', jenjang: 'ejenjang' }                // Kelola Sekolah (panel Ubah)
    ];
    pasangan.forEach(function (p) {
      var selJenis = document.getElementById(p.jenis);
      var selJenjang = document.getElementById(p.jenjang);
      if (!selJenis || !selJenjang || selJenjang.dataset.jenjangSiap === '1') return;
      var peta, label;
      try {
        peta = JSON.parse(selJenjang.dataset.jenjang || '{}');
        label = JSON.parse(selJenjang.dataset.label || '{}');
      } catch (e) { return; }
      if (!peta || !peta.umum || !peta.madrasah) return;
      selJenjang.dataset.jenjangSiap = '1';
      var terpilih = selJenjang.value;

      function susun() {
        var jenis = selJenis.value === 'madrasah' ? 'madrasah' : 'umum';
        var daftar = peta[jenis] || [];
        // Simpan nilai sekarang; bila tidak berlaku untuk jenis baru, pilihan dikosongkan.
        var simpan = selJenjang.value;
        selJenjang.innerHTML = '';
        var kosong = document.createElement('option');
        kosong.value = '';
        kosong.textContent = '— belum dipilih —';
        selJenjang.appendChild(kosong);
        daftar.forEach(function (kode) {
          var o = document.createElement('option');
          o.value = kode;
          o.textContent = label[kode] || kode;
          selJenjang.appendChild(o);
        });
        selJenjang.value = daftar.indexOf(simpan) >= 0 ? simpan : '';
      }

      selJenis.addEventListener('change', susun);
      // Susun sekali saat halaman dibuka agar pilihan selalu cocok dengan jenis yang terpilih.
      selJenjang.value = terpilih;
      susun();
    });
  }

  /* ---------- GURU KELAS (jenjang SD/MI): satu kelas + penanda status ---------- */
  function initGuruKelas() {
    var chk = document.getElementById('guruKelas');
    if (!chk) return;
    var catatan = document.getElementById('guruKelasCatatan');
    var pil = document.getElementById('pilGuruKelas');
    var kotak = document.querySelectorAll('input[name="kelas[]"]');

    function atur() {
      var aktif = chk.checked;
      if (pil) {
        pil.textContent = aktif ? 'Guru Kelas' : 'Guru Mata Pelajaran';
        pil.classList.toggle('pill-ok', aktif);
        pil.classList.toggle('pill-gray', !aktif);
      }
      if (catatan) {
        catatan.textContent = aktif
          ? 'Centang tepat SATU kelas pada bagian "Kelas yang Diajar" — guru otomatis menjadi wali kelasnya.'
          : 'Jenjang SD/MI memakai konsep guru kelas: satu kelas, banyak mata pelajaran.';
      }
      // Saat ditandai guru kelas, centang kelas berikutnya membatalkan centang sebelumnya
      // (server juga menolak lebih dari satu kelas).
      kotak.forEach(function (k) {
        if (aktif && k.checked) {
          kotak.forEach(function (l) { if (l !== k) l.checked = false; });
        }
      });
    }

    kotak.forEach(function (k) {
      k.addEventListener('change', function () {
        if (!chk.checked || !k.checked) return;
        kotak.forEach(function (l) { if (l !== k) l.checked = false; });
      });
    });
    chk.addEventListener('change', atur);
    atur();
  }

  /* ---------- NSM hanya untuk MADRASAH (permintaan pemilik) ----------
     Pada halaman pendaftaran, medan NSM disembunyikan ketika jenis "Sekolah Umum" dipilih karena
     sekolah umum tidak memiliki NSM. Server JUGA mencetak atribut `hidden` saat halaman dirender
     ulang dengan jenis umum, jadi tanpa JavaScript pun keadaan awalnya sudah benar — skrip ini
     hanya mengikuti perubahan pilihan di peramban. */
  function initJenisNsm() {
    var sel = document.getElementById('jenis');
    var blok = document.getElementById('blokNsm');
    if (!sel || !blok) return;
    function atur() {
      blok.hidden = String(sel.value) === 'umum';
    }
    sel.addEventListener('change', atur);
    atur();
  }

  /* ---------- Notulen rapat: cari guru, tandai semua, hitung hadir terpilih ---------- */
  function initNotulen() {
    var tbl = document.getElementById('tblPilihGuru');
    if (!tbl) return;
    var cari = document.getElementById('cariGuru');
    var ringkas = document.getElementById('ringkasHadir');
    var kotak = tbl.querySelectorAll('input.chk-hadir');

    function update() {
      if (!ringkas) return;
      var n = 0;
      kotak.forEach(function (c) { if (c.checked) n++; });
      ringkas.value = n + ' guru';
    }
    kotak.forEach(function (c) { c.addEventListener('change', update); });

    if (cari) {
      cari.addEventListener('input', function () {
        var v = cari.value.toLowerCase().trim();
        tbl.querySelectorAll('tbody tr').forEach(function (tr) {
          var hit = (tr.dataset.nama || tr.textContent.toLowerCase()).indexOf(v) !== -1;
          tr.style.display = hit ? '' : 'none';
        });
      });
    }
    // "Tandai semua" hanya menandai baris yang SEDANG TAMPIL (hasil pencarian), supaya tidak
    // menandai guru yang sedang disembunyikan pencarian.
    function semua(nilai) {
      tbl.querySelectorAll('tbody tr').forEach(function (tr) {
        if (tr.style.display === 'none') return;
        var c = tr.querySelector('input.chk-hadir');
        if (c) c.checked = nilai;
      });
      update();
    }
    var btnSemua = document.getElementById('btnHadirSemua');
    if (btnSemua) btnSemua.addEventListener('click', function () { semua(true); });
    var btnKosong = document.getElementById('btnHadirKosong');
    if (btnKosong) btnKosong.addEventListener('click', function () { semua(false); });
    update();
  }

  /* ---------- Foto bukti kegiatan notulen: pratinjau & pemeriksaan berkas ---------- */
  function initFotoNotulen() {
    var inp = document.getElementById('fotoNotulen');
    if (!inp) return;
    var wrap = document.getElementById('fotoNotulenPreviewWrap');
    var prev = document.getElementById('fotoNotulenPreview');
    var info = document.getElementById('fotoNotulenUkuran');
    var hint = document.getElementById('fotoNotulenHint');
    var btn = document.getElementById('btnFotoNotulen');
    var form = document.getElementById('formFotoNotulen');
    var status = document.getElementById('fotoNotulenStatus');
    var MAKS = 2 * 1024 * 1024;   // batas per berkas 2 MB (UNGGAH_MAKS_BYTE di inc/config.php)
    var OK = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    function mb(b) { return (b / 1048576).toFixed(b < 1048576 ? 2 : 1).replace('.', ',') + ' MB'; }

    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f) return;
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (OK.indexOf(ext) === -1) {
        hint.innerHTML = '<b style="color:#b91c1c">Format .' + ext + ' tidak didukung.</b> Pakai JPG, PNG, GIF, WEBP, atau SVG.';
        if (wrap) wrap.hidden = true;
        if (btn) btn.disabled = true;
        return;
      }
      if (f.size > MAKS) {
        hint.innerHTML = '<b style="color:#b91c1c">Terlalu besar (' + mb(f.size) + ').</b> Maksimal 2 MB — perkecil dulu fotonya.';
        if (wrap) wrap.hidden = true;
        if (btn) btn.disabled = true;
        return;
      }
      if (btn) btn.disabled = false;
      hint.innerHTML = 'Berkas siap diunggah: <b>' + f.name.replace(/[<>&"]/g, '') + '</b>';
      if (info) info.textContent = mb(f.size);
      if (ext === 'svg') {
        if (wrap) wrap.hidden = true;
        return;
      }
      var fr = new FileReader();
      fr.onload = function () {
        if (prev) prev.innerHTML = '<img src="' + fr.result + '" alt="Pratinjau foto bukti">';
        if (wrap) wrap.hidden = false;
      };
      fr.readAsDataURL(f);
    });

    if (form) {
      form.addEventListener('submit', function () {
        if (btn) { btn.disabled = true; btn.textContent = 'Mengunggah...'; }
        if (status) status.textContent = 'Mohon tunggu, foto sedang diunggah.';
      });
    }
  }

  /* ---------- Salin teks (mis. KODE AKTIVASI lembaga) ----------
     Dipakai tombol `data-salin="#selektor"` pada halaman Aktivasi / kelola sekolah. */
  function initSalinKode() {
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-salin]');
      if (!b) return;
      var el = document.querySelector(b.getAttribute('data-salin'));
      if (!el) return;
      var teks = (el.textContent || '').trim();
      var asli = b.getAttribute('data-asli') || b.innerHTML;
      b.setAttribute('data-asli', asli);
      function umpan(pesan) {
        b.innerHTML = pesan;
        window.setTimeout(function () { b.innerHTML = asli; }, 1800);
      }
      function cadangan() {
        try {
          var t = document.createElement('textarea');
          t.value = teks;
          t.style.position = 'fixed';
          t.style.opacity = '0';
          document.body.appendChild(t);
          t.select();
          document.execCommand('copy');
          document.body.removeChild(t);
          umpan('Tersalin');
        } catch (e) { umpan('Gagal menyalin'); }
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(teks).then(function () { umpan('Tersalin'); }, cadangan);
      } else {
        cadangan();
      }
    });
  }

  /* ---------- Popup "Selamat datang" (pengumuman sambutan) ----------
     Permintaan pemilik: popup selamat datang tampil SEKALI setiap kali pengguna masuk; isinya
     diatur Super Admin pada halaman Pengumuman Sambutan. Popup memakai elemen <dialog> bawaan
     peramban sehingga Esc & klik latar gelap ikut menutupnya tanpa skrip tambahan. Isi pengumuman
     ada pada markup (bukan diambil lewat AJAX) supaya tetap terbaca walau skrip ini gagal dimuat. */
  function initSambutan() {
    var pop = document.getElementById('popupSambutan');
    if (!pop) return;
    if (typeof pop.showModal === 'function') {
      try { pop.showModal(); } catch (e) { pop.setAttribute('open', ''); }
    } else {
      pop.setAttribute('open', '');
    }
    function tutup() {
      if (typeof pop.close === 'function' && pop.open) pop.close();
      else pop.removeAttribute('open');
    }
    document.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-sambutan="tutup"]')) tutup();
    });
    // Klik di area gelap (di luar kartu popup) menutup popup.
    pop.addEventListener('click', function (ev) {
      var kotak = pop.getBoundingClientRect();
      var luar = ev.clientX < kotak.left || ev.clientX > kotak.right
        || ev.clientY < kotak.top || ev.clientY > kotak.bottom;
      if (luar) tutup();
    });
  }

  /* ---------- Popup PERINGATAN AKTIVASI (masa aktif ≤ 7 hari) ----------
     Permintaan pemilik (v1.71.4): popup peringatan tampil di akun SEMUA pengguna lembaga itu sampai
     masa aktif benar-benar habis. Markupnya dicetak server (inc/layout.php → aktivasi_ingat_render())
     dengan atribut `open`, jadi isinya tetap terbaca walau skrip ini gagal dimuat; di sini popup
     dinaikkan menjadi modal (showModal) dan tombol Tutup/klik latar/Esc menutupnya. Popup TIDAK
     punya penanda sesi — memang muncul lagi pada halaman berikutnya selama sisanya ≤ 7 hari. */
  function initPeringatanAktivasi() {
    var pop = document.getElementById('popupPeringatanAktivasi');
    if (!pop) return;
    if (typeof pop.showModal === 'function') {
      try { pop.showModal(); } catch (e) { pop.setAttribute('open', ''); }
    } else {
      pop.setAttribute('open', '');
    }
    function tutup() {
      if (typeof pop.close === 'function' && pop.open) pop.close();
      else pop.removeAttribute('open');
    }
    pop.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-peringatan="tutup"]')) { tutup(); return; }
      var kotak = pop.getBoundingClientRect();
      var luar = ev.clientX < kotak.left || ev.clientX > kotak.right
        || ev.clientY < kotak.top || ev.clientY > kotak.bottom;
      if (luar) tutup();
    });
  }

  /* ---------- Hitung mundur masa uji coba (halaman Aktivasi) ----------
     Permintaan pemilik: "Pada bagian Status Aktivasi Lembaga tampilkan sisa hari dan jam dalam
     hitungan mundur". Waktu acuan diambil dari SERVER (data-sekarang) + waktu berjalan sejak halaman
     dibuka, jadi hitungan tetap benar walau jam perangkat pengguna meleset. Nilai awal sudah dicetak
     PHP sehingga tetap terbaca bila skrip ini gagal dimuat. Bila hitungan mencapai nol, halaman
     dimuat ulang sekali supaya penguncian (gerbang_aktivasi) langsung berlaku. */
  function initHitungMundur() {
    var kotak = document.querySelector('[data-hitung-mundur]');
    if (!kotak) return;
    var akhir = parseInt(kotak.getAttribute('data-berakhir'), 10);
    var dasar = parseInt(kotak.getAttribute('data-sekarang'), 10);
    if (!isFinite(akhir) || !isFinite(dasar) || akhir <= 0) return;
    var mulai = Date.now();
    var simpul = {
      hari: kotak.querySelector('[data-hm="hari"]'),
      jam: kotak.querySelector('[data-hm="jam"]'),
      menit: kotak.querySelector('[data-hm="menit"]'),
      detik: kotak.querySelector('[data-hm="detik"]')
    };
    var ket = kotak.querySelector('.hitung-mundur-ket');
    var sudahSelesai = false;
    function dua(n) { return (n < 10 ? '0' : '') + n; }
    function gambar() {
      var lewat = Math.floor((Date.now() - mulai) / 1000);
      var sisa = akhir - (dasar + lewat);
      if (sisa < 0) sisa = 0;
      var hari = Math.floor(sisa / 86400);
      var jam = Math.floor((sisa % 86400) / 3600);
      var menit = Math.floor((sisa % 3600) / 60);
      var detik = sisa % 60;
      if (simpul.hari) simpul.hari.textContent = String(hari);
      if (simpul.jam) simpul.jam.textContent = dua(jam);
      if (simpul.menit) simpul.menit.textContent = dua(menit);
      if (simpul.detik) simpul.detik.textContent = dua(detik);
      if (ket) {
        ket.textContent = 'Sisa ' + hari + ' hari ' + dua(jam) + ':' + dua(menit) + ':' + dua(detik);
      }
      if (sisa <= 0 && !sudahSelesai) {
        sudahSelesai = true;
        kotak.classList.add('habis');
        if (ket) ket.textContent = 'Masa uji coba baru saja berakhir — memuat ulang halaman...';
        window.setTimeout(function () { window.location.reload(); }, 2500);
      }
    }
    gambar();
    window.setInterval(gambar, 1000);
  }

  /* ---------- HALAMAN DEPAN (LANDING PAGE) pada login.php (skema v44) ----------
     Permintaan pemilik: "Buat halaman depan aplikasi menjadi tampilan web landing page yang
     menampilkan beranda, Slider (Judul, gambar, keterangan), fitur aplikasi, keunggulan,
     Total Pengguna, testimoni, kontak. Panel login tetap dipertahankan."

     Yang ditangani di sini: menu bilah navigasi pada layar kecil, SLIDER BERANDA (judul + gambar +
     keterangan) dan SLIDER TESTIMONI. Keduanya memakai MESIN yang sama (pasangSlider()).
     Tanpa JavaScript pun keduanya tetap bisa digeser sendiri (overflow-x + scroll-snap) dan seluruh
     slidenya tetap ada di HTML. */
  function initLanding() {
    var navToggle = document.getElementById('lpNavToggle');
    var navLink = document.getElementById('lpNavLink');
    if (navToggle && navLink) {
      navToggle.addEventListener('click', function () {
        navLink.classList.toggle('lp-nav-on');
      });
      navLink.addEventListener('click', function (ev) {
        if (ev.target.closest('a')) navLink.classList.remove('lp-nav-on');
      });
    }

    pasangSlider({
      slider: document.getElementById('lpSlider'),
      slide: '.lp-slide',
      // Wadah yang benar-benar digulir pada slider beranda adalah jalur slidenya (tombol panah &
      // titik berada DI LUAR jalur itu supaya tidak ikut tergulir).
      gulir: '.lp-slider-track',
      nav: document.getElementById('lpSliderNav'),
      titik: document.getElementById('lpSliderTitik'),
      panah: '.lp-slider-panah',
      label: 'Slide'
    });
    pasangSlider({
      slider: document.getElementById('testiSlider'),
      slide: '.lp-testi-slide',
      nav: document.getElementById('testiNav'),
      titik: document.getElementById('testiTitik'),
      panah: '.lp-testi-panah',
      label: 'Testimoni'
    });
  }

  /**
   * Mesin slider sederhana: geser satu layar sekali, tombol panah + titik penunjuk, dan geser
   * otomatis yang hanya DITUNDA satu siklus saat pengguna menyorot/menyentuh (bukan dimatikan).
   * Menghormati prefers-reduced-motion (tanpa geser otomatis).
   */
  function pasangSlider(opt) {
    var slider = opt.slider;
    if (!slider) return;
    var slides = slider.querySelectorAll(opt.slide);
    var nav = opt.nav;
    var titikWadah = opt.titik;
    // `gulir` = elemen yang benar-benar punya overflow-x (bila tidak diberikan, slider itu sendiri).
    var gulir = opt.gulir ? slider.querySelector(opt.gulir) : slider;
    if (!gulir || slides.length < 2 || !nav || !titikWadah) return;

    var jeda = parseInt(slider.getAttribute('data-otomatis'), 10) || 6000;
    var gerakMinimal = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var titik = [];
    var jam = null;

    function lebarSlide() {
      var a = slides[0].getBoundingClientRect();
      var b = slides.length > 1 ? slides[1].getBoundingClientRect() : null;
      return b ? (b.left - a.left) : a.width;
    }
    function perLayar() {
      var l = lebarSlide();
      return l > 0 ? Math.max(1, Math.round(gulir.clientWidth / l)) : 1;
    }
    function maksIndeks() { return Math.max(0, slides.length - perLayar()); }
    function indeksKini() {
      var l = lebarSlide();
      var i = l > 0 ? Math.round(gulir.scrollLeft / l) : 0;
      return Math.max(0, Math.min(i, maksIndeks()));
    }
    function tandai(i) {
      for (var j = 0; j < titik.length; j++) titik[j].classList.toggle('on', j === i);
    }
    function keSlide(i) {
      var mk = maksIndeks();
      if (i < 0) i = mk;
      if (i > mk) i = 0;
      gulir.scrollTo({ left: lebarSlide() * i, behavior: 'smooth' });
      tandai(i);
    }
    function bangunTitik() {
      titikWadah.innerHTML = '';
      titik = [];
      for (var i = 0; i <= maksIndeks(); i++) {
        (function (idx) {
          var b = document.createElement('button');
          b.type = 'button';
          b.setAttribute('aria-label', opt.label + ' ' + (idx + 1));
          b.addEventListener('click', function () { keSlide(idx); jadwalkan(jeda); });
          titikWadah.appendChild(b);
          titik.push(b);
        })(i);
      }
      tandai(Math.min(indeksKini(), maksIndeks()));
    }
    function hentikan() { if (jam) { window.clearTimeout(jam); jam = null; } }
    function jadwalkan(t) {
      hentikan();
      if (gerakMinimal) return;
      jam = window.setTimeout(function () {
        keSlide(indeksKini() + 1);
        jadwalkan(jeda);
      }, t);
    }

    nav.addEventListener('click', function (ev) {
      var btn = ev.target.closest(opt.panah);
      if (!btn) return;
      keSlide(indeksKini() + (parseInt(btn.getAttribute('data-arah'), 10) || 1));
      jadwalkan(jeda);
    });
    gulir.addEventListener('scroll', function () { tandai(indeksKini()); });
    window.addEventListener('resize', function () { window.setTimeout(bangunTitik, 150); });
    bangunTitik();

    if (!gerakMinimal) {
      jadwalkan(jeda);
      ['mouseenter', 'focusin', 'touchstart', 'pointerdown'].forEach(function (ev) {
        slider.addEventListener(ev, function () { jadwalkan(jeda); }, { passive: true });
      });
      document.addEventListener('visibilitychange', function () {
        if (document.hidden) { hentikan(); } else { jadwalkan(jeda); }
      });
    }
  }

  /* ---------- Cetak ---------- */
  function initPrint() {
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-print]');
      if (b) { ev.preventDefault(); window.print(); }
    });
  }

  /* ---------- OBROLAN / CHAT sesama pengguna aplikasi (v1.51.0) ----------
     Permintaan pemilik: "Tambahkan fitur chat agar sesama pengguna aplikasi yang sedang online bisa
     berkomunikasi." Pesan baru muncul OTOMATIS tiap beberapa detik (data-jeda pada #chatKotak),
     tanpa perlu mengklik apa pun. Kotak mengambang di pojok kanan bawah, ada di semua halaman.

     Prinsip yang dijaga:
       • isi pesan orang lain TIDAK PERNAH disisipkan sebagai HTML — selalu lewat textContent
         (server juga meng-escape; ini lapis kedua agar tidak ada celah injeksi);
       • pesan yang kita kirim sendiri ditampilkan segera (optimistis) lalu dikonfirmasi server;
       • pengambilan berhenti saat tab tidak terlihat, saat panel tertutup TETAP berjalan
         (supaya lencana "belum dibaca" bertambah) namun hanya meminta jumlahnya... ringkasnya:
         polling terus jalan, tetapi penandaan "sudah dibaca" hanya dikirim saat panel TERBUKA;
       • kegagalan jaringan tidak menampilkan galat menakutkan — cukup dicoba lagi pada jeda berikutnya.
  */
  function initChat() {
    var kotak = document.getElementById('chatKotak');
    if (!kotak) { return; }

    var tombol = document.getElementById('chatTombol');
    var panel = document.getElementById('chatPanel');
    var isi = document.getElementById('chatIsi');
    var form = document.getElementById('chatForm');
    var isian = document.getElementById('chatIsian');
    var sisa = document.getElementById('chatSisa');
    var lencana = document.getElementById('chatLencana');
    var status = document.getElementById('chatStatus');

    var URL_CHAT = kotak.getAttribute('data-url') || 'chat.php';
    var JEDA = parseInt(kotak.getAttribute('data-jeda'), 10) || 5000;
    var MAKS = parseInt(kotak.getAttribute('data-maks'), 10) || 500;
    var terakhir = parseInt(kotak.getAttribute('data-terakhir'), 10) || 0;
    var CSRF = (form.querySelector('input[name=csrf]') || {}).value || '';
    var terbuka = false;
    var timer = null;

    function jamSekarang() {
      var d = new Date();
      return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
    }

    function tampilkanLencana(n) {
      if (!lencana) { return; }
      if (n > 0) {
        lencana.textContent = n > 99 ? '99+' : String(n);
        lencana.hidden = false;
      } else {
        lencana.hidden = true;
      }
    }

    function keBawah() {
      if (isi) { isi.scrollTop = isi.scrollHeight; }
    }

    /** Tambahkan satu pesan ke daftar. Semua teks memakai textContent (bukan innerHTML). */
    function tambahPesan(p, punyaSaya) {
      if (!isi) { return; }
      var kosong = document.getElementById('chatKosong');
      if (kosong) { kosong.remove(); }

      var bungkus = document.createElement('div');
      bungkus.className = 'chat-pesan' + (punyaSaya ? ' saya' : '');
      bungkus.setAttribute('data-id', String(p.id));

      if (!punyaSaya) {
        var dari = document.createElement('span');
        dari.className = 'chat-dari';
        dari.textContent = (p.nama || '') + ' · ' + (p.asal || '');
        bungkus.appendChild(dari);
      }
      var gelembung = document.createElement('span');
      gelembung.className = 'chat-gelembung';
      gelembung.textContent = p.isi || '';
      bungkus.appendChild(gelembung);

      var jam = document.createElement('span');
      jam.className = 'chat-jam';
      jam.textContent = p.jam || jamSekarang();
      bungkus.appendChild(jam);

      // Pisahkan galat (mis. pesan gagal terkirim) dari daftar pesan biasa.
      var galat = isi.querySelector('.chat-galat');
      if (galat) { isi.insertBefore(bungkus, galat); } else { isi.appendChild(bungkus); }
      keBawah();
    }

    function catatGalat(teks) {
      if (!isi) { return; }
      var el = isi.querySelector('.chat-galat');
      if (!el) {
        el = document.createElement('div');
        el.className = 'chat-galat';
        isi.appendChild(el);
      }
      el.textContent = teks;
      keBawah();
    }

    /** Ambil pesan baru dari server. `baca` = tandai sudah dibaca (hanya saat panel terbuka). */
    function tarik(baca) {
      var url = URL_CHAT + '?aksi=ambil&sejak=' + encodeURIComponent(terakhir) + (baca ? '&baca=1' : '');
      return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || !d.ok) { return; }
          var daftar = d.pesan || [];
          for (var i = 0; i < daftar.length; i++) {
            tambahPesan(daftar[i], !!daftar[i].saya);
            if (daftar[i].id > terakhir) { terakhir = daftar[i].id; }
          }
          if (baca && terakhir > 0 && daftar.length === 0) {
            // Panel terbuka & tidak ada pesan baru → pastikan lencana bersih.
            tampilkanLencana(0);
          } else {
            tampilkanLencana(d.belum || 0);
          }
          if (status && typeof d.online === 'number') {
            status.innerHTML = 'Satu ruang untuk semua pengguna &middot; <b>' + d.online + '</b> online';
          }
        })
        .catch(function () { /* diamkan: dicoba lagi pada jeda berikutnya */ });
    }

    function pilihAwal() {
      // Pemuatan pertama tanpa lencana (pesan lama sudah dicetak server).
      tarik(false);
    }

    function mulai() {
      if (timer) { return; }
      timer = window.setInterval(function () {
        if (document.hidden) { return; }   // hemat: tab tidak terlihat
        tarik(terbuka);
      }, JEDA);
    }
    function hentikan() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }

    function buka() {
      if (!panel) { return; }
      terbuka = true;
      panel.hidden = false;
      if (tombol) { tombol.setAttribute('aria-expanded', 'true'); }
      tarik(true);                    // tandai yang tampil sudah dibaca
      mulai();
      window.setTimeout(function () { if (isian) { isian.focus(); } }, 30);
      keBawah();
    }
    function tutup() {
      if (!panel) { return; }
      terbuka = false;
      panel.hidden = true;
      if (tombol) { tombol.setAttribute('aria-expanded', 'false'); }
      tarik(false);                   // lencana kembali dihitung tanpa menandai dibaca
    }

    if (tombol) {
      tombol.addEventListener('click', function () { if (terbuka) { tutup(); } else { buka(); } });
    }
    var tutupBtn = document.getElementById('chatTutup');
    if (tutupBtn) { tutupBtn.addEventListener('click', tutup); }
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && terbuka) { tutup(); }
    });

    /** Hitungan karakter yang tersisa (bukan pemblokir: server tetap yang menentukan). */
    function perbaruiSisa() {
      if (!sisa || !isian) { return; }
      var n = MAKS - isian.value.length;
      sisa.textContent = n + ' karakter';
    }

    if (isian) {
      isian.addEventListener('input', perbaruiSisa);
      // Enter mengirim, Shift+Enter menambah baris baru.
      isian.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && !ev.shiftKey) {
          ev.preventDefault();
          if (form) { form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })); }
        }
      });
    }

    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!isian) { return; }
        var teks = isian.value.trim();
        if (teks === '') { return; }
        var tombolKirim = document.getElementById('chatKirim');
        if (tombolKirim) { tombolKirim.disabled = true; }

        var data = new FormData();
        data.append('csrf', CSRF);
        data.append('aksi', 'kirim');
        data.append('isi', teks);

        fetch(URL_CHAT, {
          method: 'POST', body: data, credentials: 'same-origin',
          headers: { 'X-Requested-With': 'fetch' }
        })
          .then(function (r) { return r.json().catch(function () { return null; }); })
          .then(function (d) {
            if (d && d.ok && d.pesan) {
              isian.value = '';
              perbaruiSisa();
              tambahPesan(d.pesan, true);
              if (d.pesan.id > terakhir) { terakhir = d.pesan.id; }
              var el = isi && isi.querySelector('.chat-galat');
              if (el) { el.remove(); }
              menyegarkan();
            } else {
              catatGalat((d && d.pesan_galat) ? d.pesan_galat : 'Pesan gagal terkirim. Coba lagi.');
            }
          })
          .catch(function () { catatGalat('Pesan gagal terkirim. Periksa sambungan lalu coba lagi.'); })
          .then(function () { if (tombolKirim) { tombolKirim.disabled = false; } });
      });
    }

    function menyegarkan() { tarik(terbuka); }

    // Mulai: ambil sekali (agar lencana akurat) lalu berjalan otomatis pada jeda yang ditentukan.
    pilihAwal();
    mulai();
    // Saat tab kembali terlihat, langsung tarik supaya tidak menunggu jeda berikutnya.
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { tarik(terbuka); }
    });
  }

  /* ---------- Grafik (Chart.js) ---------- */
  var PALETTE = ['#0b4a33', '#178a5f', '#c9a227', '#6d1b25', '#1d6f8b', '#2aa87a', '#a9741b', '#14556b'];
  var PRED_COLOR = { A: '#0f7a53', B: '#1d6f8b', C: '#c9a227', D: '#8d2733' };

  function baseOpts(extra) {
    return Object.assign({
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { font: { family: 'Inter', size: 11 }, color: '#5c6b63', boxWidth: 12, usePointStyle: true, padding: 14 } },
        tooltip: { backgroundColor: 'rgba(11,42,30,.94)', padding: 10, cornerRadius: 10 }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } },
        y: { beginAtZero: true, grid: { color: 'rgba(11,74,51,.14)' }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } }
      }
    }, extra || {});
  }

  window.EG = {
    palette: PALETTE,
    chart: function (id, cfg) {
      var el = document.getElementById(id);
      if (!el || typeof Chart === 'undefined') return null;
      return new Chart(el.getContext('2d'), cfg);
    },
    bar: function (id, labels, data, horizontal, colors) {
      return window.EG.chart(id, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Jumlah', data: data, backgroundColor: colors || 'rgba(11,74,51,.78)', borderRadius: 6, maxBarThickness: horizontal ? 24 : 44 }] },
        options: baseOpts({
          indexAxis: horizontal ? 'y' : 'x',
          plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(11,42,30,.94)', padding: 10, cornerRadius: 10 } },
          scales: horizontal
            ? { x: { beginAtZero: true, grid: { color: 'rgba(11,74,51,.14)' }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } }, y: { grid: { display: false }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } } }
            : { y: { beginAtZero: true, grid: { color: 'rgba(11,74,51,.14)' }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } }, x: { grid: { display: false }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } } }
        })
      });
    },
    /* Batang GANDA (v1.71.6) — dipakai Dashboard Pengawas untuk membandingkan dua besaran
       (mis. guru vs siswa) per sekolah/madrasah binaan pada satu sumbu. `set` = array
       [{label, data, warna?}]. `horizontal` = true → batang mendatar (label sekolah di sumbu Y). */
    barGanda: function (id, labels, set, horizontal) {
      var warna = [PALETTE[0], PALETTE[2], PALETTE[1], PALETTE[3], PALETTE[4]];
      var sets = (set || []).map(function (s, i) {
        return {
          label: s.label,
          data: s.data,
          backgroundColor: s.warna || warna[i % warna.length],
          borderRadius: 6,
          maxBarThickness: horizontal ? 18 : 34
        };
      });
      return window.EG.chart(id, {
        type: 'bar',
        data: { labels: labels, datasets: sets },
        options: baseOpts(horizontal
          ? { indexAxis: 'y',
              plugins: { tooltip: { backgroundColor: 'rgba(11,42,30,.94)', padding: 10, cornerRadius: 10 } },
              scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(11,74,51,.14)' }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } },
                y: { grid: { display: false }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } }
              } }
          : {})
      });
    },
    line: function (id, labels, data, label) {
      return window.EG.chart(id, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: label || 'Jumlah', data: data, borderColor: '#0b4a33', backgroundColor: 'rgba(11,74,51,.14)',
            fill: true, tension: .35, pointBackgroundColor: '#fff', pointBorderColor: '#0b4a33', pointBorderWidth: 2, pointRadius: 4
          }]
        },
        options: baseOpts({
          plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(11,42,30,.94)', padding: 10, cornerRadius: 10 } },
          scales: { y: { beginAtZero: true, grid: { color: 'rgba(11,74,51,.14)' }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } }, x: { grid: { display: false }, ticks: { color: '#5c6b63', font: { family: 'Inter', size: 11 } } } }
        })
      });
    },
    donut: function (id, labels, data, colors) {
      return window.EG.chart(id, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: colors || PALETTE, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
        options: baseOpts({
          cutout: '62%',
          plugins: {
            legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 }, color: '#5c6b63', boxWidth: 10, usePointStyle: true, padding: 12 } },
            tooltip: { backgroundColor: 'rgba(11,42,30,.94)', padding: 10, cornerRadius: 10 }
          },
          scales: {}
        })
      });
    }
  };

  /**
   * Kartu "Pengaturan Penilaian" (nilai.php, guru) — pelengkap saja.
   * Tanpa skrip ini pun kartu tetap bisa dibuka: formulirnya dibungkus <details> dan tombolnya
   * adalah <summary> (klik = buka/tutup). Skrip ini hanya (a) membuka kartu saat tombol
   * "Pengaturan Penilaian" diklik, dan (b) membukanya otomatis saat halaman dibuka dengan
   * #cardPengaturanNilai (mis. setelah menyimpan pengaturan).
   */
  var initSetNilai = function () {
    var kartu = document.getElementById('cardPengaturanNilai');
    if (!kartu) { return; }
    var det = kartu.querySelector('details');
    if (!det) { return; }
    var buka = function () { det.open = true; };
    var tombol = document.querySelectorAll('[data-set-nilai]');
    for (var i = 0; i < tombol.length; i++) {
      tombol[i].addEventListener('click', buka);
    }
    if (window.location.hash === '#cardPengaturanNilai') { buka(); }
  };

  /* ---------- Popup KODE DOWNLOAD halaman publik "Perangkat Guru" (v1.66.0) ----------
     Dialognya sudah dicetak SERVER dengan atribut `open` (jadi tetap terbaca walau skrip gagal
     dimuat); skrip ini hanya menaikkannya menjadi modal, menutupnya lewat klik latar / tombol Esc,
     dan mengembalikan alamat halaman ke bentuk tanpa `?unduh=` supaya tidak terbuka lagi. */
  function initKodeUnduh() {
    var d = document.getElementById('kodeUnduh');
    if (!d) { return; }
    if (typeof d.showModal === 'function') {
      try { d.showModal(); } catch (e) { d.setAttribute('open', ''); }
    } else {
      d.setAttribute('open', '');
    }
    var bersih = d.getAttribute('data-tutup') || '';
    var tutup = function () {
      if (bersih) { window.location.href = bersih; return; }
      if (typeof d.close === 'function' && d.open) { d.close(); } else { d.removeAttribute('open'); }
    };
    d.addEventListener('cancel', function (ev) {
      if (!bersih) { return; }
      ev.preventDefault();
      tutup();
    });
    d.addEventListener('click', function (ev) {
      var k = d.getBoundingClientRect();
      var luar = ev.clientX < k.left || ev.clientX > k.right || ev.clientY < k.top || ev.clientY > k.bottom;
      if (luar) { tutup(); }
    });
  }

  /* ---------- TANDA TANGAN LANGSUNG DI LAYAR SENTUH pada notulen rapat (v1.70.0) ----------
     Permintaan pemilik: "Pada notulen rapat buat tanda tangan langsung lewat HP seperti aplikasi
     TUSmart". Tombol "Tanda Tangan" sudah dicetak SERVER dengan atribut `hidden`; skrip ini hanya
     memunculkannya bila perangkat benar-benar memiliki layar sentuh (komputer biasa tetap
     mencetak lembar dengan kolom tanda tangan kosong, dan tombol tidak ikut tercetak).
     Coretan pada <canvas> diekspor sebagai PNG yang SUDAH DIPANGKAS ke area coretan, lalu dikirim
     ke `upload_notulen_ttd.php` (server mengunggahnya ke media proxy; hanya URL yang disimpan). */
  function initTtdSentuh() {
    var dataEl = document.getElementById('ttdData');
    var dialog = document.getElementById('ttdDialog');
    if (!dataEl || !dialog) return;
    var data;
    try { data = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
    if (!data || !data.endpoint) return;
    var kanvas = document.getElementById('ttdKanvas');
    var pihakEl = document.getElementById('ttdPihak');
    var pesanEl = document.getElementById('ttdPesan');
    var judulEl = document.getElementById('ttdJudul');
    var subEl = document.getElementById('ttdSub');
    if (!kanvas || !pihakEl) return;

    var sentuh = (navigator.maxTouchPoints && navigator.maxTouchPoints > 0) || ('ontouchstart' in window);
    var tombolSemua = document.querySelectorAll('[data-ttd-tombol]');
    var catatan = document.getElementById('ttdCatatan');
    tombolSemua.forEach(function (b) { b.hidden = !sentuh; });
    if (catatan) catatan.hidden = !sentuh;

    var ctx = kanvas.getContext('2d');
    var menggambar = false;
    var adaCoretan = false;
    var pihakAktif = '';
    var terakhir = null;

    function bersihkan() {
      var lebar = kanvas._lebar || 600;
      var tinggi = kanvas._tinggi || 220;
      ctx.clearRect(0, 0, lebar, tinggi);
      ctx.lineWidth = 2.6;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#0b2a1e';
      adaCoretan = false;
    }

    function ukuranKanvas() {
      var rasio = window.devicePixelRatio || 1;
      var kotak = kanvas.getBoundingClientRect();
      var lebar = Math.max(280, Math.round(kotak.width || kanvas.clientWidth || 600));
      var tinggi = Math.max(140, Math.round(kotak.height || kanvas.clientHeight || 220));
      kanvas.width = Math.round(lebar * rasio);
      kanvas.height = Math.round(tinggi * rasio);
      ctx.setTransform(rasio, 0, 0, rasio, 0, 0);
      kanvas._lebar = lebar;
      kanvas._tinggi = tinggi;
      bersihkan();
    }

    function titik(ev) {
      var kotak = kanvas.getBoundingClientRect();
      return { x: ev.clientX - kotak.left, y: ev.clientY - kotak.top };
    }

    function mulai(ev) {
      ev.preventDefault();
      menggambar = true;
      terakhir = titik(ev);
      if (kanvas.setPointerCapture && ev.pointerId !== undefined) {
        try { kanvas.setPointerCapture(ev.pointerId); } catch (e) {}
      }
    }

    function gerak(ev) {
      if (!menggambar) return;
      ev.preventDefault();
      var p = titik(ev);
      ctx.beginPath();
      ctx.moveTo(terakhir.x, terakhir.y);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      terakhir = p;
      adaCoretan = true;
    }

    function selesai(ev) {
      if (ev) ev.preventDefault();
      menggambar = false;
      terakhir = null;
    }

    kanvas.addEventListener('pointerdown', mulai);
    kanvas.addEventListener('pointermove', gerak);
    kanvas.addEventListener('pointerup', selesai);
    kanvas.addEventListener('pointercancel', selesai);
    kanvas.addEventListener('pointerleave', selesai);
    // Peramban lama tanpa Pointer Events: pakai sentuhan langsung.
    kanvas.addEventListener('touchstart', function (ev) {
      if (window.PointerEvent) return;
      var t = ev.touches[0];
      if (t) mulai({ preventDefault: function () { ev.preventDefault(); }, clientX: t.clientX, clientY: t.clientY });
    }, { passive: false });
    kanvas.addEventListener('touchmove', function (ev) {
      if (window.PointerEvent) return;
      var t = ev.touches[0];
      if (t) gerak({ preventDefault: function () { ev.preventDefault(); }, clientX: t.clientX, clientY: t.clientY });
    }, { passive: false });
    kanvas.addEventListener('touchend', function (ev) {
      if (window.PointerEvent) return;
      selesai({ preventDefault: function () { ev.preventDefault(); } });
    }, { passive: false });

    function pesan(teks, jenis) {
      if (!pesanEl) return;
      pesanEl.textContent = teks || '';
      pesanEl.className = 'ttd-pesan' + (jenis ? ' ' + jenis : '');
    }

    function buka(pihak) {
      pihakAktif = pihak;
      pihakEl.value = pihak;
      var info = (data.pihak || {})[pihak] || {};
      if (judulEl) judulEl.textContent = 'Tanda Tangan — ' + (info.label || 'Peserta');
      if (subEl) {
        subEl.textContent = (info.nama || '') !== ''
          ? 'Nama: ' + info.nama + ' · tanda tangan langsung pada layar (jari atau stylus).'
          : 'Tanda tangan langsung pada layar (jari atau stylus).';
      }
      pesan('', '');
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', 'open');
      window.setTimeout(ukuranKanvas, 30);
    }

    function tutup() {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
      pihakAktif = '';
    }

    tombolSemua.forEach(function (b) {
      b.addEventListener('click', function () { buka(b.getAttribute('data-ttd-tombol') || ''); });
    });

    var tombolTutup = document.getElementById('ttdTutup');
    if (tombolTutup) tombolTutup.addEventListener('click', tutup);
    var tombolHapus = document.getElementById('ttdHapus');
    if (tombolHapus) tombolHapus.addEventListener('click', function () { bersihkan(); pesan('', ''); });

    // Menghasilkan PNG yang SUDAH DIPANGKAS ke area coretan (margin transparan dibuang) supaya
    // tanda tangan tampil besar pada lembar cetak, bukan titik kecil di tengah gambar kosong.
    function eksporPng() {
      var data;
      try {
        data = ctx.getImageData(0, 0, kanvas.width, kanvas.height);
      } catch (e) {
        return kanvas.toDataURL('image/png');
      }
      var w = kanvas.width, h = kanvas.height;
      var minX = w, minY = h, maxX = -1, maxY = -1;
      for (var y = 0; y < h; y++) {
        for (var x = 0; x < w; x++) {
          if (data.data[(y * w + x) * 4 + 3] > 8) {
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
          }
        }
      }
      if (maxX < 0) return kanvas.toDataURL('image/png');
      var pad = Math.round(8 * (window.devicePixelRatio || 1));
      minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
      maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
      var cw = maxX - minX + 1, ch = maxY - minY + 1;
      var keluar = document.createElement('canvas');
      keluar.width = cw;
      keluar.height = ch;
      keluar.getContext('2d').drawImage(kanvas, minX, minY, cw, ch, 0, 0, cw, ch);
      return keluar.toDataURL('image/png');
    }

    /* Pesan kegagalan pengiriman tanda tangan.
       Bila server menjawab halaman NON-JSON (galat PHP / pengalihan), sebutkan kode HTTP-nya
       supaya penyebabnya bisa ditelusuri (dulu pesannya selalu "Gagal menghubungi server" saja). */
    function pesanGagalKirim(e) {
      if (e && e.bukanJson) {
        return 'Tanda tangan gagal disimpan — server menjawab kode ' + e.status
          + '. Muat ulang halaman lalu coba lagi; bila tetap gagal, hubungi Admin aplikasi.';
      }
      return 'Gagal menghubungi server. Periksa koneksi lalu coba lagi.';
    }

    function kirim(aksi) {
      var csrf = dialog.querySelector('input[name=csrf]');
      var fd = new FormData();
      fd.append('aksi', aksi);
      fd.append('id', String(data.id || 0));
      fd.append('pihak', pihakAktif);
      fd.append('format', 'json');
      if (csrf) fd.append('csrf', csrf.value);
      if (aksi === 'simpan') fd.append('gambar', eksporPng());
      return fetch(data.endpoint, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (r) {
        // Balasan WAJIB JSON. Kalau server menjawab halaman lain (mis. galat PHP atau
        // pengalihan ke halaman masuk), permintaan ini GAGAL — dan pesannya harus menyebut
        // kode HTTP-nya, bukan "Gagal menghubungi server" tanpa keterangan.
        var tipe = (r.headers.get('content-type') || '').toLowerCase();
        if (tipe.indexOf('application/json') === -1) {
          var galat = new Error('server menjawab HTTP ' + r.status + ' (bukan JSON)');
          galat.bukanJson = true;
          galat.status = r.status;
          throw galat;
        }
        return r.json();
      });
    }

    function perbaruiSel(pihak, url) {
      var sel = document.querySelector('[data-ttd-sel="' + pihak + '"]');
      if (!sel) return;
      var img = sel.querySelector('img.ttd-img');
      if (url) {
        if (!img) {
          img = document.createElement('img');
          img.className = 'ttd-img';
          img.alt = 'Tanda tangan';
          sel.insertBefore(img, sel.firstChild);
        }
        img.src = url;
      } else if (img && img.parentNode) {
        img.parentNode.removeChild(img);
      }
      var tombol = sel.querySelector('[data-ttd-tombol]');
      if (tombol) {
        tombol.hidden = !sentuh;
        tombol.textContent = url ? 'Ulangi' : 'Tanda Tangan';
      }
      var jumlah = document.querySelectorAll('img.ttd-img').length;
      document.querySelectorAll('[data-ttd-jumlah]').forEach(function (el) {
        el.textContent = String(jumlah);
      });
    }

    var tombolSimpan = document.getElementById('ttdSimpan');
    if (tombolSimpan) {
      tombolSimpan.addEventListener('click', function () {
        if (!adaCoretan) {
          pesan('Tanda tangan masih kosong — coretkan tanda tangan terlebih dahulu.', 'err');
          return;
        }
        pesan('Menyimpan tanda tangan…', '');
        tombolSimpan.disabled = true;
        kirim('simpan').then(function (r) {
          tombolSimpan.disabled = false;
          if (!r || !r.ok) {
            pesan((r && r.pesan) || 'Tanda tangan gagal disimpan.', 'err');
            return;
          }
          perbaruiSel(pihakAktif, r.url || '');
          data.pihak[pihakAktif] = data.pihak[pihakAktif] || {};
          data.pihak[pihakAktif].ada = true;
          tutup();
        }).catch(function (e) {
          tombolSimpan.disabled = false;
          pesan(pesanGagalKirim(e), 'err');
        });
      });
    }

    var tombolHapusTersimpan = document.getElementById('ttdHapusTersimpan');
    if (tombolHapusTersimpan) {
      tombolHapusTersimpan.addEventListener('click', function () {
        pesan('Menghapus tanda tangan tersimpan…', '');
        kirim('hapus').then(function (r) {
          if (!r || !r.ok) {
            pesan((r && r.pesan) || 'Tanda tangan tidak dapat dihapus.', 'err');
            return;
          }
          perbaruiSel(pihakAktif, '');
          bersihkan();
          pesan('Tanda tangan tersimpan sudah dihapus.', 'ok');
        }).catch(function (e) {
          pesan(pesanGagalKirim(e), 'err');
        });
      });
    }

    window.addEventListener('resize', function () {
      if (dialog.open && !adaCoretan) ukuranKanvas();
    });
  }

  /**
   * POPUP WAJIB GANTI KATA SANDI (v1.74.0).
   * Dialog sudah dicetak dengan atribut `open` (tetap terlihat walau skrip gagal dimuat); di sini
   * dinaikkan menjadi MODAL (showModal) supaya latar halaman tidak dapat diklik sebelum sandi
   * diganti. Tidak ada tombol Tutup — sengaja (lihat gerbang_sandi() di inc/helpers.php).
   */
  function initWajibSandi() {
    var d = document.getElementById('popupWajibSandi');
    if (!d || !d.showModal) { return; }
    if (!d.hasAttribute('open')) { try { d.showModal(); } catch (e) {} }
    // Esc juga tidak boleh menutup popup wajib ini.
    d.addEventListener('cancel', function (ev) { ev.preventDefault(); });
    var tombol = document.getElementById('btnGantiSandiSekarang');
    if (tombol) {
      tombol.addEventListener('click', function () {
        var isian = document.getElementById('sandi_baru');
        if (isian) { setTimeout(function () { isian.focus(); }, 60); }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initWajibSandi();
    initNav();
    initConfirm();
    initTableSearch();
    initAutoFilter();
    initJurnalForm();
    initNilaiGrid();
    initSetNilai();
    initHadirGrid();
    initTentang();
    initLogo();
    initFoto();
    initPasFotoSiswa();
    initJenis();
    initJenisJenjang();
    initJenisNsm();
    initGuruKelas();
    initNotulen();
    initFotoNotulen();
    initTtdSentuh();
    initLanding();
    initSalinKode();
    initHitungMundur();
    initSambutan();
    initPeringatanAktivasi();
    initPrint();
    initChat();
    initKodeUnduh();
  });
})();
