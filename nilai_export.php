<?php
/**
 * Ekspor daftar nilai (satu kelas + satu mata pelajaran) ke CSV.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
if (!$ta || !$sem) {
    flash('Atur tahun ajaran & semester terlebih dahulu.', 'warn');
    redirect('nilai.php');
}

$kelasId = (int) ($_GET['kelas'] ?? 0);
$mapelId = (int) ($_GET['mapel'] ?? 0);
if (!kelas_by_id($kelasId) || !mapel_by_id($mapelId)) {
    flash('Pilih kelas dan mata pelajaran terlebih dahulu.', 'warn');
    redirect('nilai.php');
}
// Penjaga cakupan SETELAH memastikan kelas/mapel ada (permintaan tanpa parameter
// tidak lagi berbuntut 403 "Kelas itu tidak Anda ampu").
if (!boleh_kelas($kelasId)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
if (!boleh_mapel($mapelId)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}

$jf = jumlah_formatif();
$js = jumlah_sumatif();
$siswa = siswa_kelas($kelasId, true);
$nilai = nilai_map($kelasId, $mapelId, (int) $ta['id'], (int) $sem['id']);

$header = ['No', 'NIS', 'Nama Siswa'];
for ($k = 1; $k <= $jf; $k++) {
    $header[] = 'Formatif ' . $k;
}
for ($k = 1; $k <= $js; $k++) {
    $header[] = 'Sumatif Lingkup Materi ' . $k;
}
$header[] = 'ASAS';
$header[] = 'Rata-rata Sumatif Lingkup Materi';
$header[] = 'Nilai Akhir';
$header[] = 'Predikat';

$baris = [];
$no = 0;
foreach ($siswa as $s) {
    $no++;
    $d = $nilai[(int) $s['id']] ?? ['formatif' => [], 'sumatif' => [], 'asas' => null];
    $isian = [$no, (string) ($s['nis'] ?: ''), (string) $s['nama']];
    for ($k = 1; $k <= $jf; $k++) {
        $v = $d['formatif'][$k] ?? null;
        $isian[] = $v === null ? '' : r1($v);
    }
    $sumArr = [];
    for ($k = 1; $k <= $js; $k++) {
        $v = $d['sumatif'][$k] ?? null;
        $sumArr[$k] = $v;
        $isian[] = $v === null ? '' : r1($v);
    }
    $asas = $d['asas'];
    $isian[] = $asas === null ? '' : r1($asas);
    $rataS = rata($sumArr);
    $isian[] = $rataS === null ? '' : r1($rataS);
    $akhir = nilai_akhir($sumArr, $asas);
    $isian[] = $akhir === null ? '' : r1($akhir);
    $isian[] = predikat_dari($akhir);
    $baris[] = $isian;
}

$namaBerkas = 'nilai-' . preg_replace('/[^A-Za-z0-9]+/', '-', kelas_nama($kelasId) . '-' . mapel_nama($mapelId)) . '-'
    . preg_replace('/[^A-Za-z0-9]+/', '', ta_nama($ta)) . '-' . preg_replace('/[^A-Za-z0-9]+/', '', sem_nama($sem)) . '.csv';

// Baris keterangan agar pemilik berkas tahu asal datanya.
array_unshift($baris, []);
array_unshift($baris, ['Nilai akhir = (rata-rata Sumatif Lingkup Materi x ' . r0(bobot_sumatif()) . '%) + (ASAS x ' . r0(bobot_asas()) . '%). Nilai Formatif hanya penilaian proses.']);
array_unshift($baris, [nama_sekolah() . ' — ' . kelas_nama($kelasId) . ' — ' . mapel_nama($mapelId) . ' — T.A. ' . ta_nama($ta) . ' Semester ' . sem_nama($sem)]);

csv_kirim($namaBerkas, $header, $baris);
