(function () {
    var body = document.body;

    if (!body) {
        return;
    }

    var page = body.dataset.page || '';
    var basePath = body.dataset.basePath || '.';
    var apiBase = basePath + '/api';
    var roomCode = body.dataset.roomCode || '';
    var currentState = window.APP_INITIAL_STATE || null;
    var pollTimer = null;
    var roomTimer = null;
    var isPolling = false;
    var messageTimer = null;
    var lastStateKey = '';
    var lastRoomListKey = '';
    var drafts = {
        bid: '',
        answer: ''
    };
    var activeQuestionId = currentState && currentState.current_question ? currentState.current_question.id : null;
    var lastAnimatedResultKey = (
        currentState &&
        currentState.viewer &&
        currentState.viewer.current_is_correct !== null &&
        currentState.current_question
    ) ? [
        currentState.current_question.id,
        currentState.viewer.current_is_correct ? '1' : '0',
        currentState.viewer.current_score_delta
    ].join(':') : '';

    function byId(id) {
        return document.getElementById(id);
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stateKey(value) {
        try {
            return JSON.stringify(value);
        } catch (error) {
            return String(Date.now());
        }
    }

    function formatRole(role) {
        if (role === 'moderator') {
            return 'Moderator';
        }

        if (role === 'spectator') {
            return 'Spectator';
        }

        return 'Player';
    }

    function formatPhase(phase) {
        if (phase === 'bidding') {
            return 'Fase Bidding';
        }

        if (phase === 'answering') {
            return 'Fase Menjawab';
        }

        if (phase === 'review') {
            return 'Fase Review';
        }

        return phase || '-';
    }

    function describeRoom(state) {
        if (!state || !state.room) {
            return '';
        }

        if (state.room.status === 'waiting') {
            return 'Room menunggu moderator memulai game.';
        }

        if (state.room.status === 'finished') {
            return 'Game selesai. Leaderboard akhir sudah tersedia.';
        }

        if (state.room.status === 'paused') {
            return 'Game sedang dihentikan sementara oleh host.';
        }

        if (state.room.round_phase === 'bidding') {
            if ((state.summary.players_required_to_bid || 0) < 1) {
                return 'Tidak ada player yang punya cukup poin untuk ikut bidding ronde ini.';
            }

            return 'Semua player aktif sedang mengunci bid. Soal masih disembunyikan sampai semua bid masuk.';
        }

        if (state.room.round_phase === 'answering') {
            return 'Soal sudah terbuka. Semua player yang sudah bid sedang menjawab secara bersamaan.';
        }

        if (state.room.round_phase === 'review') {
            return 'Semua jawaban sudah masuk dan hasil ronde sudah dinilai.';
        }

        return 'Game sedang berlangsung.';
    }

    function showMessage(message, type, timeoutMs) {
        var notice = byId('globalMessage');

        if (!notice) {
            return;
        }

        if (!message) {
            notice.textContent = '';
            notice.className = 'notice hidden';
            return;
        }

        notice.textContent = message;
        notice.className = 'notice notice-' + (type || 'info');

        if (messageTimer) {
            window.clearTimeout(messageTimer);
        }

        if (timeoutMs !== 0) {
            messageTimer = window.setTimeout(function () {
                notice.className = 'notice hidden';
            }, timeoutMs || 4500);
        }
    }

    function setButtonBusy(button, isBusy, busyLabel) {
        if (!button) {
            return;
        }

        if (!button.dataset.defaultLabel) {
            button.dataset.defaultLabel = button.textContent;
        }

        button.disabled = isBusy;
        button.textContent = isBusy ? busyLabel : button.dataset.defaultLabel;
    }

    function triggerResultAnimation(isCorrect, scoreDelta) {
        var flash = byId('resultFlash');
        var questionFeedback = byId('questionFeedback');
        var answerStatus = byId('answerStatus');
        var label = isCorrect ? 'BENAR' : 'SALAH';
        var deltaLabel = scoreDelta > 0 ? '+' + scoreDelta : String(scoreDelta);

        if (flash) {
            flash.textContent = label + ' ' + deltaLabel;
            flash.className = 'result-flash ' + (isCorrect ? 'result-flash-correct' : 'result-flash-wrong');

            window.setTimeout(function () {
                flash.className = 'result-flash hidden';
            }, 1500);
        }

        if (questionFeedback) {
            questionFeedback.classList.remove('result-panel-correct', 'result-panel-wrong');
            void questionFeedback.offsetWidth;
            questionFeedback.classList.add(isCorrect ? 'result-panel-correct' : 'result-panel-wrong');
        }

        if (answerStatus) {
            answerStatus.classList.remove('result-text-correct', 'result-text-wrong');
            void answerStatus.offsetWidth;
            answerStatus.classList.add(isCorrect ? 'result-text-correct' : 'result-text-wrong');
        }
    }

    function maybeAnimatePlayerResult(state) {
        if (!state || state.viewer.role !== 'player' || !state.current_question) {
            return;
        }

        if (state.viewer.current_is_correct === null || state.viewer.current_score_delta === null) {
            return;
        }

        var key = [
            state.current_question.id,
            state.viewer.current_is_correct ? '1' : '0',
            state.viewer.current_score_delta
        ].join(':');

        if (key === lastAnimatedResultKey) {
            return;
        }

        lastAnimatedResultKey = key;
        triggerResultAnimation(state.viewer.current_is_correct, state.viewer.current_score_delta);
    }

    async function sendRequest(url, method, data) {
        var options = {
            method: method || 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json'
            }
        };

        if (options.method !== 'GET') {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data || {});
        }

        var response = await fetch(url, options);
        var payload = {};

        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Terjadi kesalahan saat memproses permintaan.');
        }

        return payload;
    }

    async function refreshState() {
        var url = apiBase + '/get_room_state.php';

        if (page === 'spectate') {
            url = apiBase + '/get_public_room_state.php?room_code=' + encodeURIComponent(roomCode);
        }

        var payload = await sendRequest(url, 'GET');
        currentState = payload.state;

        return currentState;
    }

    async function refreshPublicRooms() {
        var payload = await sendRequest(apiBase + '/get_public_rooms.php', 'GET');
        return payload.rooms || [];
    }

    function syncDrafts(nextState) {
        var nextQuestionId = nextState && nextState.current_question ? nextState.current_question.id : null;

        if (nextQuestionId !== activeQuestionId) {
            drafts.bid = '';
            drafts.answer = '';
            activeQuestionId = nextQuestionId;

            var questionFeedback = byId('questionFeedback');
            var answerStatus = byId('answerStatus');

            if (questionFeedback) {
                questionFeedback.classList.remove('result-panel-correct', 'result-panel-wrong');
            }

            if (answerStatus) {
                answerStatus.classList.remove('result-text-correct', 'result-text-wrong');
            }
        }
    }

    function renderLiveRooms(rooms) {
        var container = byId('publicRoomList');

        if (!container) {
            return;
        }

        if (!rooms.length) {
            container.innerHTML = '<div class="empty-state">Belum ada room publik yang bisa ditonton.</div>';
            return;
        }

        container.innerHTML = rooms.map(function (room) {
            return (
                '<article class="room-card">' +
                    '<div class="room-card-head">' +
                        '<div>' +
                            '<span class="eyebrow">Room ' + escapeHtml(room.code) + '</span>' +
                            '<h3>' + escapeHtml(room.player_total + ' player') + '</h3>' +
                        '</div>' +
                        '<span class="pill">' + escapeHtml(room.status) + '</span>' +
                    '</div>' +
                    '<p class="soft-text">' +
                        'Ronde ' + escapeHtml(String(room.current_round)) + ' / ' + escapeHtml(String(room.total_questions)) +
                        ' | ' + escapeHtml(formatPhase(room.round_phase)) +
                    '</p>' +
                    '<a class="button button-ghost full-width" href="spectate.php?room=' + encodeURIComponent(room.code) + '">Spectate Room</a>' +
                '</article>'
            );
        }).join('');
    }

    function buildPlayerStateLine(player, phase) {
        if (player.role === 'moderator') {
            return 'Host room';
        }

        if (phase === 'waiting') {
            return 'Menunggu game dimulai';
        }

        if (!player.has_bid) {
            if (!player.has_enough_points_to_bid) {
                return 'Poin tidak cukup untuk ikut bidding';
            }

            return 'Belum bid';
        }

        var line = 'Bid ' + player.current_bid + ' poin';

        if (phase === 'review' || phase === 'finished') {
            if (player.has_answer) {
                line += ' | Jawaban masuk';
            }
        } else if (player.has_answer) {
            line += ' | Jawaban terkunci';
        } else if (phase === 'answering') {
            line += ' | Menunggu jawaban';
        }

        return line;
    }

    function renderPlayers(state) {
        var playerList = byId('playerList');

        if (!playerList) {
            return;
        }

        if (!state.players || !state.players.length) {
            playerList.innerHTML = '<div class="empty-state">Belum ada peserta.</div>';
            return;
        }

        var phaseKey = state.room.status === 'waiting'
            ? 'waiting'
            : (state.room.status === 'finished' ? 'finished' : state.room.round_phase);

        playerList.innerHTML = state.players.map(function (player) {
            return (
                '<article class="list-card list-card-compact">' +
                    '<div class="list-card-head">' +
                        '<div>' +
                            '<h3>' + escapeHtml(player.username) + '</h3>' +
                            '<span class="role-badge role-' + escapeHtml(player.role) + '">' + escapeHtml(formatRole(player.role)) + '</span>' +
                        '</div>' +
                        '<span class="score-chip">' + escapeHtml(String(player.score)) + ' pts</span>' +
                    '</div>' +
                    '<p class="soft-text">' + escapeHtml(buildPlayerStateLine(player, phaseKey)) + '</p>' +
                '</article>'
            );
        }).join('');
    }

    function renderQuestion(state) {
        var phaseBadge = byId('phaseBadge');
        var questionNumberLabel = byId('questionNumberLabel');
        var progressLabel = byId('progressLabel');
        var questionContent = byId('questionContent');
        var questionFeedback = byId('questionFeedback');
        var gameStatusText = byId('gameStatusText');
        var scoreLabel = byId('scoreLabel');
        var spectateRoundLabel = byId('spectateRoundLabel');
        var spectateStatusLabel = byId('spectateStatusLabel');

        if (phaseBadge) {
            phaseBadge.textContent = state.room.status === 'finished'
                ? 'Game Selesai'
                : formatPhase(state.room.round_phase);
        }

        if (questionNumberLabel) {
            if (state.room.status === 'finished') {
                questionNumberLabel.textContent = 'Pertandingan Selesai';
            } else {
                questionNumberLabel.textContent = 'Ronde ' + state.room.current_round + ' dari ' + state.room.total_questions;
            }
        }

        if (progressLabel) {
            if (state.room.status === 'finished') {
                progressLabel.textContent = 'Final';
            } else if (state.room.round_phase === 'bidding') {
                progressLabel.textContent = state.summary.players_bid + '/' + state.summary.players_required_to_bid + ' bid';
            } else {
                progressLabel.textContent = state.summary.players_answered + '/' + state.summary.players_required_to_answer + ' answer';
            }
        }

        if (gameStatusText) {
            gameStatusText.textContent = describeRoom(state);
        }

        if (scoreLabel && state.viewer.score !== null) {
            scoreLabel.textContent = state.viewer.score;
        }

        if (spectateRoundLabel) {
            spectateRoundLabel.textContent = state.room.current_round;
        }

        if (spectateStatusLabel) {
            spectateStatusLabel.textContent = state.room.status;
        }

        if (state.room.status === 'finished') {
            if (questionContent) {
                questionContent.innerHTML = '<p>Semua ronde telah selesai. Lihat hasil akhir pada leaderboard final.</p>';
            }

            if (questionFeedback) {
                questionFeedback.innerHTML = '<p>' + escapeHtml(describeRoom(state)) + '</p>';
            }

            return;
        }

        if (state.room.status === 'paused') {
            if (questionFeedback) {
                questionFeedback.innerHTML = '<p>Game sedang dijeda oleh host. Semua input pemain dikunci sementara.</p>';
            }
        }

        if (!state.current_question) {
            if (questionContent) {
                questionContent.innerHTML = '<p>Moderator belum memulai ronde aktif.</p>';
            }

            if (questionFeedback) {
                questionFeedback.innerHTML = '<p>Tunggu update berikutnya.</p>';
            }

            return;
        }

        if (!state.current_question.is_visible) {
            if (questionContent) {
                questionContent.innerHTML = state.summary.players_required_to_bid > 0
                    ? '<p>Soal masih dikunci. Semua player aktif harus menyelesaikan bidding terlebih dahulu.</p>'
                    : '<p>Tidak ada player yang punya poin cukup untuk ikut bidding ronde ini.</p>';
            }

            if (questionFeedback) {
                var hiddenLines = [];

                if (state.room.status === 'paused') {
                    hiddenLines.push('Game sedang dijeda oleh host.');
                }

                if (state.summary.players_out_of_points > 0) {
                    hiddenLines.push(String(state.summary.players_out_of_points) + ' player tidak punya poin cukup untuk ikut bidding ronde ini.');
                }

                if (state.summary.players_required_to_bid > 0) {
                    hiddenLines.push(String(state.summary.waiting_for_bid) + ' player aktif lagi belum bid.');
                } else {
                    hiddenLines.push('Tidak ada player aktif yang bisa membuka soal.');
                }

                questionFeedback.innerHTML = hiddenLines.map(function (line) {
                    return '<p>' + escapeHtml(line) + '</p>';
                }).join('');
            }

            return;
        }

        if (questionContent) {
            questionContent.innerHTML =
                '<span class="eyebrow">' + escapeHtml(state.current_question.category || 'Kategori') + '</span>' +
                '<h3 class="question-title">' + escapeHtml(state.current_question.question || '') + '</h3>';
        }

        if (questionFeedback) {
            var lines = [];

            if (state.room.status === 'paused') {
                lines.push('Game sedang dijeda oleh host. Semua input pemain dikunci sementara.');
            }

            if (state.room.round_phase === 'answering') {
                lines.push('Semua jawaban diproses bersama setelah seluruh player selesai menjawab.');
                lines.push(state.summary.waiting_for_answer + ' player lagi belum mengirim jawaban.');
            }

            if (state.room.round_phase === 'review') {
                lines.push('Ronde sudah dinilai.');
                lines.push('Jawaban benar: ' + (state.current_question.correct_answer || '-'));
            }

            if (state.viewer.role === 'player' && state.viewer.current_answer) {
                lines.push('Jawaban kamu: ' + state.viewer.current_answer);
            }

            questionFeedback.innerHTML = lines.map(function (line) {
                return '<p>' + escapeHtml(line) + '</p>';
            }).join('');
        }
    }

    function renderAudienceFeed(state) {
        var responseList = byId('responseList');

        if (!responseList) {
            return;
        }

        if (!state.responses || !state.responses.length) {
            responseList.innerHTML = state.summary.players_required_to_bid > 0
                ? '<div class="empty-state">Belum ada bid yang masuk pada ronde ini.</div>'
                : '<div class="empty-state">Tidak ada player yang punya poin cukup untuk ikut bidding ronde ini.</div>';
            return;
        }

        responseList.innerHTML = state.responses.map(function (item) {
            var lines = ['Bid: ' + item.bid_amount + ' poin'];

            if (state.room.round_phase === 'bidding') {
                lines.push('Bid terkunci');
            } else if (item.has_answer) {
                lines.push('Sudah menjawab');
            } else {
                lines.push('Menunggu jawaban');
            }

            return (
                '<article class="response-card response-card-compact">' +
                    '<div class="response-row">' +
                        '<strong>' + escapeHtml(item.username) + '</strong>' +
                        '<span class="score-chip">' + escapeHtml(String(item.bid_amount)) + ' bid</span>' +
                    '</div>' +
                    '<p class="soft-text">' + escapeHtml(lines.join(' | ')) + '</p>' +
                '</article>'
            );
        }).join('');
    }

    function renderModeratorAnswers(state) {
        var responseList = byId('responseList');

        if (!responseList) {
            return;
        }

        if (!state.responses || !state.responses.length) {
            responseList.innerHTML = state.summary.players_required_to_bid > 0
                ? '<div class="empty-state">Belum ada bid yang masuk pada ronde ini.</div>'
                : '<div class="empty-state">Tidak ada player yang punya poin cukup untuk ikut bidding ronde ini.</div>';
            return;
        }

        responseList.innerHTML = state.responses.map(function (item) {
            var statusLabel = 'Menunggu jawaban';

            if (item.is_correct === true) {
                statusLabel = 'Benar';
            } else if (item.is_correct === false) {
                statusLabel = 'Salah';
            } else if (item.has_answer) {
                statusLabel = 'Jawaban masuk';
            }

            var answerBody = item.answer_text
                ? '<pre class="answer-bubble">' + escapeHtml(item.answer_text) + '</pre>'
                : '<div class="answer-empty">Belum ada jawaban dikirim.</div>';

            var meta = [
                'Bid ' + item.bid_amount + ' poin',
                statusLabel
            ];

            if (item.is_correct !== null) {
                meta.push('Delta ' + item.score_delta);
            }

            return (
                '<article class="response-card response-card-detailed">' +
                    '<div class="response-row">' +
                        '<div>' +
                            '<strong>' + escapeHtml(item.username) + '</strong>' +
                            '<p class="soft-text">' + escapeHtml(meta.join(' | ')) + '</p>' +
                        '</div>' +
                        '<span class="score-chip">' + escapeHtml(String(item.bid_amount)) + ' bid</span>' +
                    '</div>' +
                    '<div class="answer-frame">' +
                        '<span class="eyebrow">Jawaban Player</span>' +
                        answerBody +
                    '</div>' +
                '</article>'
            );
        }).join('');
    }

    function renderRanking(state) {
        var rankingPanel = byId('rankingPanel');
        var winnerHighlight = byId('winnerHighlight');
        var rankingList = byId('rankingList');

        if (!rankingPanel || !winnerHighlight || !rankingList) {
            return;
        }

        if (state.room.status !== 'finished') {
            rankingPanel.classList.add('hidden');
            return;
        }

        rankingPanel.classList.remove('hidden');

        if (!state.summary.ranking || !state.summary.ranking.length) {
            winnerHighlight.innerHTML = '<p>Belum ada data ranking.</p>';
            rankingList.innerHTML = '';
            return;
        }

        if (state.summary.winners.length === 1) {
            winnerHighlight.innerHTML =
                '<p>Pemenang: <strong>' + escapeHtml(state.summary.winners[0].username) +
                '</strong> dengan skor ' + escapeHtml(String(state.summary.winners[0].score)) + ' poin.</p>';
        } else {
            winnerHighlight.innerHTML =
                '<p>Hasil seri di posisi pertama: ' + state.summary.winners.map(function (winner) {
                    return escapeHtml(winner.username) + ' (' + escapeHtml(String(winner.score)) + ')';
                }).join(', ') + '.</p>';
        }

        rankingList.innerHTML = state.summary.ranking.map(function (item, index) {
            return (
                '<article class="ranking-card">' +
                    '<span class="ranking-index">#' + (index + 1) + '</span>' +
                    '<strong>' + escapeHtml(item.username) + '</strong>' +
                    '<span class="score-chip">' + escapeHtml(String(item.score)) + ' pts</span>' +
                '</article>'
            );
        }).join('');
    }

    function renderPlayerControls(state) {
        var panel = byId('playerControlsPanel');

        if (!panel) {
            return;
        }

        if (state.viewer.role !== 'player') {
            panel.classList.add('hidden');
            return;
        }

        panel.classList.remove('hidden');

        var bidForm = byId('bidForm');
        var bidAmount = byId('bidAmount');
        var bidSubmitBtn = byId('bidSubmitBtn');
        var bidLimitText = byId('bidLimitText');
        var bidStatus = byId('bidStatus');
        var answerForm = byId('answerForm');
        var answerText = byId('answerText');
        var answerSubmitBtn = byId('answerSubmitBtn');
        var answerStatus = byId('answerStatus');

        if (state.room.status === 'finished') {
            if (bidForm) {
                bidForm.classList.add('hidden');
            }

            if (answerForm) {
                answerForm.classList.add('hidden');
            }

            if (bidStatus) {
                bidStatus.textContent = 'Game selesai.';
            }

            if (answerStatus) {
                answerStatus.textContent = 'Game selesai.';
            }

            return;
        }

        if (state.room.status === 'paused') {
            if (bidAmount) {
                bidAmount.disabled = true;
                bidAmount.value = state.viewer.current_bid || drafts.bid;
            }

            if (bidSubmitBtn) {
                bidSubmitBtn.disabled = true;
            }

            if (answerText) {
                answerText.disabled = true;
                answerText.value = state.viewer.current_answer || drafts.answer;
            }

            if (answerSubmitBtn) {
                answerSubmitBtn.disabled = true;
            }

            if (bidStatus) {
                bidStatus.textContent = state.viewer.current_bid
                    ? 'Bid terkunci: ' + state.viewer.current_bid + ' poin. Game sedang dijeda.'
                    : 'Host sedang menghentikan game sementara.';
            }

            if (answerStatus) {
                if (state.room.round_phase === 'review' && state.viewer.current_is_correct !== null) {
                    var pausedResultText = state.viewer.current_is_correct ? 'benar' : 'salah';
                    answerStatus.textContent =
                        'Ronde sudah direview. Jawaban kamu ' + pausedResultText +
                        ' dengan delta skor ' + state.viewer.current_score_delta +
                        '. Game sedang dijeda sementara.';
                } else {
                    answerStatus.textContent = 'Jawaban dikunci sementara sampai host melanjutkan game.';
                }
            }

            return;
        }

        if (bidLimitText) {
            if (!state.viewer.has_enough_points_to_bid) {
                bidLimitText.textContent = 'Saldo tidak cukup untuk bidding. Kamu harus punya minimal 2 poin.';
            } else {
                bidLimitText.textContent = 'Bid minimal 1 poin dan maksimal ' + (state.viewer.score - 1) + ' poin.';
            }
        }

        if (state.room.round_phase === 'bidding') {
            if (bidForm) {
                bidForm.classList.remove('hidden');
            }

            if (answerForm) {
                answerForm.classList.add('hidden');
            }

            if (bidAmount) {
                bidAmount.disabled = !state.viewer.can_bid;
                bidAmount.max = Math.max(1, state.viewer.score - 1);
                bidAmount.value = state.viewer.has_bid ? state.viewer.current_bid : drafts.bid;
            }

            if (bidSubmitBtn) {
                bidSubmitBtn.disabled = !state.viewer.can_bid;
            }

            if (bidStatus) {
                bidStatus.textContent = state.viewer.has_bid
                    ? 'Bid kamu terkunci: ' + state.viewer.current_bid + ' poin.'
                    : (state.viewer.has_enough_points_to_bid
                        ? 'Kunci bid kamu. Soal akan muncul setelah semua player aktif bid.'
                        : 'Poin kamu tidak cukup untuk ikut ronde ini. Menunggu player lain.');
            }

            if (answerStatus) {
                answerStatus.textContent = state.viewer.has_enough_points_to_bid
                    ? 'Form jawaban akan terbuka otomatis setelah fase bidding selesai.'
                    : 'Kamu melewati ronde ini karena poin tidak cukup untuk bidding.';
            }

            return;
        }

        if (bidForm) {
            bidForm.classList.remove('hidden');
        }

        if (bidAmount) {
            bidAmount.disabled = true;
            bidAmount.value = state.viewer.current_bid || '';
        }

        if (bidSubmitBtn) {
            bidSubmitBtn.disabled = true;
        }

        if (bidStatus) {
            if (state.viewer.current_bid) {
                bidStatus.textContent = 'Bid terkunci: ' + state.viewer.current_bid + ' poin.';
            } else if (!state.viewer.has_enough_points_to_bid) {
                bidStatus.textContent = 'Kamu tidak ikut ronde ini karena poin tidak cukup untuk bidding.';
            } else {
                bidStatus.textContent = 'Kamu tidak ikut ronde ini.';
            }
        }

        if (state.room.round_phase === 'answering') {
            if (answerForm) {
                if (state.viewer.has_bid) {
                    answerForm.classList.remove('hidden');
                } else {
                    answerForm.classList.add('hidden');
                }
            }

            if (answerText) {
                answerText.disabled = !state.viewer.can_answer;
                answerText.value = state.viewer.has_answer ? (state.viewer.current_answer || '') : drafts.answer;
            }

            if (answerSubmitBtn) {
                answerSubmitBtn.disabled = !state.viewer.can_answer;
            }

            if (answerStatus) {
                if (state.viewer.has_answer) {
                    answerStatus.textContent = 'Jawaban kamu sudah terkunci. Menunggu player lain.';
                } else if (state.viewer.can_answer) {
                    answerStatus.textContent = 'Ketik jawaban teks kamu lalu kirim.';
                } else {
                    answerStatus.textContent = 'Kamu tidak bisa menjawab karena tidak ikut bidding pada ronde ini.';
                }
            }

            return;
        }

        if (answerForm) {
            if (state.viewer.has_bid) {
                answerForm.classList.remove('hidden');
            } else {
                answerForm.classList.add('hidden');
            }
        }

        if (answerText) {
            answerText.disabled = true;
            answerText.value = state.viewer.current_answer || '';
        }

        if (answerSubmitBtn) {
            answerSubmitBtn.disabled = true;
        }

        if (answerStatus) {
            var resultText = state.viewer.has_bid
                ? 'Ronde sudah direview.'
                : 'Kamu tidak ikut ronde ini.';

            if (state.viewer.current_answer) {
                resultText += ' Jawaban kamu: ' + state.viewer.current_answer + '.';
            }

            if (state.viewer.current_is_correct === true) {
                resultText += ' Hasil: benar.';
            } else if (state.viewer.current_is_correct === false) {
                resultText += ' Hasil: salah.';
            }

            if (state.viewer.current_score_delta !== null && state.viewer.current_score_delta !== undefined) {
                resultText += ' Delta skor: ' + state.viewer.current_score_delta + '.';
            }

            if (state.current_question && state.current_question.correct_answer) {
                resultText += ' Kunci: ' + state.current_question.correct_answer + '.';
            }

            answerStatus.textContent = resultText;
        }
    }

    function renderModeratorPanel(state) {
        var panel = byId('moderatorPanel');

        if (!panel) {
            return;
        }

        if (state.viewer.role !== 'moderator') {
            panel.classList.add('hidden');
            return;
        }

        panel.classList.remove('hidden');

        var moderatorSummary = byId('moderatorSummary');
        var nextQuestionBtn = byId('nextQuestionBtn');
        var pauseGameBtn = byId('pauseGameBtn');
        var finishGameBtn = byId('finishGameBtn');

        if (moderatorSummary) {
            if (state.room.status === 'finished') {
                moderatorSummary.textContent = 'Game selesai. Lihat leaderboard final.';
            } else if (state.room.status === 'paused') {
                moderatorSummary.textContent = 'Game sedang dijeda. Host bisa melanjutkan atau mengakhiri game.';
            } else if (state.room.round_phase === 'bidding') {
                if (state.summary.players_required_to_bid < 1) {
                    moderatorSummary.textContent = 'Tidak ada player yang punya cukup poin untuk lanjut bidding.';
                } else {
                    moderatorSummary.textContent = state.summary.players_bid + ' dari ' + state.summary.players_required_to_bid + ' player aktif sudah bid.';
                }

                if (state.summary.players_out_of_points > 0) {
                    moderatorSummary.textContent += ' ' + state.summary.players_out_of_points + ' player kehabisan poin.';
                }
            } else if (state.room.round_phase === 'answering') {
                moderatorSummary.textContent = state.summary.players_answered + ' dari ' + state.summary.players_required_to_answer + ' player sudah menjawab.';
            } else {
                moderatorSummary.textContent = 'Semua jawaban sudah dinilai. Moderator bisa lanjut ke ronde berikutnya.';
            }
        }

        if (nextQuestionBtn) {
            nextQuestionBtn.disabled = !state.room.can_next;
        }

        if (pauseGameBtn) {
            pauseGameBtn.textContent = state.room.can_resume ? 'Lanjutkan Game' : 'Hentikan Sementara';
            pauseGameBtn.disabled = !(state.room.can_pause || state.room.can_resume);
        }

        if (finishGameBtn) {
            finishGameBtn.disabled = !state.room.can_finish;
        }
    }

    function renderLobbyState(state) {
        if (state.room.status !== 'waiting') {
            window.location.href = 'game.php';
            return;
        }

        var roomCodeLabel = byId('roomCodeLabel');
        var roomStatusText = byId('roomStatusText');
        var totalQuestionsLabel = byId('totalQuestionsLabel');
        var playerCountLabel = byId('playerCountLabel');
        var lobbyHint = byId('lobbyHint');
        var startGameBtn = byId('startGameBtn');

        if (roomCodeLabel) {
            roomCodeLabel.textContent = state.room.code;
        }

        if (roomStatusText) {
            roomStatusText.textContent = describeRoom(state);
        }

        if (totalQuestionsLabel) {
            totalQuestionsLabel.textContent = state.room.total_questions;
        }

        if (playerCountLabel) {
            playerCountLabel.textContent = state.summary.players_total + ' player';
        }

        if (lobbyHint) {
            if (state.viewer.role === 'moderator') {
                if (state.room.can_start) {
                    lobbyHint.textContent = 'Room siap dimulai. Semua player aktif akan masuk ke fase bidding bersama.';
                } else if (state.summary.players_total > 0 && state.summary.players_required_to_bid < 1) {
                    lobbyHint.textContent = 'Belum ada player yang punya minimal 2 poin untuk ikut bidding.';
                } else {
                    lobbyHint.textContent = 'Butuh minimal 1 player aktif dan bank soal yang tersedia untuk memulai.';
                }
            } else {
                lobbyHint.textContent = 'Tunggu moderator memulai game. Setelah mulai, semua player yang punya minimal 2 poin akan bidding dulu.';
            }
        }

        if (startGameBtn) {
            startGameBtn.disabled = !state.room.can_start;
        }

        renderPlayers(state);
    }

    function renderGameState(state) {
        renderQuestion(state);
        renderPlayers(state);
        renderRanking(state);

        if (state.viewer.role === 'moderator') {
            renderModeratorAnswers(state);
            renderModeratorPanel(state);
            return;
        }

        renderAudienceFeed(state);

        if (state.viewer.role === 'player') {
            renderPlayerControls(state);
            maybeAnimatePlayerResult(state);
        }
    }

    function renderCoreState(state) {
        if (!state) {
            return;
        }

        if (page === 'lobby') {
            renderLobbyState(state);
            return;
        }

        if (page === 'game' || page === 'spectate') {
            renderGameState(state);
        }
    }

    async function pollState() {
        if (isPolling) {
            return;
        }

        isPolling = true;

        try {
            var state = await refreshState();
            var key = stateKey(state);

            currentState = state;
            syncDrafts(state);

            if (key !== lastStateKey) {
                lastStateKey = key;
                renderCoreState(state);
            }
        } catch (error) {
            showMessage(error.message, 'error', 5000);

            if (page === 'spectate' && error.message.toLowerCase().indexOf('tidak ditemukan') !== -1) {
                window.setTimeout(function () {
                    window.location.href = 'index.php';
                }, 1200);
            }

            if ((page === 'lobby' || page === 'game') && error.message.toLowerCase().indexOf('session') !== -1) {
                window.setTimeout(function () {
                    window.location.href = 'index.php';
                }, 1200);
            }
        } finally {
            isPolling = false;
        }
    }

    function startStatePolling() {
        if (page === 'lobby' || page === 'game' || page === 'spectate') {
            pollTimer = window.setInterval(pollState, 3000);
        }
    }

    function startRoomPolling() {
        if (page !== 'index') {
            return;
        }

        async function loadRooms() {
            try {
                var rooms = await refreshPublicRooms();
                var key = stateKey(rooms);

                if (key !== lastRoomListKey) {
                    lastRoomListKey = key;
                    renderLiveRooms(rooms);
                }
            } catch (error) {
                if (lastRoomListKey !== 'empty') {
                    lastRoomListKey = 'empty';
                    renderLiveRooms([]);
                }
            }
        }

        loadRooms();
        roomTimer = window.setInterval(loadRooms, 4000);
    }

    function bindIndexPage() {
        var createRoomForm = byId('createRoomForm');
        var joinRoomForm = byId('joinRoomForm');

        startRoomPolling();

        if (createRoomForm) {
            createRoomForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                var button = createRoomForm.querySelector('button[type="submit"]');
                var formData = new FormData(createRoomForm);

                setButtonBusy(button, true, 'Membuat...');

                try {
                    var payload = await sendRequest(apiBase + '/create_room.php', 'POST', {
                        username: formData.get('username')
                    });

                    showMessage(payload.message, 'success', 1000);
                    window.setTimeout(function () {
                        window.location.href = payload.redirect;
                    }, 500);
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(button, false);
                }
            });
        }

        if (joinRoomForm) {
            joinRoomForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                var button = joinRoomForm.querySelector('button[type="submit"]');
                var formData = new FormData(joinRoomForm);

                setButtonBusy(button, true, 'Memeriksa...');

                try {
                    var payload = await sendRequest(apiBase + '/join_room.php', 'POST', {
                        username: formData.get('username'),
                        room_code: formData.get('room_code')
                    });

                    showMessage(payload.message, 'success', 1000);
                    window.setTimeout(function () {
                        window.location.href = payload.redirect;
                    }, 500);
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(button, false);
                }
            });
        }
    }

    function bindLobbyPage() {
        lastStateKey = stateKey(currentState);
        renderCoreState(currentState);
        startStatePolling();

        var startGameBtn = byId('startGameBtn');

        if (!startGameBtn) {
            return;
        }

        startGameBtn.addEventListener('click', async function () {
            setButtonBusy(startGameBtn, true, 'Memulai...');

            try {
                var payload = await sendRequest(apiBase + '/start_game.php', 'POST', {});
                showMessage(payload.message, 'success', 1000);
                window.setTimeout(function () {
                    window.location.href = payload.redirect;
                }, 500);
            } catch (error) {
                showMessage(error.message, 'error');
            } finally {
                setButtonBusy(startGameBtn, false);
                renderCoreState(currentState);
            }
        });
    }

    function bindGamePage() {
        lastStateKey = stateKey(currentState);
        renderCoreState(currentState);
        startStatePolling();

        var bidAmount = byId('bidAmount');
        var bidForm = byId('bidForm');
        var answerText = byId('answerText');
        var answerForm = byId('answerForm');
        var nextQuestionBtn = byId('nextQuestionBtn');
        var pauseGameBtn = byId('pauseGameBtn');
        var finishGameBtn = byId('finishGameBtn');

        if (bidAmount) {
            bidAmount.addEventListener('input', function () {
                drafts.bid = bidAmount.value;
            });
        }

        if (answerText) {
            answerText.addEventListener('input', function () {
                drafts.answer = answerText.value;
            });
        }

        if (bidForm) {
            bidForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                var bidSubmitBtn = byId('bidSubmitBtn');

                setButtonBusy(bidSubmitBtn, true, 'Mengunci...');

                try {
                    var payload = await sendRequest(apiBase + '/submit_bid.php', 'POST', {
                        bid_amount: bidAmount ? bidAmount.value : ''
                    });

                    drafts.bid = String(payload.bid_amount || '');
                    showMessage(payload.message, 'success');
                    await pollState();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(bidSubmitBtn, false);
                    renderCoreState(currentState);
                }
            });
        }

        if (answerForm) {
            answerForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                var answerSubmitBtn = byId('answerSubmitBtn');
                var answerValue = answerText ? answerText.value : '';

                if (!String(answerValue || '').trim()) {
                    showMessage('Jawaban teks tidak boleh kosong.', 'error');
                    return;
                }

                setButtonBusy(answerSubmitBtn, true, 'Mengirim...');

                try {
                    var payload = await sendRequest(apiBase + '/submit_answer.php', 'POST', {
                        answer: answerValue
                    });

                    drafts.answer = answerValue;

                    if (payload.round_reviewed && payload.correct_answer) {
                        showMessage(payload.message + ' Kunci: ' + payload.correct_answer + '.', 'success', 5000);
                    } else {
                        showMessage(payload.message, 'success');
                    }

                    await pollState();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(answerSubmitBtn, false);
                    renderCoreState(currentState);
                }
            });
        }

        if (nextQuestionBtn) {
            nextQuestionBtn.addEventListener('click', async function () {
                setButtonBusy(nextQuestionBtn, true, 'Memuat...');

                try {
                    var payload = await sendRequest(apiBase + '/next_question.php', 'POST', {});
                    showMessage(payload.message, 'success');
                    await pollState();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(nextQuestionBtn, false);
                    renderCoreState(currentState);
                }
            });
        }

        if (pauseGameBtn) {
            pauseGameBtn.addEventListener('click', async function () {
                setButtonBusy(pauseGameBtn, true, 'Memproses...');

                try {
                    var payload = await sendRequest(apiBase + '/toggle_pause.php', 'POST', {});
                    showMessage(payload.message, 'success');
                    await pollState();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(pauseGameBtn, false);
                    renderCoreState(currentState);
                }
            });
        }

        if (finishGameBtn) {
            finishGameBtn.addEventListener('click', async function () {
                var confirmed = window.confirm('Akhiri game sekarang? Semua player akan langsung melihat leaderboard final.');

                if (!confirmed) {
                    return;
                }

                setButtonBusy(finishGameBtn, true, 'Mengakhiri...');

                try {
                    var payload = await sendRequest(apiBase + '/finish_game.php', 'POST', {});
                    showMessage(payload.message, 'success');
                    await pollState();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    setButtonBusy(finishGameBtn, false);
                    renderCoreState(currentState);
                }
            });
        }
    }

    function bindSpectatePage() {
        lastStateKey = stateKey(currentState);
        renderCoreState(currentState);
        startStatePolling();
    }

    if (page === 'index') {
        bindIndexPage();
    } else if (page === 'lobby') {
        bindLobbyPage();
    } else if (page === 'game') {
        bindGamePage();
    } else if (page === 'spectate') {
        bindSpectatePage();
    }

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            window.clearInterval(pollTimer);
        }

        if (roomTimer) {
            window.clearInterval(roomTimer);
        }
    });
})();
