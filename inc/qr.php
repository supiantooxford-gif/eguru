<?php
/**
 * PEMBUAT BARCODE QR (kode QR) — e-GURU v1.71.0.
 *
 * Permintaan pemilik: *"Pada bagian cetak kartu pelajar tambahkan barcode … Barcode ketika di
 * scan menampilkan data Lembaga/sekolah"*. Kode yang dipakai adalah **QR** karena isinya berupa
 * ALAMAT (URL) halaman data lembaga — kamera HP dapat membukanya langsung, sedangkan barcode
 * garis (Code 39/128) tidak praktis untuk alamat sepanjang ini pada kartu 88 × 55 mm.
 *
 * Ditulis sendiri TANPA pustaka luar / TANPA GD (server ini tidak menyediakan GD di PHP CLI, dan
 * aplikasi ini memang tidak memakai pustaka luar). Keluarannya **SVG** yang dicetak halaman
 * (bukan berkas gambar), jadi tetap tajam saat dicetak maupun diperbesar.
 *
 * Yang diterapkan: mode BYTE (UTF-8 apa adanya), koreksi galat tingkat **M**, versi **1–10**
 * (cukup untuk alamat ±200 karakter), Reed–Solomon GF(256) polinomial 0x11D, pola fungsi standar
 * (finder/timing/alignment/dark module), info format & versi, serta pemilihan mask 0–7.
 *
 * Pemakaian: `echo qr_svg('https://…', 'Kode QR data lembaga');`
 */

/** Tabel kapasitas: versi => [total codeword, EC per blok, [[jumlah blok, codeword data], …]] (ECC M). */
function qr_tabel_m(): array
{
    return [
        1  => [26, 10, [[1, 16]]],
        2  => [44, 16, [[1, 28]]],
        3  => [70, 26, [[1, 44]]],
        4  => [100, 18, [[2, 32]]],
        5  => [134, 24, [[2, 43]]],
        6  => [172, 16, [[4, 27]]],
        7  => [196, 18, [[4, 31]]],
        8  => [242, 22, [[2, 38], [2, 39]]],
        9  => [292, 22, [[3, 36], [2, 37]]],
        10 => [346, 26, [[4, 43], [1, 44]]],
    ];
}

/** Posisi pola alignment per versi. */
function qr_alignment(): array
{
    return [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
}

/** Tabel eksponen & logaritma GF(256) (polinomial 0x11D). */
function qr_gf(): array
{
    static $tabel = null;
    if ($tabel !== null) {
        return $tabel;
    }
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if (($x & 0x100) !== 0) {
            $x ^= 0x11D;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }
    return $tabel = [$exp, $log];
}

/** Perkalian dua elemen GF(256). */
function qr_gf_kali(int $a, int $b): int
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    [$exp, $log] = qr_gf();
    return $exp[$log[$a] + $log[$b]];
}

/** Polinomial generator Reed–Solomon berderajat $derajat. */
function qr_rs_pembagi(int $derajat): array
{
    $hasil = array_fill(0, $derajat, 0);
    $hasil[$derajat - 1] = 1;
    $akar = 1;
    for ($i = 0; $i < $derajat; $i++) {
        for ($j = 0; $j < $derajat; $j++) {
            $hasil[$j] = qr_gf_kali($hasil[$j], $akar);
            if ($j + 1 < $derajat) {
                $hasil[$j] ^= $hasil[$j + 1];
            }
        }
        $akar = qr_gf_kali($akar, 0x02);
    }
    return $hasil;
}

/** Sisa pembagian polinomial data oleh pembagi RS (= codeword koreksi galat). */
function qr_rs_sisa(array $data, array $pembagi): array
{
    $hasil = array_fill(0, count($pembagi), 0);
    foreach ($data as $b) {
        $faktor = $b ^ array_shift($hasil);
        $hasil[] = 0;
        foreach ($pembagi as $i => $koef) {
            $hasil[$i] ^= qr_gf_kali($koef, $faktor);
        }
    }
    return $hasil;
}

/** Bit ke-$i (dihitung dari LSB) dari sebuah bilangan bulat. */
function qr_bit(int $x, int $i): int
{
    return ($x >> $i) & 1;
}

/**
 * Menyusun matriks modul QR (array 2 dimensi berisi 0/1).
 * Mengembalikan null bila teks terlalu panjang untuk versi yang didukung.
 */
function qr_matriks(string $teks): ?array
{
    $data = array_values(unpack('C*', $teks) ?: []);
    $tabel = qr_tabel_m();

    // ---- pilih versi terkecil yang muat ----
    $versi = 0;
    $struktur = [];
    foreach ($tabel as $v => [$total, $ecPerBlok, $blok]) {
        $kapasitasData = 0;
        foreach ($blok as [$jml, $dataPerBlok]) {
            $kapasitasData += $jml * $dataPerBlok;
        }
        $bitHitung = $v <= 9 ? 8 : 16;
        $perlu = (int) ceil((4 + $bitHitung + count($data) * 8) / 8);
        if ($perlu <= $kapasitasData) {
            $versi = $v;
            $struktur = ['total' => $total, 'ec' => $ecPerBlok, 'blok' => $blok,
                'data' => $kapasitasData];
            break;
        }
    }
    if ($versi === 0) {
        return null;
    }

    // ---- rangkaian bit: mode BYTE + jumlah + data ----
    $bit = [];
    $tulis = static function (int $nilai, int $panjang) use (&$bit): void {
        for ($i = $panjang - 1; $i >= 0; $i--) {
            $bit[] = ($nilai >> $i) & 1;
        }
    };
    $tulis(0b0100, 4);
    $tulis(count($data), $versi <= 9 ? 8 : 16);
    foreach ($data as $b) {
        $tulis($b, 8);
    }
    // terminator (maks 4 bit nol) lalu rapikan ke batas byte
    $maksBit = $struktur['data'] * 8;
    $sisa = min(4, $maksBit - count($bit));
    for ($i = 0; $i < $sisa; $i++) {
        $bit[] = 0;
    }
    while (count($bit) % 8 !== 0) {
        $bit[] = 0;
    }
    // codeword data + padding 0xEC/0x11 bergantian
    $codeword = [];
    for ($i = 0; $i < count($bit); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bit[$i + $j];
        }
        $codeword[] = $byte;
    }
    $pad = [0xEC, 0x11];
    $ke = 0;
    while (count($codeword) < $struktur['data']) {
        $codeword[] = $pad[$ke % 2];
        $ke++;
    }

    // ---- pecah per blok, hitung EC, lalu interleave ----
    $pembagi = qr_rs_pembagi($struktur['ec']);
    $blokData = [];
    $blokEc = [];
    $pos = 0;
    foreach ($struktur['blok'] as [$jml, $dataPerBlok]) {
        for ($i = 0; $i < $jml; $i++) {
            $potong = array_slice($codeword, $pos, $dataPerBlok);
            $pos += $dataPerBlok;
            $blokData[] = $potong;
            $blokEc[] = qr_rs_sisa($potong, $pembagi);
        }
    }
    $gabung = [];
    $maksData = 0;
    foreach ($blokData as $b) {
        $maksData = max($maksData, count($b));
    }
    for ($i = 0; $i < $maksData; $i++) {
        foreach ($blokData as $b) {
            if ($i < count($b)) {
                $gabung[] = $b[$i];
            }
        }
    }
    $maksEc = count($pembagi);
    for ($i = 0; $i < $maksEc; $i++) {
        foreach ($blokEc as $b) {
            if ($i < count($b)) {
                $gabung[] = $b[$i];
            }
        }
    }

    // ---- siapkan matriks & pola fungsi ----
    $ukuran = 17 + 4 * $versi;
    $m = array_fill(0, $ukuran, array_fill(0, $ukuran, 0));
    $fungsi = array_fill(0, $ukuran, array_fill(0, $ukuran, false));

    $setFungsi = static function (int $x, int $y, int $nilai) use (&$m, &$fungsi): void {
        $m[$y][$x] = $nilai;
        $fungsi[$y][$x] = true;
    };

    // timing pattern
    for ($i = 0; $i < $ukuran; $i++) {
        $setFungsi(6, $i, $i % 2 === 0 ? 1 : 0);
        $setFungsi($i, 6, $i % 2 === 0 ? 1 : 0);
    }
    // finder + separator
    $finder = static function (int $cx, int $cy) use ($setFungsi, $ukuran): void {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $y < 0 || $x >= $ukuran || $y >= $ukuran) {
                    continue;
                }
                $jarak = max(abs($dx), abs($dy));
                $setFungsi($x, $y, ($jarak !== 2 && $jarak !== 4) ? 1 : 0);
            }
        }
    };
    $finder(3, 3);
    $finder($ukuran - 4, 3);
    $finder(3, $ukuran - 4);
    // alignment pattern
    $posisiAlign = qr_alignment()[$versi] ?? [];
    $jmlAlign = count($posisiAlign);
    for ($i = 0; $i < $jmlAlign; $i++) {
        for ($j = 0; $j < $jmlAlign; $j++) {
            // lewati yang bertabrakan dengan finder
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $jmlAlign - 1)
                || ($i === $jmlAlign - 1 && $j === 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $jarak = max(abs($dx), abs($dy));
                    $setFungsi($posisiAlign[$i] + $dx, $posisiAlign[$j] + $dy,
                        ($jarak !== 1) ? 1 : 0);
                }
            }
        }
    }
    // cadangkan area info format & versi (diisi di akhir)
    $setFungsi(8, $ukuran - 8, 1);   // dark module
    // area info format
    for ($i = 0; $i <= 8; $i++) {
        $fungsi[8][$i] = true;
        $fungsi[$i][8] = true;
    }
    for ($i = 0; $i < 8; $i++) {
        $fungsi[8][$ukuran - 1 - $i] = true;
        $fungsi[$ukuran - 1 - $i][8] = true;
    }
    // area info versi (versi >= 7)
    if ($versi >= 7) {
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $fungsi[$ukuran - 11 + $j][$i] = true;
                $fungsi[$i][$ukuran - 11 + $j] = true;
            }
        }
    }

    // ---- tempatkan bit data (zig-zag dari kanan bawah) ----
    $i2 = 0;
    $totalBit = count($gabung) * 8;
    for ($kanan = $ukuran - 1; $kanan >= 1; $kanan -= 2) {
        if ($kanan === 6) {
            $kanan = 5;
        }
        for ($vert = 0; $vert < $ukuran; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $kanan - $j;
                $naik = (($kanan + 1) & 2) === 0;
                $y = $naik ? $ukuran - 1 - $vert : $vert;
                if (!$fungsi[$y][$x] && $i2 < $totalBit) {
                    $m[$y][$x] = qr_bit($gabung[$i2 >> 3], 7 - ($i2 & 7));
                    $i2++;
                }
            }
        }
    }

    // ---- pilih mask dengan penalti terkecil, lalu tulis info format ----
    $terbaik = null;
    $penaltiTerbaik = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $coba = $m;
        for ($y = 0; $y < $ukuran; $y++) {
            for ($x = 0; $x < $ukuran; $x++) {
                if ($fungsi[$y][$x]) {
                    continue;
                }
                $balik = false;
                switch ($mask) {
                    case 0: $balik = ($x + $y) % 2 === 0; break;
                    case 1: $balik = $y % 2 === 0; break;
                    case 2: $balik = $x % 3 === 0; break;
                    case 3: $balik = ($x + $y) % 3 === 0; break;
                    case 4: $balik = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $balik = (($x * $y) % 2 + ($x * $y) % 3) === 0; break;
                    case 6: $balik = ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0; break;
                    case 7: $balik = ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0; break;
                }
                if ($balik) {
                    $coba[$y][$x] ^= 1;
                }
            }
        }
        // info format: ECC M = 0b00
        $bits = qr_format_bits(0, $mask);
        for ($i = 0; $i <= 5; $i++) {
            $coba[$i][8] = qr_bit($bits, $i);
        }
        $coba[7][8] = qr_bit($bits, 6);
        $coba[8][8] = qr_bit($bits, 7);
        $coba[8][7] = qr_bit($bits, 8);
        for ($i = 9; $i < 15; $i++) {
            $coba[8][14 - $i] = qr_bit($bits, $i);
        }
        for ($i = 0; $i < 8; $i++) {
            $coba[8][$ukuran - 1 - $i] = qr_bit($bits, $i);
        }
        for ($i = 8; $i < 15; $i++) {
            $coba[8][$ukuran - 15 + $i] = qr_bit($bits, $i);
        }
        $coba[$ukuran - 8][8] = 1;
        // info versi (wajib untuk versi >= 7)
        if ($versi >= 7) {
            $vbits = qr_version_bits($versi);
            for ($i = 0; $i < 18; $i++) {
                $b = qr_bit($vbits, $i);
                $a = $ukuran - 11 + $i % 3;
                $bb = intdiv($i, 3);
                $coba[$bb][$a] = $b;
                $coba[$a][$bb] = $b;
            }
        }
        $p = qr_penalti($coba, $ukuran);
        if ($p < $penaltiTerbaik) {
            $penaltiTerbaik = $p;
            $terbaik = $coba;
        }
    }
    return $terbaik;
}

/** 15 bit info format (ECC M = 0b00) + BCH + topeng 0x5412. */
function qr_format_bits(int $ecc, int $mask): int
{
    $data = ($ecc << 3) | $mask;
    $rem = $data << 10;
    $panjang = static function (int $v): int {
        $n = 0;
        while ($v !== 0) {
            $v >>= 1;
            $n++;
        }
        return $n;
    };
    while ($panjang($rem) - 1 >= 10) {
        $rem ^= 0x537 << ($panjang($rem) - 11);
    }
    return (($data << 10) | $rem) ^ 0x5412;
}

/** 18 bit info versi (dipakai versi >= 7) + BCH generator 0x1F25. */
function qr_version_bits(int $versi): int
{
    $rem = $versi;
    for ($i = 0; $i < 12; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
    }
    return ($versi << 12) | ($rem & 0xFFF);
}

/** Penalti mask (aturan 1, 2, 3, 4 standar — hanya untuk memilih mask terbaik). */
function qr_penalti(array $m, int $ukuran): int
{
    $skor = 0;
    // aturan 1: deret 5+ modul sewarna
    for ($y = 0; $y < $ukuran; $y++) {
        for ($x = 0; $x < $ukuran; $x++) {
            if ($x + 4 < $ukuran && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y][$x + 2]
                && $m[$y][$x] === $m[$y][$x + 3] && $m[$y][$x] === $m[$y][$x + 4]) {
                $skor += 3;
            }
            if ($y + 4 < $ukuran && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 2][$x]
                && $m[$y][$x] === $m[$y + 3][$x] && $m[$y][$x] === $m[$y + 4][$x]) {
                $skor += 3;
            }
        }
    }
    // aturan 2: blok 2x2 sewarna
    for ($y = 0; $y < $ukuran - 1; $y++) {
        for ($x = 0; $x < $ukuran - 1; $x++) {
            $v = $m[$y][$x];
            if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                $skor += 3;
            }
        }
    }
    // aturan 4: keseimbangan terang/gelap
    $gelap = 0;
    foreach ($m as $baris) {
        foreach ($baris as $v) {
            $gelap += $v;
        }
    }
    $total = $ukuran * $ukuran;
    $persen = (int) floor($gelap * 100 / $total);
    $skor += 10 * intdiv(abs($persen - 50), 5);
    return $skor;
}

/**
 * SVG kode QR untuk $teks ('' bila teks terlalu panjang untuk versi yang didukung).
 *
 * @param string $teks   isi kode (mis. alamat halaman data lembaga)
 * @param string $kelas  kelas CSS untuk elemen <svg>
 * @param string $label  teks alternatif (aria-label) untuk pembaca layar
 * @param int    $quiet  lebar zona tenang dalam modul (standar minimal 4; 2 sudah terbaca di kartu)
 */
function qr_svg(string $teks, string $kelas = 'qr', string $label = 'Kode QR', int $quiet = 2): string
{
    $m = qr_matriks($teks);
    if ($m === null) {
        return '';
    }
    $ukuran = count($m);
    $total = $ukuran + 2 * $quiet;
    $path = [];
    for ($y = 0; $y < $ukuran; $y++) {
        for ($x = 0; $x < $ukuran; $x++) {
            if ($m[$y][$x] === 1) {
                $path[] = 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
            }
        }
    }
    return '<svg class="' . htmlspecialchars($kelas, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 ' . $total . ' ' . $total . '"'
        . ' shape-rendering="crispEdges" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"'
        . ' xmlns="http://www.w3.org/2000/svg">'
        . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
        . '<path fill="#0b1120" d="' . implode('', $path) . '"/>'
        . '</svg>';
}
