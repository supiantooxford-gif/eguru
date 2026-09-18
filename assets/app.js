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

    form.addEventListener('input', function (ev) {
      if (ev.target.matches('input[data-jenis]')) {
        var v = angka(ev.target);
        if (v !== null && v !== parseFloat(String(ev.target.value).replace(',', '.'))) ev.target.value = v;
        refresh();
      }
    });
    form.addEventListener('change', function (ev) {
      if (ev.target.matches('input[data-jenis]')) refresh();
    });
    refresh();
  }

  /* ---------- Daftar hadir: status H/S/I/A ---------- */
  function initHadirGrid() {
    var form = document.getElementById('formHadir');
    if (!form) return;

    function tandai(lb) {
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

    form.addEventListener('change', function (ev) {
      if (ev.target.matches('input[type=radio][data-st]') && ev.target.checked) {
        tandai(ev.target.closest('label'));
        segarkanRingkas();
      }
    });

    function segarkanRingkas() {
      var c = { H: 0, S: 0, I: 0, A: 0 };
      form.querySelectorAll('input[type=radio][data-st]:checked').forEach(function (i) {
        if (c[i.value] !== undefined) c[i.value]++;
      });
      ['H', 'S', 'I', 'A'].forEach(function (k) {
        var el = document.getElementById('jml' + k);
        if (el) el.textContent = c[k];
      });
    }

    var semua = document.getElementById('btnSemuaHadir');
    if (semua) {
      semua.addEventListener('click', function () {
        form.querySelectorAll('tr[data-siswa]').forEach(function (tr) {
          var inp = tr.querySelector('input[type=radio][data-st][value="H"]');
          if (inp) { inp.checked = true; tandai(inp.closest('label')); }
        });
        segarkanRingkas();
      });
    }
    segarkanRingkas();
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
    var MAKS = 15 * 1024 * 1024;
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
        hint.innerHTML = '<b style="color:#b91c1c">Terlalu besar (' + mb(f.size) + ').</b> Maksimal 15 MB — perkecil dulu berkasnya.';
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

  /* ---------- Cetak ---------- */
  function initPrint() {
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-print]');
      if (b) { ev.preventDefault(); window.print(); }
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

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initConfirm();
    initTableSearch();
    initAutoFilter();
    initNilaiGrid();
    initHadirGrid();
    initLogo();
    initJenis();
    initPrint();
  });
})();
