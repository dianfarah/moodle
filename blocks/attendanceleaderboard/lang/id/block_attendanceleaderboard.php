<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Indonesian language strings for block_attendanceleaderboard.
 *
 * @package   block_attendanceleaderboard
 * @copyright 2024 ACMLS Project
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Nama dan deskripsi plugin.
$string['pluginname']        = 'Papan Peringkat Kehadiran (ACMLS)';
$string['plugindescription'] = 'Adaptive Cognitive-Motivational Learning System — plugin blok cerdas yang menyediakan rekomendasi pembelajaran yang dipersonalisasi, intervensi motivasional, dan papan peringkat berbasis keterlibatan.';

// Judul blok.
$string['attendanceleaderboard'] = 'Papan Peringkat Kehadiran';
$string['blocktitle']            = 'ACMLS — Papan Peringkat Kehadiran';

// Kapabilitas.
$string['attendanceleaderboard:viewleaderboard']  = 'Melihat papan peringkat kehadiran';
$string['attendanceleaderboard:manageresources']  = 'Mengelola sumber belajar';
$string['attendanceleaderboard:viewanalytics']    = 'Melihat analitik pembelajaran';
$string['attendanceleaderboard:addinstance']      = 'Menambahkan blok Papan Peringkat Kehadiran baru';
$string['attendanceleaderboard:myaddinstance']    = 'Menambahkan blok Papan Peringkat Kehadiran ke Dasbor';

// Pengaturan — bagian umum.
$string['settings_general']             = 'Pengaturan Umum';
$string['settings_general_desc']        = 'Konfigurasi umum untuk plugin ACMLS.';

// Pengaturan — konfigurasi LLM.
$string['settings_llm']                 = 'Konfigurasi LLM';
$string['settings_llm_desc']            = 'Konfigurasi penyedia Gemini yang digunakan untuk menghasilkan konten motivasional.';
$string['settings_llm_provider']        = 'Penyedia LLM';
$string['settings_llm_provider_desc']   = 'Gemini adalah satu-satunya penyedia yang didukung untuk menghasilkan kalimat motivasional.';
$string['settings_llm_provider_gemini'] = 'Gemini';
$string['settings_gemini_apikey']       = 'Kunci API Gemini';
$string['settings_gemini_apikey_desc']  = 'Masukkan kunci API Gemini Anda. Jaga kerahasiaan nilai ini.';
$string['settings_gemini_model']        = 'Model Gemini';
$string['settings_gemini_model_desc']   = 'Model Gemini yang akan digunakan untuk menghasilkan konten motivasional (misalnya, gemini-3.5-flash).';
$string['settings_llm_provider_openai'] = 'OpenAI';
$string['settings_llm_provider_ollama'] = 'Ollama (Lokal)';
$string['settings_openai_apikey']       = 'Kunci API OpenAI';
$string['settings_openai_apikey_desc']  = 'Masukkan kunci API OpenAI Anda. Jaga kerahasiaan nilai ini.';
$string['settings_openai_model']        = 'Model OpenAI';
$string['settings_openai_model_desc']   = 'Model OpenAI yang akan digunakan (misalnya, gpt-4o-mini, gpt-4o).';
$string['settings_ollama_endpoint']     = 'URL Endpoint Ollama';
$string['settings_ollama_endpoint_desc'] = 'URL dasar instans Ollama lokal Anda (misalnya, http://localhost:11434).';
$string['settings_ollama_model']        = 'Model Ollama';
$string['settings_ollama_model_desc']   = 'Model Ollama yang akan digunakan (misalnya, llama3.2, mistral).';

// Pengaturan — konfigurasi Leaderboard.
$string['settings_leaderboard']              = 'Pengaturan Papan Peringkat';
$string['settings_leaderboard_desc']         = 'Konfigurasi bobot penilaian dan opsi tampilan papan peringkat.';
$string['settings_weight_attendance']        = 'Bobot Kehadiran';
$string['settings_weight_attendance_desc']   = 'Bobot skor kehadiran dalam total skor papan peringkat (0,0 – 1,0).';
$string['settings_weight_engagement']        = 'Bobot Keterlibatan';
$string['settings_weight_engagement_desc']   = 'Bobot skor keterlibatan dalam total skor papan peringkat (0,0 - 1,0).';
$string['settings_weight_completion']        = 'Bobot Penyelesaian';
$string['settings_weight_completion_desc']   = 'Bobot skor penyelesaian aktivitas dalam total skor papan peringkat (0,0 – 1,0).';
$string['settings_leaderboard_privacy']      = 'Aktifkan Privasi Papan Peringkat';
$string['settings_leaderboard_privacy_desc'] = 'Jika diaktifkan, nama mahasiswa lain ditampilkan secara anonim (inisial atau nama samaran).';
$string['settings_leaderboard_scope']        = 'Cakupan Papan Peringkat Default';
$string['settings_leaderboard_scope_desc']   = 'Cakupan default untuk tampilan papan peringkat.';
$string['settings_scope_course']             = 'Kursus';
$string['settings_scope_program']            = 'Program Studi';
$string['settings_scope_institution']        = 'Institusi';

// Pengaturan — ambang batas motivasional.
$string['settings_motivation']                    = 'Ambang Batas Motivasional';
$string['settings_motivation_desc']               = 'Konfigurasi ambang batas yang memicu intervensi motivasional.';
$string['settings_motivation_threshold']          = 'Ambang Batas Tingkat Motivasi';
$string['settings_motivation_threshold_desc']     = 'Motivation_Level di bawah nilai ini (0–100) memicu permintaan intervensi.';
$string['settings_motivation_threshold_days']     = 'Hari Berturut-turut di Bawah Ambang Batas';
$string['settings_motivation_threshold_days_desc'] = 'Jumlah hari berturut-turut Motivation_Level harus berada di bawah ambang batas sebelum intervensi dipicu.';
$string['settings_motivation_capacity_warning']      = 'Peringatan Kapasitas Repositori (%)';
$string['settings_motivation_capacity_warning_desc'] = 'Persentase kapasitas Motivation Sentence Repository yang memicu notifikasi peringatan kepada administrator. Default: 80.';

// Pengaturan — parameter Coach / Profiling.
$string['settings_coach']                         = 'Parameter Coach & Profiling';
$string['settings_coach_desc']                    = 'Konfigurasi parameter mesin adaptif dan algoritma profiling.';
$string['settings_learning_rate']                 = 'Learning Rate (α)';
$string['settings_learning_rate_desc']            = 'Nilai α yang digunakan dalam weighted moving average untuk pembaruan profil: new_value = (recent_data × α) + (historical_average × (1 − α)). Default: 0,7.';
$string['settings_performance_decline_threshold'] = 'Ambang Batas Penurunan Performa (%)';
$string['settings_performance_decline_threshold_desc'] = 'Persentase penurunan dari rata-rata sebelumnya yang memicu peringatan performa ke Coach. Default: 20.';

// Pengaturan — interval pembaruan leaderboard.
$string['settings_leaderboard_update_interval']      = 'Interval Pembaruan Papan Peringkat (menit)';
$string['settings_leaderboard_update_interval_desc'] = 'Seberapa sering (dalam menit) peringkat papan peringkat dihitung ulang. Default: 15.';

// Pengaturan — konfigurasi evaluasi.
$string['settings_evaluation']                    = 'Konfigurasi Evaluasi';
$string['settings_evaluation_desc']               = 'Konfigurasi ambang batas evaluasi performa dan parameter respons Coach.';
$string['settings_coach_response_timeout']        = 'Batas Waktu Respons Coach (detik)';
$string['settings_coach_response_timeout_desc']   = 'Waktu maksimum dalam detik yang diizinkan untuk mesin rule-based Coach menghitung Adaptive_Intervention sebelum timeout. Default: 10.';

// String tampilan papan peringkat.
$string['leaderboard_title']          = 'Papan Peringkat';
$string['leaderboard_rank']           = 'Peringkat';
$string['leaderboard_name']           = 'Nama';
$string['leaderboard_score']          = 'Skor';
$string['leaderboard_change']         = 'Perubahan';
$string['leaderboard_rank_change']    = 'Perubahan';
$string['leaderboard_points_to_next'] = 'Poin ke Berikutnya';
$string['leaderboard_your_rank']      = 'Peringkat Anda saat ini: {$a}';
$string['leaderboard_no_data']        = 'Data papan peringkat belum tersedia.';

// Kategori performa.
$string['performance_low']    = 'Rendah';
$string['performance_middle'] = 'Menengah';
$string['performance_high']   = 'Tinggi';

// Kategori konten motivasional.
$string['encouragement_reinforcement'] = 'Penguatan Positif';
$string['encouragement_achievement']   = 'Dorongan Pencapaian';
$string['encouragement_recovery']      = 'Dorongan Pemulihan';
$string['encouragement_persistence']   = 'Motivasi Ketekunan';

// Pesan kesalahan.
$string['error_llm_unavailable']       = 'Layanan LLM saat ini tidak tersedia. Menggunakan template statis sebagai gantinya.';
$string['error_invalid_config']        = 'Nilai konfigurasi tidak valid: {$a}. Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_db_write']              = 'Gagal menulis ke basis data. Data telah di-buffer dan akan dicoba ulang.';
$string['error_gradebook_unavailable'] = 'Data Moodle Gradebook saat ini tidak tersedia. Mempertahankan nilai performa terakhir yang valid.';

// Pesan kesalahan validasi konfigurasi.
$string['error_validation_llm_provider']         = 'Penyedia LLM tidak valid. Nilai harus berupa "gemini". Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_api_key']              = 'Kunci API Gemini tidak boleh kosong ketika generasi Gemini diaktifkan. Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_ollama_endpoint']      = 'Endpoint Ollama harus berupa URL yang valid dan diawali dengan http:// atau https:// (contoh: http://localhost:11434). Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_weight_range']         = 'Setiap bobot papan peringkat harus berupa angka antara 0,0 dan 1,0. Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_weight_sum']           = 'Ketiga bobot papan peringkat harus berjumlah 1,0 (jumlah saat ini: {$a}). Sesuaikan bobot sehingga kehadiran + keterlibatan + penyelesaian = 1,0. Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_motivation_threshold'] = 'Ambang batas motivasi harus berupa bilangan bulat antara 1 dan 100. Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_learning_rate']        = 'Learning rate (α) harus berupa angka antara 0,01 dan 1,0. Konfigurasi valid sebelumnya telah dipertahankan.';
$string['error_validation_decline_threshold']    = 'Ambang batas penurunan performa harus berupa bilangan bulat antara 1 dan 100. Konfigurasi valid sebelumnya telah dipertahankan.';

// Privasi.
$string['privacy:metadata:acmls_learner_profile']     = 'Menyimpan profil multidimensional mahasiswa termasuk tingkat kognitif, tingkat motivasi, dan kategori performa.';
$string['privacy:metadata:acmls_activity_log']        = 'Menyimpan log seluruh aktivitas mahasiswa di dalam LMS.';
$string['privacy:metadata:acmls_motivation_sentence'] = 'Menyimpan konten motivasional yang dihasilkan untuk mahasiswa.';
$string['privacy:metadata:acmls_learning_resource']   = 'Menyimpan metadata sumber belajar yang tersedia dalam kursus.';
$string['privacy:metadata:acmls_learner_record']      = 'Menyimpan data analitik pembelajaran longitudinal untuk setiap mahasiswa.';
$string['privacy:metadata:acmls_coach_decision']      = 'Menyimpan keputusan rekomendasi Coach beserta alasannya.';
$string['privacy:metadata:acmls_leaderboard']         = 'Menyimpan data peringkat papan peringkat untuk mahasiswa.';
$string['privacy:metadata:gemini_api']                = 'Data konteks mahasiswa yang telah dianonimkan dapat dikirimkan ke Gemini API untuk menghasilkan konten motivasional.';
$string['privacy:metadata:openai_api']                = 'Data konteks mahasiswa yang telah dianonimkan dapat dikirimkan ke OpenAI API untuk menghasilkan konten motivasional.';

// String dasbor analitik.
$string['analytics_dashboard']       = 'Dasbor Analitik';
$string['analytics_dashboard_title'] = 'Dasbor Analitik ACMLS';
$string['performance_distribution']  = 'Distribusi Kategori Performa';
$string['motivation_per_course']     = 'Rata-rata Tingkat Motivasi per Kursus';
$string['intervention_effectiveness'] = 'Efektivitas Intervensi';
$string['category_low']              = 'Rendah';
$string['category_middle']           = 'Menengah';
$string['category_high']             = 'Tinggi';
$string['total_learners']            = 'Total Pelajar';
$string['avg_motivation']            = 'Rata-rata Tingkat Motivasi';
$string['total_decisions']           = 'Total Keputusan';
$string['positive_responses']        = 'Respons Positif';
$string['effectiveness_percentage']  = 'Efektivitas';
$string['no_data_available']         = 'Belum ada data yang tersedia.';
$string['course_name']               = 'Kursus';
$string['count']                     = 'Jumlah';
$string['percentage']                = 'Persentase';
$string['viewdashboard']             = 'Lihat Dasbor Analitik';

// String konfigurasi instans blok (digunakan oleh edit_form.php).
$string['configtitle']          = 'Judul blok';
$string['blocksettings']        = 'Pengaturan blok';
$string['configdisplaytype']    = 'Tipe tampilan';
$string['displaytype_top']      = 'N mahasiswa teratas';
$string['displaytype_all']      = 'Semua mahasiswa';
$string['numbertodisplay']      = 'Jumlah mahasiswa yang ditampilkan';
$string['confignameformat']     = 'Format nama';
$string['nameformat_full']      = 'Nama lengkap (Nama Depan Nama Belakang)';
$string['nameformat_initial']   = 'Inisial (Nama Depan N.)';

// String halaman manajemen sumber belajar.
$string['manage_resources_title']       = 'Kelola Sumber Belajar';
$string['manage_resources_link']        = 'Kelola Sumber Belajar';
$string['addresource']                  = 'Tambah Sumber Belajar';
$string['editresource']                 = 'Edit Sumber Belajar';
$string['deleteresource']               = 'Hapus Sumber Belajar';
$string['saveresource']                 = 'Simpan Sumber Belajar';
$string['resource_title']               = 'Judul';
$string['resource_title_help']          = 'Masukkan judul sumber belajar.';
$string['resource_type']                = 'Jenis Sumber Belajar';
$string['resource_type_help']           = 'Pilih jenis sumber belajar.';
$string['resource_type_resource']       = 'File / Sumber Daya';
$string['resource_type_page']           = 'Halaman';
$string['resource_type_book']           = 'Buku';
$string['resource_type_video']          = 'Video';
$string['resource_type_audio']          = 'Audio';
$string['resource_type_document']       = 'Dokumen';
$string['resource_type_pdf']            = 'PDF';
$string['resource_type_quiz']           = 'Kuis';
$string['resource_type_assign']         = 'Tugas';
$string['resource_type_workshop']       = 'Workshop';
$string['resource_type_forum']          = 'Forum';
$string['resource_type_chat']           = 'Obrolan';
$string['difficulty_level']             = 'Tingkat Kesulitan';
$string['difficulty_level_help']        = 'Pilih tingkat kesulitan sumber belajar ini.';
$string['difficulty_basic']             = 'Dasar';
$string['difficulty_intermediate']      = 'Menengah';
$string['difficulty_advanced']          = 'Lanjutan';
$string['topic_tags']                   = 'Tag Topik';
$string['topic_tags_help']              = 'Masukkan tag topik yang dipisahkan koma (misalnya: aljabar, kalkulus, statistika).';
$string['learning_styles']              = 'Gaya Belajar';
$string['learning_styles_help']         = 'Pilih satu atau lebih gaya belajar yang didukung sumber belajar ini. Tahan Ctrl/Cmd untuk memilih beberapa.';
$string['style_visual']                 = 'Visual';
$string['style_auditory']               = 'Auditori';
$string['style_reading']                = 'Membaca / Menulis';
$string['style_kinesthetic']            = 'Kinestetik';
$string['resource_is_active']           = 'Aktif';
$string['resource_is_active_desc']      = 'Sumber belajar yang tidak aktif disembunyikan dari rekomendasi tetapi datanya tetap tersimpan.';
$string['resource_active']              = 'Aktif';
$string['resource_inactive']            = 'Tidak Aktif';
$string['resource_status']              = 'Status';
$string['access_count']                 = 'Jumlah Akses';
$string['avg_rating']                   = 'Rating Rata-rata';
$string['effectiveness_score']          = 'Efektivitas';
$string['actions']                      = 'Aksi';
$string['no_resources']                 = 'Belum ada sumber belajar yang ditambahkan. Klik "Tambah Sumber Belajar" untuk memulai.';
$string['resource_added']               = 'Sumber belajar berhasil ditambahkan.';
$string['resource_updated']             = 'Sumber belajar berhasil diperbarui.';
$string['resource_deleted']             = 'Sumber belajar berhasil dinonaktifkan.';
$string['resource_not_found']           = 'Sumber belajar yang diminta tidak ditemukan.';
$string['resource_delete_confirm']      = 'Apakah Anda yakin ingin menonaktifkan sumber belajar "{$a}"? Data sumber belajar akan tetap tersimpan untuk keperluan analitik.';
$string['manage_resources_link_desc']   = 'Tambah, edit, dan kelola sumber belajar untuk kursus ini.';

// String monitoring profil pelajar.
$string['learnerprofiles']           = 'Profil Pelajar';
$string['learnerprofilemonitoring']  = 'Monitoring Profil Pelajar';
$string['learnerprofiledetail']      = 'Detail Profil Pelajar';
$string['performancecategory']       = 'Kategori Performa';
$string['cognitivelevel']            = 'Tingkat Kognitif';
$string['motivationlevel']           = 'Tingkat Motivasi';
$string['learningstyle']             = 'Gaya Belajar';
$string['behavioralscore']           = 'Skor Perilaku';
$string['engagementscore']           = 'Skor Keterlibatan';
$string['lastupdated']               = 'Terakhir Diperbarui';
$string['profileversion']            = 'Versi Profil';
$string['profilehistory']            = 'Riwayat Profil';
$string['noprofilefound']            = 'Profil tidak ditemukan untuk pelajar ini.';
$string['nolearnersprofile']         = 'Tidak ada profil pelajar yang ditemukan untuk kursus ini.';
$string['filterbyperformance']       = 'Filter berdasarkan Kategori Performa';
$string['allcategories']             = 'Semua Kategori';
$string['viewdetail']                = 'Lihat Detail';
$string['low']                       = 'Rendah';
$string['middle']                    = 'Sedang';
$string['high']                      = 'Tinggi';
$string['backtolist']                = 'Kembali ke Daftar Pelajar';
$string['viewlearnerprofiles']       = 'Lihat Profil Pelajar';
$string['viewlearnerprofiles_desc']  = 'Pantau profil pelajar untuk kursus ini (memerlukan kapabilitas viewanalytics).';

// Nama scheduled task.
$string['task_flush_activity_logs']  = 'Kirim log aktivitas ACMLS ke Sistem Profiling';
$string['task_update_profiles']      = 'Perbarui profil pelajar ACMLS';
$string['task_update_leaderboard']   = 'Perbarui peringkat papan peringkat ACMLS';

// Log Audit Konfigurasi (Task 13.6).
$string['audit_log_title']           = 'Log Audit Konfigurasi';
$string['audit_log_desc']            = 'Mencatat setiap perubahan konfigurasi yang dilakukan oleh administrator, menampilkan siapa yang mengubah apa dan kapan.';
$string['audit_log_settings_desc']   = 'Lihat riwayat lengkap semua perubahan konfigurasi administrator.';
$string['audit_view_full_log']       = 'Lihat Log Audit Lengkap';
$string['audit_no_entries']          = 'Belum ada perubahan konfigurasi yang dicatat.';
$string['audit_col_time']            = 'Tanggal / Waktu';
$string['audit_col_user']            = 'Administrator';
$string['audit_col_setting']         = 'Pengaturan';
$string['audit_col_old_value']       = 'Nilai Sebelumnya';
$string['audit_col_new_value']       = 'Nilai Baru';
$string['audit_recent_changes']      = 'perubahan konfigurasi dalam 30 hari terakhir';
$string['audit_showing']             = 'Menampilkan {$a->from}–{$a->to} dari {$a->total} entri';
$string['audit_total_entries']       = '{$a} entri total';
$string['privacy:metadata:acmls_config_audit_log'] = 'Menyimpan log semua perubahan konfigurasi administrator, termasuk siapa yang melakukan perubahan, kapan, dan apa yang diubah.';

// Metadata privasi — string tingkat field untuk acmls_learner_profile.
$string['privacy:metadata:acmls_learner_profile:userid']               = 'ID mahasiswa yang profilnya disimpan.';
$string['privacy:metadata:acmls_learner_profile:courseid']             = 'Kursus tempat profil mahasiswa ini berada.';
$string['privacy:metadata:acmls_learner_profile:cognitive_level']      = 'Tingkat kognitif mahasiswa (1=Rendah, 2=Menengah, 3=Tinggi).';
$string['privacy:metadata:acmls_learner_profile:motivation_level']     = 'Skor tingkat motivasi mahasiswa (0,00–100,00).';
$string['privacy:metadata:acmls_learner_profile:performance_category'] = 'Kategori performa mahasiswa (1=Rendah, 2=Menengah, 3=Tinggi).';
$string['privacy:metadata:acmls_learner_profile:learning_style']       = 'Gaya belajar mahasiswa yang diklasifikasikan (misalnya visual, auditori).';
$string['privacy:metadata:acmls_learner_profile:behavioral_score']     = 'Skor keterlibatan perilaku mahasiswa.';
$string['privacy:metadata:acmls_learner_profile:engagement_score']     = 'Skor keterlibatan keseluruhan mahasiswa.';
$string['privacy:metadata:acmls_learner_profile:profile_version']      = 'Nomor versi snapshot profil mahasiswa ini.';
$string['privacy:metadata:acmls_learner_profile:last_updated']         = 'Timestamp ketika profil ini terakhir diperbarui.';

// Metadata privasi — string tingkat field untuk acmls_activity_log.
$string['privacy:metadata:acmls_activity_log:userid']           = 'ID mahasiswa yang melakukan aktivitas.';
$string['privacy:metadata:acmls_activity_log:courseid']         = 'Kursus tempat aktivitas terjadi.';
$string['privacy:metadata:acmls_activity_log:event_type']       = 'Jenis event yang dicatat (misalnya course_module_viewed, quiz_submitted).';
$string['privacy:metadata:acmls_activity_log:component']        = 'Komponen Moodle yang menghasilkan event (misalnya mod_quiz).';
$string['privacy:metadata:acmls_activity_log:objectid']         = 'ID objek yang terlibat dalam event.';
$string['privacy:metadata:acmls_activity_log:action']           = 'Tindakan yang dilakukan oleh mahasiswa.';
$string['privacy:metadata:acmls_activity_log:duration_seconds'] = 'Durasi aktivitas dalam detik.';
$string['privacy:metadata:acmls_activity_log:result_value']     = 'Nilai skor atau hasil yang terkait dengan aktivitas, jika ada.';
$string['privacy:metadata:acmls_activity_log:context_data']     = 'Metadata JSON tambahan tentang konteks aktivitas.';
$string['privacy:metadata:acmls_activity_log:timecreated']      = 'Timestamp ketika entri log aktivitas ini dibuat.';

// Metadata privasi — string tingkat field untuk acmls_motivation_sentence.
$string['privacy:metadata:acmls_motivation_sentence:learner_context'] = 'Data konteks mahasiswa yang telah dianonimkan, digunakan untuk menghasilkan kalimat motivasional ini. Tidak ada informasi identitas pribadi yang disimpan.';
$string['privacy:metadata:acmls_motivation_sentence:content']         = 'Konten kalimat motivasional yang dihasilkan untuk mahasiswa.';
$string['privacy:metadata:acmls_motivation_sentence:category']        = 'Kategori motivasional (reinforcement, achievement, recovery, persistence).';
$string['privacy:metadata:acmls_motivation_sentence:timecreated']     = 'Timestamp ketika kalimat motivasional ini dihasilkan.';

// Metadata privasi — string tingkat field untuk acmls_learner_record.
$string['privacy:metadata:acmls_learner_record:userid']           = 'ID mahasiswa yang dimiliki rekaman ini.';
$string['privacy:metadata:acmls_learner_record:courseid']         = 'Kursus yang terkait dengan rekaman pembelajaran ini.';
$string['privacy:metadata:acmls_learner_record:record_type']      = 'Jenis rekaman pembelajaran (misalnya profile_snapshot, performance, intervention).';
$string['privacy:metadata:acmls_learner_record:source_component'] = 'Komponen ACMLS yang membuat rekaman ini (misalnya profiling, evaluation, coach).';
$string['privacy:metadata:acmls_learner_record:data_payload']     = 'Payload data berformat JSON yang berisi data analitik pembelajaran mahasiswa.';
$string['privacy:metadata:acmls_learner_record:profile_version']  = 'Nomor versi profil pada saat rekaman ini dibuat.';
$string['privacy:metadata:acmls_learner_record:timecreated']      = 'Timestamp ketika rekaman pembelajaran ini dibuat.';

// Metadata privasi — string tingkat field untuk acmls_coach_decision.
$string['privacy:metadata:acmls_coach_decision:userid']                = 'ID mahasiswa yang menjadi sasaran keputusan Coach ini.';
$string['privacy:metadata:acmls_coach_decision:courseid']              = 'Konteks kursus tempat keputusan ini dibuat.';
$string['privacy:metadata:acmls_coach_decision:decision_type']         = 'Jenis keputusan Coach (resource_recommendation atau motivation_intervention).';
$string['privacy:metadata:acmls_coach_decision:input_profile']         = 'Snapshot JSON profil mahasiswa pada saat keputusan dibuat.';
$string['privacy:metadata:acmls_coach_decision:recommended_resources'] = 'Array JSON berisi ID sumber belajar yang direkomendasikan kepada mahasiswa.';
$string['privacy:metadata:acmls_coach_decision:motivation_category']   = 'Kategori motivasional yang dipilih untuk intervensi ini.';
$string['privacy:metadata:acmls_coach_decision:reasoning']             = 'Alasan dan aturan yang menghasilkan keputusan Coach ini.';
$string['privacy:metadata:acmls_coach_decision:rules_triggered']       = 'Array JSON berisi ID aturan yang dipicu untuk menghasilkan keputusan ini.';
$string['privacy:metadata:acmls_coach_decision:learner_response']      = 'Respons mahasiswa terhadap keputusan ini (0=diabaikan, 1=diakses, null=menunggu).';
$string['privacy:metadata:acmls_coach_decision:response_time']         = 'Timestamp ketika mahasiswa merespons keputusan ini.';
$string['privacy:metadata:acmls_coach_decision:timecreated']           = 'Timestamp ketika keputusan Coach ini dicatat.';

// Metadata privasi — string tingkat field untuk acmls_leaderboard.
$string['privacy:metadata:acmls_leaderboard:userid']           = 'ID mahasiswa yang dimiliki entri papan peringkat ini.';
$string['privacy:metadata:acmls_leaderboard:courseid']         = 'Kursus tempat entri papan peringkat ini berada.';
$string['privacy:metadata:acmls_leaderboard:scope']            = 'Cakupan entri papan peringkat ini (course, program, atau institution).';
$string['privacy:metadata:acmls_leaderboard:attendance_score'] = 'Komponen skor kehadiran mahasiswa.';
$string['privacy:metadata:acmls_leaderboard:engagement_score'] = 'Komponen skor keterlibatan mahasiswa.';
$string['privacy:metadata:acmls_leaderboard:completion_score'] = 'Komponen skor penyelesaian aktivitas mahasiswa.';
$string['privacy:metadata:acmls_leaderboard:total_score']      = 'Total skor papan peringkat berbobot mahasiswa.';
$string['privacy:metadata:acmls_leaderboard:current_rank']     = 'Peringkat mahasiswa saat ini di papan peringkat.';
$string['privacy:metadata:acmls_leaderboard:previous_rank']    = 'Peringkat mahasiswa sebelumnya di papan peringkat.';
$string['privacy:metadata:acmls_leaderboard:rank_change']      = 'Perubahan peringkat sejak pembaruan terakhir (positif = meningkat, negatif = menurun).';
$string['privacy:metadata:acmls_leaderboard:points_to_next']   = 'Jumlah poin yang dibutuhkan untuk mencapai peringkat berikutnya.';
$string['privacy:metadata:acmls_leaderboard:display_name']     = 'Nama tampilan yang ditampilkan di papan peringkat (mungkin dianonimkan).';
$string['privacy:metadata:acmls_leaderboard:last_updated']     = 'Timestamp ketika entri papan peringkat ini terakhir diperbarui.';

// Metadata privasi — string tingkat field untuk acmls_config_audit_log.
$string['privacy:metadata:acmls_config_audit_log:userid']       = 'ID administrator yang melakukan perubahan konfigurasi.';
$string['privacy:metadata:acmls_config_audit_log:setting_name'] = 'Nama lengkap pengaturan konfigurasi yang diubah.';
$string['privacy:metadata:acmls_config_audit_log:old_value']    = 'Nilai sebelumnya dari pengaturan konfigurasi sebelum perubahan.';
$string['privacy:metadata:acmls_config_audit_log:new_value']    = 'Nilai baru dari pengaturan konfigurasi setelah perubahan.';
$string['privacy:metadata:acmls_config_audit_log:context']      = 'Konteks tempat perubahan konfigurasi dilakukan (misalnya admin_settings_page).';
$string['privacy:metadata:acmls_config_audit_log:timecreated']  = 'Timestamp ketika perubahan konfigurasi ini dicatat.';

// Metadata privasi — string tingkat field untuk OpenAI external API.
$string['privacy:metadata:gemini_api:learner_context'] = 'Data konteks mahasiswa yang telah dianonimkan (kategori performa, tingkat motivasi, dan pola pembelajaran) yang dikirimkan ke Gemini API untuk menghasilkan konten motivasional yang dipersonalisasi. Tidak ada informasi identitas pribadi yang disertakan.';

// Metadata privasi — tabel acmls_data_access_log (Task 14.5 / Req 15.4).
$string['privacy:metadata:acmls_data_access_log']                          = 'Menyimpan log audit setiap akses ke data Learner_Record, mencatat identitas pengakses, mahasiswa yang datanya diakses, waktu akses, dan data apa yang diakses.';
$string['privacy:metadata:acmls_data_access_log:accessor_userid']          = 'ID pengguna yang mengakses data Learner_Record.';
$string['privacy:metadata:acmls_data_access_log:target_userid']            = 'ID mahasiswa yang datanya diakses.';
$string['privacy:metadata:acmls_data_access_log:courseid']                 = 'Konteks kursus tempat akses data terjadi.';
$string['privacy:metadata:acmls_data_access_log:access_type']              = 'Jenis operasi akses yang dilakukan (misalnya query_longitudinal, export_csv, export_json).';
$string['privacy:metadata:acmls_data_access_log:record_type_filter']       = 'Filter record_type yang diterapkan saat akses, jika ada.';
$string['privacy:metadata:acmls_data_access_log:source_component_filter']  = 'Filter source_component yang diterapkan saat akses, jika ada.';
$string['privacy:metadata:acmls_data_access_log:records_returned']         = 'Jumlah entri Learner_Record yang dikembalikan oleh operasi akses ini.';
$string['privacy:metadata:acmls_data_access_log:timecreated']              = 'Timestamp ketika peristiwa akses data ini dicatat.';

// String dialog persetujuan (Task 14.6 / Req 15.5).
$string['consent_dialog_title']   = 'Persetujuan Privasi Data';
$string['consent_dialog_body']    = 'Untuk memberikan konten motivasional yang dipersonalisasi, ACMLS dapat mengirimkan data pembelajaran yang telah dianonimkan ke layanan AI eksternal (Gemini). Data anonim berikut mungkin dikirimkan:';
$string['consent_agree']          = 'Setuju';
$string['consent_decline']        = 'Tolak';
$string['consent_required_notice'] = 'Anda dapat mengubah preferensi ini kapan saja melalui pengaturan profil. Menolak akan menggunakan template motivasional yang telah disiapkan sebelumnya.';
$string['consent_data_item_performance']  = 'Kategori performa (Rendah / Menengah / Tinggi)';
$string['consent_data_item_motivation']   = 'Tingkat motivasi (dibulatkan ke kelipatan 10 terdekat)';
$string['consent_data_item_style']        = 'Gaya belajar (misalnya visual, auditori)';
$string['consent_data_item_engagement']   = 'Skor keterlibatan (dibulatkan ke bilangan bulat terdekat)';
$string['consent_anonymization_notice']   = 'Semua pengenal pribadi (nama, email, NIM) dihapus sebelum data apa pun dikirimkan.';

// Metadata privasi — tabel acmls_learner_consent (Task 14.6 / Req 15.5).
$string['privacy:metadata:acmls_learner_consent']                    = 'Menyimpan keputusan persetujuan eksplisit setiap mahasiswa untuk pengiriman data anonim ke layanan LLM eksternal.';
$string['privacy:metadata:acmls_learner_consent:userid']             = 'ID mahasiswa yang keputusan persetujuannya disimpan.';
$string['privacy:metadata:acmls_learner_consent:courseid']           = 'Konteks kursus tempat keputusan persetujuan ini berlaku.';
$string['privacy:metadata:acmls_learner_consent:consent_given']      = 'Apakah mahasiswa telah memberikan persetujuan (1 = setuju, 0 = tolak).';
$string['privacy:metadata:acmls_learner_consent:consent_timestamp']  = 'Timestamp ketika mahasiswa memberikan persetujuan, jika berlaku.';
$string['privacy:metadata:acmls_learner_consent:timecreated']        = 'Timestamp ketika rekaman persetujuan ini pertama kali dibuat.';
$string['privacy:metadata:acmls_learner_consent:timemodified']       = 'Timestamp ketika rekaman persetujuan ini terakhir dimodifikasi.';

// Popup motivasi dan umpan balik untuk riset.
$string['motivation_popup_title'] = 'Check-In Motivasi';
$string['motivation_popup_body'] = 'Silakan baca pesan berikut, lalu beri tahu kami bagaimana perasaan Anda setelah menerimanya.';
$string['motivation_feeling_prompt'] = 'Bagaimana perasaan Anda setelah membaca pesan ini?';
$string['motivation_reflection_label'] = 'Refleksi singkat (opsional)';
$string['motivation_reflection_placeholder'] = 'Ceritakan reaksi Anda dalam satu atau dua kalimat.';
$string['motivation_submit'] = 'Kirim respons';
$string['motivation_feeling_required'] = 'Silakan pilih satu perasaan sebelum mengirim respons Anda.';
$string['motivation_research_notice'] = 'Respons Anda akan disimpan untuk evaluasi intervensi dan analisis penelitian.';
$string['motivation_feeling_very_motivated'] = 'Sangat termotivasi';
$string['motivation_feeling_motivated'] = 'Termotivasi';
$string['motivation_feeling_neutral'] = 'Netral';
$string['motivation_feeling_confused'] = 'Bingung';
$string['motivation_feeling_discouraged'] = 'Kurang bersemangat';

$string['motivation_e1_prompt'] = 'Saya termotivasi untuk melanjutkan pembelajaran ini.';
$string['motivation_e2_prompt'] = 'Saya merasa percaya diri dengan kemampuan saya.';
$string['motivation_e3_prompt'] = 'Saya merasa didukung dalam proses belajar ini.';
$string['motivation_likert_1'] = 'Sangat Tidak Menerima';
$string['motivation_likert_2'] = 'Tidak Menerima';
$string['motivation_likert_3'] = 'Netral';
$string['motivation_likert_4'] = 'Menerima';
$string['motivation_likert_5'] = 'Sangat Menerima';
$string['motivation_feedback_required'] = 'Silakan jawab ketiga pertanyaan sebelum mengirim respons.';

// Metadata privasi - acmls_motivation_feedback.
$string['privacy:metadata:acmls_motivation_feedback'] = 'Menyimpan setiap respons mahasiswa terhadap popup motivasi, termasuk pilihan perasaan dan teks refleksi opsional.';
$string['privacy:metadata:acmls_motivation_feedback:userid'] = 'ID mahasiswa yang mengirimkan umpan balik motivasi.';
$string['privacy:metadata:acmls_motivation_feedback:courseid'] = 'Konteks kursus tempat umpan balik motivasi dikirimkan.';
$string['privacy:metadata:acmls_motivation_feedback:sentenceid'] = 'Rekaman kalimat motivasi yang ditampilkan kepada mahasiswa, jika tersedia.';
$string['privacy:metadata:acmls_motivation_feedback:category'] = 'Kategori motivasi yang terkait dengan pesan popup.';
$string['privacy:metadata:acmls_motivation_feedback:source'] = 'Sumber pesan yang ditampilkan kepada mahasiswa (template atau Gemini).';
$string['privacy:metadata:acmls_motivation_feedback:feeling_key'] = 'Kunci label perasaan yang dipilih mahasiswa.';
$string['privacy:metadata:acmls_motivation_feedback:feeling_score'] = 'Skor numerik yang dipetakan dari perasaan yang dipilih mahasiswa.';
$string['privacy:metadata:acmls_motivation_feedback:reflection_note'] = 'Refleksi tertulis opsional dari mahasiswa setelah menerima pesan motivasi.';
$string['privacy:metadata:acmls_motivation_feedback:message_content'] = 'Isi pesan motivasi yang ditampilkan kepada mahasiswa.';
$string['privacy:metadata:acmls_motivation_feedback:timecreated'] = 'Timestamp ketika mahasiswa mengirimkan umpan balik motivasi.';
