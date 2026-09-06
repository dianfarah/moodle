# Dokumentasi Menu Report Guru dan Siswa

## Fitur

Plugin ini menambahkan dua menu report untuk hasil nilai quiz di course Moodle:

### 1. **Report Guru (Quiz Report Teacher)**
- **Akses**: Hanya untuk guru/admin (yang punya capability `moodle/course:manageactivities`)
- **Lokasi**: Di course tabs header, tampil sebagai tab di sebelah "Course": **Course | Laporan Guru | Settings | Participants | ...**
- **Fitur**:
  - Menampilkan daftar semua siswa di course beserta nilai quiz mereka
  - Kolom yang ditampilkan:
    - Nama Siswa
    - Email
    - Nilai (dalam persentase)
    - Status (Selesai/Sedang Dikerjakan)
  - **Klik pada siswa** untuk melihat detail:
    - Informasi attempt quiz (nama quiz, nilai, status, tanggal & waktu, durasi)
    - Setiap pertanyaan dengan:
      - Jawaban siswa
      - Jawaban benar (jika salah)
      - Skor per pertanyaan
    - Visual indikator untuk jawaban benar/salah

### 2. **Report Siswa (My Quiz Scores)**
- **Akses**: Untuk semua siswa di course
- **Lokasi**: Di course navigation
- **Fitur**:
  - Ringkasan nilai quiz:
    - Total quiz yang ada di course
    - Jumlah quiz yang sudah selesai
    - Rata-rata nilai
    - Nilai tertinggi
  - Daftar quiz dengan informasi:
    - Nama quiz
    - Tanggal & waktu mengerjakan
    - Durasi pengerjaan
    - Status
    - Nilai (dalam persentase)

## Instalasi

1. Copy folder `blocks/adaptive_learning_ai` ke folder `blocks` di Moodle
2. Akses halaman admin Moodle untuk trigger database upgrade
3. Notifikasi akan muncul bahwa plugin sudah terinstall

## File-File Penting

```
blocks/adaptive_learning_ai/
├── reports/
│   ├── teacher_report.php          # Halaman report guru
│   └── student_report.php          # Halaman report siswa
├── classes/
│   └── report_helper.php           # Class helper untuk query dan functions
├── lib.php                         # File untuk hooks dan navigation
├── lang/
│   ├── en/
│   │   └── block_adaptive_learning_ai.php  # English strings
│   └── id/
│       └── block_adaptive_learning_ai.php  # Indonesian strings
└── version.php
```

## Database Tables yang Digunakan

Plugin ini menggunakan table Moodle core yang sudah ada:

- `{quiz_attempts}` - Data attempt quiz
- `{question_attempts}` - Data jawaban per pertanyaan
- `{question_attempt_steps}` - Data step jawaban
- `{user}` - Data user
- `{quiz}` - Data quiz

## Customization

### Mengubah Warna dan Style

Edit file `reports/teacher_report.php` atau `reports/student_report.php` di bagian CSS style. Variabel warna utama:

```css
.score-value.success { /* Nilai >= 85% */
    background-color: #d1fae5;
    color: #065f46;
}

.score-value.warning { /* Nilai 70-84% */
    background-color: #fed7aa;
    color: #92400e;
}

.score-value.danger { /* Nilai < 70% */
    background-color: #fee2e2;
    color: #991b1b;
}
```

### Menambah Kolom Baru di Daftar Siswa

Edit query di `classes/report_helper.php` function `get_students_quiz_list()` untuk menambahkan field baru.

## Troubleshooting

### Menu tidak muncul di course navigation

1. Cek apakah plugin sudah diinstall dengan benar
2. Cek capability user - guru harus punya `moodle/course:manageactivities`
3. Cek file `lib.php` dan pastikan hook function name benar

### Data tidak muncul

1. Cek apakah ada data quiz di course
2. Cek apakah user sudah mengerjakan quiz
3. Buka Developer Console (F12) untuk lihat error message

## Support

Untuk pertanyaan atau report bug, hubungi developer.
