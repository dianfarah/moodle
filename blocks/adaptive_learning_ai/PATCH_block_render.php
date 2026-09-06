<?php
// =============================================================
// PATCH: Tambahkan ini ke render_block_html() di
// block_adaptive_learning_ai.php
//
// CARA PAKAI:
// 1. Ganti bagian <select class="alai-topic-select"> dengan
//    kode di bawah (sudah include tombol "Lihat Materi Week Saya")
// 2. Ganti bagian <script>(function() {...})(); dengan
//    versi baru di bawah
// =============================================================

// ============================================================
// [A] GANTI BAGIAN SELECT DROPDOWN — tambah opsi materi Moodle
// ============================================================

/*
GANTI INI:
    <select class="alai-topic-select" id="alaiTopicSelect_...">
        <option value="">🎯 Pilih Topik Microlearning...</option>
        ...
    </select>

DENGAN INI (copy paste ke render_block_html):
*/

$select_html = '
<!-- TOMBOL LIHAT MATERI MOODLE SESUAI WEEK & LEVEL -->
<button class="alai-material-btn alai-material-btn-' . $statusClass . '"
    onclick="alaiLoadMaterials_' . $courseid . '()"
    id="alaiMatBtn_' . $courseid . '">
    <i class="fas fa-book-open"></i>
    Lihat Materi Week ' . ($weekNum + 1) . ' — ' . $levelText . '
    <span class="alai-material-badge">' . $levelIcon . '</span>
</button>

<!-- DROPDOWN TOPIK MICROLEARNING -->
<select class="alai-topic-select" id="alaiTopicSelect_' . $courseid . '"
    onchange="alaiSendTopic_' . $courseid . '(this.value)">
    <option value="">🎯 Atau pilih topik microlearning AI...</option>
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
';

// ============================================================
// [B] CSS TAMBAHAN — tambahkan ke dalam tag <style> yang sudah ada
// ============================================================

$extra_css = '
/* TOMBOL MATERI MOODLE */
.alai-material-btn {
    display:flex;align-items:center;justify-content:center;gap:8px;
    width:100%;padding:13px 16px;border-radius:14px;
    font-size:.82rem;font-weight:700;cursor:pointer;
    border:none;margin-bottom:10px;
    transition:all .25s cubic-bezier(.4,0,.2,1);
    position:relative;overflow:hidden;
}
.alai-material-btn::before {
    content:"";position:absolute;inset:0;
    background:linear-gradient(135deg,rgba(255,255,255,.08),transparent);
    pointer-events:none;
}
.alai-material-btn-remedial {
    background:linear-gradient(135deg,rgba(239,68,68,.25),rgba(220,38,38,.15));
    color:#fca5a5;border:1px solid rgba(239,68,68,.35);
}
.alai-material-btn-standard {
    background:linear-gradient(135deg,rgba(245,158,11,.25),rgba(217,119,6,.15));
    color:#fcd34d;border:1px solid rgba(245,158,11,.35);
}
.alai-material-btn-advanced {
    background:linear-gradient(135deg,rgba(16,185,129,.25),rgba(5,150,105,.15));
    color:#6ee7b7;border:1px solid rgba(16,185,129,.35);
}
.alai-material-btn-nodata {
    background:linear-gradient(135deg,rgba(148,163,184,.2),rgba(100,116,139,.1));
    color:#94a3b8;border:1px solid rgba(148,163,184,.25);
}
.alai-material-btn:hover { transform:translateY(-2px);filter:brightness(1.1) }
.alai-material-btn:active { transform:translateY(0) }
.alai-material-btn:disabled { opacity:.6;cursor:not-allowed;transform:none }
.alai-material-badge {
    margin-left:auto;font-size:.9rem;
}

/* KARTU MATERI DI CHAT */
.alai-mat-card {
    background:rgba(255,255,255,.05);
    border:1px solid rgba(255,255,255,.1);
    border-radius:14px;padding:14px;margin-top:10px;
}
.alai-mat-card-header {
    display:flex;align-items:center;gap:8px;margin-bottom:10px;
}
.alai-mat-open-btn {
    margin-left:auto;
    background:rgba(59,130,246,.2);color:#93c5fd;
    border:1px solid rgba(59,130,246,.3);
    padding:5px 12px;border-radius:8px;
    font-size:.68rem;font-weight:600;text-decoration:none;
    transition:background .2s;
}
.alai-mat-open-btn:hover { background:rgba(59,130,246,.35);color:#bfdbfe;text-decoration:none }
';

// ============================================================
// [C] GANTI BAGIAN <script> DI render_block_html
// Tambah fungsi alaiLoadMaterials_CID
// ============================================================

$script_patch = '
<script>
(function() {
    var CID       = ' . $courseid . ';
    var SCORE     = ' . $userScore . ';
    var LEVEL     = "' . $level . '";
    var WEEKNUM   = ' . ($weekNum + 1) . ';
    var SCLASS    = "' . $statusClass . '";
    var PLUGINURL = "' . $pluginUrl . '";

    // --------------------------------------------------------
    // FUNGSI UTAMA: Load materi dari Moodle sesuai week & level
    // --------------------------------------------------------
    window["alaiLoadMaterials_" + CID] = function() {
        var chat   = document.getElementById("alaiChat_" + CID);
        var btn    = document.getElementById("alaiMatBtn_" + CID);

        // Tampilkan pesan user di chat
        var userMsg       = document.createElement("div");
        userMsg.className = "alai-msg alai-msg-user";
        userMsg.innerHTML = "<i class=\"fas fa-book-open\"></i> Tampilkan materi Week " + WEEKNUM + " — " + LEVEL;
        chat.appendChild(userMsg);

        // Disable tombol sementara
        if (btn) { btn.disabled = true; btn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Memuat materi..."; }

        // Loading dots
        var loader       = document.createElement("div");
        loader.className = "alai-msg-loading";
        loader.innerHTML = "<div class=\"alai-dot\"></div><div class=\"alai-dot\"></div><div class=\"alai-dot\"></div>";
        chat.appendChild(loader);
        chat.scrollTop = chat.scrollHeight;

        // AJAX ke ajax_get_materials.php
        fetch(PLUGINURL + "/ajax_get_materials.php", {
            method:  "POST",
            headers: {"Content-Type": "application/json"},
            body:    JSON.stringify({
                courseid: CID,
                weeknum:  WEEKNUM,
                level:    LEVEL,
                score:    SCORE
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            loader.remove();

            var aiMsg       = document.createElement("div");
            aiMsg.className = "alai-msg alai-msg-ai";
            aiMsg.innerHTML = d.reply || "<span style=\"color:#f87171\">Gagal memuat materi.</span>";
            chat.appendChild(aiMsg);
            chat.scrollTop = chat.scrollHeight;

            // Update tombol setelah berhasil
            if (btn) {
                btn.disabled    = false;
                var icon        = LEVEL === "LOW" ? "🔴" : LEVEL === "HIGH" ? "🟢" : "🟡";
                var levelLabel  = LEVEL === "LOW" ? "Remedial" : LEVEL === "HIGH" ? "Advanced" : "Standard";
                btn.innerHTML   = "<i class=\"fas fa-sync-alt\"></i> Refresh Materi Week " + WEEKNUM + " — " + levelLabel + " <span class=\"alai-material-badge\">" + icon + "</span>";
            }
        })
        .catch(function(e) {
            loader.remove();
            if (btn) {
                btn.disabled  = false;
                btn.innerHTML = "<i class=\"fas fa-book-open\"></i> Lihat Materi Week " + WEEKNUM + " — " + LEVEL;
            }
            var errMsg       = document.createElement("div");
            errMsg.className = "alai-msg alai-msg-ai";
            errMsg.innerHTML = "<span style=\"color:#f87171\"><i class=\"fas fa-exclamation-triangle\"></i> Gagal memuat materi. Coba lagi.</span>";
            chat.appendChild(errMsg);
        });
    };

    // --------------------------------------------------------
    // FUNGSI: Kirim topik microlearning (tidak berubah)
    // --------------------------------------------------------
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

        var loader       = document.createElement("div");
        loader.className = "alai-msg-loading";
        loader.innerHTML = "<div class=\"alai-dot\"></div><div class=\"alai-dot\"></div><div class=\"alai-dot\"></div>";
        chat.appendChild(loader);
        chat.scrollTop = chat.scrollHeight;

        fetch(PLUGINURL + "/ajax_microlearning.php", {
            method:  "POST",
            headers: {"Content-Type": "application/json"},
            body:    JSON.stringify({
                courseid: CID,
                message:  topic,
                score:    SCORE,
                level:    LEVEL
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            loader.remove();
            var aiMsg       = document.createElement("div");
            aiMsg.className = "alai-msg alai-msg-ai";
            aiMsg.innerHTML = d.success ? formatAlaiResponse(d.reply) : "<span style=\"color:#f87171\">Error: " + (d.reply || "Unknown") + "</span>";
            chat.appendChild(aiMsg);
            chat.scrollTop = chat.scrollHeight;
        })
        .catch(function() {
            loader.remove();
            var errMsg       = document.createElement("div");
            errMsg.className = "alai-msg alai-msg-ai";
            errMsg.innerHTML = "<span style=\"color:#f87171\"><i class=\"fas fa-exclamation-triangle\"></i> Gagal memuat. Coba lagi.</span>";
            chat.appendChild(errMsg);
        });
    };

    // --------------------------------------------------------
    // Auto-load materi Moodle saat block dibuka (delay 1 detik)
    // Hapus baris ini kalau TIDAK mau auto-load
    // --------------------------------------------------------
    document.addEventListener("DOMContentLoaded", function() {
        if (SCORE > 0) {
            setTimeout(function() {
                window["alaiLoadMaterials_" + CID]();
            }, 1000);
        }
    });

    function formatAlaiResponse(text) {
        text = text.replace(/```([\s\S]*?)```/g, "<div class=\"alai-code\">$1</div>");
        text = text.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        text = text.replace(/\n/g, "<br>");
        return text;
    }
})();
</script>
';

// NOTE untuk developer:
// 1. Masukkan $extra_css ke dalam <style> di render_block_html()
// 2. Ganti bagian select dropdown dengan $select_html
// 3. Ganti bagian <script> dengan $script_patch
// 4. Letakkan file ajax_get_materials.php di folder blocks/adaptive_learning_ai/