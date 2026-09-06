<?php
defined('MOODLE_INTERNAL') || die();

class block_adaptive_learning_ai extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_adaptive_learning_ai');
    }

    public function applicable_formats() {
        return ['course-view' => true];
    }

    public function get_content() {
        global $COURSE, $CFG, $DB, $USER, $OUTPUT, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $courseid      = $COURSE->id;

        // ============================================================
        // 1. BACA NILAI QUIZ MINGGU SEBELUMNYA via Grade API
        // ============================================================
        require_once($CFG->libdir . '/gradelib.php');

        $userScore    = 0;
        $weekNum      = 1;
        $quizName     = '-';
        $lastQuizTime = null;
        $attemptCount = 0;

        try {
            // --------------------------------------------------------
            // Ambil quiz terakhir yang selesai dikerjakan siswa
            // Beserta info section-nya untuk deteksi week number
            // --------------------------------------------------------
            $sql = "SELECT qa.id, qa.sumgrades, q.grade AS maxgrade,
                           q.name AS quizname, qa.timefinish, q.id AS quizid,
                           cs.section AS secnum
                    FROM {quiz_attempts} qa
                    JOIN {quiz} q ON qa.quiz = q.id
                    JOIN {course_modules} cm
                        ON cm.instance = q.id
                        AND cm.course = q.course
                        AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                    JOIN {course_sections} cs ON cs.id = cm.section
                    WHERE q.course = :courseid
                      AND qa.userid = :userid
                      AND qa.state = 'finished'
                    ORDER BY qa.timefinish DESC
                    LIMIT 1";

            $result = $DB->get_record_sql($sql, ['courseid' => $courseid, 'userid' => $USER->id]);

            if ($result) {
                $maxgrade     = $result->maxgrade ?: 100;
                $userScore    = round(($result->sumgrades / $maxgrade) * 100);
                $quizName     = $result->quizname;
                $lastQuizTime = $result->timefinish;
                $weekNum      = intval($result->secnum); // section number = week number
            }

            // Hitung total attempts user
            $attemptCount = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {quiz_attempts} qa
                 JOIN {quiz} q ON qa.quiz = q.id
                 WHERE q.course = ? AND qa.userid = ? AND qa.state = 'finished'",
                [$courseid, $USER->id]
            );

        } catch (Exception $e) {
            $userScore = 0;
        }

        // ============================================================
        // 2. TENTUKAN LEVEL OTOMATIS
        // Score 0-69   → LOW   (Remedial)
        // Score 70-89  → MEDIUM (Standard)
        // Score 90-100 → HIGH  (Advanced)
        // ============================================================
        $level        = 'MEDIUM';
        $levelText    = 'Standard';
        $statusClass  = 'standard';
        $scoreColor   = '#f59e0b';
        $levelColor   = '#fcd34d';
        $progressColor = '#f59e0b';
        $greetingMsg  = 'Pilih topik untuk mulai belajar!';
        $nextTarget   = 90;
        $remedialNote = '';

        if ($userScore === 0) {
            $level         = 'NODATA';
            $levelText     = 'Belum Quiz';
            $statusClass   = 'nodata';
            $scoreColor    = '#94a3b8';
            $levelColor    = '#94a3b8';
            $progressColor = '#6366f1';
            $greetingMsg   = '📝 Kerjakan <b>Quiz Placement</b> terlebih dahulu untuk membuka materi Week 1!';
            $nextTarget    = 70;
        } elseif ($userScore < 70) {
            $level         = 'LOW';
            $levelText     = 'Remedial';
            $statusClass   = 'remedial';
            $scoreColor    = '#ef4444';
            $levelColor    = '#fca5a5';
            $progressColor = '#ef4444';
            $greetingMsg   = '🔴 Nilai ' . $userScore . '% → <b>Wajib kerjakan Quiz Remedial</b> terlebih dahulu sebelum lanjut ke Week ' . ($weekNum + 1) . '. Target: 70%+!';
            $nextTarget    = 70;
            $remedialNote  = '⚠️ Nilai kamu di bawah 70%. Kerjakan <b>Quiz Remedial</b> terlebih dahulu. Setelah remedial selesai, materi Week berikutnya (level Medium) akan terbuka otomatis.';
        } elseif ($userScore < 90) {
            $level         = 'MEDIUM';
            $levelText     = 'Standard';
            $statusClass   = 'standard';
            $scoreColor    = '#f59e0b';
            $levelColor    = '#fcd34d';
            $progressColor = '#f59e0b';
            $greetingMsg   = '🟡 Nilai ' . $userScore . '% → Materi &amp; Quiz <b>Medium</b> Week ' . ($weekNum + 1) . ' terbuka. Kejar 90%!';
            $nextTarget    = 90;
        } else {
            $level         = 'HIGH';
            $levelText     = 'Advanced';
            $statusClass   = 'advanced';
            $scoreColor    = '#10b981';
            $levelColor    = '#6ee7b7';
            $progressColor = '#10b981';
            $greetingMsg   = '🟢 Nilai ' . $userScore . '% → Materi &amp; Quiz <b>High</b> Week ' . ($weekNum + 1) . ' terbuka. Excellent!';
            $nextTarget    = 100;
        }

        // ============================================================
        // 3. BUKA/SEMBUNYIKAN MATERI via Availability API otomatis
        // ============================================================
        $coursecontext = context_course::instance($courseid);
        $isTeacher     = has_capability('moodle/course:manageactivities', $coursecontext);

        $availabilityUnlocked = 0;
        $availabilityLocked   = 0;

        // Jalankan adaptive availability untuk semua siswa (score=0 pun perlu kunci Week 1)
        if (!$isTeacher) {
            require_once($CFG->libdir . '/completionlib.php');
            list($availabilityUnlocked, $availabilityLocked) =
                self::apply_adaptive_availability($courseid, $USER->id, $level, $weekNum, $DB, $CFG);
        }

        // ============================================================
        // 4. GEMINI API — rekomendasi & feedback
        // ============================================================
        $geminiApiKey    = 'AIzaSyAbrc6AJ-emlxlWXfqzApien8EqkNh1fGk';
        $aiRecommendation = '';
        $aiWeakness       = '';

        if ($userScore > 0) {
            try {
                $aiRecommendation = self::get_gemini_recommendation($geminiApiKey, $userScore, $level, $quizName, $courseid);
            } catch (Exception $e) {
                $aiRecommendation = 'AI rekomendasi tidak tersedia saat ini.';
            }
        }

        // ============================================================
        // 5. PROGRESS STATS
        // ============================================================
        $progressToNext  = ($nextTarget > 0 && $nextTarget > $userScore)
            ? round(($userScore / $nextTarget) * 100)
            : 100;
        $overallProgress = min(100, $userScore);

        $quizTimeStr = $lastQuizTime
            ? date('d M Y, H:i', $lastQuizTime)
            : 'Belum ada';

        // ============================================================
        // 6. LINK REPORT
        // ============================================================
        $wwwroot          = $CFG->wwwroot;
        $studentreportlink = '';
        if (!$isTeacher) {
            $studentreportlink = '<a class="alai-report-btn" href="'
                . $wwwroot . '/blocks/adaptive_learning_ai/reports/student_report.php?courseid=' . $courseid . '">'
                . '<i class="fas fa-chart-bar"></i> Lihat Laporan Saya</a>';
        } else {
            $studentreportlink = '<a class="alai-report-btn alai-report-btn-teacher" href="'
                . $wwwroot . '/blocks/adaptive_learning_ai/reports/teacher_report.php?courseid=' . $courseid . '">'
                . '<i class="fas fa-users"></i> Dashboard Guru</a>';
        }

        // ============================================================
        // 7. BUILD HTML
        // ============================================================
        $pluginUrl = $wwwroot . '/blocks/adaptive_learning_ai';

        $this->content->text = $this->render_block_html(
            $courseid, $userScore, $level, $levelText, $statusClass,
            $scoreColor, $levelColor, $progressColor, $greetingMsg,
            $weekNum, $quizName, $quizTimeStr, $attemptCount,
            $overallProgress, $progressToNext, $nextTarget,
            $remedialNote, $aiRecommendation,
            $availabilityUnlocked, $availabilityLocked,
            $studentreportlink, $pluginUrl, $isTeacher
        );

        $this->content->footer = '';
        return $this->content;
    }

    // ================================================================
    // APPLY ADAPTIVE AVAILABILITY — Unlock/Lock berdasarkan NAMA modul
    //
    // STRUKTUR KURSUS YANG DIDUKUNG:
    //   Section 0: Penganalan / Placement Quiz
    //   Section 1: Remidial  ← untuk nilai < 70 dari section 0
    //   Section 2: Minggu 1  ← dibuka jika section 0 ≥ 70, atau section 1 selesai
    //   Section 3: Remidial  ← untuk nilai < 70 dari section 2 (Minggu 1)
    //   Section 4: Minggu 2  ← dibuka jika section 2 ≥ 70, atau section 3 selesai
    //   ...dst
    //
    // LOGIKA PER SECTION:
    //   Jika section adalah REMIDIAL:
    //     → Buka jika section non-remidial TEPAT sebelumnya nilai < 70
    //     → Kunci jika nilai ≥ 70 (tidak perlu remedial)
    //
    //   Jika section adalah MINGGU BIASA:
    //     → Lihat nilai section non-remidial sebelumnya (prevMinggu)
    //     → Jika nilai ≥ 90  : buka level HIGH langsung
    //     → Jika nilai 70-89 : buka level MEDIUM langsung
    //     → Jika nilai < 70  : cek apakah section Remidial antara prevMinggu
    //                          dan section ini sudah dikerjakan
    //                          → Sudah: buka MEDIUM
    //                          → Belum: kunci semua
    //     → Jika belum ada nilai sama sekali: kunci semua
    // ================================================================
    private static function apply_adaptive_availability($courseid, $userid, $level, $weekNum, $DB, $CFG) {
        $unlocked = 0;
        $locked   = 0;

        try {
            // ────────────────────────────────────────────────────────
            // STEP 1: Ambil semua section beserta namanya
            // ────────────────────────────────────────────────────────
            $allSections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');

            // ────────────────────────────────────────────────────────
            // STEP 2: Klasifikasikan setiap section:
            //   'remedial' → namanya mengandung remidial/remedial
            //   'minggu'   → section konten biasa (Week/Minggu)
            //   'placement'→ section 0
            // ────────────────────────────────────────────────────────
            $sectionTypes = []; // secNum => 'placement'|'remedial'|'minggu'
            foreach ($allSections as $sec) {
                $num  = intval($sec->section);
                $name = strtolower(trim($sec->name ?? ''));
                if ($num === 0) {
                    $sectionTypes[$num] = 'placement';
                } elseif (strpos($name, 'remidial') !== false || strpos($name, 'remedial') !== false) {
                    $sectionTypes[$num] = 'remedial';
                } else {
                    $sectionTypes[$num] = 'minggu';
                }
            }

            // ────────────────────────────────────────────────────────
            // STEP 3: Bangun score map hanya untuk section NON-remedial
            // (quiz Low/Medium/High hasilnya masuk ke score section minggu itu)
            // ────────────────────────────────────────────────────────
            $sectionScoreMap = self::build_section_score_map($courseid, $userid, $DB);

            // ────────────────────────────────────────────────────────
            // STEP 4: Buat mapping: setiap section Remidial → secNum
            // section Minggu mana yang jadi "sumber nilainya"
            // (yaitu section Minggu non-remedial tepat sebelumnya)
            //
            // Contoh urutan: 0(P), 1(R), 2(M1), 3(R), 4(M2), 5(R), 6(M3)
            //   Section 1 Remidial ← sumber: section 0 (Placement)
            //   Section 3 Remidial ← sumber: section 2 (Minggu 1)
            //   Section 5 Remidial ← sumber: section 4 (Minggu 2)
            // ────────────────────────────────────────────────────────
            $remedialSourceMap = []; // remedialSecNum => sourceMingguSecNum
            $secNums = array_keys($sectionTypes);
            sort($secNums);

            foreach ($secNums as $sn) {
                if ($sectionTypes[$sn] !== 'remedial') continue;
                // Cari section non-remedial tepat sebelum section ini
                for ($prev = $sn - 1; $prev >= 0; $prev--) {
                    if (isset($sectionTypes[$prev]) && $sectionTypes[$prev] !== 'remedial') {
                        $remedialSourceMap[$sn] = $prev;
                        break;
                    }
                }
            }

            // ────────────────────────────────────────────────────────
            // STEP 5: Untuk setiap section Minggu, cari Remidial
            // yang ada di antara section sebelumnya dan section ini
            // ────────────────────────────────────────────────────────
            // mingguSecNum => remedialSecNum (atau null kalau tidak ada)
            $mingguToRemedial = [];
            foreach ($secNums as $sn) {
                if ($sectionTypes[$sn] !== 'minggu' && $sectionTypes[$sn] !== 'placement') continue;
                // Cari section Remidial yang sumbernya adalah section ini
                foreach ($remedialSourceMap as $rSec => $srcSec) {
                    if ($srcSec === $sn) {
                        $mingguToRemedial[$sn] = $rSec;
                        break;
                    }
                }
            }

            // ────────────────────────────────────────────────────────
            // STEP 6: Proses tiap section
            // ────────────────────────────────────────────────────────
            foreach ($allSections as $section) {
                $secNum  = intval($section->section);
                $secType = $sectionTypes[$secNum] ?? 'minggu';

                // Section 0 = Placement: selalu terbuka, skip
                if ($secType === 'placement') continue;

                if (empty($section->sequence)) continue;
                $cmIds = array_filter(array_map('intval', explode(',', $section->sequence)));

                // ── SECTION REMIDIAL ──────────────────────────────
                if ($secType === 'remedial') {
                    $sourceSec   = $remedialSourceMap[$secNum] ?? null;
                    $sourceScore = ($sourceSec !== null) ? ($sectionScoreMap[$sourceSec] ?? null) : null;

                    // Buka Remidial jika nilai section sumber < 70
                    // Kunci jika nilai ≥ 70 (tidak perlu remedial) atau belum ada nilai
                    $remedialNeeded = ($sourceScore !== null && $sourceScore < 70);

                    foreach ($cmIds as $cmId) {
                        if ($cmId <= 0) continue;
                        $visible = $remedialNeeded ? 1 : 0;
                        $DB->set_field('course_modules', 'visible', $visible, ['id' => $cmId]);
                        $visible ? $unlocked++ : $locked++;
                    }
                    continue;
                }

                // ── SECTION MINGGU BIASA ──────────────────────────
                // Cari section non-remedial tepat sebelum section ini
                $prevMingguSec = null;
                for ($prev = $secNum - 1; $prev >= 0; $prev--) {
                    if (isset($sectionTypes[$prev]) && $sectionTypes[$prev] !== 'remedial') {
                        $prevMingguSec = $prev;
                        break;
                    }
                }

                $prevScore      = ($prevMingguSec !== null) ? ($sectionScoreMap[$prevMingguSec] ?? null) : null;
                $effectiveLevel = null;

                if ($prevScore !== null) {
                    if ($prevScore >= 90) {
                        // ≥ 90 → langsung HIGH, tidak perlu remedial
                        $effectiveLevel = 'HIGH';

                    } elseif ($prevScore >= 70) {
                        // 70-89 → langsung MEDIUM, tidak perlu remedial
                        $effectiveLevel = 'MEDIUM';

                    } else {
                        // < 70 → cek apakah sudah kerjakan Remidial antara
                        // section prevMinggu dan section ini
                        $remedialBetween = $mingguToRemedial[$prevMingguSec] ?? null;
                        $remedialScore   = ($remedialBetween !== null)
                            ? ($sectionScoreMap[$remedialBetween] ?? null)
                            : null;

                        if ($remedialScore !== null) {
                            // Sudah kerjakan Remidial → level ditentukan dari NILAI REMIDIAL
                            if ($remedialScore >= 90) {
                                $effectiveLevel = 'HIGH';   // Nilai Remidial ≥ 90 → HIGH
                            } elseif ($remedialScore >= 70) {
                                $effectiveLevel = 'MEDIUM'; // Nilai Remidial 70-89 → MEDIUM
                            } else {
                                $effectiveLevel = 'LOW';    // Nilai Remidial < 70 → LOW
                            }
                        } else {
                            // Belum kerjakan Remidial → kunci section ini
                            $effectiveLevel = null;
                        }
                    }
                }
                // $prevScore === null → section pertama belum ada nilai → kunci

                // ── Buka/kunci modul dalam section ini ───────────
                foreach ($cmIds as $cmId) {
                    if ($cmId <= 0) continue;

                    $cm = $DB->get_record('course_modules', ['id' => $cmId], 'id,module,instance,visible');
                    if (!$cm) continue;

                    $modType  = $DB->get_field('modules', 'name', ['id' => $cm->module]);
                    $modTitle = self::get_module_title($modType, $cm->instance, $DB);
                    $modTag   = self::detect_level_tag($modTitle);

                    if ($effectiveLevel === null) {
                        // Section belum boleh dibuka → kunci semua modul berlevel
                        if ($modTag !== null) {
                            $DB->set_field('course_modules', 'visible', 0, ['id' => $cmId]);
                            $locked++;
                        }
                        // Modul tanpa tag (umum) → biarkan terbuka
                    } else {
                        if ($modTag === null) {
                            // Modul umum → selalu buka
                            $DB->set_field('course_modules', 'visible', 1, ['id' => $cmId]);
                            $unlocked++;
                        } elseif ($modTag === $effectiveLevel) {
                            // Level cocok → BUKA
                            $DB->set_field('course_modules', 'visible', 1, ['id' => $cmId]);
                            $unlocked++;
                        } else {
                            // Level tidak cocok → KUNCI
                            $DB->set_field('course_modules', 'visible', 0, ['id' => $cmId]);
                            $locked++;
                        }
                    }
                }
            }

            // Rebuild cache agar perubahan visible langsung terlihat
            rebuild_course_cache($courseid, true);

        } catch (Exception $e) {
            // Silent fail — jangan crash halaman
        }

        return [$unlocked, $locked];
    }

    // ================================================================
    // BUILD SECTION SCORE MAP
    // Kembalikan array: [section_number => best_score%]
    // Berdasarkan nilai quiz terbaik (tertinggi) yang selesai per section
    // Mencakup semua quiz di section (Low, Medium, High, Remedial)
    // ================================================================
    private static function build_section_score_map($courseid, $userid, $DB) {
        $map = [];

        $sql = "SELECT q.id AS quizid, q.name AS quizname,
                       cs.section AS secnum,
                       qa.sumgrades, q.grade AS maxgrade,
                       qa.timefinish
                FROM {quiz} q
                JOIN {course_modules} cm ON cm.instance = q.id
                    AND cm.module = (SELECT id FROM {modules} WHERE name = 'quiz')
                    AND cm.course = :courseid
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {quiz_attempts} qa ON qa.quiz = q.id
                    AND qa.userid = :userid
                    AND qa.state = 'finished'
                ORDER BY cs.section ASC, qa.timefinish DESC";

        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        foreach ($rows as $r) {
            $secNum = intval($r->secnum);
            $max    = $r->maxgrade ?: 100;
            $score  = round(($r->sumgrades / $max) * 100);

            // Simpan nilai TERTINGGI per section (siswa mungkin retake)
            if (!isset($map[$secNum]) || $score > $map[$secNum]) {
                $map[$secNum] = $score;
            }
        }

        return $map;
    }

    // ================================================================
    // BUILD SECTION LEVEL MAP (wrapper — dipakai komponen lain)
    // Kembalikan array: [section_number => 'LOW'|'MEDIUM'|'HIGH']
    // ================================================================
    private static function build_section_level_map($courseid, $userid, $DB) {
        $scoreMap = self::build_section_score_map($courseid, $userid, $DB);
        $levelMap = [];
        foreach ($scoreMap as $secNum => $score) {
            if ($score < 70)     $levelMap[$secNum] = 'LOW';
            elseif ($score < 90) $levelMap[$secNum] = 'MEDIUM';
            else                 $levelMap[$secNum] = 'HIGH';
        }
        return $levelMap;
    }

    // ================================================================
    // GET MODULE TITLE — ambil nama/judul modul dari tabelnya
    // ================================================================
    private static function get_module_title($modType, $instanceId, $DB) {
        $titleFields = [
            'quiz'     => 'name',
            'page'     => 'name',
            'resource' => 'name',
            'url'      => 'name',
            'folder'   => 'name',
            'label'    => 'name',
            'assign'   => 'name',
            'forum'    => 'name',
            'scorm'    => 'name',
            'hvp'      => 'name',
            'h5pactivity' => 'name',
        ];

        $field = $titleFields[$modType] ?? 'name';
        try {
            return $DB->get_field($modType, $field, ['id' => $instanceId]) ?: '';
        } catch (Exception $e) {
            return '';
        }
    }

    // ================================================================
    // DETECT LEVEL TAG — deteksi kata Low/Medium/High/Remedial dalam nama modul
    // Return: 'LOW' | 'MEDIUM' | 'HIGH' | 'REMEDIAL' | null (modul umum)
    // ================================================================
    private static function detect_level_tag($title) {
        if (empty($title)) return null;

        $t = strtolower($title);

        // Cek HIGH dulu (sebelum cek "medium" agar tidak overlap)
        if (preg_match('/\bhigh\b/', $t))     return 'HIGH';
        if (preg_match('/\bmedium\b/', $t))   return 'MEDIUM';
        if (preg_match('/\blow\b/', $t))      return 'LOW';

        // Remedial / Remidial (typo umum)
        if (strpos($t, 'remedial') !== false) return 'REMEDIAL';
        if (strpos($t, 'remidial') !== false) return 'REMEDIAL';

        // Alias tambahan
        if (strpos($t, 'advanced') !== false) return 'HIGH';
        if (strpos($t, 'standard') !== false) return 'MEDIUM';

        return null; // modul umum, tidak berlevel
    }

    // ================================================================
    // GEMINI API — Get AI Recommendation
    // ================================================================
    private static function get_gemini_recommendation($apiKey, $score, $level, $quizName, $courseid) {
        $prompt = "Kamu adalah AI tutor adaptif di Moodle. Seorang siswa mendapat nilai quiz '$quizName' sebesar {$score}% dengan level {$level}.\n";

        if ($level === 'LOW') {
            $prompt .= "Siswa perlu remedial. Berikan: 1) Penyebab nilai rendah, 2) 3 tips belajar spesifik, 3) Motivasi singkat. ";
        } elseif ($level === 'MEDIUM') {
            $prompt .= "Siswa di level menengah. Berikan: 1) Topik yang perlu diperkuat, 2) 2 strategi naik ke level High, 3) Tantangan belajar. ";
        } else {
            $prompt .= "Siswa di level advanced! Berikan: 1) Pujian spesifik, 2) Tantangan lebih lanjut, 3) Proyek pengayaan. ";
        }

        $prompt .= "Jawab dalam 3-4 kalimat singkat, bahasa Indonesia, format HTML dengan <b> untuk penekanan.";

        $url  = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
        $data = [
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => ["maxOutputTokens" => 200, "temperature" => 0.7]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                return $result['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        return '';
    }

    // ================================================================
    // RENDER BLOCK HTML
    // ================================================================
    private function render_block_html(
        $courseid, $userScore, $level, $levelText, $statusClass,
        $scoreColor, $levelColor, $progressColor, $greetingMsg,
        $weekNum, $quizName, $quizTimeStr, $attemptCount,
        $overallProgress, $progressToNext, $nextTarget,
        $remedialNote, $aiRecommendation,
        $availabilityUnlocked, $availabilityLocked,
        $studentreportlink, $pluginUrl, $isTeacher
    ) {
        global $CFG;

        $levelIcon = [
            'LOW'    => '🔴',
            'MEDIUM' => '🟡',
            'HIGH'   => '🟢',
            'nodata' => '⚪',
        ][$level] ?? '⚪';

        $levelBadgeColor = [
            'remedial' => 'background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.4)',
            'standard' => 'background:rgba(245,158,11,0.15);color:#fcd34d;border:1px solid rgba(245,158,11,0.4)',
            'advanced' => 'background:rgba(16,185,129,0.15);color:#6ee7b7;border:1px solid rgba(16,185,129,0.4)',
            'nodata'   => 'background:rgba(148,163,184,0.15);color:#94a3b8;border:1px solid rgba(148,163,184,0.4)',
        ][$statusClass] ?? '';

        $aiHtml = '';
        if ($aiRecommendation) {
            $aiHtml = '<div class="alai-ai-card">
                <div class="alai-ai-header"><i class="fas fa-robot"></i> Rekomendasi AI Gemini</div>
                <div class="alai-ai-body">' . nl2br($aiRecommendation) . '</div>
            </div>';
        }

        $remedialHtml = '';
        if ($remedialNote) {
            $remedialHtml = '<div class="alai-remedial-note">
                <i class="fas fa-exclamation-circle"></i> ' . $remedialNote . '
            </div>';
        }

        $availHtml = '';
        if ($availabilityUnlocked > 0 || $availabilityLocked > 0) {
            $availHtml = '<div class="alai-avail-info">
                <span><i class="fas fa-unlock-alt" style="color:#10b981"></i> ' . $availabilityUnlocked . ' materi dibuka</span>
                <span><i class="fas fa-lock" style="color:#ef4444"></i> ' . $availabilityLocked . ' dikunci</span>
            </div>';
        }

        $wwwroot = $CFG->wwwroot;

        return '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
/* ===== ALAI PREMIUM v2 ===== */
.alai-wrap *{box-sizing:border-box;font-family:"Inter",sans-serif}
.alai-wrap{
    background:linear-gradient(160deg,#0f172a 0%,#1e3a5f 50%,#0f2044 100%);
    border-radius:20px;overflow:hidden;
    border:1px solid rgba(99,160,255,.18);
    box-shadow:0 20px 60px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.04);
    position:relative;
}
.alai-wrap::before{
    content:"";position:absolute;inset:0;
    background:radial-gradient(ellipse 80% 50% at 50% -10%,rgba(96,165,250,.18),transparent);
    pointer-events:none;z-index:0;
}

/* HEADER */
.alai-header{
    position:relative;z-index:1;
    background:linear-gradient(135deg,rgba(59,130,246,.25) 0%,rgba(96,165,250,.12) 100%);
    border-bottom:1px solid rgba(99,160,255,.15);
    padding:18px 18px 14px;
    backdrop-filter:blur(20px);
}
.alai-brand{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.alai-logo{
    width:46px;height:46px;flex-shrink:0;
    background:linear-gradient(135deg,#3b82f6,#60a5fa);
    border-radius:14px;display:flex;align-items:center;justify-content:center;
    font-size:.7rem;font-weight:900;color:#fff;letter-spacing:.05em;
    box-shadow:0 6px 20px rgba(59,130,246,.4);
}
.alai-brand-text h3{
    margin:0;font-size:1rem;font-weight:800;
    background:linear-gradient(90deg,#fff,#93c5fd);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    line-height:1.2;
}
.alai-brand-text p{margin:2px 0 0;font-size:.62rem;color:rgba(255,255,255,.5);letter-spacing:.5px;text-transform:uppercase}

/* METRICS GRID */
.alai-metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
.alai-metric{
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.08);
    border-radius:14px;padding:12px 14px;
    transition:transform .2s,box-shadow .2s;text-align:center;
}
.alai-metric:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.3)}
.alai-metric-val{font-size:1.5rem;font-weight:900;line-height:1;margin-bottom:4px}
.alai-metric-lbl{font-size:.58rem;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.8px;font-weight:600}
.alai-metric-remedial .alai-metric-val{color:#f87171}
.alai-metric-standard .alai-metric-val{color:#fbbf24}
.alai-metric-advanced .alai-metric-val{color:#34d399}
.alai-metric-nodata   .alai-metric-val{color:#94a3b8}

/* PROGRESS BAR */
.alai-progress-wrap{margin-bottom:8px}
.alai-progress-labels{display:flex;justify-content:space-between;font-size:.6rem;color:rgba(255,255,255,.4);margin-bottom:4px}
.alai-progress-track{
    height:7px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden;
    border:1px solid rgba(255,255,255,.05);
}
.alai-progress-bar{
    height:100%;border-radius:4px;
    transition:width 1.2s cubic-bezier(.4,0,.2,1);
}
.alai-progress-remedial .alai-progress-bar{background:linear-gradient(90deg,#ef4444,#f87171)}
.alai-progress-standard .alai-progress-bar{background:linear-gradient(90deg,#f59e0b,#fbbf24)}
.alai-progress-advanced .alai-progress-bar{background:linear-gradient(90deg,#10b981,#34d399)}
.alai-progress-nodata   .alai-progress-bar{background:linear-gradient(90deg,#6366f1,#818cf8)}

/* LEVEL BADGE */
.alai-level-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:5px 12px;border-radius:20px;font-size:.72rem;font-weight:700;
}

/* GREETING */
.alai-greeting{
    font-size:.78rem;color:rgba(255,255,255,.8);
    margin-top:8px;line-height:1.5;
    background:rgba(255,255,255,.04);border-radius:10px;padding:8px 12px;
    border-left:3px solid rgba(99,160,255,.5);
}

/* STATS ROW */
.alai-stats-row{
    display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;
}
.alai-stat-chip{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 10px;border-radius:8px;font-size:.65rem;
    background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);
    color:rgba(255,255,255,.65);
}
.alai-stat-chip i{font-size:.6rem;color:#60a5fa}

/* BODY */
.alai-body{
    position:relative;z-index:1;padding:16px;
}

/* AI RECOMMENDATION CARD */
.alai-ai-card{
    background:linear-gradient(135deg,rgba(59,130,246,.12),rgba(99,102,241,.08));
    border:1px solid rgba(99,160,255,.2);
    border-radius:14px;padding:14px;margin-bottom:12px;
}
.alai-ai-header{
    font-size:.72rem;font-weight:700;color:#93c5fd;
    margin-bottom:8px;display:flex;align-items:center;gap:6px;
}
.alai-ai-body{
    font-size:.78rem;color:rgba(255,255,255,.75);line-height:1.65;
}
.alai-ai-body b{color:#93c5fd}

/* REMEDIAL NOTE */
.alai-remedial-note{
    background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);
    border-radius:10px;padding:10px 12px;margin-bottom:12px;
    font-size:.72rem;color:#fca5a5;line-height:1.5;
}

/* AVAILABILITY INFO */
.alai-avail-info{
    display:flex;gap:12px;margin-bottom:12px;
    padding:8px 12px;background:rgba(255,255,255,.04);border-radius:10px;
    border:1px solid rgba(255,255,255,.07);
}
.alai-avail-info span{font-size:.65rem;color:rgba(255,255,255,.6);display:flex;align-items:center;gap:5px}

/* TOPICS SELECT */
.alai-topic-select{
    width:100%;padding:11px 14px;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(99,160,255,.2);
    border-radius:12px;font-size:.8rem;font-weight:600;
    color:#e2e8f0;margin-bottom:10px;cursor:pointer;
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 20 20\'%3E%3Cpath stroke=\'%2360a5fa\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'m6 8 4 4 4-4\'/%3E%3C/svg%3E");
    background-position:right 12px center;background-repeat:no-repeat;background-size:16px;
    transition:border-color .2s,box-shadow .2s;
}
.alai-topic-select:focus{outline:none;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.15)}
.alai-topic-select option{background:#1e3a5f;color:#e2e8f0}

/* CHAT */
.alai-chat{
    background:rgba(255,255,255,.04);border:1px solid rgba(99,160,255,.1);
    border-radius:14px;padding:14px;min-height:260px;max-height:340px;
    overflow-y:auto;margin-bottom:12px;
}
.alai-chat::-webkit-scrollbar{width:4px}
.alai-chat::-webkit-scrollbar-thumb{background:rgba(96,165,250,.3);border-radius:2px}

/* MESSAGES */
.alai-msg{
    padding:12px 14px;border-radius:14px;font-size:.78rem;
    line-height:1.65;margin-bottom:10px;
    animation:msgIn .35s cubic-bezier(.25,.46,.45,.94);
    word-wrap:break-word;
}
@keyframes msgIn{from{opacity:0;transform:translateY(12px) scale(.97)}to{opacity:1;transform:none}}
.alai-msg-user{
    background:linear-gradient(135deg,#3b82f6,#6366f1);
    color:#fff;margin-left:auto;max-width:88%;
    border:1px solid rgba(99,160,255,.3);
}
.alai-msg-ai{
    background:rgba(255,255,255,.06);color:rgba(255,255,255,.85);
    border:1px solid rgba(255,255,255,.08);
}
.alai-msg-ai strong{color:#93c5fd}
.alai-msg-loading{
    display:flex;gap:5px;padding:14px;align-items:center;
    background:rgba(255,255,255,.06);border-radius:14px;margin-bottom:10px;
}
.alai-dot{
    width:7px;height:7px;background:#60a5fa;border-radius:50%;
    animation:bounce .8s infinite;
}
.alai-dot:nth-child(2){animation-delay:.15s}
.alai-dot:nth-child(3){animation-delay:.3s}
@keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-8px)}}

/* CODE BLOCK */
.alai-code{
    background:rgba(15,23,42,.8);border:1px solid rgba(99,160,255,.15);
    border-radius:10px;padding:12px;font-family:"Fira Code","Monaco",monospace;
    font-size:.72rem;overflow-x:auto;margin:8px 0;
    color:#e2e8f0;line-height:1.7;
}

/* TIP */
.alai-tip{
    background:rgba(96,165,250,.1);border-left:3px solid #60a5fa;
    border-radius:0 8px 8px 0;padding:8px 12px;
    margin-top:10px;font-size:.72rem;color:rgba(255,255,255,.75);
}

/* INPUT AREA */
.alai-input-row{display:flex;gap:8px}
.alai-input{
    flex:1;padding:12px 16px;
    background:rgba(255,255,255,.07);
    border:1px solid rgba(99,160,255,.2);
    border-radius:12px;font-size:.8rem;
    color:#e2e8f0;transition:border-color .2s,box-shadow .2s;
}
.alai-input::placeholder{color:rgba(255,255,255,.3)}
.alai-input:focus{outline:none;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.12)}
.alai-send{
    background:linear-gradient(135deg,#3b82f6,#6366f1);
    color:#fff;border:none;padding:12px 18px;border-radius:12px;
    font-size:.8rem;font-weight:700;cursor:pointer;
    transition:transform .2s,box-shadow .2s;
    display:flex;align-items:center;gap:6px;
    box-shadow:0 4px 14px rgba(59,130,246,.35);
}
.alai-send:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(59,130,246,.45)}

/* REPORT BTN */
.alai-footer{
    position:relative;z-index:1;
    padding:0 16px 16px;
}
.alai-report-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;
    width:100%;padding:11px;border-radius:12px;
    background:rgba(59,130,246,.15);border:1px solid rgba(59,130,246,.3);
    color:#93c5fd;text-decoration:none;font-size:.8rem;font-weight:600;
    transition:all .2s;
}
.alai-report-btn:hover{background:rgba(59,130,246,.25);color:#bfdbfe;text-decoration:none}
.alai-report-btn-teacher{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.3);color:#6ee7b7}
.alai-report-btn-teacher:hover{background:rgba(16,185,129,.22)}

/* ADAPTIVE NOTICE */
.alai-adaptive-notice{
    background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);
    border-radius:10px;padding:10px 12px;margin-bottom:12px;
    font-size:.68rem;color:rgba(255,255,255,.6);line-height:1.6;
    display:flex;gap:8px;align-items:flex-start;
}
.alai-adaptive-notice i{color:#818cf8;margin-top:1px;flex-shrink:0}

/* WEEK BADGE */
.alai-week-badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;border-radius:8px;font-size:.62rem;font-weight:700;
    background:rgba(99,102,241,.15);color:#a5b4fc;border:1px solid rgba(99,102,241,.25);
}

@media(max-width:480px){
    .alai-metrics{grid-template-columns:1fr}
    .alai-stats-row .alai-stat-chip:nth-child(n+3){display:none}
}
</style>

<div class="alai-wrap ' . $statusClass . '" id="alaiBlock_' . $courseid . '">

    <!-- HEADER -->
    <div class="alai-header">
        <div class="alai-brand">
            <div class="alai-logo">AI</div>
            <div class="alai-brand-text">
                <h3>Adaptive Learning AI</h3>
                <p>Smart · Adaptive · Gemini Powered</p>
            </div>
            <div style="margin-left:auto">
                <span class="alai-week-badge"><i class="fas fa-calendar-week"></i> Week ' . $weekNum . '</span>
            </div>
        </div>

        <!-- METRICS -->
        <div class="alai-metrics">
            <div class="alai-metric alai-metric-' . $statusClass . '">
                <div class="alai-metric-val">' . $userScore . '%</div>
                <div class="alai-metric-lbl">Quiz Score</div>
            </div>
            <div class="alai-metric alai-metric-' . $statusClass . '">
                <div class="alai-metric-val" style="font-size:1.1rem">' . $levelIcon . ' ' . $levelText . '</div>
                <div class="alai-metric-lbl">Level Saat Ini</div>
            </div>
        </div>

        <!-- PROGRESS BAR -->
        <div class="alai-progress-wrap">
            <div class="alai-progress-labels">
                <span>Progress ke ' . $nextTarget . '%</span>
                <span>' . $overallProgress . '%</span>
            </div>
            <div class="alai-progress-track alai-progress-' . $statusClass . '">
                <div class="alai-progress-bar" id="alaiProgressBar" style="width:' . $overallProgress . '%"></div>
            </div>
        </div>

        <!-- GREETING -->
        <div class="alai-greeting">' . $greetingMsg . '</div>
    </div>

    <!-- BODY -->
    <div class="alai-body">

        <!-- STATS CHIPS -->
        <div class="alai-stats-row">
            <span class="alai-stat-chip"><i class="fas fa-clock"></i> ' . $quizTimeStr . '</span>
            <span class="alai-stat-chip"><i class="fas fa-redo"></i> ' . $attemptCount . ' attempt</span>
            <span class="alai-stat-chip"><i class="fas fa-file-alt"></i> ' . htmlspecialchars(mb_substr($quizName, 0, 22)) . '</span>
        </div>

        <!-- ADAPTIVE AVAILABILITY INFO -->
        ' . ($availHtml ?: '<div class="alai-adaptive-notice">
            <i class="fas fa-magic"></i>
            <span>Sistem Adaptive membuka materi otomatis sesuai nilai quiz Anda. Selesaikan quiz untuk mengaktifkan.</span>
        </div>') . '

        <!-- AI RECOMMENDATION -->
        ' . $aiHtml . '

        <!-- REMEDIAL NOTE -->
        ' . $remedialHtml . '

        <!-- TOPIC SELECT -->
        <select class="alai-topic-select" id="alaiTopicSelect_' . $courseid . '" onchange="alaiSendTopic_' . $courseid . '(this.value)">
            <option value="">🎯 Pilih Topik Microlearning...</option>
            <optgroup label="📚 Level LOW (Remedial)">
                <option value="variable">📦 Variable &amp; Tipe Data</option>
                <option value="html">🌐 Dasar HTML</option>
                <option value="css">🎨 Dasar CSS</option>
            </optgroup>
            <optgroup label="⚡ Level MEDIUM (Standard)">
                <option value="if-else">🔀 Kondisi If-Else</option>
                <option value="loop">🔁 Loop &amp; Perulangan</option>
                <option value="array">📋 Array</option>
            </optgroup>
            <optgroup label="🚀 Level HIGH (Advanced)">
                <option value="function">⚙️ Function &amp; Arrow</option>
                <option value="object">🧩 Object &amp; OOP</option>
                <option value="async">⏳ Async/Await &amp; Fetch</option>
            </optgroup>
        </select>

        <!-- CHAT AREA -->
        <div class="alai-chat" id="alaiChat_' . $courseid . '">
            <div class="alai-msg alai-msg-ai">
                <strong><i class="fas fa-robot"></i> Adaptive Learning AI</strong>
                <div style="margin-top:6px;font-size:.8rem;">
                    Level: <span style="color:' . $levelColor . ';font-weight:700;">' . $levelText . '</span> |
                    Score: <span style="color:' . $scoreColor . ';font-weight:700;">' . $userScore . '%</span>
                </div>
                <div style="margin-top:6px;color:rgba(255,255,255,.65);font-size:.77rem;">' . $greetingMsg . '</div>
            </div>
        </div>

        <!-- INPUT -->
        <div class="alai-input-row">
            <input type="text" class="alai-input" id="alaiInput_' . $courseid . '"
                placeholder="Tanya AI atau pilih topik..."
                onkeypress="if(event.key===\'Enter\') alaiSendTopic_' . $courseid . '(this.value)">
            <button class="alai-send" onclick="alaiSendTopic_' . $courseid . '(document.getElementById(\'alaiInput_' . $courseid . '\').value)">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="alai-footer">' . $studentreportlink . '</div>
</div>

<script>
(function() {
    var CID      = ' . $courseid . ';
    var SCORE    = ' . $userScore . ';
    var LEVEL    = "' . $level . '";
    var SCLASS   = "' . $statusClass . '";
    var PLUGINURL = "' . $pluginUrl . '";
    var WWWROOT  = "' . $CFG->wwwroot . '";

    // Auto-load rekomendasi topik sesuai level setelah 800ms
    document.addEventListener("DOMContentLoaded", function() {
        if (SCORE > 0) {
            setTimeout(function() {
                var autoTopic = LEVEL === "LOW" ? "variable" : LEVEL === "HIGH" ? "function" : "if-else";
                alaiSendTopic_CID(autoTopic, true);
            }, 800);
        }
    });

    window["alaiSendTopic_" + CID] = function(topic, isAuto) {
        if (!topic) return;
        var chat  = document.getElementById("alaiChat_" + CID);
        var input = document.getElementById("alaiInput_" + CID);

        if (!isAuto) {
            var userMsg       = document.createElement("div");
            userMsg.className = "alai-msg alai-msg-user";
            userMsg.innerHTML = "<i class=\"fas fa-user\"></i> " + (input.value || topic);
            chat.appendChild(userMsg);
            input.value = "";
        }

        // Loading dots
        var loader       = document.createElement("div");
        loader.className = "alai-msg-loading";
        loader.innerHTML = "<div class=\"alai-dot\"></div><div class=\"alai-dot\"></div><div class=\"alai-dot\"></div>";
        chat.appendChild(loader);
        chat.scrollTop = chat.scrollHeight;

        // AJAX ke ajax_microlearning.php
        fetch(PLUGINURL + "/ajax_microlearning.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                courseid: CID,
                message: topic,
                score: SCORE,
                level: LEVEL
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            loader.remove();
            var aiMsg       = document.createElement("div");
            aiMsg.className = "alai-msg alai-msg-ai";
            aiMsg.innerHTML = d.success ? formatAlaiResponse(d.reply) : "<span style=\"color:#f87171\">Error: " + (d.reply||"Unknown") + "</span>";
            chat.appendChild(aiMsg);
            chat.scrollTop = chat.scrollHeight;
        })
        .catch(function(e) {
            loader.remove();
            var errMsg       = document.createElement("div");
            errMsg.className = "alai-msg alai-msg-ai";
            errMsg.innerHTML = "<span style=\"color:#f87171\"><i class=\"fas fa-exclamation-triangle\"></i> Gagal memuat. Coba lagi.</span>";
            chat.appendChild(errMsg);
        });
    };

    // Replace function declaration to use CID variable
    var fnKey = "alaiSendTopic_" + CID;

    function formatAlaiResponse(text) {
        // Format pre/code blocks
        text = text.replace(/```([\\s\\S]*?)```/g, \'<div class="alai-code">$1</div>\');
        // Bold
        text = text.replace(/\\*\\*([^*]+)\\*\\*/g, \'<strong>$1</strong>\');
        // Newlines
        text = text.replace(/\\n/g, \'<br>\');
        return text;
    }
})();
</script>
';
    }
}