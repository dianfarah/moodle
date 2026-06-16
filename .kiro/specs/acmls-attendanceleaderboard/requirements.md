# Dokumen Persyaratan (Requirements Document)

## Adaptive Cognitive-Motivational Learning System (ACMLS)
### Plugin Moodle: `attendanceleaderboard`

---

## Pendahuluan

Sistem ACMLS (*Adaptive Cognitive-Motivational Learning System*) merupakan pengembangan lanjutan dari plugin Moodle `attendanceleaderboard` menjadi ekosistem pembelajaran adaptif yang komprehensif. Sistem ini mengintegrasikan analitik kognitif, analitik motivasional, *rule-based recommendation engine*, dan intervensi motivasional berbasis LLM (*Large Language Model*) untuk menciptakan pengalaman belajar yang dipersonalisasi secara dinamis.

Sistem beroperasi di atas platform Moodle LMS (berbasis PHP) dan dirancang untuk mendukung penelitian di bidang *educational technology*, khususnya dalam konteks tesis magister/doktoral. ACMLS mengadopsi pendekatan *hybrid adaptive learning* yang menggabungkan pemodelan pelajar multidimensional dengan mekanisme adaptasi berbasis aturan dan kecerdasan buatan.

---

## Glosarium

- **ACMLS**: *Adaptive Cognitive-Motivational Learning System* — sistem pembelajaran adaptif yang menjadi subjek pengembangan.
- **Learner**: Mahasiswa/peserta didik yang terdaftar dan aktif berinteraksi dengan LMS Moodle.
- **Learner_Profile**: Model multidimensional yang merepresentasikan karakteristik kognitif, motivasional, dan perilaku seorang Learner.
- **Coach**: Komponen *rule-based recommendation engine* yang bertindak sebagai pengontrol adaptasi pusat.
- **Delivery_System**: Komponen yang bertanggung jawab atas pengiriman konten pembelajaran dan intervensi motivasional kepada Learner.
- **Evaluation_System**: Komponen yang menilai performa akademik Learner dan menghasilkan skor/hasil evaluasi.
- **Tracking_System**: Komponen yang mengumpulkan dan memonitor log aktivitas Learner di LMS.
- **Profiling_System**: Komponen yang membangun dan memperbarui Learner_Profile secara dinamis.
- **LLM_Preparation**: Komponen yang menghasilkan kalimat motivasional yang dipersonalisasi menggunakan LLM.
- **Motivation_Component**: Komponen yang menyimpan logika motivasional dan mendukung personalisasi *encouragement*.
- **Motivation_Sentence_Repository**: Repositori yang menyimpan konten *encouragement* yang telah dihasilkan.
- **Learning_Resource**: Repositori materi pembelajaran yang dikelola dan diquery oleh Coach.
- **Learner_Record**: Repositori analitik pembelajaran (*learning analytics repository*) yang menyimpan data historis Learner.
- **Cognitive_Level**: Tingkat kemampuan kognitif Learner berdasarkan taksonomi yang ditetapkan.
- **Motivation_Level**: Tingkat motivasi belajar Learner yang diukur berdasarkan indikator perilaku dan respons.
- **Learning_Style**: Preferensi gaya belajar Learner (misalnya: visual, auditori, kinestetik, atau berdasarkan model VARK/Felder-Silverman).
- **Adaptive_Intervention**: Tindakan sistem yang disesuaikan dengan profil Learner, mencakup rekomendasi sumber belajar dan intervensi motivasional.
- **LMS**: *Learning Management System* — platform Moodle yang digunakan sebagai basis sistem.
- **Activity_Log**: Rekaman aktivitas digital Learner di LMS, mencakup login, akses sumber belajar, dan interaksi lainnya.
- **Performance_Category**: Klasifikasi performa akademik Learner: *Low*, *Middle*, atau *High*.
- **Encouragement_Content**: Konten motivasional yang dihasilkan LLM dan disimpan di Motivation_Sentence_Repository.
- **Leaderboard**: Papan peringkat yang menampilkan posisi relatif Learner berdasarkan metrik tertentu.

---

## Persyaratan

---

### Persyaratan 1: Autentikasi dan Akses Learner

**User Story:** Sebagai Learner, saya ingin dapat login ke sistem ACMLS melalui Moodle, sehingga saya dapat mengakses sumber belajar dan menerima intervensi adaptif yang dipersonalisasi.

#### Kriteria Penerimaan

1. WHEN seorang Learner melakukan login ke Moodle, THE ACMLS SHALL memverifikasi identitas Learner menggunakan mekanisme autentikasi Moodle yang sudah ada.
2. WHEN sesi Learner berhasil diautentikasi, THE Tracking_System SHALL mencatat waktu login, identitas Learner, dan konteks sesi ke dalam Activity_Log.
3. IF autentikasi Learner gagal, THEN THE ACMLS SHALL menampilkan pesan kesalahan yang deskriptif dan mengarahkan Learner ke halaman login Moodle.
4. WHILE sesi Learner aktif, THE Tracking_System SHALL memantau dan mencatat seluruh interaksi Learner dengan LMS secara berkelanjutan.
5. WHEN sesi Learner berakhir (logout atau timeout), THE Tracking_System SHALL mencatat waktu akhir sesi dan mengirimkan ringkasan Activity_Log ke Profiling_System.

---

### Persyaratan 2: Pelacakan Aktivitas Learner (Tracking)

**User Story:** Sebagai administrator sistem, saya ingin sistem secara otomatis melacak seluruh aktivitas Learner di LMS, sehingga data perilaku yang akurat tersedia untuk analitik dan adaptasi.

#### Kriteria Penerimaan

1. THE Tracking_System SHALL merekam setiap akses Learner terhadap Learning_Resource, mencakup: identitas sumber belajar, waktu akses, dan durasi keterlibatan.
2. WHEN seorang Learner menyelesaikan aktivitas pembelajaran (kuis, tugas, forum, atau modul), THE Tracking_System SHALL mencatat jenis aktivitas, waktu penyelesaian, dan hasil aktivitas ke dalam Activity_Log.
3. THE Tracking_System SHALL mengumpulkan data keterlibatan (*engagement*) Learner, mencakup: frekuensi login, jumlah sumber belajar yang diakses, dan durasi total sesi belajar per periode.
4. WHEN Activity_Log baru tersedia, THE Tracking_System SHALL mengirimkan data tersebut ke Profiling_System dalam interval tidak lebih dari 5 menit.
5. IF koneksi ke Profiling_System tidak tersedia, THEN THE Tracking_System SHALL menyimpan Activity_Log secara lokal dan mengirimkannya kembali ketika koneksi pulih.
6. THE Tracking_System SHALL mengumpulkan data interaksi sosial Learner di LMS, mencakup: partisipasi forum, kolaborasi, dan aktivitas komunikasi.

---

### Persyaratan 3: Pembuatan dan Pembaruan Profil Learner (Profiling)

**User Story:** Sebagai sistem adaptif, saya ingin membangun model multidimensional setiap Learner, sehingga Coach dapat membuat rekomendasi yang tepat dan dipersonalisasi.

#### Kriteria Penerimaan

1. WHEN data Activity_Log diterima dari Tracking_System, THE Profiling_System SHALL memperbarui Learner_Profile yang mencakup dimensi: Cognitive_Level, Academic_Performance, Motivation_Level, Learning_Style, dan Behavioral_History.
2. WHEN seorang Learner pertama kali terdaftar dalam sistem, THE Profiling_System SHALL membuat Learner_Profile awal dengan Performance_Category yang diklasifikasikan sebagai *Low*, *Middle*, atau *High* berdasarkan data akademik awal yang tersedia.
3. WHEN hasil evaluasi baru diterima dari Evaluation_System, THE Profiling_System SHALL memperbarui Performance_Category Learner berdasarkan skor terbaru dengan mempertimbangkan riwayat performa sebelumnya.
4. WHILE Learner aktif dalam sistem, THE Profiling_System SHALL memperbarui Motivation_Level berdasarkan pola keterlibatan, frekuensi akses, dan respons terhadap Adaptive_Intervention sebelumnya.
5. THE Profiling_System SHALL menyimpan seluruh versi historis Learner_Profile ke dalam Learner_Record untuk mendukung analisis longitudinal.
6. WHEN Learner_Profile diperbarui, THE Profiling_System SHALL mengirimkan notifikasi pembaruan ke Coach untuk memicu evaluasi ulang rekomendasi adaptif.
7. THE Profiling_System SHALL mengklasifikasikan Learning_Style Learner berdasarkan pola interaksi dengan berbagai tipe Learning_Resource menggunakan model yang telah ditetapkan.

---

### Persyaratan 4: Mesin Rekomendasi Adaptif (Coach)

**User Story:** Sebagai Learner, saya ingin menerima rekomendasi sumber belajar dan intervensi motivasional yang disesuaikan dengan profil saya, sehingga pengalaman belajar saya menjadi lebih efektif dan relevan.

#### Kriteria Penerimaan

1. WHEN Coach menerima notifikasi pembaruan Learner_Profile, THE Coach SHALL mengevaluasi profil tersebut menggunakan rule-based engine dan menentukan Adaptive_Intervention yang sesuai dalam waktu tidak lebih dari 10 detik.
2. THE Coach SHALL merekomendasikan Learning_Resource berdasarkan kombinasi: Cognitive_Level, Performance_Category, Learning_Style, dan Behavioral_History Learner.
3. WHEN Performance_Category Learner adalah *Low*, THE Coach SHALL memprioritaskan rekomendasi Learning_Resource dengan tingkat kesulitan dasar dan meningkatkan frekuensi intervensi motivasional.
4. WHEN Performance_Category Learner adalah *High*, THE Coach SHALL merekomendasikan Learning_Resource dengan tingkat kesulitan lanjutan dan tantangan pengayaan (*enrichment*).
5. THE Coach SHALL menentukan jenis dan waktu Adaptive_Intervention motivasional berdasarkan Motivation_Level dan pola keterlibatan Learner terkini.
6. WHEN Coach menghasilkan rekomendasi, THE Coach SHALL menyimpan keputusan rekomendasi beserta alasannya ke dalam Learner_Record untuk keperluan audit dan penelitian.
7. THE Coach SHALL memperbarui aturan rekomendasi (*recommendation rules*) berdasarkan efektivitas intervensi sebelumnya yang tersimpan di Learner_Record.
8. WHERE fitur *leaderboard* diaktifkan, THE Coach SHALL mempertimbangkan posisi relatif Learner dalam Leaderboard sebagai salah satu faktor dalam penentuan intervensi motivasional.

---

### Persyaratan 5: Pengiriman Konten dan Intervensi (Delivery)

**User Story:** Sebagai Learner, saya ingin menerima konten pembelajaran dan pesan motivasional yang relevan secara langsung di antarmuka Moodle, sehingga saya dapat belajar tanpa harus berpindah platform.

#### Kriteria Penerimaan

1. WHEN Coach menghasilkan rekomendasi Learning_Resource, THE Delivery_System SHALL menampilkan rekomendasi tersebut kepada Learner di antarmuka Moodle dalam format yang mudah dipahami.
2. WHEN Encouragement_Content tersedia dari Motivation_Sentence_Repository, THE Delivery_System SHALL menampilkan konten tersebut kepada Learner pada waktu yang ditentukan oleh Coach.
3. THE Delivery_System SHALL mengintegrasikan tampilan Leaderboard ke dalam antarmuka Moodle, menampilkan posisi Learner relatif terhadap rekan-rekannya.
4. WHILE Learner mengakses Learning_Resource yang direkomendasikan, THE Delivery_System SHALL mencatat interaksi tersebut dan mengirimkan data ke Tracking_System.
5. THE Delivery_System SHALL mendukung penyampaian Adaptive_Intervention dalam berbagai format, mencakup: notifikasi dalam aplikasi, pesan di dashboard, dan blok informasi pada halaman kursus.
6. IF Learner menolak atau mengabaikan rekomendasi yang diberikan, THEN THE Delivery_System SHALL mencatat respons tersebut dan mengirimkan data ke Tracking_System untuk digunakan dalam pembaruan profil.

---

### Persyaratan 6: Evaluasi Performa Akademik (Evaluation)

**User Story:** Sebagai instruktur, saya ingin sistem secara otomatis menilai performa akademik Learner dan memperbarui profil mereka, sehingga rekomendasi adaptif selalu didasarkan pada data terkini.

#### Kriteria Penerimaan

1. WHEN seorang Learner menyelesaikan aktivitas yang dapat dinilai (kuis, tugas, atau ujian) di Moodle, THE Evaluation_System SHALL mengambil skor hasil penilaian dari Moodle Gradebook.
2. WHEN skor baru tersedia, THE Evaluation_System SHALL menghitung metrik performa agregat Learner dan mengirimkan hasilnya ke Profiling_System dalam waktu tidak lebih dari 60 detik.
3. THE Evaluation_System SHALL menghasilkan laporan performa yang mencakup: skor per aktivitas, tren performa dari waktu ke waktu, dan perbandingan dengan rata-rata kelas.
4. WHEN performa Learner mengalami penurunan signifikan (lebih dari 20% dari rata-rata sebelumnya), THE Evaluation_System SHALL mengirimkan sinyal peringatan ke Coach untuk memicu intervensi segera.
5. THE Evaluation_System SHALL menyimpan seluruh data skor dan metrik performa ke dalam Learner_Record untuk mendukung analisis longitudinal.
6. IF data skor dari Moodle Gradebook tidak tersedia atau tidak valid, THEN THE Evaluation_System SHALL mencatat kesalahan tersebut dan mempertahankan nilai performa terakhir yang valid.

---

### Persyaratan 7: Persiapan dan Generasi Kalimat Motivasional (LLM Preparation)

**User Story:** Sebagai Learner, saya ingin menerima pesan motivasional yang dipersonalisasi dan relevan dengan kondisi belajar saya saat ini, sehingga saya merasa didukung dan termotivasi untuk terus belajar.

#### Kriteria Penerimaan

1. WHEN Coach menentukan bahwa Learner memerlukan intervensi motivasional, THE LLM_Preparation SHALL menghasilkan Encouragement_Content yang dipersonalisasi berdasarkan: Performance_Category, Motivation_Level, pola keterlibatan, dan riwayat pembelajaran Learner.
2. THE LLM_Preparation SHALL menghasilkan Encouragement_Content dalam empat kategori: *Reinforcement* (penguatan positif), *Achievement Prompts* (dorongan pencapaian), *Recovery Encouragement* (dorongan pemulihan), dan *Persistence Motivation* (motivasi ketekunan).
3. WHEN Encouragement_Content dihasilkan, THE LLM_Preparation SHALL menyimpan konten tersebut ke dalam Motivation_Sentence_Repository beserta metadata: kategori, konteks Learner, dan waktu generasi.
4. THE LLM_Preparation SHALL menghasilkan Encouragement_Content dalam bahasa Indonesia dengan gaya formal-akademik yang sesuai untuk konteks pendidikan tinggi.
5. IF layanan LLM eksternal tidak tersedia, THEN THE LLM_Preparation SHALL mengambil Encouragement_Content dari template yang telah tersimpan di Motivation_Sentence_Repository berdasarkan kategori dan Performance_Category yang sesuai.
6. THE LLM_Preparation SHALL memastikan bahwa Encouragement_Content yang dihasilkan tidak mengandung konten yang tidak pantas, menyesatkan, atau bertentangan dengan nilai-nilai akademik.
7. WHEN Encouragement_Content yang sama telah dikirimkan kepada Learner dalam 7 hari terakhir, THE LLM_Preparation SHALL menghasilkan variasi konten baru untuk menghindari pengulangan yang berlebihan.

---

### Persyaratan 8: Repositori Kalimat Motivasional (Motivation Sentence Repository)

**User Story:** Sebagai sistem motivasional, saya ingin memiliki repositori konten *encouragement* yang terorganisir, sehingga Encouragement_Content yang tepat dapat diambil dengan cepat dan efisien.

#### Kriteria Penerimaan

1. THE Motivation_Sentence_Repository SHALL menyimpan Encouragement_Content yang diindeks berdasarkan: kategori konten, Performance_Category target, Motivation_Level target, dan konteks pembelajaran.
2. WHEN Coach meminta Encouragement_Content untuk Learner tertentu, THE Motivation_Sentence_Repository SHALL mengembalikan konten yang paling relevan dalam waktu tidak lebih dari 2 detik.
3. THE Motivation_Sentence_Repository SHALL mempertahankan riwayat Encouragement_Content yang telah dikirimkan kepada setiap Learner untuk mencegah pengulangan berlebihan.
4. THE Motivation_Sentence_Repository SHALL mendukung operasi CRUD (*Create, Read, Update, Delete*) untuk pengelolaan konten oleh administrator sistem.
5. WHEN kapasitas penyimpanan Motivation_Sentence_Repository mencapai 80% dari batas yang ditetapkan, THE Motivation_Sentence_Repository SHALL mengirimkan notifikasi peringatan kepada administrator sistem.

---

### Persyaratan 9: Repositori Sumber Belajar (Learning Resource)

**User Story:** Sebagai instruktur, saya ingin mengelola katalog sumber belajar yang dapat diquery oleh Coach, sehingga rekomendasi yang diberikan kepada Learner selalu relevan dan terbarui.

#### Kriteria Penerimaan

1. THE Learning_Resource SHALL menyimpan metadata setiap sumber belajar, mencakup: judul, jenis konten, tingkat kesulitan, topik, dan tag pembelajaran.
2. WHEN Coach mengirimkan query rekomendasi dengan parameter Learner_Profile, THE Learning_Resource SHALL mengembalikan daftar sumber belajar yang sesuai dalam waktu tidak lebih dari 3 detik.
3. THE Learning_Resource SHALL mendukung penambahan, pembaruan, dan penghapusan sumber belajar oleh instruktur melalui antarmuka administrasi Moodle.
4. WHEN sumber belajar baru ditambahkan ke Moodle, THE Learning_Resource SHALL secara otomatis mengindeks sumber belajar tersebut dengan metadata yang relevan.
5. THE Learning_Resource SHALL menyimpan data penggunaan setiap sumber belajar (frekuensi akses, rating, dan efektivitas) untuk mendukung peningkatan kualitas rekomendasi Coach.

---

### Persyaratan 10: Repositori Analitik Pembelajaran (Learner Record)

**User Story:** Sebagai peneliti, saya ingin memiliki repositori data pembelajaran yang komprehensif dan terstruktur, sehingga analisis longitudinal dan penelitian berbasis data dapat dilakukan dengan efektif.

#### Kriteria Penerimaan

1. THE Learner_Record SHALL menyimpan data historis setiap Learner, mencakup: riwayat performa akademik, data perilaku, riwayat motivasi, dan riwayat interaksi dengan Learning_Resource.
2. THE Learner_Record SHALL mendukung query analitik untuk menghasilkan laporan longitudinal performa dan keterlibatan Learner dalam rentang waktu yang dapat dikonfigurasi.
3. WHEN data baru diterima dari Profiling_System, Evaluation_System, atau Tracking_System, THE Learner_Record SHALL menyimpan data tersebut dengan timestamp yang akurat dan metadata sumber data.
4. THE Learner_Record SHALL menyediakan antarmuka ekspor data dalam format CSV dan JSON untuk keperluan analisis penelitian eksternal.
5. THE Learner_Record SHALL menerapkan kontrol akses berbasis peran (*role-based access control*) sesuai dengan kebijakan privasi data Moodle, memastikan hanya pengguna yang berwenang yang dapat mengakses data Learner.
6. IF integritas data Learner_Record terganggu, THEN THE Learner_Record SHALL mengirimkan notifikasi kepada administrator dan memulai prosedur pemulihan data otomatis.

---

### Persyaratan 11: Komponen Motivasi (Motivation Component)

**User Story:** Sebagai sistem adaptif, saya ingin memiliki logika motivasional yang terstruktur, sehingga intervensi motivasional yang diberikan kepada Learner konsisten, terukur, dan efektif.

#### Kriteria Penerimaan

1. THE Motivation_Component SHALL mendefinisikan dan menyimpan aturan motivasional (*motivational rules*) yang menentukan kapan dan jenis intervensi motivasional apa yang harus diberikan berdasarkan kondisi Learner_Profile.
2. WHEN Motivation_Level Learner berada di bawah ambang batas yang ditetapkan selama lebih dari 3 hari berturut-turut, THE Motivation_Component SHALL memicu permintaan intervensi motivasional kepada Coach.
3. THE Motivation_Component SHALL mendukung konfigurasi ambang batas motivasional (*motivational thresholds*) oleh administrator sistem melalui antarmuka administrasi Moodle.
4. WHEN intervensi motivasional dikirimkan kepada Learner, THE Motivation_Component SHALL mencatat respons Learner dan menggunakan data tersebut untuk menyesuaikan aturan motivasional di masa mendatang.
5. THE Motivation_Component SHALL mengintegrasikan data Leaderboard sebagai salah satu faktor dalam perhitungan Motivation_Level Learner.

---

### Persyaratan 12: Leaderboard Kehadiran dan Keterlibatan

**User Story:** Sebagai Learner, saya ingin melihat posisi saya dalam leaderboard berdasarkan kehadiran dan keterlibatan belajar, sehingga saya termotivasi untuk meningkatkan partisipasi saya.

#### Kriteria Penerimaan

1. THE ACMLS SHALL menampilkan Leaderboard yang memuat peringkat Learner berdasarkan metrik gabungan: kehadiran, frekuensi akses sumber belajar, dan penyelesaian aktivitas.
2. WHEN data aktivitas Learner diperbarui, THE ACMLS SHALL memperbarui posisi Leaderboard dalam interval tidak lebih dari 15 menit.
3. THE ACMLS SHALL menampilkan informasi Leaderboard yang mencakup: peringkat Learner saat ini, skor total, perubahan peringkat dari periode sebelumnya, dan jarak poin ke peringkat berikutnya.
4. WHERE fitur privasi Leaderboard diaktifkan oleh administrator, THE ACMLS SHALL menampilkan nama Learner lain dalam format anonim (misalnya: inisial atau pseudonim).
5. THE ACMLS SHALL menyediakan tampilan Leaderboard dalam cakupan: kelas/kursus, program studi, dan institusi (sesuai konfigurasi administrator).
6. WHEN seorang Learner mencapai peringkat baru yang lebih tinggi, THE ACMLS SHALL memicu pengiriman Encouragement_Content kategori *Achievement Prompts* kepada Learner tersebut.

---

### Persyaratan 13: Pembaruan Profil Dinamis (Dynamic Learner Updating)

**User Story:** Sebagai sistem adaptif, saya ingin profil Learner diperbarui secara dinamis berdasarkan respons terhadap intervensi, sehingga adaptasi sistem semakin akurat seiring waktu.

#### Kriteria Penerimaan

1. WHEN Learner merespons Adaptive_Intervention (mengakses sumber belajar yang direkomendasikan atau menunjukkan peningkatan keterlibatan), THE Profiling_System SHALL memperbarui Learner_Profile untuk mencerminkan perubahan perilaku tersebut.
2. THE Profiling_System SHALL menerapkan algoritma pembaruan profil yang mempertimbangkan bobot data terbaru lebih tinggi dibandingkan data historis dalam perhitungan Motivation_Level dan Cognitive_Level.
3. WHEN Performance_Category Learner berubah (misalnya dari *Low* ke *Middle*), THE Profiling_System SHALL mengirimkan notifikasi perubahan ke Coach dan mencatat peristiwa tersebut di Learner_Record.
4. THE Profiling_System SHALL memperbarui Learner_Profile setidaknya sekali setiap 24 jam untuk setiap Learner yang aktif, meskipun tidak ada aktivitas baru yang tercatat.
5. WHILE proses pembaruan profil berlangsung, THE Profiling_System SHALL memastikan ketersediaan Learner_Profile versi terakhir yang valid untuk digunakan oleh Coach tanpa gangguan layanan.

---

### Persyaratan 14: Antarmuka Administrasi dan Konfigurasi

**User Story:** Sebagai administrator Moodle, saya ingin dapat mengkonfigurasi seluruh komponen ACMLS melalui antarmuka administrasi Moodle yang familiar, sehingga pengelolaan sistem dapat dilakukan tanpa keahlian teknis khusus.

#### Kriteria Penerimaan

1. THE ACMLS SHALL menyediakan halaman konfigurasi plugin di panel administrasi Moodle yang memungkinkan administrator mengatur: parameter Coach, ambang batas motivasional, konfigurasi LLM, dan pengaturan Leaderboard.
2. THE ACMLS SHALL menyediakan dasbor analitik untuk administrator yang menampilkan: ringkasan distribusi Performance_Category, rata-rata Motivation_Level, dan efektivitas intervensi adaptif.
3. WHEN administrator mengubah konfigurasi sistem, THE ACMLS SHALL menerapkan perubahan tersebut tanpa memerlukan restart sistem dan mencatat perubahan konfigurasi di log audit.
4. THE ACMLS SHALL menyediakan antarmuka untuk instruktur guna mengelola Learning_Resource, melihat profil Learner (sesuai izin), dan memantau progres kelas secara keseluruhan.
5. IF konfigurasi yang dimasukkan administrator tidak valid, THEN THE ACMLS SHALL menampilkan pesan kesalahan yang deskriptif dan mempertahankan konfigurasi sebelumnya yang valid.

---

### Persyaratan 15: Privasi Data dan Kepatuhan

**User Story:** Sebagai institusi pendidikan, saya ingin sistem ACMLS mematuhi regulasi privasi data yang berlaku, sehingga data Learner terlindungi dan kepercayaan pengguna terjaga.

#### Kriteria Penerimaan

1. THE ACMLS SHALL mematuhi kebijakan privasi data Moodle dan regulasi perlindungan data yang berlaku, termasuk penerapan enkripsi untuk data sensitif Learner yang tersimpan di Learner_Record.
2. THE ACMLS SHALL mengimplementasikan API privasi Moodle (*Moodle Privacy API*) untuk mendukung permintaan akses data, penghapusan data, dan ekspor data Learner sesuai hak pengguna.
3. WHEN data Learner dikirimkan ke layanan LLM eksternal untuk generasi Encouragement_Content, THE LLM_Preparation SHALL menganonimkan data identitas pribadi Learner sebelum pengiriman.
4. THE ACMLS SHALL menyimpan log akses data Learner_Record yang mencatat: identitas pengakses, waktu akses, dan data yang diakses, untuk keperluan audit keamanan.
5. WHERE fitur pengiriman data ke layanan eksternal diaktifkan, THE ACMLS SHALL meminta persetujuan eksplisit dari Learner sebelum data mereka dikirimkan ke layanan tersebut.

