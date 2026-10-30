<?php
/**
 * DAFTAR HADIR MEETING — akun LEMBAGA (`meeting_hadir.php`), e-GURU v1.73.0 / skema v55.
 *
 * Halaman ini muncul pada akun **kepala sekolah/madrasah, admin operator, dan guru** untuk
 * menandatangani daftar hadir meeting yang diaktifkan pengawas bina mereka (tanda tangan langsung
 * pada layar sentuh, mekanisme yang sama dengan Notulen Rapat).
 *
 * Seluruh logika ada pada MODUL `inc/meeting.php` (`meeting_halaman_sekolah()`) — jangan menyalinnya.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/meeting.php';

meeting_halaman_sekolah();
