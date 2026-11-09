<?php
/**
 * Pembuat berkas Word (.docx) sederhana TANPA pustaka luar — dipakai UNDUHAN WORD
 * "Daftar Sekolah/Madrasah" (`sekolah_lintas_word.php`, v1.82.0).
 *
 * Aplikasi ini tidak boleh memakai ekstensi opsional (ZipArchive tidak tersedia di server), sedangkan
 * .docx pada dasarnya adalah berkas ZIP berisi XML. ZIP di sini ditulis sendiri dengan metode STORE
 * (tanpa kompresi) — bentuk ZIP yang sah dan dapat dibuka Word/LibreOffice.
 *
 * Isi dokumen disusun dari daftar BLOK:
 *   ['teks' => '...', 'tebal' => bool, 'tengah' => bool, 'miring' => bool, 'ukuran' => int,
 *    'jarak' => int, 'tabel' => [kolom, baris], 'halaman' => bool]
 * Data yang dicetak tetap berasal dari halaman pemanggil (satu sumber: `sekolah_lintas_ringkas()`),
 * modul ini hanya "pencetak".
 *
 * Berkas ini diangkat dari modul yang sama pada aplikasi Supervisi Guru (sudah terbukti dipakai).
 * JANGAN menambahkan pustaka luar/`ZipArchive` untuk keperluan ini.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** Susun berkas ZIP dari daftar entri (nama => isi) — semuanya TANPA kompresi. */
function zip_sederhana(array $entri): string
{
    $lokal = '';
    $pusat = '';
    $offset = 0;
    $jam = getdate();
    $waktuDos = (($jam['hours'] << 11) | ($jam['minutes'] << 5) | ($jam['seconds'] >> 1)) & 0xFFFF;
    $tglDos = ((($jam['year'] - 1980) << 9) | ($jam['mon'] << 5) | $jam['mday']) & 0xFFFF;

    foreach ($entri as $nama => $isi) {
        $nama = (string) $nama;
        $isi = (string) $isi;
        $crc = crc32($isi);
        $panjang = strlen($isi);
        $kepala = pack('V', 0x04034b50) . pack('v', 20) . pack('v', 0) . pack('v', 0)
            . pack('v', $waktuDos) . pack('v', $tglDos) . pack('V', $crc)
            . pack('V', $panjang) . pack('V', $panjang) . pack('v', strlen($nama)) . pack('v', 0);
        $lokal .= $kepala . $nama . $isi;

        $pusat .= pack('V', 0x02014b50) . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 0)
            . pack('v', $waktuDos) . pack('v', $tglDos) . pack('V', $crc)
            . pack('V', $panjang) . pack('V', $panjang) . pack('v', strlen($nama)) . pack('v', 0)
            . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $offset)
            . $nama;
        $offset += strlen($kepala) + strlen($nama) + $panjang;
    }
    $akhir = pack('V', 0x06054b50) . pack('v', 0) . pack('v', 0)
        . pack('v', count($entri)) . pack('v', count($entri))
        . pack('V', strlen($pusat)) . pack('V', strlen($lokal)) . pack('v', 0);
    return $lokal . $pusat . $akhir;
}

/** Escape teks untuk XML Word. */
function docx_esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Satu paragraf Word. $opsi: tebal, miring, tengah, kanan, ukuran (setengah poin), jarak (dxa), indent (dxa). */
function docx_par(string $teks, array $opsi = []): string
{
    $ukuran = (int) ($opsi['ukuran'] ?? 22); // 22 = 11pt, 28 = 14pt, 32 = 16pt
    $rpr = '<w:rPr><w:sz w:val="' . $ukuran . '"/><w:szCs w:val="' . $ukuran . '"/>'
        . (!empty($opsi['tebal']) ? '<w:b/>' : '') . (!empty($opsi['miring']) ? '<w:i/>' : '') . '</w:rPr>';
    $jc = !empty($opsi['tengah']) ? 'center' : (!empty($opsi['kanan']) ? 'right' : 'left');
    $pr = '<w:pPr><w:jc w:val="' . $jc . '"/>';
    $indent = (int) ($opsi['indent'] ?? 0);
    if ($indent > 0) {
        $pr .= '<w:ind w:left="' . $indent . '"/>';
    }
    $pr .= '<w:spacing w:before="0" w:after="' . (int) ($opsi['jarak'] ?? 60)
        . '" w:line="276" w:lineRule="auto"/></w:pPr>';

    $isi = '';
    foreach (explode("\n", $teks) as $i => $baris) {
        if ($i > 0) {
            $isi .= '<w:r><w:br/></w:r>';
        }
        $isi .= '<w:r>' . $rpr . '<w:t xml:space="preserve">' . docx_esc($baris) . '</w:t></w:r>';
    }
    if ($isi === '') {
        $isi = '<w:r>' . $rpr . '<w:t xml:space="preserve"></w:t></w:r>';
    }
    return '<w:p>' . $pr . $isi . '</w:p>';
}

/** Satu sel tabel Word (teks boleh memuat baris baru lewat <w:br/>). */
function docx_sel(string $teks, bool $tebal = false): string
{
    $par = '';
    foreach (explode("\n", $teks) as $i => $baris) {
        $par .= '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr><w:r>'
            . ($tebal ? '<w:rPr><w:b/><w:sz w:val="19"/></w:rPr>' : '<w:rPr><w:sz w:val="19"/></w:rPr>')
            . '<w:t xml:space="preserve">' . docx_esc($baris) . '</w:t></w:r></w:p>';
    }
    return '<w:tc><w:tcPr><w:tcMar><w:top w:w="30" w:type="dxa"/><w:bottom w:w="30" w:type="dxa"/>'
        . '<w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tcMar></w:tcPr>' . $par . '</w:tc>';
}

/** Satu baris tabel Word. Tiap sel boleh berupa string atau [teks, tebal]. */
function docx_baris(array $sel): string
{
    $isi = '';
    foreach ($sel as $s) {
        $teks = is_array($s) ? (string) ($s[0] ?? '') : (string) $s;
        $tebal = is_array($s) ? !empty($s[1]) : false;
        $isi .= docx_sel($teks, $tebal);
    }
    return '<w:tr>' . $isi . '</w:tr>';
}

/** Tabel Word bergaris. $kolom = daftar judul, $baris = daftar baris (tiap baris daftar sel). */
function docx_tabel(array $kolom, array $baris): string
{
    $t = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/>'
        . '<w:tblLayout w:type="fixed"/><w:tblBorders>'
        . '<w:top w:val="single" w:sz="6" w:color="666666"/><w:left w:val="single" w:sz="6" w:color="666666"/>'
        . '<w:bottom w:val="single" w:sz="6" w:color="666666"/><w:right w:val="single" w:sz="6" w:color="666666"/>'
        . '<w:insideH w:val="single" w:sz="4" w:color="999999"/><w:insideV w:val="single" w:sz="4" w:color="999999"/>'
        . '</w:tblBorders></w:tblPr>';
    $kepala = [];
    foreach ($kolom as $k) {
        $kepala[] = [(string) $k, true];
    }
    $t .= docx_baris($kepala);
    foreach ($baris as $b) {
        $t .= docx_baris($b);
    }
    return $t . '</w:tbl><w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';
}

/**
 * Bangun dokumen .docx dari daftar blok. Halaman A4 POTRAIT (21 x 29,7 cm) dengan margin 2,5 cm —
 * mengikuti bentuk lembar cetak HTML-nya. `'halaman' => true` menyisipkan pemisah halaman.
 */
function docx_bangun(array $blok, string $judul = ''): string
{
    $isi = '';
    foreach ($blok as $b) {
        if (!empty($b['halaman'])) {
            $isi .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
            continue;
        }
        if (isset($b['tabel']) && is_array($b['tabel'])) {
            $isi .= docx_tabel($b['tabel'][0] ?? [], $b['tabel'][1] ?? []);
            continue;
        }
        $isi .= docx_par((string) ($b['teks'] ?? ''), $b);
    }
    $dokumen = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:body>' . $isi
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1418" w:header="708" w:footer="708" w:gutter="0"/>'
        . '</w:sectPr></w:body></w:document>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '</Relationships>';
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/">'
        . '<dc:title>' . docx_esc($judul !== '' ? $judul : APP_NAME) . '</dc:title>'
        . '</cp:coreProperties>';

    return zip_sederhana([
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels'       => $rels,
        'docProps/core.xml' => $core,
        'word/document.xml' => $dokumen,
    ]);
}

/** Kirim berkas .docx ke peramban lalu hentikan skrip. */
function docx_kirim(string $namaBerkas, string $bytes): void
{
    if (!str_ends_with(strtolower($namaBerkas), '.docx')) {
        $namaBerkas .= '.docx';
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}
