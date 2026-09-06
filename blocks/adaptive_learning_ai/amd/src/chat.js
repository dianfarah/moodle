// Adaptive Learning AI - Micro Learning Chat JavaScript
define([], function () {
    return {
        init: function () {

            window.sendAI = async function () {
                const input = document.getElementById('ai-question');
                const chat = document.getElementById('ai-answer');
                const q = input.value.trim();
                if (!q) return;

                // Add user message
                chat.innerHTML += `<div class="message user-msg">${q}</div>`;
                input.value = '';
                chat.scrollTop = chat.scrollHeight;

                // Show loading
                chat.innerHTML += `<div class="message ai-msg loading" id="loading">⏳ AI sedang berpikir...</div>`;
                chat.scrollTop = chat.scrollHeight;

                try {
                    const res = await fetch(M.cfg.wwwroot +
                        '/blocks/adaptive_learning_ai/ajax.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            courseid: COURSE_ID,
                            quizid: QUIZ_ID,
                            question: q
                        })
                    });

                    const data = await res.json();
                    document.getElementById('loading').remove();

                    // Update score display if available
                    if (data.data && data.data.score !== undefined) {
                        const scoreEl = document.getElementById('current-score');
                        if (scoreEl) {
                            scoreEl.textContent = data.data.score + '%';
                        }
                    }

                    // Build level badge based on score status
                    let levelBadge = '';
                    if (data.level === 'LOW') {
                        levelBadge = '<span class="level-indicator level-low">⚠️ REMEDIAL</span>';
                    } else if (data.level === 'MEDIUM') {
                        levelBadge = '<span class="level-indicator level-medium">✅ STANDARD</span>';
                    } else {
                        levelBadge = '<span class="level-indicator level-high">🏆 ADVANCED</span>';
                    }

                    // Build response HTML
                    let responseHtml = '<div class="message ai-msg">';
                    responseHtml += '<strong>🤖 Adaptive Learning AI:</strong> ' + levelBadge + '<br><br>';
                    responseHtml += data.reply.replace(/\n/g, '<br>');
                    
                    // Add action required indicator based on status (belum_terapai vs sudah_terapai)
                    if (data.status === 'belum_terapai') {
                        responseHtml += '<div class="action-required remedial-alert">';
                        responseHtml += '<h4>⚠️ PERINGATAN: BELUM TERCAPAI</h4>';
                        responseHtml += '<ul>';
                        responseHtml += '<li>Nilai Anda < 70%</li>';
                        responseHtml += '<li>Anda HARUS mengulang materi</li>';
                        responseHtml += '<li>Kerjakan kuis lagi dan capai minimal 70%</li>';
                        responseHtml += '</ul>';
                        responseHtml += '</div>';
                    } else if (data.status === 'sudah_terapai' && data.level === 'MEDIUM') {
                        responseHtml += '<div class="action-required" style="border-left-color: #ffa502;">';
                        responseHtml += '<h4>👍 LANJUT: Nilai Anda sudah cukup.</h4>';
                        responseHtml += '<ul>';
                        responseHtml += '<li>Tingkatkan ke 85% untuk materi advanced!</li>';
                        responseHtml += '</ul>';
                        responseHtml += '</div>';
                    } else if (data.status === 'sudah_terapai' && data.level === 'HIGH') {
                        responseHtml += '<div class="action-required success-alert">';
                        responseHtml += '<h4>🏆 EXCELLENT: Anda sangat kompeten!</h4>';
                        responseHtml += '<ul>';
                        responseHtml += '<li>Lanjut ke challenge atau topik baru!</li>';
                        responseHtml += '</ul>';
                        responseHtml += '</div>';
                    }
                    
                    responseHtml += '</div>';
                    
                    chat.innerHTML += responseHtml;
                    chat.scrollTop = chat.scrollHeight;
                    
                } catch (error) {
                    document.getElementById('loading').remove();
                    chat.innerHTML += `<div class="message ai-msg" style="color: red;">❌ Error: ${error.message}</div>`;
                    chat.scrollTop = chat.scrollHeight;
                }
            };
            
            // Topic quick send function
            window.sendTopic = function(topic) {
                const input = document.getElementById('ai-question');
                if (input) {
                    input.value = topic;
                    sendAI();
                }
            };
            
            // Add keyboard shortcut (Enter to send)
            const input = document.getElementById('ai-question');
            if (input) {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        sendAI();
                    }
                });
            }
        }
    };
});

