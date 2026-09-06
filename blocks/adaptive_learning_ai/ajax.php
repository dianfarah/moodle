<?php
// Adaptive Learning AI - AJAX Handler - Direct Page Content
define('AJAX_SCRIPT', true);
require('../../config.php');
require_login();

header('Content-Type: application/json; charset=utf-8');

// Get input
$data = json_decode(file_get_contents('php://input'), true);

$courseid = isset($data['courseid']) ? intval($data['courseid']) : 0;
$question = isset($data['question']) ? trim($data['question']) : '';
$userScore = isset($data['score']) ? intval($data['score']) : 0;
$userLevel = isset($data['level']) ? $data['level'] : 'MEDIUM';

if (!$question) {
    echo json_encode(['success' => false, 'reply' => 'Pertanyaan kosong']);
    exit;
}

global $DB;

// Status based on score
$status = ($userScore >= 70) ? 'sudah_terapai' : 'belum_terapai';
$level = $userLevel;

// Detect topic
$q = strtolower($question);
$reply = '';

// Welcome message for general
if ($q === '' || $q === 'pilih topik' || strpos($q, 'pilih') !== false) {
    $reply = '<strong>🤖 Halo! Adaptive Learning AI</strong>
    <p style="margin:8px 0 0 0;color:#666;font-size:0.75rem;">
        Skor Anda: <strong>' . $userScore . '%</strong><br>
        Level: <strong>' . $level . '</strong><br>
        📌 <strong>Pilih topik dari dropdown</strong> untuk memulai!<br>
        • JavaScript Low - Materi Dasar<br>
        • JavaScript Medium - Lanjutan<br>
        • JavaScript High - Advanced
    </p>';
}
// JavaScript Low - Get from mdl_page IDs 13,14,15,16
elseif (strpos($q, 'javascript-low') !== false || $q === 'javascript-low') {
    $pageIds = [13, 14, 15, 16];
    $pages = $DB->get_records_list('page', 'id', $pageIds);
    
    $reply = '<div style="background:#f8fbff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(59,130,246,0.12);margin:8px 0;">';
    $reply .= '<div style="background:linear-gradient(135deg,#3b82f6,#60a5fa);padding:12px 16px;">';
    $reply .= '<span style="color:white;font-weight:600;font-size:0.9rem;">📚 JavaScript Low - Dasar</span>';
    $reply .= '<span style="background:rgba(255,255,255,0.7);color:#0f172a;padding:4px 10px;border-radius:12px;font-size:0.75rem;float:right;">⏱️ 10 menit</span>';
    $reply .= '</div>';
    $reply .= '<div style="padding:16px;font-size:0.8rem;line-height:1.6;color:#0f172a;max-height:280px;overflow-y:auto;">';
    
    if (!empty($pages)) {
        $i = 1;
        foreach ($pages as $p) {
            $title = strip_tags($p->title);
            $content = strip_tags($p->content);
            $reply .= '<div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid rgba(59,130,246,0.15);">';
            $reply .= '<strong style="color:#1d4ed8;font-size:0.85rem;">' . $i . '. ' . htmlspecialchars($title) . '</strong><br>';
            $reply .= '<span style="color:#334155;font-size:0.75rem;line-height:1.5;">' . htmlspecialchars(substr($content, 0, 350)) . '</span>';
            $reply .= '</div>';
            $i++;
        }
    } else {
        $reply .= '<p style="color:#b91c1c;padding:10px;background:#fef2f2;border-radius:8px;">⚠️ Materi tidak ditemukan.</p>';
    }
    
    $reply .= '<div style="background:#dbeafe;padding:10px;border-radius:8px;margin-top:10px;font-size:0.7rem;">';
    $reply .= '<strong>💡 TIPS:</strong> Praktikkan kode di atas!';
    $reply .= '</div></div>';
}
// JavaScript Medium
elseif (strpos($q, 'javascript-medium') !== false || $q === 'javascript-medium') {
    $reply = '<div style="background:#f8fbff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(59,130,246,0.12);margin:8px 0;">';
    $reply .= '<div style="background:linear-gradient(135deg,#3b82f6,#60a5fa);padding:12px 16px;">';
    $reply .= '<span style="color:white;font-weight:600;font-size:0.9rem;">📚 JavaScript Medium - Lanjutan</span>';
    $reply .= '<span style="background:rgba(255,255,255,0.7);color:#0f172a;padding:4px 10px;border-radius:12px;font-size:0.75rem;float:right;">⏱️ 15 menit</span>';
    $reply .= '</div>';
    $reply .= '<div style="padding:16px;font-size:0.8rem;line-height:1.6;color:#0f172a;">';
    
    $reply .= '<strong style="color:#1d4ed8;">1. Variable & Tipe Data</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">let nama = "Budi";\nlet usia = 20;\nconst PI = 3.14;</pre>';
    
    $reply .= '<strong style="color:#6366f1;">2. Operator</strong><br>';
    $reply .= '<span style="color:#666;">Aritmatika: + - * / %<br>Perbandingan: == === != < > <= >=<br>Logika: && || !</span><br>';
    
    $reply .= '<strong style="color:#1d4ed8;">3. Kondisi (If-Else)</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">if (usia >= 18) {\n    console.log("Dewasa");\n}</pre>';
    
    $reply .= '<strong style="color:#1d4ed8;">4. Perulangan (Loop)</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">for(let i=0; i<5; i++) { \n    console.log(i); \n}</pre>';
    
    $reply .= '<div style="background:#dbeafe;padding:10px;border-radius:8px;margin-top:10px;font-size:0.7rem;">';
    $reply .= '<strong>💪 LATIHAN:</strong> Coba buat kalkulator sederhana!';
    $reply .= '</div></div>';
}
// JavaScript High
elseif (strpos($q, 'javascript-high') !== false || $q === 'javascript-high') {
    $reply = '<div style="background:#f8fbff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(59,130,246,0.12);margin:8px 0;">';
    $reply .= '<div style="background:linear-gradient(135deg,#3b82f6,#60a5fa);padding:12px 16px;">';
    $reply .= '<span style="color:white;font-weight:600;font-size:0.9rem;">🔥 JavaScript High - Advanced</span>';
    $reply .= '<span style="background:rgba(255,255,255,0.7);color:#0f172a;padding:4px 10px;border-radius:12px;font-size:0.75rem;float:right;">⏱️ 20 menit</span>';
    $reply .= '</div>';
    $reply .= '<div style="padding:16px;font-size:0.8rem;line-height:1.6;color:#0f172a;">';
    
    $reply .= '<strong style="color:#10b981;">1. Function & Arrow Function</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">function tambah(a,b) { return a+b; }\nconst kali = (a,b) => a * b;</pre>';
    
    $reply .= '<strong style="color:#10b981;">2. Array Methods</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">arr.map(); arr.filter(); arr.reduce();</pre>';
    
    $reply .= '<strong style="color:#10b981;">3. DOM Manipulation</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">document.getElementById("id");\nelement.innerHTML = "Hello";</pre>';
    
    $reply .= '<strong style="color:#10b981;">4. Async/Await</strong><br>';
    $reply .= '<pre style="background:#eff6ff;color:#0f172a;padding:10px;border-radius:6px;overflow-x:auto;font-size:0.7rem;margin:8px 0;">async function fetchData() {\n    let res = await fetch(url);\n}</pre>';
    
    $reply .= '<div style="background:#dbeafe;padding:10px;border-radius:8px;margin-top:10px;font-size:0.7rem;">';
    $reply .= '<strong>🎯 CHALLENGE:</strong> Buat aplikasi TODO list!';
    $reply .= '</div></div>';
}
// Default fallback
else {
    $reply = '<strong>🤖 Adaptive Learning AI</strong>
    <p style="margin:8px 0 0 0;color:#666;font-size:0.75rem;">
        Skor: <strong>' . $userScore . '%</strong> | Level: <strong>' . $level . '</strong><br>
        📌 Pilih topik dari dropdown untuk melihat materi!
    </p>';
}

// Return JSON
echo json_encode([
    'success' => true,
    'reply' => $reply,
    'level' => $level,
    'status' => $status,
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
