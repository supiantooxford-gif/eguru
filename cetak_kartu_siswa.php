<?php
/**
 * CETAK KARTU PELAJAR (kartu tanda pengenal siswa) — lembar A4 POTRAIT.
 *
 * Permintaan pemilik: "Pada menu data siswa tambahkan fitur cetak kartu pelajar.
 * Kartu pelajar berisi: Nama Siswa, NISN, NIS, Jenis Kelamin, Tanggal Lahir.
 * Desain kartu pelajar berwarna hijau keemasan."
 *
 * Keputusan tata letak (jangan diubah tanpa alasan):
 *  - Ukuran kartu **88 x 55 mm** (mendekati ukuran kartu standar 85,6 × 54 mm), 2 kolom x 4 baris =
 *    **8 kartu per lembar A4**. Lebar konten A4 (margin 10 mm) = 190 mm → 2 x 88 + celah 8 = 184 mm;
 *    tinggi 4 x 55 + 3 x 8 = 244 mm ≤ 277 mm. Semua satuan memakai **mm** supaya ukurannya benar
 *    saat dicetak (bukan menebak dari px).
 *  - Setiap 8 kartu dibungkus satu wadah `.kartu-sheet` (satu lembar) yang di media cetak memakai
 *    `break-after:page`, jadi pemenggalan halaman PASTI 8 kartu per lembar dan tidak bergantung
 *    pada perkiraan tinggi oleh peramban.
 *  - Warna **hijau tua + emas**: kepala kartu gradasi hijau dengan garis emas, garis tepi kartu emas,
 *    nama siswa hijau tua, baris kaki campuran hijau/emas. Wajib ada `print-color-adjust: exact`
 *    (tanpa itu peramban tidak mencetak warna latar sehingga desain hilang saat dicetak).
 *  - Foto: data siswa TIDAK menyimpan foto, jadi disediakan **kotak foto 3×4 kosong** (garis putus-putus)
 *    untuk ditempel foto asli.
 *  - Tanda tangan TIDAK dicetak (kartu kecil); identitas lembaga cukup kop kepala kartu + NPSN di kaki.
 *
 * Akses: Super Admin (dalam konteks sekolah) & Admin Operator — sama dengan tombol "Cetak Data Siswa".
 * Filter mengikuti halaman Data Siswa (kelas, status siswa, aktif/nonaktif, kata kunci).
 *
 * TEMPLATE WARNA (v1.72.1 — permintaan pemilik): *"Pada fitur cetak kartu pelajar tambahkan pilihan
 * template warna … Untuk posisi tulisan, logo, barcode tetap. Yang saya upload hanya untuk contoh
 * tampilan desainnya saja"*. Yang berubah **hanya warna/desain**; TATA LETAK kartu TIDAK berubah.
 * Daftar templatenya ada di konstanta **`KARTU_TEMPLATE`** (`inc/config.php`), dipilih lewat
 * `?tpl=<kode>` dan diingat di sesi (`KARTU_TEMPLATE_SESI`). Template terpasang sebagai kelas
 * `tpl-<kode>` pada `div.kartu`; warna-warnanya diatur blok CSS "TEMPLATE WARNA KARTU PELAJAR"
 * di bawah. **Desain bawaan (`hijau-emas`) memakai aturan CSS dasar yang tidak diubah** supaya
 * tampilan lamanya tetap sama persis.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/qr.php';   // pembuat kode QR (barcode) kartu pelajar — v1.71.0

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_lihat_cetak(); // Super Admin (dalam konteks sekolah) & Admin Operator; super tanpa konteks, kepala, guru 403

const KARTU_KOLOM = 2;   // kartu per baris pada satu lembar
const KARTU_BARIS = 4;   // baris kartu per lembar -> 8 kartu/lembar A4

// ---------------------------------------------------------------- template warna (v1.72.1)
// Pilihan template disimpan di sesi supaya tidak perlu dipilih ulang setiap kali mencetak.
// Nilai dari URL hanya diterima bila kodenya benar-benar ada di KARTU_TEMPLATE.
$tplSesi = (string) ($_SESSION[KARTU_TEMPLATE_SESI] ?? '');
if (!array_key_exists($tplSesi, KARTU_TEMPLATE)) {
    $tplSesi = 'hijau-emas';
}
$tpl = (string) ($_GET['tpl'] ?? '');
if (!array_key_exists($tpl, KARTU_TEMPLATE)) {
    $tpl = $tplSesi;      // tanpa parameter (atau kode tak dikenal) → pakai yang terakhir dipilih
}
$_SESSION[KARTU_TEMPLATE_SESI] = $tpl;
$tplLabel = (string) (KARTU_TEMPLATE[$tpl]['label'] ?? 'Hijau Emas');

// ---------------------------------------------------------------- filter (sama dengan siswa.php)
$fId = (int) ($_GET['id'] ?? 0);   // cetak kartu SATU siswa (dipakai tombol "Lihat Kartu Pelajar" di form siswa)
$fKelas = (int) ($_GET['kelas'] ?? 0);
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$fLulus = in_array((string) ($_GET['status_siswa'] ?? ''), ['aktif', 'lulus'], true) ? (string) $_GET['status_siswa'] : '';

$w = ['s.sekolah_id = ?'];
$p = [sekolah_id()];
if ($fId > 0) {
    // Anti-IDOR: siswa sekolah lain dianggap tidak ada.
    if (!siswa_by_id($fId)) {
        tolak403('Siswa itu bukan milik sekolah/madrasah yang sedang dikelola.');
    }
    $w[] = 's.id = ?';
    $p[] = $fId;
}
if ($fKelas > 0) {
    if (!kelas_by_id($fKelas)) {
        tolak403('Kelas itu bukan milik sekolah/madrasah yang sedang dikelola.');
    }
    $w[] = 's.kelas_id = ?';
    $p[] = $fKelas;
}
if (in_array($fStatus, ['1', '0'], true)) {
    $w[] = 's.aktif = ' . (int) $fStatus;
}
if ($fLulus === 'lulus') {
    $w[] = "s.status = 'lulus'";
} elseif ($fLulus === 'aktif') {
    $w[] = "(s.status = 'aktif' OR s.status IS NULL)";
}
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_wali LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT s.*, k.nama AS kelas_nama
    FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id' . $where . '
    ORDER BY k.urutan, k.nama, s.nama', $p);

if (!$rows) {
    flash('Belum ada data siswa untuk dicetak kartunya pada filter yang dipilih.', 'warn');
    redirect('siswa.php');
}

// Catatan: tahun ajaran & NPSN TIDAK lagi dicetak di kaki kartu (permintaan pemilik),
// jadi variabelnya tidak dihitung di sini.
$logo = logo_sekolah();

// ---------------------------------------------------------------- BARCODE (KODE QR) KARTU PELAJAR
// Permintaan pemilik: kartu pelajar diberi barcode yang ketika dipindai menampilkan DATA
// LEMBAGA/SEKOLAH. Isi kode = alamat halaman publik `lembaga.php` (memuat identitas lembaga,
// memakai token rahasia) sehingga kamera HP langsung membuka halaman itu.
$istilahLembaga = istilah_sekolah('Sekolah');
$qrTeks = lembaga_tautan(sekolah_id());
$qrSvg = $qrTeks !== '' ? qr_svg($qrTeks, 'kartu-qr-svg',
    'Kode QR data ' . $istilahLembaga . ' ' . nama_sekolah()) : '';

// Label kelas untuk keterangan di kepala halaman & judul kartu.
$labelKelas = $fKelas > 0 ? kelas_nama($fKelas) : (count(array_unique(array_column($rows, 'kelas_nama'))) === 1
    ? (string) (reset($rows)['kelas_nama'] ?? '') : 'Semua kelas');

// Bagi kartu menjadi beberapa lembar (8 kartu per lembar) supaya pemenggalan halaman pasti.
$perLembar = KARTU_KOLOM * KARTU_BARIS;
$lembar = array_chunk($rows, $perLembar);

$qPrint = array_filter([
    'id' => $fId > 0 ? $fId : null,
    'cari' => $cari !== '' ? $cari : null,
    'kelas' => $fKelas > 0 ? $fKelas : null,
    'status' => $fStatus !== '' ? $fStatus : null,
    'status_siswa' => $fLulus !== '' ? $fLulus : null,
    // Template warna ikut dibawa ke tombol Cetak agar hasil cetak/PDF memakai template yang dipilih.
    'tpl' => $tpl !== 'hijau-emas' ? $tpl : null,
]);
$autoPrint = isset($_GET['auto']);

/** "Laki-laki"/"Perempuan" dari kolom jenis_kelamin. */
function kartu_jk($v): string
{
    $v = strtoupper(trim((string) $v));
    return $v === 'L' ? 'Laki-laki' : ($v === 'P' ? 'Perempuan' : '-');
}

/** Inisial nama sekolah/madrasah (maks. 2 huruf) untuk tanda air pada badan kartu. */
function kartu_inisial(string $nama): string
{
    $inisial = '';
    foreach (preg_split('/\s+/', trim($nama)) ?: [] as $kata) {
        if ($kata !== '' && strlen($inisial) < 2 && preg_match('/^[A-Za-z]/', $kata) === 1) {
            $inisial .= strtoupper(substr($kata, 0, 1));
        }
    }
    return $inisial;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(istilah_sekolah('Kartu Pelajar')) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .toolbar{max-width:210mm;margin:0 auto 18px;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap}
  .toolbar .ket{font-size:.8rem;color:#635F82}
  .kartu-sheet{
    width:190mm;max-width:100%;margin:0 auto 20px;background:#fff;padding:8mm;
    border-radius:14px;box-shadow:0 6px 22px rgba(31,27,58,.10);border:1px solid #e7e2f5;
  }
  .kartu-grid{display:grid;grid-template-columns:repeat(<?= KARTU_KOLOM ?>,88mm);gap:8mm;justify-content:center}
  /* ---------------- KARTU PELAJAR (hijau keemasan) ---------------- */
  .kartu{
    width:88mm;height:55mm;box-sizing:border-box;position:relative;overflow:hidden;
    border:0.6mm solid #c9a227;border-radius:3mm;background:#fff;display:flex;flex-direction:column;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;break-inside:avoid;page-break-inside:avoid;
  }
  .kartu-head{
    display:flex;align-items:center;gap:2mm;padding:1.5mm 2.4mm;
    background:linear-gradient(120deg,#0b4a33,#0e5b3f 55%,#178a5f);color:#fff;
    border-bottom:0.5mm solid #c9a227;
  }
  .kartu-head img{width:8.5mm;height:8.5mm;object-fit:contain;background:#fff;border-radius:1mm;padding:.4mm;flex:0 0 auto}
  .kartu-emblem{
    width:8.5mm;height:8.5mm;flex:0 0 auto;border-radius:1mm;background:rgba(255,255,255,.14);
    border:0.3mm solid #c9a227;display:flex;align-items:center;justify-content:center;color:#f7e7b0;
  }
  .kartu-head-teks{min-width:0;flex:1}
  .kartu-sekolah{font-size:6.6pt;font-weight:800;line-height:1.12;text-transform:uppercase;letter-spacing:.02em;overflow-wrap:break-word}
  .kartu-judul{font-size:5.6pt;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#f6e3ac;margin-top:.3mm}
  .kartu-body{flex:1;display:flex;gap:2.4mm;padding:2.1mm 2.6mm;position:relative}
  .kartu-foto{
    flex:0 0 21mm;height:26.5mm;border:0.3mm dashed #b9a86a;border-radius:1mm;background:#faf8f0;
    display:flex;align-items:center;justify-content:center;text-align:center;line-height:1.25;
    font-size:4.6pt;color:#8b8674;letter-spacing:.04em;
  }
  /* Kotak foto berisi PAS FOTO siswa (3 x 4) — dipotong otomatis, fokus dari atas. */
  .kartu-foto-isi{border:0.3mm solid #c9a227;overflow:hidden;padding:0;background:#fff}
  .kartu-foto-isi img{width:100%;height:100%;object-fit:cover;object-position:center top;display:block}
  .kartu-data{flex:1;min-width:0;display:flex;flex-direction:column;gap:0.9mm<?= $qrSvg !== '' ? ';padding-right:16.5mm' : '' ?>}
  .kartu-nama{
    font-size:8.4pt;font-weight:800;line-height:1.12;color:#0b4a33;overflow-wrap:break-word;
    border-bottom:0.3mm solid #e6d29a;padding-bottom:.7mm;margin-bottom:.5mm;
  }
  .kartu-baris{display:flex;gap:1.2mm;font-size:6.2pt;color:#333;line-height:1.3}
  .kartu-baris b{flex:0 0 17mm;color:#5d5a4a;font-weight:700}
  .kartu-baris span{flex:1;min-width:0;overflow-wrap:break-word}
  .kartu-air{
    position:absolute;right:1.4mm;bottom:-1.2mm;font-size:20pt;font-weight:800;letter-spacing:.02em;
    color:rgba(11,74,51,.06);pointer-events:none;user-select:none;
  }
  .kartu-foot{
    display:flex;justify-content:center;align-items:center;text-align:center;padding:1.1mm 2.6mm;
    background:linear-gradient(90deg,rgba(11,74,51,.10),rgba(201,162,39,.18));
    border-top:0.3mm solid #e6d29a;font-size:5.2pt;line-height:1.28;color:#4a4a50;letter-spacing:.02em;
  }
  .kartu-foot b{color:#0b4a33;font-weight:700}
  /* ===== BARCODE (KODE QR) DATA LEMBAGA — v1.71.0 (permintaan pemilik) =====
     Diletakkan di sudut kanan-bawah badan kartu. Latar putih supaya tetap tajam & kontras saat
     dipindai kamera HP, ukuran 15 mm (modul ±0,4 mm pada versi 6 — cukup terbaca). */
  .kartu-qr{
    position:absolute;right:2.2mm;bottom:1.6mm;width:15mm;display:flex;flex-direction:column;
    align-items:center;gap:0.5mm;padding:0.6mm 0.6mm 0.7mm;background:#fff;
    border:0.25mm solid #e6d29a;border-radius:1mm;
  }
  .kartu-qr svg{display:block;width:100%;height:auto}
  .kartu-qr-ket{font-size:3.6pt;line-height:1.15;text-align:center;color:#5d5a4a;letter-spacing:.02em}

  /* ================= TEMPLATE WARNA KARTU PELAJAR (v1.72.1) =================
     Permintaan pemilik: *"Pada fitur cetak kartu pelajar tambahkan pilihan template warna … Untuk
     posisi tulisan, logo, barcode tetap"*. Kelas template dipasang pada <body> (`kartu-tpl-<kode>`)
     sehingga markup kartu TIDAK berubah sedikit pun — tata letak, ukuran, dan posisi seluruh isi
     (kepala+logo, foto, nama, baris data, barcode QR kanan-bawah, kaki "masa berlaku") tetap sama.
     Yang diatur di sini HANYA warna: garis tepi, gradasi kepala, warna judul, garis & latar kotak
     foto, warna nama & garis bawahnya, tanda air, kaki kartu, dan garis bingkai barcode.
     Desain BAWAAN (`kartu-tpl-hijau-emas`) sengaja TIDAK punya aturan di sini — ia memakai aturan
     dasar di atas apa adanya, jadi tampilannya tetap persis seperti sebelumnya. */
  /* ---- Hijau Cerah (hijau daun + aksen emas) ---- */
  .kartu-tpl-hijau-cerah .kartu{
    border-color:#16a34a;
    background:
      radial-gradient(120% 90% at 100% 0,rgba(74,222,128,.18),rgba(255,255,255,0) 58%),
      radial-gradient(100% 80% at 0 100%,rgba(21,128,61,.10),rgba(255,255,255,0) 60%),#fff;
  }
  .kartu-tpl-hijau-cerah .kartu-head{background:linear-gradient(120deg,#15803d,#16a34a 55%,#4ade80);border-bottom-color:#eab308}
  .kartu-tpl-hijau-cerah .kartu-emblem{background:rgba(255,255,255,.18);border-color:#fde68a;color:#fef9c3}
  .kartu-tpl-hijau-cerah .kartu-judul{color:#fde68a}
  .kartu-tpl-hijau-cerah .kartu-foto{border-color:#a7c9ac;background:#f2fbf3;color:#6b7f6f}
  .kartu-tpl-hijau-cerah .kartu-foto-isi{border-color:#16a34a;background:#fff}
  .kartu-tpl-hijau-cerah .kartu-nama{color:#14532d;border-bottom-color:#bbf7d0}
  .kartu-tpl-hijau-cerah .kartu-air{color:rgba(21,128,61,.07)}
  .kartu-tpl-hijau-cerah .kartu-foot{background:linear-gradient(90deg,rgba(21,128,61,.12),rgba(234,179,8,.22));border-top-color:#bbf7d0}
  .kartu-tpl-hijau-cerah .kartu-foot b{color:#14532d}
  .kartu-tpl-hijau-cerah .kartu-qr{border-color:#bbf7d0}

  /* ---- Biru Tosca (bersih & modern) ---- */
  .kartu-tpl-tosca .kartu{
    border-color:#0891b2;
    background:
      radial-gradient(120% 90% at 100% 0,rgba(34,211,238,.16),rgba(255,255,255,0) 58%),
      radial-gradient(100% 80% at 0 100%,rgba(8,145,178,.10),rgba(255,255,255,0) 60%),#fff;
  }
  .kartu-tpl-tosca .kartu-head{background:linear-gradient(120deg,#0e7490,#0891b2 55%,#22d3ee);border-bottom-color:#06b6d4}
  .kartu-tpl-tosca .kartu-emblem{background:rgba(255,255,255,.20);border-color:#a5f3fc;color:#ecfeff}
  .kartu-tpl-tosca .kartu-judul{color:#cffafe}
  .kartu-tpl-tosca .kartu-foto{border-color:#9fd6e0;background:#f2fbfd;color:#6b8494}
  .kartu-tpl-tosca .kartu-foto-isi{border-color:#0891b2;background:#fff}
  .kartu-tpl-tosca .kartu-nama{color:#0e7490;border-bottom-color:#bae6fd}
  .kartu-tpl-tosca .kartu-air{color:rgba(8,145,178,.08)}
  .kartu-tpl-tosca .kartu-foot{background:linear-gradient(90deg,rgba(8,145,178,.12),rgba(34,211,238,.22));border-top-color:#bae6fd}
  .kartu-tpl-tosca .kartu-foot b{color:#0e7490}
  .kartu-tpl-tosca .kartu-qr{border-color:#bae6fd}

  /* ---- Merah Maroon (tegas di atas latar putih) ---- */
  .kartu-tpl-maroon .kartu{
    border-color:#b91c1c;
    background:
      radial-gradient(120% 90% at 100% 0,rgba(248,113,113,.15),rgba(255,255,255,0) 58%),
      radial-gradient(100% 80% at 0 100%,rgba(127,29,29,.10),rgba(255,255,255,0) 60%),#fff;
  }
  .kartu-tpl-maroon .kartu-head{background:linear-gradient(120deg,#7f1d1d,#991b1b 55%,#dc2626);border-bottom-color:#dc2626}
  .kartu-tpl-maroon .kartu-emblem{background:rgba(255,255,255,.16);border-color:#fecaca;color:#fee2e2}
  .kartu-tpl-maroon .kartu-judul{color:#fecaca}
  .kartu-tpl-maroon .kartu-foto{border-color:#d8a7a7;background:#fdf5f5;color:#7c6b6b}
  .kartu-tpl-maroon .kartu-foto-isi{border-color:#b91c1c;background:#fff}
  .kartu-tpl-maroon .kartu-nama{color:#7f1d1d;border-bottom-color:#fecaca}
  .kartu-tpl-maroon .kartu-air{color:rgba(127,29,29,.07)}
  .kartu-tpl-maroon .kartu-foot{background:linear-gradient(90deg,rgba(127,29,29,.10),rgba(220,38,38,.18));border-top-color:#fecaca}
  .kartu-tpl-maroon .kartu-foot b{color:#7f1d1d}
  .kartu-tpl-maroon .kartu-qr{border-color:#fecaca}

  /* ---- Ungu Indigo (senada tema aplikasi) ---- */
  .kartu-tpl-indigo .kartu{
    border-color:#4f46e5;
    background:
      radial-gradient(120% 90% at 100% 0,rgba(139,92,246,.16),rgba(255,255,255,0) 58%),
      radial-gradient(100% 80% at 0 100%,rgba(49,46,129,.10),rgba(255,255,255,0) 60%),#fff;
  }
  .kartu-tpl-indigo .kartu-head{background:linear-gradient(120deg,#312e81,#4f46e5 55%,#8b5cf6);border-bottom-color:#7c3aed}
  .kartu-tpl-indigo .kartu-emblem{background:rgba(255,255,255,.18);border-color:#ddd6fe;color:#ede9fe}
  .kartu-tpl-indigo .kartu-judul{color:#ddd6fe}
  .kartu-tpl-indigo .kartu-foto{border-color:#b9b2e0;background:#f7f6ff;color:#6b6880}
  .kartu-tpl-indigo .kartu-foto-isi{border-color:#4f46e5;background:#fff}
  .kartu-tpl-indigo .kartu-nama{color:#312e81;border-bottom-color:#ddd6fe}
  .kartu-tpl-indigo .kartu-air{color:rgba(49,46,129,.07)}
  .kartu-tpl-indigo .kartu-foot{background:linear-gradient(90deg,rgba(49,46,129,.10),rgba(139,92,246,.18));border-top-color:#ddd6fe}
  .kartu-tpl-indigo .kartu-foot b{color:#312e81}
  .kartu-tpl-indigo .kartu-qr{border-color:#ddd6fe}

  /* Deretan pemilih template pada toolbar (tidak ikut tercetak). */
  .toolbar-tpl{
    max-width:210mm;margin:0 auto 18px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;
    padding:12px 14px;border-radius:12px;background:#fff;border:1px solid #e7e2f5;
    box-shadow:0 4px 14px rgba(31,27,58,.06);
  }
  .toolbar-tpl .judul{font-size:.78rem;font-weight:700;color:#1F1B3A;margin-right:2px}
  .tpl-chip{
    display:inline-flex;align-items:center;gap:7px;padding:5px 11px 5px 7px;border-radius:999px;
    font-size:.76rem;font-weight:700;color:#3a3556;text-decoration:none;background:#f7f5ff;
    border:1px solid #e2dcf6;
  }
  .tpl-chip:hover{background:#efeaff}
  .tpl-chip.on{background:#fff;border-color:#4F46E5;box-shadow:0 0 0 2px rgba(79,70,229,.16)}
  .tpl-chip .contoh{width:16px;height:16px;border-radius:50%;border:1px solid rgba(0,0,0,.12);flex:0 0 auto}
  .toolbar-tpl .ket{font-size:.72rem;color:#635F82}
  .ket-print{max-width:190mm;margin:0 auto;font-size:.72rem;color:#635F82;line-height:1.5}
  @media screen and (max-width:820px){
    body{padding:16px 10px 30px}
    .kartu-sheet{width:auto;padding:5mm}
    .kartu-grid{display:block}
    .kartu{margin:0 auto 6mm}
  }
  @media print{
    /* PENTING: tema menetapkan body{min-height:100vh}. Saat MENCETAK, 100vh = tinggi kertas,
       sehingga dokumen menjadi setinggi halaman dan peramban mencetak halaman kedua yang kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.toolbar-tpl,.no-print,.ket-print{display:none !important}
    .kartu-sheet{
      width:auto;max-width:none;margin:0;padding:0;border:none;border-radius:0;box-shadow:none;
      break-after:page;page-break-after:always;
    }
    .kartu-sheet:last-child{break-after:auto;page-break-after:auto}
    .kartu{box-shadow:none}
  }
  @page{size:A4 portrait;margin:10mm}
</style>
</head>
<body class="kartu-tpl-<?= e($tpl) ?>">
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="siswa.php?<?= e(http_build_query($qPrint)) ?>">Kembali</a>
  <div class="ket">
    <?= count($rows) ?> kartu<?= $labelKelas !== '' ? ', ' . e($labelKelas) : '' ?>,
    <?= count($lembar) ?> lembar (8 kartu/lembar), ukuran kartu 88 x 55 mm
  </div>
  <a class="btn btn-primary" href="cetak_kartu_siswa.php?<?= e(http_build_query(array_filter($qPrint + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<?php // ---------------------------------------------------------------- PILIH TEMPLATE WARNA (v1.72.1)
      // Permintaan pemilik: *"Pada fitur cetak kartu pelajar tambahkan pilihan template warna"*.
      // Setiap tombol adalah TAUTAN biasa (bekerja tanpa JavaScript) yang membawa seluruh penyaring
      // yang sedang aktif + `tpl=<kode>`; pilihannya diingat di sesi oleh halaman ini. ?>
<div class="toolbar-tpl no-print">
  <span class="judul"><?= ikon('star', 14) ?> Template warna:</span>
  <?php foreach (KARTU_TEMPLATE as $kodeTpl => $t): ?>
    <?php
    $aktifTpl = $kodeTpl === $tpl;
    $qTpl = array_filter(array_merge($qPrint, ['tpl' => $kodeTpl]), static fn($v) => $v !== null && $v !== '');
    ?>
    <a class="tpl-chip<?= $aktifTpl ? ' on' : '' ?>" href="cetak_kartu_siswa.php?<?= e(http_build_query($qTpl)) ?>"
       title="<?= e((string) $t['ket']) ?>"<?= $aktifTpl ? ' aria-current="true"' : '' ?>>
      <span class="contoh" style="background:linear-gradient(135deg,<?= e((string) $t['warna'][0]) ?>,<?= e((string) $t['warna'][1]) ?>)"></span>
      <?= e((string) $t['label']) ?>
    </a>
  <?php endforeach; ?>
  <span class="ket">Template terpilih: <b><?= e($tplLabel) ?></b> — tata letak, posisi logo, tulisan, dan
    barcode tidak berubah, hanya warnanya.</span>
</div>

<?php $no = 0; foreach ($lembar as $kartuLembar): ?>
  <div class="kartu-sheet">
    <div class="kartu-grid">
      <?php foreach ($kartuLembar as $s): $no++; ?>
        <div class="kartu">
          <div class="kartu-head">
            <?php if ($logo !== ''): ?>
              <img src="<?= e($logo) ?>" alt="Logo">
            <?php else: ?>
              <span class="kartu-emblem"><?= ikon('school', 14) ?></span>
            <?php endif; ?>
            <div class="kartu-head-teks">
              <div class="kartu-sekolah"><?= e(tetap_kata(nama_sekolah() !== '' ? nama_sekolah() : '-')) ?></div>
              <div class="kartu-judul"><?= e(istilah_sekolah('Kartu Pelajar')) ?></div>
            </div>
          </div>

          <div class="kartu-body">
            <?php // Tanda air: inisial nama sekolah/madrasah. Dikirim TANPA tetap_kata() karena
                  // fungsinya hanya mengambil huruf awal — penanda internal akan membuat huruf
                  // pertama ikut terlewat (dulu menghasilkan "ST" bukan "MS" untuk "MA. Sirojuth…"). ?>
            <div class="kartu-air" aria-hidden="true"><?= e(kartu_inisial(nama_sekolah())) ?></div>
            <?php $fotoSiswa = siswa_foto($s); ?>
            <?php if ($fotoSiswa !== null): ?>
              <?php // Pas foto 3x4 yang diunggah dari form Data Siswa (dipotong otomatis, fokus dari atas). ?>
              <div class="kartu-foto kartu-foto-isi"><img src="<?= e($fotoSiswa) ?>" alt="Pas foto <?= e((string) $s['nama']) ?>"></div>
            <?php else: ?>
              <div class="kartu-foto">FOTO<br>3 x 4</div>
            <?php endif; ?>
            <div class="kartu-data">
              <div class="kartu-nama"><?= e((string) $s['nama']) ?></div>
              <div class="kartu-baris"><b>NISN</b><span><?= e(trim((string) ($s['nisn'] ?? '')) !== '' ? (string) $s['nisn'] : '-') ?></span></div>
              <div class="kartu-baris"><b>NIS</b><span><?= e(trim((string) ($s['nis'] ?? '')) !== '' ? (string) $s['nis'] : '-') ?></span></div>
              <div class="kartu-baris"><b>Jenis Kelamin</b><span><?= e(kartu_jk($s['jenis_kelamin'] ?? '')) ?></span></div>
              <div class="kartu-baris"><b>Tanggal Lahir</b><span><?= e(trim((string) ($s['tanggal_lahir'] ?? '')) !== '' ? tanggal_id((string) $s['tanggal_lahir']) : '-') ?></span></div>
            </div>
            <?php // BARCODE (KODE QR): dipindai kamera HP → membuka halaman DATA LEMBAGA. ?>
            <?php if ($qrSvg !== ''): ?>
              <div class="kartu-qr" role="img"
                   aria-label="Barcode data <?= e($istilahLembaga) ?> — pindai untuk membuka data lembaga">
                <?= $qrSvg ?>
                <span class="kartu-qr-ket">Data <?= e($istilahLembaga) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <?php // Kaki kartu: MASA BERLAKU kartu (permintaan pemilik). Tulisan lama
                // "Tahun Ajaran …, Kelas …, NPSN …" TIDAK dicetak lagi — jangan dikembalikan. ?>
          <div class="kartu-foot">
            <span>Berlaku selama menjadi siswa di <b><?= e(tetap_kata(nama_sekolah() !== '' ? nama_sekolah() : '-')) ?></b></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<p class="ket-print no-print">
  Kartu dicetak 8 buah per lembar A4 (2 kolom x 4 baris) dengan ukuran 88 x 55 mm. Kotak bergaris
  putus-putus di sisi kiri kartu disediakan untuk menempel foto siswa ukuran 3 x 4.
  <b>Barcode (kode QR)</b> di sudut kanan bawah kartu berisi alamat data lembaga — bila dipindai
  dengan kamera HP akan terbuka halaman berisi identitas <?= e($istilahLembaga) ?>.
  Gunakan <b>Cetak / Simpan PDF</b> lalu pilih ukuran kertas A4 dan aktifkan opsi mencetak warna
  latar (background graphics) agar desain kartu ikut tercetak.
  <b>Template warna</b> (Hijau Emas, Hijau Cerah, Biru Tosca, Merah Maroon, Ungu Indigo) hanya
  mengubah warna kartu — tata letak, posisi logo, tulisan, dan barcode tetap sama.
</p>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
