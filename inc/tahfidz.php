<?php
/**
 * MODUL MENU TUGAS TAMBAHAN "PEMBINA TAHFIDZ" — SETORAN HAFALAN SISWA.
 *
 * Permintaan pemilik: *"Pada menu Pembina Tahfidz isi dengan inputan modul No, nama siswa, kelas,
 * Setor hafalan (nama surah, dari ayat ke, sampai ayat ke), keterangan. Tambahkan fitur cetak dan
 * hasil cetak seperti contoh. Tambahkan tanda tangan Pembina tahfidz dan mengetahui kepala
 * madrasah/sekolah. Untuk nama surah isi otomatis pilihan nama surah dari 1. Al-Fatihah - 114.
 * An-Nas. Untuk ayatnya di sesuaikan dengan jumlah ayat setiap surah di dalam al-qur'an"*
 *
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. Nama siswa **dipilih dari Data Siswa** (pilih kelas → daftar siswa kelas itu);
 *   2. **Satu setoran per siswa per tanggal** — jadi ada riwayat (siswa muncul beberapa kali pada
 *      tanggal berbeda), dan menyimpan ulang pada tanggal yang sama MEMPERBARUI setoran itu;
 *   3. Cetak **dua mode**: per kelas (lembar isian seluruh siswa kelas itu, sel kosong bila belum
 *      ada setoran) dan rincian (semua setoran pada rentang tanggal).
 *
 * Daftar 114 surah + jumlah ayatnya ada pada `tahfidz_surah_list()` di bawah (BUKAN di basis data)
 * sehingga:
 *   • pilihan "Nama Surah" pada formulir selalu lengkap 1..114,
 *   • batas "Dari ayat ke-" / "Sampai ayat ke-" otomatis mengikuti jumlah ayat surah terpilih
 *     (diperiksa ULANG di server, bukan hanya oleh skrip),
 *   • tidak perlu migrasi bila suatu saat daftar itu diperbaiki.
 *
 * Data disimpan per SEKOLAH (`tahfidz_setoran.sekolah_id`) — setiap query dibatasi `sekolah_id()`
 * termasuk saat mengambil satu baris berdasarkan id (anti-IDOR).
 *
 * Hak ubah mengikuti aturan menu Tugas Tambahan lain: `boleh_ubah_tugas_menu()` (guru yang
 * ditugaskan + Super Admin dalam konteks). Kepala sekolah/madrasah & Admin Operator hanya melihat
 * dan mencetak; pengawas juga hanya melihat & mencetak (gerbang pengawas di inc/helpers.php).
 */
declare(strict_types=1);

// ============================================================================
// DAFTAR SURAH AL-QUR'AN (1..114) + JUMLAH AYAT
// ============================================================================

/**
 * Daftar 114 surah: [nomor, nama, jumlah ayat].
 *
 * Nama memakai ejaan Indonesia yang lazim (Al-Fatihah, An-Nas, …). Jumlah ayat mengikuti mushaf
 * standar (total 6236 ayat) — dipakai sebagai batas "Dari ayat ke-" / "Sampai ayat ke-" dan
 * diperiksa ulang di `tahfidz_ayat_benar()`.
 *
 * @return array<int,array{no:int,nama:string,ayat:int}>
 */
function tahfidz_surah_list(): array
{
    static $daftar = null;
    if ($daftar !== null) {
        return $daftar;
    }
    // [nama, jumlah ayat] — urutan array = nomor surah (1..114).
    $baku = [
        ['Al-Fatihah', 7],    ['Al-Baqarah', 286], ['Ali \'Imran', 200], ['An-Nisa', 176],
        ['Al-Ma\'idah', 120], ['Al-An\'am', 165],  ['Al-A\'raf', 206],   ['Al-Anfal', 75],
        ['At-Taubah', 129],   ['Yunus', 109],      ['Hud', 123],         ['Yusuf', 111],
        ['Ar-Ra\'d', 43],     ['Ibrahim', 52],     ['Al-Hijr', 99],      ['An-Nahl', 128],
        ['Al-Isra', 111],     ['Al-Kahf', 110],    ['Maryam', 98],       ['Ta-Ha', 135],
        ['Al-Anbiya', 112],   ['Al-Hajj', 78],     ['Al-Mu\'minun', 118],['An-Nur', 64],
        ['Al-Furqan', 77],    ['Asy-Syu\'ara', 227],['An-Naml', 93],     ['Al-Qasas', 88],
        ['Al-\'Ankabut', 69], ['Ar-Rum', 60],      ['Luqman', 34],       ['As-Sajdah', 30],
        ['Al-Ahzab', 73],     ['Saba', 54],        ['Fatir', 45],        ['Yasin', 83],
        ['As-Saffat', 182],   ['Sad', 88],         ['Az-Zumar', 75],     ['Ghafir', 85],
        ['Fussilat', 54],     ['Asy-Syura', 53],   ['Az-Zukhruf', 89],   ['Ad-Dukhan', 59],
        ['Al-Jasiyah', 37],   ['Al-Ahqaf', 35],    ['Muhammad', 38],     ['Al-Fath', 29],
        ['Al-Hujurat', 18],   ['Qaf', 45],         ['Az-Zariyat', 60],   ['At-Tur', 49],
        ['An-Najm', 62],      ['Al-Qamar', 55],    ['Ar-Rahman', 78],    ['Al-Waqi\'ah', 96],
        ['Al-Hadid', 29],     ['Al-Mujadilah', 22],['Al-Hasyr', 24],     ['Al-Mumtahanah', 13],
        ['As-Saff', 14],      ['Al-Jumu\'ah', 11], ['Al-Munafiqun', 11], ['At-Tagabun', 18],
        ['At-Talaq', 12],     ['At-Tahrim', 12],   ['Al-Mulk', 30],      ['Al-Qalam', 52],
        ['Al-Haqqah', 52],    ['Al-Ma\'arij', 44], ['Nuh', 28],          ['Al-Jinn', 28],
        ['Al-Muzzammil', 20], ['Al-Muddatstsir', 56], ['Al-Qiyamah', 40],['Al-Insan', 31],
        ['Al-Mursalat', 50],  ['An-Naba', 40],     ['An-Nazi\'at', 46],  ['\'Abasa', 42],
        ['At-Takwir', 29],    ['Al-Infitar', 19],  ['Al-Mutaffifin', 36],['Al-Insyiqaq', 25],
        ['Al-Buruj', 22],     ['At-Tariq', 17],    ['Al-A\'la', 19],     ['Al-Gasyiyah', 26],
        ['Al-Fajr', 30],      ['Al-Balad', 20],    ['Asy-Syams', 15],    ['Al-Lail', 21],
        ['Ad-Duha', 11],      ['Asy-Syarh', 8],    ['At-Tin', 8],        ['Al-\'Alaq', 19],
        ['Al-Qadr', 5],       ['Al-Bayyinah', 8],  ['Az-Zalzalah', 8],   ['Al-\'Adiyat', 11],
        ['Al-Qari\'ah', 11],  ['At-Takasur', 8],   ['Al-\'Asr', 3],      ['Al-Humazah', 9],
        ['Al-Fil', 5],        ['Quraisy', 4],      ['Al-Ma\'un', 7],     ['Al-Kausar', 3],
        ['Al-Kafirun', 6],    ['An-Nasr', 3],      ['Al-Lahab', 5],      ['Al-Ikhlas', 4],
        ['Al-Falaq', 5],      ['An-Nas', 6],
    ];
    $daftar = [];
    foreach ($baku as $i => $b) {
        $daftar[] = ['no' => $i + 1, 'nama' => $b[0], 'ayat' => (int) $b[1]];
    }
    return $daftar;
}

/** Satu surah berdasarkan nomornya (1..114); null bila di luar jangkauan. */
function tahfidz_surah(int $no): ?array
{
    if ($no < 1 || $no > 114) {
        return null;
    }
    $daftar = tahfidz_surah_list();
    return $daftar[$no - 1] ?? null;
}

/**
 * Cari surah berdasarkan NAMANYA (untuk data lama/impor manual). Pencocokan mengabaikan besar-kecil
 * huruf, spasi, tanda hubung, dan apostrof — jadi "al baqarah" tetap menemukan "Al-Baqarah".
 */
function tahfidz_surah_cari(string $nama): ?array
{
    $kunci = static function (string $s): string {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($s));
    };
    $cari = $kunci($nama);
    if ($cari === '') {
        return null;
    }
    foreach (tahfidz_surah_list() as $s) {
        if ($kunci($s['nama']) === $cari) {
            return $s;
        }
    }
    return null;
}

/**
 * Nomor surah dari nilai kiriman formulir: boleh nomor ("2") maupun nama ("Al-Baqarah").
 * Nilai yang tidak dikenali dikembalikan sebagai 0 (pemanggil yang memutuskan pesan galatnya).
 */
function tahfidz_surah_dari($nilai): int
{
    $nilai = trim((string) $nilai);
    if ($nilai === '') {
        return 0;
    }
    // ⚠️ JANGAN memakai ctype_digit(): ekstensi ctype TIDAK selalu tersedia di server ini
    // (terbukti fatal "Call to undefined function ctype_digit()"), jadi dipakai preg_match.
    if (preg_match('/^\d+$/', $nilai) === 1) {
        return tahfidz_surah((int) $nilai) !== null ? (int) $nilai : 0;
    }
    $s = tahfidz_surah_cari($nilai);
    return $s !== null ? (int) $s['no'] : 0;
}

/**
 * Rentang ayat yang SAH untuk sebuah surah.
 *
 * Aturan: `dari` minimal 1, `sampai` maksimal jumlah ayat surah itu, dan `dari` tidak melebihi
 * `sampai`. Nilai di luar batas DIPOTONG (bukan ditolak) supaya kiriman yang keliru tetap tersimpan
 * dalam bentuk yang masuk akal; `null` bila nomor surahnya tidak sah.
 *
 * @return array{0:int,1:int}|null [dari, sampai]
 */
function tahfidz_ayat_benar(int $surahNo, int $dari, int $sampai): ?array
{
    $s = tahfidz_surah($surahNo);
    if ($s === null) {
        return null;
    }
    $maks = (int) $s['ayat'];
    $dari = max(1, min($maks, $dari > 0 ? $dari : 1));
    $sampai = max(1, min($maks, $sampai > 0 ? $sampai : $dari));
    if ($sampai < $dari) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    return [$dari, $sampai];
}

// ============================================================================
// PENGENALAN MENU
// ============================================================================

/**
 * Apakah baris `tugas_menu` ini adalah menu **Pembina Tahfidz**?
 *
 * ⚠️ Dicocokkan lewat KODE **maupun NAMA**: pada basis data pemilik menunya sudah dibuat sendiri
 * lewat "Kelola Tugas Tambahan" dan kodenya bukan `pembina_tahfidz` (kode tidak dapat diubah
 * setelah dibuat). Tanpa pencocokan nama, modul ini tidak akan pernah terbuka di sana.
 * JANGAN mengganti menjadi pencocokan kode saja.
 */
function tahfidz_tugas_kunci(?array $tugas): bool
{
    if (!$tugas) {
        return false;
    }
    $kode = strtolower(trim((string) ($tugas['kode'] ?? '')));
    if ($kode !== '' && ($kode === TAHFIDZ_KODE_MENU || in_array($kode, TAHFIDZ_KODE_LAIN, true))) {
        return true;
    }
    $nama = tugas_nama_kunci((string) ($tugas['nama'] ?? ''));
    return $nama !== '' && in_array($nama, TAHFIDZ_NAMA_MENU, true);
}

/** Kode yang dipakai URL menu ini (kode menu yang sebenarnya dipakai, agar tautan tetap benar). */
function tahfidz_kode(array $tugas): string
{
    $kode = trim((string) ($tugas['kode'] ?? ''));
    return $kode !== '' ? $kode : TAHFIDZ_KODE_MENU;
}

// ============================================================================
// DATA SETORAN
// ============================================================================

/**
 * Daftar setoran pada sekolah yang sedang aktif, TERBARU di atas.
 *
 * @param array<string,mixed> $f filter: kelas (id), dari, sampai, cari
 * @return array<int,array<string,mixed>>
 */
function tahfidz_setoran_list(array $f = []): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    $w = ['sekolah_id = ?'];
    $p = [$sid];
    $kelas = (int) ($f['kelas'] ?? 0);
    if ($kelas > 0) {
        $w[] = 'kelas_id = ?';
        $p[] = $kelas;
    }
    $dari = tgl_ok($f['dari'] ?? '');
    $sampai = tgl_ok($f['sampai'] ?? '');
    if ($dari !== null && $sampai !== null && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    if ($dari !== null) {
        $w[] = 'tanggal >= ?';
        $p[] = $dari;
    }
    if ($sampai !== null) {
        $w[] = 'tanggal <= ?';
        $p[] = $sampai;
    }
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(siswa_nama LIKE ? OR surah_nama LIKE ? OR keterangan LIKE ? OR kelas_nama LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($p, $like, $like, $like, $like);
    }
    return all('SELECT * FROM tahfidz_setoran WHERE ' . implode(' AND ', $w)
        . ' ORDER BY tanggal DESC, siswa_nama COLLATE NOCASE, id DESC', $p);
}

/** Satu setoran berdasarkan id — WAJIB dibatasi sekolah (anti-IDOR). */
function tahfidz_setoran_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM tahfidz_setoran WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
}

/**
 * Setoran seorang siswa pada satu TANGGAL (dasar aturan "satu setoran per siswa per tanggal").
 * Dipakai saat menyimpan: baris yang sudah ada DIPERBARUI, bukan ditambah.
 */
function tahfidz_setoran_tanggal(int $siswaId, string $tanggal): ?array
{
    if ($siswaId <= 0 || $tanggal === '') {
        return null;
    }
    return one('SELECT * FROM tahfidz_setoran WHERE siswa_id = ? AND tanggal = ? AND sekolah_id = ?',
        [$siswaId, $tanggal, sekolah_id()]);
}

/**
 * Setoran TERAKHIR tiap siswa pada sebuah rentang tanggal — dipakai lembar cetak mode per kelas.
 *
 * @param array<int,int> $siswaIds
 * @return array<int,array<string,mixed>> siswa_id => baris setoran
 */
function tahfidz_terakhir_map(array $siswaIds, string $dari = '', string $sampai = ''): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $siswaIds),
        static fn(int $v): bool => $v > 0)));
    if (!$ids || sekolah_id() <= 0) {
        return [];
    }
    $w = ['sekolah_id = ?', 'siswa_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'];
    $p = array_merge([sekolah_id()], $ids);
    if ($dari !== '') {
        $w[] = 'tanggal >= ?';
        $p[] = $dari;
    }
    if ($sampai !== '') {
        $w[] = 'tanggal <= ?';
        $p[] = $sampai;
    }
    $peta = [];
    // Urut naik (tanggal, id) lalu ditimpa terus → yang tersisa adalah baris TERAKHIR.
    foreach (all('SELECT * FROM tahfidz_setoran WHERE ' . implode(' AND ', $w)
        . ' ORDER BY tanggal, id', $p) as $r) {
        $peta[(int) $r['siswa_id']] = $r;
    }
    return $peta;
}

/**
 * Ringkasan untuk kartu chip: jumlah setoran, siswa yang menyetor, kelas yang tercatat, dan
 * sebaran surah terbanyak.
 *
 * @param array<int,array<string,mixed>> $baris
 * @return array{setoran:int,siswa:int,kelas:int,surah:array<string,int>}
 */
function tahfidz_ringkas(array $baris): array
{
    $siswa = [];
    $kelas = [];
    $surah = [];
    foreach ($baris as $r) {
        $siswa[(int) $r['siswa_id']] = true;
        $kelas[(string) $r['kelas_nama']] = true;
        $s = (string) ($r['surah_nama'] ?? '');
        if ($s !== '') {
            $surah[$s] = ($surah[$s] ?? 0) + 1;
        }
    }
    arsort($surah);
    return ['setoran' => count($baris), 'siswa' => count($siswa), 'kelas' => count($kelas), 'surah' => $surah];
}

/** Hapus satu setoran (dibatasi sekolah). */
function tahfidz_setoran_hapus(int $id): bool
{
    $r = tahfidz_setoran_by_id($id);
    if (!$r) {
        return false;
    }
    q('DELETE FROM tahfidz_setoran WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
    return true;
}

/**
 * Nama & NIP PEMBINA TAHFIDZ untuk blok tanda tangan lembar cetak.
 *
 * Urutan sumber:
 *   1. data guru milik akun yang DITUGASKAN pada menu ini (`user_tugas` → `users.guru_id` → `guru`);
 *   2. bila yang sedang masuk adalah salah satu pengguna itu, dialah yang dipakai;
 *   3. cadangan: nama akun yang sedang masuk.
 * NIP boleh kosong (dicetak "-" oleh `ttd_blok()`).
 *
 * @return array{nama:string,nip:string}
 */
function tahfidz_pembina(?array $tugas = null, ?int $guruIdPilih = 0): array
{
    $sid = sekolah_id();
    $daftar = [];
    if ($tugas && $sid > 0) {
        $tid = (int) ($tugas['id'] ?? 0);
        if ($tid > 0) {
            $daftar = all('SELECT g.id, g.nama, g.nip FROM user_tugas ut
                             JOIN users u ON u.id = ut.user_id
                             JOIN guru g ON g.id = u.guru_id
                            WHERE ut.tugas_id = ? AND g.sekolah_id = ?
                            ORDER BY g.nama COLLATE NOCASE', [$tid, $sid]);
        }
    }
    if ($guruIdPilih !== null && $guruIdPilih > 0) {
        foreach ($daftar as $g) {
            if ((int) $g['id'] === $guruIdPilih) {
                return ['nama' => (string) $g['nama'], 'nip' => (string) ($g['nip'] ?? '')];
            }
        }
        // Guru yang dipilih tetapi bukan pengguna menu ini tetap dipakai bila memang guru sekolah ini.
        $g = guru_by_id($guruIdPilih);
        if ($g) {
            return ['nama' => (string) $g['nama'], 'nip' => (string) ($g['nip'] ?? '')];
        }
    }
    if ($daftar) {
        // Utamakan guru milik akun yang sedang masuk (pembina yang mencetak lembar ini).
        $guruSaya = (int) (guru_saya()['id'] ?? 0);
        foreach ($daftar as $g) {
            if ($guruSaya > 0 && (int) $g['id'] === $guruSaya) {
                return ['nama' => (string) $g['nama'], 'nip' => (string) ($g['nip'] ?? '')];
            }
        }
        return ['nama' => (string) $daftar[0]['nama'], 'nip' => (string) ($daftar[0]['nip'] ?? '')];
    }
    $u = current_user();
    return ['nama' => (string) ($u['nama'] ?? ''), 'nip' => ''];
}

/** Daftar guru sekolah ini (pilihan Pembina pada formulir cetak). */
function tahfidz_guru_list(): array
{
    if (sekolah_id() <= 0) {
        return [];
    }
    return all('SELECT id, nama, nip FROM guru WHERE sekolah_id = ? AND IFNULL(aktif,1) = 1
                ORDER BY nama COLLATE NOCASE', [sekolah_id()]);
}

/**
 * Parameter URL halaman menu ini (kode menu asli + filter yang sedang dilihat).
 *
 * @param array<string,mixed> $f
 */
function tahfidz_url(array $tugas, array $tambah = [], string $berkas = 'tugas.php'): string
{
    $q = ['kode' => tahfidz_kode($tugas)];
    foreach ($tambah as $k => $v) {
        if ((string) $v !== '') {
            $q[$k] = $v;
        }
    }
    return $berkas . '?' . http_build_query($q);
}

/** Label filter aktif (dipakai lembar cetak & keterangan pada halaman). */
function tahfidz_label_filter(array $f): array
{
    $label = [];
    $kelas = (int) ($f['kelas'] ?? 0);
    if ($kelas > 0) {
        $k = kelas_by_id($kelas);
        if ($k) {
            $label[] = 'Kelas: ' . $k['nama'];
        }
    }
    $dari = tgl_ok($f['dari'] ?? '');
    $sampai = tgl_ok($f['sampai'] ?? '');
    if ($dari !== null && $sampai !== null && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    if ($dari !== null && $sampai !== null) {
        $label[] = $dari === $sampai ? 'Tanggal: ' . tanggal_id($dari)
            : 'Tanggal: ' . tanggal_id($dari) . ' s.d. ' . tanggal_id($sampai);
    } elseif ($dari !== null) {
        $label[] = 'Sejak ' . tanggal_id($dari);
    } elseif ($sampai !== null) {
        $label[] = 'Sampai ' . tanggal_id($sampai);
    }
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $label[] = 'Cari: "' . $cari . '"';
    }
    return $label;
}

// ============================================================================
// HALAMAN MENU
// ============================================================================

/** Tabel daftar setoran (dipakai halaman menu). */
function tahfidz_tabel(array $baris, bool $bolehUbah, array $tugas): void
{
    ?>
    <div class="table-wrap">
      <table class="tbl tbl-tahfidz" id="tblSetoran">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th class="ctr" style="width:110px">Tanggal</th>
            <th>Nama Siswa</th>
            <th class="ctr" style="width:90px">Kelas</th>
            <th>Nama Surah</th>
            <th class="ctr" style="width:80px">Dari Ayat</th>
            <th class="ctr" style="width:90px">Sampai Ayat</th>
            <th>Keterangan</th>
            <?php if ($bolehUbah): ?><th class="ctr" style="width:150px">Tindakan</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td class="ctr xs"><?= e(tanggal_id((string) $r['tanggal'])) ?></td>
            <td><b><?= e((string) $r['siswa_nama']) ?></b></td>
            <td class="ctr xs"><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
            <td><?= e((string) $r['surah_nama']) ?></td>
            <td class="ctr"><?= (int) $r['ayat_dari'] ?></td>
            <td class="ctr"><?= (int) $r['ayat_sampai'] ?></td>
            <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
            <?php if ($bolehUbah): ?>
              <td class="ctr">
                <div class="row-actions" style="justify-content:center">
                  <a class="btn btn-ghost btn-sm"
                     href="<?= e(tahfidz_url($tugas, ['edit' => (int) $r['id']]) . '#formSetoran') ?>"><?= ikon('edit', 14) ?> Ubah</a>
                  <form method="post" data-confirm="Hapus setoran hafalan <?= e((string) $r['siswa_nama']) ?> tanggal <?= e(tanggal_id((string) $r['tanggal'])) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
                  </form>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/**
 * Jalankan halaman menu "Pembina Tahfidz".
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (judul mengikuti nama menu hasil ubah)
 */
function tahfidz_halaman(array $tugas): void
{
    require_sekolah();

    $namaMenu = (string) ($tugas['nama'] ?? 'Pembina Tahfidz');
    $bolehUbah = boleh_ubah_tugas_menu();
    // Pengawas & peran lain yang tidak berhak mengubah: POST ditolak SEBELUM apa pun diproses.
    if (!$bolehUbah && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        tolak_ubah_tugas_menu('setoran hafalan siswa');
    }

    // ---------------------------------------------------------------- POST (simpan / hapus)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        if ($aksi === 'hapus') {
            $r = tahfidz_setoran_by_id($id);
            if (!$r) {
                flash('Setoran hafalan itu tidak ditemukan.', 'err');
                redirect(tahfidz_url($tugas));
            }
            tahfidz_setoran_hapus($id);
            flash('Setoran hafalan ' . $r['siswa_nama'] . ' (' . tanggal_id((string) $r['tanggal'])
                . ') dihapus.', 'warn');
            redirect(tahfidz_url($tugas));
        }

        if ($aksi === 'simpan') {
            $siswaId = (int) ($_POST['siswa_id'] ?? 0);
            $siswa = siswa_by_id($siswaId);   // WAJIB milik sekolah ini (anti-IDOR)
            $tanggal = tgl_ok($_POST['tanggal'] ?? '');
            $surahNo = tahfidz_surah_dari($_POST['surah_no'] ?? $_POST['surah'] ?? '');
            $dari = (int) ($_POST['ayat_dari'] ?? 0);
            $sampai = (int) ($_POST['ayat_sampai'] ?? 0);
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
            if (function_exists('mb_substr')) {
                $keterangan = mb_substr($keterangan, 0, TAHFIDZ_KET_MAKS);
            } else {
                $keterangan = substr($keterangan, 0, TAHFIDZ_KET_MAKS);
            }
            $kembali = tahfidz_url($tugas, ['edit' => $id]) . '#formSetoran';

            if (!$siswa) {
                flash('Pilih siswa dari Data Siswa sekolah/madrasah ini dulu.', 'err');
                redirect($kembali);
            }
            if ($tanggal === null) {
                flash('Tanggal setoran wajib diisi dengan benar.', 'err');
                redirect($kembali);
            }
            $surah = tahfidz_surah($surahNo);
            if ($surah === null) {
                flash('Nama surah wajib dipilih dari daftar 114 surah.', 'err');
                redirect($kembali);
            }
            // Batas ayat mengikuti jumlah ayat surah terpilih — diperiksa ULANG di server.
            $ayat = tahfidz_ayat_benar($surahNo, $dari, $sampai);
            if ($ayat === null) {
                flash('Rentang ayat tidak sah untuk surah ' . $surah['nama'] . '.', 'err');
                redirect($kembali);
            }
            if ($dari > (int) $surah['ayat'] || $sampai > (int) $surah['ayat']) {
                flash('Surah ' . $surah['nama'] . ' hanya memiliki ' . (int) $surah['ayat']
                    . ' ayat — rentang ayat disesuaikan otomatis.', 'warn');
            }
            [$dari, $sampai] = $ayat;

            $kelasId = (int) ($siswa['kelas_id'] ?? 0);
            $kelasNama = $kelasId > 0 ? kelas_nama($kelasId) : '';
            if ($kelasNama === '-') {
                $kelasNama = '';
            }
            $siswaNama = (string) $siswa['nama'];
            $pemilik = tahfidz_setoran_tanggal($siswaId, $tanggal);

            if ($id > 0) {
                // Ubah baris tertentu. Bila siswa/tanggalnya dipindah ke pasangan yang SUDAH ada,
                // kiriman ini digabungkan ke baris itu (aturan satu setoran per siswa per tanggal).
                $lama = tahfidz_setoran_by_id($id);
                if (!$lama) {
                    flash('Setoran hafalan itu tidak ditemukan.', 'err');
                    redirect(tahfidz_url($tugas));
                }
                $bentrok = $pemilik && (int) $pemilik['id'] !== $id ? $pemilik : null;
                if ($bentrok) {
                    q('UPDATE tahfidz_setoran SET surah_no=?, surah_nama=?, ayat_dari=?, ayat_sampai=?,
                          keterangan=?, diubah=? WHERE id=? AND sekolah_id=?',
                        [$surahNo, $surah['nama'], $dari, $sampai, $keterangan, $now,
                         (int) $bentrok['id'], sekolah_id()]);
                    q('DELETE FROM tahfidz_setoran WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
                    flash('Setoran hafalan ' . $siswaNama . ' tanggal ' . tanggal_id($tanggal)
                        . ' diperbarui (setoran pada tanggal itu sudah ada).');
                } else {
                    q('UPDATE tahfidz_setoran SET siswa_id=?, kelas_id=?, siswa_nama=?, kelas_nama=?,
                          tanggal=?, surah_no=?, surah_nama=?, ayat_dari=?, ayat_sampai=?, keterangan=?, diubah=?
                       WHERE id=? AND sekolah_id=?',
                        [$siswaId, $kelasId > 0 ? $kelasId : null, $siswaNama, $kelasNama, $tanggal,
                         $surahNo, $surah['nama'], $dari, $sampai, $keterangan, $now, $id, sekolah_id()]);
                    flash('Setoran hafalan ' . $siswaNama . ' diperbarui.');
                }
                redirect(tahfidz_url($tugas));
            }

            if ($pemilik) {
                // Siswa itu sudah punya setoran pada tanggal tersebut → PERBARUI (bukan baris kembar).
                q('UPDATE tahfidz_setoran SET kelas_id=?, siswa_nama=?, kelas_nama=?, surah_no=?,
                      surah_nama=?, ayat_dari=?, ayat_sampai=?, keterangan=?, diubah=?
                   WHERE id=? AND sekolah_id=?',
                    [$kelasId > 0 ? $kelasId : null, $siswaNama, $kelasNama, $surahNo, $surah['nama'],
                     $dari, $sampai, $keterangan, $now, (int) $pemilik['id'], sekolah_id()]);
                flash('Setoran hafalan ' . $siswaNama . ' tanggal ' . tanggal_id($tanggal)
                    . ' diperbarui.');
            } else {
                q('INSERT INTO tahfidz_setoran (sekolah_id, siswa_id, kelas_id, siswa_nama, kelas_nama,
                      tanggal, surah_no, surah_nama, ayat_dari, ayat_sampai, keterangan, dicatat_oleh, dibuat)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $siswaId, $kelasId > 0 ? $kelasId : null, $siswaNama, $kelasNama,
                     $tanggal, $surahNo, $surah['nama'], $dari, $sampai, $keterangan,
                     (int) (current_user()['id'] ?? 0), $now]);
                flash('Setoran hafalan ' . $siswaNama . ' disimpan.');
            }
            redirect(tahfidz_url($tugas));
        }

        redirect(tahfidz_url($tugas));
    }

    // ---------------------------------------------------------------- tampilan
    $f = [
        'kelas'  => (int) ($_GET['kelas'] ?? 0),
        'dari'   => (string) ($_GET['dari'] ?? ''),
        'sampai' => (string) ($_GET['sampai'] ?? ''),
        'cari'   => (string) ($_GET['cari'] ?? ''),
    ];
    $daftar = tahfidz_setoran_list($f);
    $ringkas = tahfidz_ringkas($daftar);
    $kelas = kelas_list(true);
    $siswaSemua = siswa_list(0, '', true);
    $edit = tahfidz_setoran_by_id((int) ($_GET['edit'] ?? 0));
    $labelFilter = tahfidz_label_filter($f);
    $qCetak = http_build_query(array_filter([
        'kode' => tahfidz_kode($tugas), 'mode' => 'kelas',
        'kelas' => $f['kelas'] > 0 ? (int) $f['kelas'] : '',
        'dari' => trim((string) tgl_ok($f['dari']) ?? ''),
        'sampai' => trim((string) tgl_ok($f['sampai']) ?? ''),
        'cari' => trim($f['cari']),
    ], static fn($v): bool => (string) $v !== ''));
    $namaSekolah = nama_sekolah_tampil();
    // Nilai awal formulir: baris yang sedang diubah, atau nilai bawaan (tanggal hari ini).
    $fv = [
        'siswa_id'   => (int) ($edit['siswa_id'] ?? 0),
        'tanggal'    => (string) ($edit['tanggal'] ?? date('Y-m-d')),
        'surah_no'   => (int) ($edit['surah_no'] ?? 1),
        'ayat_dari'  => (int) ($edit['ayat_dari'] ?? 1),
        'ayat_sampai' => (int) ($edit['ayat_sampai'] ?? (int) (tahfidz_surah((int) ($edit['surah_no'] ?? 1))['ayat'] ?? 1)),
        'keterangan' => (string) ($edit['keterangan'] ?? ''),
    ];
    $surahAwal = tahfidz_surah($fv['surah_no']) ?? tahfidz_surah(1);

    page_top($namaMenu, 'Setoran Hafalan Siswa');
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('book', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Pencatatan <b>setoran hafalan</b> siswa untuk <b><?= tetap_kata($namaSekolah) ?></b>:
        nama siswa &amp; kelas diambil dari Data Siswa, nama surah dipilih dari 114 surah Al-Qur'an,
        dan rentang ayat mengikuti jumlah ayat surah tersebut.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $ringkas['setoran'] ?> setoran</span>
      <?php // Dua mode cetak (permintaan pemilik): lembar per kelas & rincian semua setoran. ?>
      <a class="btn btn-primary btn-sm" href="cetak_tahfidz.php?<?= e($qCetak) ?>"
         target="_blank" rel="noopener"><?= ikon('print', 15) ?> Cetak Lembar Kelas</a>
      <a class="btn btn-ghost btn-sm"
         href="cetak_tahfidz.php?<?= e(str_replace('mode=kelas', 'mode=rincian', $qCetak)) ?>"
         target="_blank" rel="noopener"><?= ikon('print', 15) ?> Cetak Rincian</a>
    </div>
  </div>

  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('setoran hafalan siswa') ?></span>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- formulir setoran ?>
  <?php if ($bolehUbah): ?>
    <div class="card" id="formSetoran" style="margin-bottom:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon($edit ? 'edit' : 'plus', 16) ?> <?= $edit ? 'Ubah Setoran Hafalan' : 'Catat Setoran Hafalan' ?></h2>
          <p>Pilih kelas untuk menyaring daftar siswa, lalu isi surah serta rentang ayatnya. Satu
            siswa hanya punya <b>satu setoran per tanggal</b> — menyimpan ulang pada tanggal yang
            sama akan memperbarui setoran itu.</p>
        </div>
        <?php if ($edit): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(tahfidz_url($tugas) . '#tabelSetoran') ?>"><?= ikon('prev', 14) ?> Batal</a>
        <?php endif; ?>
      </div>
      <form method="post" class="form-grid" id="formTahfidz">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="grid g3" style="gap:16px">
          <div>
            <?php // Pemilih kelas hanya MENYARING daftar siswa di bawah (tanpa JavaScript daftar
                  // siswa tetap lengkap & berkelompok per kelas, jadi formulir tetap bisa dipakai). ?>
            <label class="f" for="kelasPilih">Kelas</label>
            <select id="kelasPilih" name="kelas_pilih">
              <option value="">Semua kelas</option>
              <?php
              $kelasTerpilih = 0;
              if ($fv['siswa_id'] > 0) {
                  foreach ($siswaSemua as $sw) {
                      if ((int) $sw['id'] === $fv['siswa_id']) {
                          $kelasTerpilih = (int) ($sw['kelas_id'] ?? 0);
                      }
                  }
              }
              foreach ($kelas as $k): ?>
                <option value="<?= (int) $k['id'] ?>" <?= $kelasTerpilih === (int) $k['id'] ? 'selected' : '' ?>>
                  <?= e((string) $k['nama']) ?> (<?= (int) $k['jml_siswa'] ?> siswa)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f" for="siswa_id">Nama Siswa *</label>
            <select id="siswa_id" name="siswa_id" required>
              <option value="">— pilih siswa —</option>
              <?php
              // Siswa dikelompokkan per kelas (optgroup) supaya tetap mudah dipakai tanpa skrip.
              $perKelas = [];
              foreach ($siswaSemua as $sw) {
                  $perKelas[(string) ($sw['kelas_nama'] ?: 'Tanpa kelas')][] = $sw;
              }
              ksort($perKelas);
              foreach ($perKelas as $namaKelas => $murid): ?>
                <optgroup label="<?= e((string) $namaKelas) ?>">
                  <?php foreach ($murid as $sw): ?>
                    <option value="<?= (int) $sw['id'] ?>"
                            data-kelas="<?= (int) ($sw['kelas_id'] ?? 0) ?>"
                            <?= $fv['siswa_id'] === (int) $sw['id'] ? 'selected' : '' ?>>
                      <?= e((string) $sw['nama']) ?><?= (string) ($sw['nis'] ?? '') !== '' ? ' (' . e((string) $sw['nis']) . ')' : '' ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f" for="tanggal">Tanggal Setoran *</label>
            <input type="date" id="tanggal" name="tanggal" required value="<?= e($fv['tanggal']) ?>">
          </div>
        </div>
        <div class="grid g4" style="gap:16px">
          <div>
            <label class="f" for="surah_no">Nama Surah *</label>
            <select id="surah_no" name="surah_no" required>
              <?php foreach (tahfidz_surah_list() as $s): ?>
                <option value="<?= (int) $s['no'] ?>" data-ayat="<?= (int) $s['ayat'] ?>"
                        <?= $fv['surah_no'] === (int) $s['no'] ? 'selected' : '' ?>>
                  <?= (int) $s['no'] ?>. <?= e($s['nama']) ?> (<?= (int) $s['ayat'] ?> ayat)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f" for="ayat_dari">Dari Ayat ke- *</label>
            <input type="number" id="ayat_dari" name="ayat_dari" min="1" max="<?= (int) $surahAwal['ayat'] ?>"
                   required value="<?= (int) $fv['ayat_dari'] ?>">
          </div>
          <div>
            <label class="f" for="ayat_sampai">Sampai Ayat ke- *</label>
            <input type="number" id="ayat_sampai" name="ayat_sampai" min="1" max="<?= (int) $surahAwal['ayat'] ?>"
                   required value="<?= (int) $fv['ayat_sampai'] ?>">
          </div>
          <div>
            <label class="f" for="keterangan">Keterangan</label>
            <input type="text" id="keterangan" name="keterangan" maxlength="<?= (int) TAHFIDZ_KET_MAKS ?>"
                   value="<?= e($fv['keterangan']) ?>" placeholder="mis. lancar, perlu murojaah">
          </div>
        </div>
        <p class="hint" id="hintAyat">Batas ayat menyesuaikan surah yang dipilih: <b><?= e($surahAwal['nama']) ?></b>
          memiliki <b><?= (int) $surahAwal['ayat'] ?></b> ayat.</p>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Simpan Setoran' ?></button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- ringkasan & saringan ?>
  <div class="flex mb16" style="flex-wrap:wrap;gap:10px">
    <span class="chip"><?= (int) $ringkas['setoran'] ?> setoran</span>
    <span class="chip"><?= (int) $ringkas['siswa'] ?> siswa menyetor</span>
    <span class="chip"><?= (int) $ringkas['kelas'] ?> kelas</span>
    <?php foreach (array_slice($ringkas['surah'], 0, 3, true) as $nm => $jml): ?>
      <span class="chip"><?= e((string) $nm) ?>: <b><?= (int) $jml ?></b></span>
    <?php endforeach; ?>
  </div>

  <div class="flex mb16" style="flex-wrap:wrap;gap:10px;align-items:flex-end">
    <form method="get" id="formSaring" class="flex" style="gap:8px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="kode" value="<?= e(tahfidz_kode($tugas)) ?>">
      <div>
        <label class="f" for="saringKelas">Kelas</label>
        <select id="saringKelas" name="kelas">
          <option value="">Semua kelas</option>
          <?php foreach ($kelas as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= $f['kelas'] === (int) $k['id'] ? 'selected' : '' ?>>
              <?= e((string) $k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f" for="saringDari">Dari tanggal</label>
        <input type="date" id="saringDari" name="dari" value="<?= e((string) tgl_ok($f['dari']) ?? '') ?>">
      </div>
      <div>
        <label class="f" for="saringSampai">Sampai tanggal</label>
        <input type="date" id="saringSampai" name="sampai" value="<?= e((string) tgl_ok($f['sampai']) ?? '') ?>">
      </div>
      <div>
        <label class="f" for="saringCari">Cari</label>
        <input type="text" id="saringCari" name="cari" value="<?= e($f['cari']) ?>"
               placeholder="nama siswa / surah / keterangan">
      </div>
      <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Tampilkan</button>
      <?php if ($labelFilter): ?>
        <a class="btn btn-ghost" href="<?= e(tahfidz_url($tugas)) ?>"><?= ikon('prev', 15) ?> Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$daftar): ?>
    <div class="empty"><?= ikon('book', 34) ?>
      <h3>Belum ada setoran hafalan</h3>
      <p><?= $bolehUbah
        ? 'Catat setoran lewat formulir di atas, atau ubah saringan bila setorannya tidak tampil.'
        : pesan_kosong_hanya_lihat('setoran hafalan siswa') ?></p>
    </div>
  <?php else: ?>
    <div class="section-title mb8"><?= ikon('book', 15) ?> Daftar Setoran Hafalan (<?= count($daftar) ?> baris)</div>
    <?php tahfidz_tabel($daftar, $bolehUbah, $tugas); ?>
  <?php endif; ?>

  <p class="hint mt16">Lembar cetak <b>per kelas</b> memuat seluruh siswa kelas tersebut (sel kosong
    bila belum ada setoran) dan ditandatangani <b>Pembina Tahfidz</b> serta <b>mengetahui Kepala
    <?= e(istilah_sekolah('Sekolah')) ?>/Madrasah</b>. Lembar <b>rincian</b> memuat semua setoran pada
    rentang tanggal yang dipilih.</p>
</div>
    <?php
    page_bottom();
}
