# Implementation Tasks

## Adaptive Cognitive-Motivational Learning System (ACMLS)
### Plugin Moodle: `block_attendanceleaderboard`

---

## Task Overview

Tasks diurutkan berdasarkan dependensi teknis: fondasi plugin → layer database → komponen input → intelligence layer → output layer → antarmuka → pengujian.

---

- [x] 1. Plugin Foundation — Struktur direktori dan metadata plugin Moodle
  - [x] 1.1 Buat struktur direktori lengkap plugin `blocks/attendanceleaderboard/` sesuai desain (classes/, db/, lang/, templates/, amd/, tests/)
  - [x] 1.2 Buat `version.php` dengan metadata plugin (component, version, requires, maturity)
  - [x] 1.3 Buat `block_attendanceleaderboard.php` — kelas utama block plugin yang meng-extend `block_base`, implementasi `init()` dan `get_content()`
  - [x] 1.4 Buat `db/access.php` — definisi capabilities: `viewleaderboard`, `manageresources`, `viewanalytics`
  - [x] 1.5 Buat file bahasa `lang/en/block_attendanceleaderboard.php` dan `lang/id/block_attendanceleaderboard.php`
  - [x] 1.6 Buat `settings.php` — halaman konfigurasi admin untuk parameter Coach, ambang batas motivasional, konfigurasi LLM, dan pengaturan Leaderboard
  - [x] 1.7 Verifikasi plugin dapat diinstall di Moodle tanpa error

- [x] 2. Database Schema — Definisi dan instalasi semua tabel ACMLS
  - [x] 2.1 Buat `db/install.xml` dengan definisi XMLDB untuk tabel `acmls_learner_profile` (userid, courseid, cognitive_level, motivation_level, performance_category, learning_style, behavioral_score, engagement_score, profile_version, last_updated)
  - [x] 2.2 Tambahkan definisi tabel `acmls_activity_log` ke `install.xml` (userid, courseid, event_type, component, objectid, action, duration_seconds, result_value, context_data, timecreated, sent_to_profiler)
  - [x] 2.3 Tambahkan definisi tabel `acmls_motivation_sentence` ke `install.xml` (category, performance_target, motivation_target, content, language, source, llm_model, learner_context, is_active, usage_count)
  - [x] 2.4 Tambahkan definisi tabel `acmls_learning_resource` ke `install.xml` (courseid, cmid, title, resource_type, difficulty_level, topic_tags, learning_styles, access_count, avg_rating, effectiveness_score, is_active)
  - [x] 2.5 Tambahkan definisi tabel `acmls_learner_record` ke `install.xml` (userid, courseid, record_type, source_component, data_payload, profile_version, timecreated)
  - [x] 2.6 Tambahkan definisi tabel `acmls_coach_decision` ke `install.xml` (userid, courseid, decision_type, input_profile, recommended_resources, motivation_category, reasoning, rules_triggered, learner_response, response_time)
  - [x] 2.7 Tambahkan definisi tabel `acmls_leaderboard` ke `install.xml` (userid, courseid, scope, attendance_score, engagement_score, completion_score, total_score, current_rank, previous_rank, rank_change, points_to_next, display_name, last_updated)
  - [x] 2.8 Buat `db/upgrade.php` dengan fungsi upgrade dasar
  - [x] 2.9 Verifikasi semua tabel berhasil dibuat saat instalasi plugin

- [x] 3. Tracking System — Pengumpulan dan pencatatan aktivitas Learner
  - [x] 3.1 Buat `classes/tracking/activity_log.php` — kelas data `ActivityLog` dengan semua properti dan metode `to_db_record()`
  - [x] 3.2 Buat `classes/tracking/tracking_system.php` — kelas `TrackingSystem` dengan metode: `handle_event()`, `record_login()`, `record_activity()`, `get_pending_logs()`
  - [x] 3.3 Implementasi metode `flush_to_profiler()` di `TrackingSystem` — kirim Activity_Log ke Profiling System dengan mekanisme retry jika koneksi gagal (simpan lokal, kirim ulang saat koneksi pulih)
  - [x] 3.4 Buat `classes/event/observer.php` — kelas `observer` dengan handler untuk semua events: `user_loggedin`, `user_loggedout`, `course_module_viewed`, `quiz_attempt_submitted`, `grade_item_updated`, `forum_post_created`, `course_module_completion_updated`
  - [x] 3.5 Buat `db/events.php` — registrasi semua event observers ke Moodle Events API
  - [x] 3.6 Buat `classes/task/flush_activity_logs_task.php` — scheduled task yang berjalan setiap 5 menit untuk flush Activity_Log ke Profiling System
  - [x] 3.7 Daftarkan scheduled task di `db/tasks.php`
  - [x] 3.8 Tulis unit tests untuk `TrackingSystem`: pencatatan login, pencatatan aktivitas, kalkulasi durasi sesi, mekanisme retry saat koneksi gagal

- [x] 4. Evaluation System — Integrasi dengan Moodle Gradebook
  - [x] 4.1 Buat `classes/evaluation/evaluation_system.php` — kelas `EvaluationSystem` dengan metode: `handle_grade_event()`, `fetch_gradebook_score()`, `calculate_aggregate_metrics()`
  - [x] 4.2 Implementasi metode `detect_performance_decline()` — deteksi penurunan >20% dari rata-rata performa sebelumnya menggunakan data dari `acmls_learner_record`
  - [x] 4.3 Implementasi metode `send_alert_to_coach()` — kirim sinyal peringatan ke Coach saat penurunan terdeteksi
  - [x] 4.4 Implementasi metode `save_to_learner_record()` — simpan data skor ke `acmls_learner_record` dengan timestamp dan metadata sumber
  - [x] 4.5 Integrasi dengan Moodle Gradebook API (`grade_item::fetch()`, `grade_grade`) untuk mengambil skor dari aktivitas yang dapat dinilai
  - [x] 4.6 Tulis unit tests untuk `EvaluationSystem`: kalkulasi metrik agregat, deteksi penurunan performa (termasuk edge cases: penurunan tepat 20%, penurunan <20%, tidak ada data historis)

- [x] 5. Profiling System — Pemodelan Learner multidimensional
  - [x] 5.1 Buat `classes/profiling/learner_profile.php` — kelas `LearnerProfile` dengan semua properti (cognitive_level, motivation_level, performance_category, learning_style, behavioral_score, engagement_score, profile_version) dan metode `get_snapshot()`
  - [x] 5.2 Buat `classes/profiling/profiling_system.php` — kelas `ProfilingSystem` dengan metode: `process_activity_log()`, `update_profile()`, `save_profile_snapshot()`
  - [x] 5.3 Implementasi metode `classify_performance_category()` — klasifikasi Low/Middle/High berdasarkan aturan: <60%=Low, 60-79%=Middle, ≥80%=High
  - [x] 5.4 Implementasi metode `classify_learning_style()` — klasifikasi gaya belajar berdasarkan pola interaksi dengan tipe resource yang berbeda (visual, auditory, reading, kinesthetic)
  - [x] 5.5 Implementasi metode `calculate_motivation_level()` — kalkulasi Motivation_Level menggunakan weighted moving average: `new_value = (recent_data × 0.7) + (historical_average × 0.3)`, dengan α yang dapat dikonfigurasi
  - [x] 5.6 Implementasi metode `notify_coach()` — kirim notifikasi pembaruan profil ke Coach setelah setiap update
  - [x] 5.7 Implementasi pembaruan profil terjadwal harian melalui `classes/task/update_profiles_task.php` (berjalan pukul 02:00)
  - [x] 5.8 Daftarkan `update_profiles_task` di `db/tasks.php`
  - [x] 5.9 Tulis unit tests untuk `ProfilingSystem`: klasifikasi Performance_Category (semua boundary values), kalkulasi Motivation_Level dengan weighted average, klasifikasi Learning_Style, penyimpanan versi historis profil

- [x] 6. Learning Resource Repository — Katalog sumber belajar
  - [x] 6.1 Buat `classes/repository/learning_resource_repository.php` — kelas `LearningResourceRepository` dengan metode: `find_by_profile()`, `add_resource()`, `update_resource()`, `delete_resource()`, `record_access()`
  - [x] 6.2 Implementasi metode `auto_index_new_resource()` — observer untuk event `course_module_created` yang secara otomatis mengindeks resource baru dengan metadata (judul, tipe, tingkat kesulitan, topik, tag)
  - [x] 6.3 Implementasi metode `find_by_profile()` — query resource berdasarkan kombinasi difficulty_level, learning_styles, dan topic_tags sesuai Learner_Profile, dengan response time ≤3 detik
  - [x] 6.4 Implementasi metode `get_effectiveness_data()` — kalkulasi effectiveness_score berdasarkan korelasi akses resource dengan peningkatan performa Learner
  - [x] 6.5 Tulis unit tests untuk `LearningResourceRepository`: query berdasarkan profil, auto-indexing, kalkulasi effectiveness score

- [x] 7. Coach — Rule-based recommendation engine
  - [x] 7.1 Buat `classes/coach/rule_engine.php` — kelas `RuleEngine` yang memuat dan mengevaluasi aturan IF-THEN dari konfigurasi plugin
  - [x] 7.2 Definisikan aturan rekomendasi default dalam format yang dapat dikonfigurasi:
    - Low + visual → difficulty_level=1, learning_styles contains 'visual', ORDER BY effectiveness_score DESC LIMIT 3
    - High + cognitive_level≥3 → difficulty_level=3, ORDER BY effectiveness_score DESC LIMIT 5
    - motivation_level<50 + rank_change<0 → tingkatkan frekuensi intervensi motivasional
  - [x] 7.3 Buat `classes/coach/coach_decision.php` — kelas data `CoachDecision` dengan semua properti dan metode serialisasi
  - [x] 7.4 Buat `classes/coach/coach.php` — kelas `Coach` dengan metode: `evaluate_profile()`, `recommend_resources()`, `determine_motivation_intervention()`, `save_decision()`
  - [x] 7.5 Implementasi metode `get_leaderboard_factor()` — ambil posisi Leaderboard Learner dan hitung faktor pengaruhnya terhadap keputusan intervensi
  - [x] 7.6 Implementasi metode `update_rules_from_history()` — analisis efektivitas intervensi historis dari `acmls_coach_decision` dan sesuaikan bobot aturan
  - [x] 7.7 Implementasi batas waktu respons Coach ≤10 detik dengan fallback ke rekomendasi default berbasis Performance_Category saja
  - [x] 7.8 Tulis unit tests untuk `Coach`: rekomendasi untuk setiap kombinasi Performance_Category × Learning_Style, penentuan kategori intervensi motivasional, penyimpanan keputusan dengan alasan lengkap

- [x] 8. Motivation Component dan Motivation Sentence Repository
  - [x] 8.1 Buat `classes/motivation/motivation_sentence_repository.php` — kelas `MotivationSentenceRepository` dengan metode: `save()`, `find_relevant()`, `get_delivery_history()`, `check_duplicate()`, operasi CRUD
  - [x] 8.2 Implementasi metode `find_relevant()` — query konten berdasarkan kategori, performance_target, dan motivation_target dengan response time ≤2 detik
  - [x] 8.3 Implementasi metode `check_duplicate()` — cek apakah konten yang sama telah dikirimkan kepada Learner dalam 7 hari terakhir menggunakan hash konten
  - [x] 8.4 Implementasi notifikasi kapasitas — kirim notifikasi ke administrator saat kapasitas tabel mencapai 80% dari batas yang dikonfigurasi
  - [x] 8.5 Buat `classes/motivation/motivation_component.php` — kelas `MotivationComponent` dengan metode: `check_motivation_threshold()`, `request_intervention()`, `record_learner_response()`, `integrate_leaderboard_factor()`
  - [x] 8.6 Implementasi pemantauan ambang batas motivasi — deteksi Motivation_Level di bawah threshold selama >3 hari berturut-turut dan trigger permintaan intervensi ke Coach
  - [x] 8.7 Implementasi tabel pemetaan intervensi: Performance_Category × Motivation_Level → kategori Encouragement_Content (reinforcement, achievement, recovery, persistence)
  - [x] 8.8 Seed data template motivasional statis untuk semua kombinasi kategori × Performance_Category sebagai fallback LLM
  - [x] 8.9 Tulis unit tests untuk `MotivationComponent` dan `MotivationSentenceRepository`: deteksi threshold, pencegahan duplikasi, relevansi query konten

- [x] 9. LLM Preparation — Generasi konten motivasional
  - [x] 9.1 Buat `classes/motivation/providers/openai_provider.php` — kelas `OpenAIProvider` dengan metode `generate()` yang memanggil OpenAI Chat Completions API
  - [x] 9.2 Buat `classes/motivation/providers/ollama_provider.php` — kelas `OllamaProvider` dengan metode `generate()` yang memanggil Ollama local API endpoint
  - [x] 9.3 Buat `classes/motivation/llm_preparation.php` — kelas `LLMPreparation` dengan metode: `generate_encouragement()`, `anonymize_profile()`, `validate_content()`, `fallback_to_template()`
  - [x] 9.4 Implementasi metode `anonymize_profile()` — hapus semua PII (nama, email, ID) dari data profil sebelum dikirim ke layanan LLM eksternal
  - [x] 9.5 Implementasi prompt template dalam bahasa Indonesia formal-akademik untuk setiap kategori intervensi (reinforcement, achievement, recovery, persistence)
  - [x] 9.6 Implementasi mekanisme fallback: timeout API >10 detik → gunakan template statis; error autentikasi → log error + notifikasi admin + gunakan template; rate limit → exponential backoff (max 3 retry) → fallback
  - [x] 9.7 Implementasi metode `validate_content()` — validasi konten yang dihasilkan tidak mengandung konten tidak pantas atau menyesatkan
  - [x] 9.8 Implementasi variasi konten — pastikan konten baru dihasilkan jika konten yang sama telah dikirimkan dalam 7 hari terakhir
  - [x] 9.9 Tulis unit tests untuk `LLMPreparation` dengan mock provider: anonimisasi profil, mekanisme fallback, validasi konten, variasi konten

- [x] 10. Leaderboard — Sistem peringkat berbasis keterlibatan
  - [x] 10.1 Buat `classes/leaderboard/leaderboard.php` — kelas `Leaderboard` dengan metode: `calculate_score()`, `update_rankings()`, `get_learner_rank()`, `trigger_achievement_prompt()`, `anonymize_display()`
  - [x] 10.2 Implementasi formula kalkulasi skor: `total_score = (attendance_score × w1) + (engagement_score × w2) + (completion_score × w3)` dengan bobot yang dapat dikonfigurasi
  - [x] 10.3 Implementasi metode `update_rankings()` — perbarui peringkat semua Learner dalam kursus, hitung rank_change dan points_to_next, dengan interval ≤15 menit
  - [x] 10.4 Buat `classes/task/update_leaderboard_task.php` — scheduled task yang berjalan setiap 15 menit
  - [x] 10.5 Daftarkan `update_leaderboard_task` di `db/tasks.php`
  - [x] 10.6 Implementasi metode `trigger_achievement_prompt()` — deteksi peningkatan peringkat (current_rank < previous_rank) dan kirim trigger ke Motivation Component
  - [x] 10.7 Implementasi fitur privasi Leaderboard — tampilkan nama anonim (inisial/pseudonim) saat fitur privasi diaktifkan administrator
  - [x] 10.8 Implementasi dukungan scope Leaderboard: course, program, institution
  - [x] 10.9 Tulis unit tests untuk `Leaderboard`: kalkulasi skor dengan berbagai bobot, konsistensi peringkat, deteksi peningkatan peringkat, anonimisasi nama

- [x] 11. Learner Record — Repositori analitik pembelajaran
  - [x] 11.1 Buat `classes/repository/learner_record.php` — kelas `LearnerRecord` dengan metode: `save()`, `query_longitudinal()`, `export_csv()`, `export_json()`, `check_integrity()`, `apply_access_control()`
  - [x] 11.2 Implementasi metode `save()` — simpan data dari semua komponen (Profiling, Evaluation, Tracking, Coach) dengan timestamp akurat dan metadata sumber
  - [x] 11.3 Implementasi metode `query_longitudinal()` — query data historis dalam rentang waktu yang dapat dikonfigurasi, mendukung filter berdasarkan record_type dan source_component
  - [x] 11.4 Implementasi metode `export_csv()` dan `export_json()` — ekspor data Learner dalam format standar untuk analisis penelitian eksternal
  - [x] 11.5 Implementasi role-based access control — hanya pengguna dengan capability `viewanalytics` yang dapat mengakses data Learner Record
  - [x] 11.6 Implementasi prosedur pemulihan data otomatis — deteksi integritas data terganggu, notifikasi administrator, inisiasi pemulihan
  - [x] 11.7 Tulis unit tests untuk `LearnerRecord`: penyimpanan data dari berbagai sumber, query longitudinal, ekspor data, access control

- [x] 12. Delivery System — Rendering dan penyampaian konten
  - [x] 12.1 Buat `classes/delivery/delivery_system.php` — kelas `DeliverySystem` dengan metode: `display_resource_recommendations()`, `display_encouragement()`, `display_leaderboard()`, `record_interaction()`, `render_block()`
  - [x] 12.2 Buat `classes/output/renderer.php` — Moodle output renderer yang meng-extend `plugin_renderer_base`
  - [x] 12.3 Buat template Mustache `templates/block_content.mustache` — template utama blok yang mengintegrasikan semua komponen tampilan
  - [x] 12.4 Buat template Mustache `templates/leaderboard.mustache` — tampilan Leaderboard dengan peringkat, skor, perubahan peringkat, dan jarak poin
  - [x] 12.5 Buat template Mustache `templates/resource_recommendations.mustache` — tampilan rekomendasi Learning_Resource dengan judul, tipe, dan tingkat kesulitan
  - [x] 12.6 Buat template Mustache `templates/encouragement_message.mustache` — tampilan Encouragement_Content dengan kategori dan konten
  - [x] 12.7 Implementasi pencatatan respons Learner — catat apakah Learner mengakses resource yang direkomendasikan atau mengabaikannya, kirim data ke Tracking System
  - [x] 12.8 Buat AMD module `amd/src/block.js` dan `amd/src/leaderboard.js` untuk interaktivitas frontend
  - [x] 12.9 Tulis unit tests untuk `DeliverySystem`: rendering konten, pencatatan interaksi, penanganan respons Learner

- [x] 13. Admin Interface — Dasbor dan konfigurasi administrator
  - [x] 13.1 Perluas `settings.php` dengan semua parameter konfigurasi: LLM provider (openai/ollama), API key, model name, Ollama endpoint, bobot Leaderboard, ambang batas motivasional, learning rate (α), threshold penurunan performa
  - [x] 13.2 Buat halaman dasbor analitik administrator yang menampilkan: distribusi Performance_Category, rata-rata Motivation_Level per kursus, efektivitas intervensi (persentase respons positif)
  - [x] 13.3 Buat antarmuka manajemen Learning_Resource untuk instruktur — tambah, edit, hapus resource; lihat data penggunaan dan effectiveness_score
  - [x] 13.4 Buat antarmuka monitoring profil Learner untuk instruktur — lihat Learner_Profile per kursus (sesuai capability `viewanalytics`)
  - [x] 13.5 Implementasi validasi konfigurasi — tampilkan pesan kesalahan deskriptif dan pertahankan konfigurasi sebelumnya jika input tidak valid
  - [x] 13.6 Implementasi log audit konfigurasi — catat setiap perubahan konfigurasi oleh administrator (siapa, kapan, apa yang diubah)

- [x] 14. Privacy API — Kepatuhan privasi data Moodle
  - [x] 14.1 Buat `classes/privacy/provider.php` — implementasi `\core_privacy\local\metadata\provider` dan `\core_privacy\local\request\plugin\provider`
  - [x] 14.2 Implementasi `get_metadata()` — daftarkan semua tabel database ACMLS dan layanan eksternal (OpenAI API) yang menyimpan data Learner
  - [x] 14.3 Implementasi `export_user_data()` — ekspor semua data Learner dari semua tabel ACMLS dalam format yang dapat dibaca
  - [x] 14.4 Implementasi `delete_data_for_user()` — hapus semua data Learner dari semua tabel ACMLS saat diminta
  - [x] 14.5 Implementasi log akses data — catat setiap akses ke Learner_Record (identitas pengakses, waktu, data yang diakses)
  - [x] 14.6 Implementasi mekanisme persetujuan Learner — tampilkan dialog persetujuan sebelum data dikirimkan ke layanan LLM eksternal
  - [x] 14.7 Tulis unit tests untuk Privacy API: ekspor data, penghapusan data, validasi kelengkapan metadata

- [x] 15. Property-Based Tests — Verifikasi 22 correctness properties
  - [x] 15.1 Setup library property-based testing (Eris atau phpunit-quickcheck) di `composer.json` dan konfigurasi autoloader
  - [x] 15.2 Tulis property test untuk **Property 1** (Kelengkapan Pencatatan Aktivitas Sesi): for any active session, all interactions must be recorded in Activity_Log with complete attributes
  - [x] 15.3 Tulis property test untuk **Property 2** (Konsistensi Ringkasan Sesi): session summary sent to Profiling System must accurately represent all activities in Activity_Log
  - [x] 15.4 Tulis property test untuk **Property 3** (Akurasi Kalkulasi Metrik Engagement): engagement metrics must be mathematically accurate and consistent with raw Activity_Log data
  - [x] 15.5 Tulis property test untuk **Property 4** (Ketahanan Data saat Koneksi Terputus): all Activity_Logs generated during disconnection must be delivered after reconnection
  - [x] 15.6 Tulis property test untuk **Property 5** (Kelengkapan Pembaruan Dimensi Profil): profile update must cover all dimensions — no dimension left unchanged when relevant data arrives
  - [x] 15.7 Tulis property test untuk **Property 6** (Kebenaran Klasifikasi Performance_Category): classification must be consistent with rules for all score values in [0, 100]
  - [x] 15.8 Tulis property test untuk **Property 7** (Kelengkapan Penyimpanan Riwayat Profil): all profile versions must be stored and retrievable in correct chronological order
  - [x] 15.9 Tulis property test untuk **Property 8** (Konsistensi Klasifikasi Learning_Style): identical interaction patterns must always produce the same Learning_Style classification
  - [x] 15.10 Tulis property test untuk **Property 9** (Kesesuaian Tingkat Kesulitan Rekomendasi): all recommended resources must have difficulty_level appropriate for the Learner's Performance_Category
  - [x] 15.11 Tulis property test untuk **Property 10** (Kelengkapan Pencatatan Keputusan Coach): every Coach decision must be saved with reasoning before being sent to Delivery System
  - [x] 15.12 Tulis property test untuk **Property 11** (Akurasi Kalkulasi Metrik Performa Agregat): aggregate performance metrics must be mathematically accurate for any set of scores
  - [x] 15.13 Tulis property test untuk **Property 12** (Ketepatan Deteksi Penurunan Performa): alert must be sent if and only if decline exceeds 20% — no false positives or false negatives
  - [x] 15.14 Tulis property test untuk **Property 13** (Kesesuaian Kategori Encouragement_Content): generated content category must match the intervention mapping table for any Learner_Profile
  - [x] 15.15 Tulis property test untuk **Property 14** (Kelengkapan Metadata Encouragement_Content): all generated content must be stored with complete metadata (category, targets, source, timestamp)
  - [x] 15.16 Tulis property test untuk **Property 15** (Pencegahan Duplikasi Encouragement_Content): no content identical to what was sent in the last 7 days must be generated for the same Learner
  - [x] 15.17 Tulis property test untuk **Property 16** (Relevansi Encouragement_Content yang Dikembalikan Repository): returned content must match the requested category and performance_target
  - [x] 15.18 Tulis property test untuk **Property 17** (Kesesuaian Learning_Resource dengan Parameter Query): all returned resources must match the difficulty_level and learning_styles parameters
  - [x] 15.19 Tulis property test untuk **Property 18** (Akurasi Kalkulasi Skor Leaderboard): leaderboard scores must be mathematically accurate and rankings must be consistent with total scores
  - [x] 15.20 Tulis property test untuk **Property 19** (Kelengkapan Informasi Tampilan Leaderboard): leaderboard output must contain all required fields for any Learner
  - [x] 15.21 Tulis property test untuk **Property 20** (Ketepatan Trigger Achievement Prompts): Achievement Prompts must be triggered if and only if rank improves — no false triggers or missed triggers
  - [x] 15.22 Tulis property test untuk **Property 21** (Dominansi Bobot Data Terbaru): recent data changes must produce larger profile value changes than equivalent historical data changes
  - [x] 15.23 Tulis property test untuk **Property 22** (Konsistensi Notifikasi Perubahan Performance_Category): every Performance_Category transition must trigger Coach notification and be recorded in Learner_Record

- [ ] 16. Integration Tests dan Smoke Tests
  - [x] 16.1 Tulis integration test untuk alur end-to-end: login Learner → event observer → Activity_Log → Profiling → Coach → Delivery
  - [x] 16.2 Tulis integration test untuk alur evaluasi: penyelesaian kuis → Gradebook event → Evaluation System → Profiling → Coach
  - [ ] 16.3 Tulis integration test untuk alur motivasional: Motivation_Level rendah → Motivation Component → LLM Preparation (dengan mock) → Motivation_Sentence_Repository → Delivery
  - [ ] 16.4 Tulis integration test untuk alur Leaderboard: update aktivitas → kalkulasi skor → update peringkat → trigger Achievement Prompts → Delivery
  - [ ] 16.5 Tulis smoke test: plugin berhasil diinstall dan diaktifkan di Moodle tanpa error
  - [ ] 16.6 Tulis smoke test: semua 7 tabel database berhasil dibuat dengan skema yang benar
  - [ ] 16.7 Tulis smoke test: semua scheduled tasks terdaftar dan dapat dieksekusi tanpa error
  - [ ] 16.8 Tulis smoke test: konfigurasi LLM valid dan koneksi ke provider dapat dibuat (dengan mock untuk OpenAI)
  - [ ] 16.9 Tulis smoke test: block plugin dapat dirender di halaman kursus Moodle tanpa error
