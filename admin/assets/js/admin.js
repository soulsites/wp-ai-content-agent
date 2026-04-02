/**
 * WP AI Content Agent — Admin JavaScript
 */
(function ($) {
    'use strict';

    const AJAX_URL = aicaData.ajaxUrl;
    const NONCE    = aicaData.nonce;
    const I18N     = aicaData.i18n;

    /* ============================================================
       Artikel-Generator
    ============================================================ */
    const Generator = {
        contentId:   null,
        pollInterval: null,
        lastLogCount: 0,
        voiceData:   {},
        pipelineData: {},
        agentInfo:   {},
        currentPipelineAgents: [],

        init() {
            const $form = $('#aica-generate-form');
            if (!$form.length) return;

            // Voice-Daten laden
            try {
                const raw = document.getElementById('aica-voice-data');
                if (raw) this.voiceData = JSON.parse(raw.textContent);
            } catch(e) {}

            // Pipeline-Daten laden
            try {
                const raw = document.getElementById('aica-pipelines-generate-data');
                if (raw) this.pipelineData = JSON.parse(raw.textContent);
            } catch(e) {}

            // Agent-Infos laden
            try {
                const raw = document.getElementById('aica-agent-info-generate-data');
                if (raw) this.agentInfo = JSON.parse(raw.textContent);
            } catch(e) {}

            $form.on('submit', (e) => { e.preventDefault(); this.start(); });
            $('#aica-generate-another').on('click', () => this.reset());
            $('#aica-voice').on('change', () => this.updateVoicePreview());
            $('#aica-pipeline').on('change', () => this.updatePipelinePreview());
            this.updateVoicePreview();
            this.updatePipelinePreview();
        },

        updatePipelinePreview() {
            const pipelineId = parseInt($('#aica-pipeline').val() || '0', 10);
            const pipeline = this.pipelineData[pipelineId];
            if (!pipeline) {
                this.currentPipelineAgents = [];
                return;
            }
            this.currentPipelineAgents = pipeline.steps || [];
        },

        buildPipelineStatusUI() {
            const $container = $('#aica-pipeline-status');
            $container.empty();

            if (!this.currentPipelineAgents || this.currentPipelineAgents.length === 0) {
                $container.html('<p style="color: var(--aica-text-muted);">Keine Agenten in dieser Pipeline konfiguriert.</p>');
                return;
            }

            this.currentPipelineAgents.forEach((step) => {
                // Abwärtskompatibel: step kann String (alt) oder Objekt (neu) sein
                const agentKey = typeof step === 'string' ? step : step.agent;
                const stepId   = typeof step === 'string' ? agentKey : (step.id || agentKey);
                const info     = this.agentInfo[agentKey] || { icon: '❓', name: agentKey };
                const html = `
                    <div class="aica-pipeline-item" id="aica-step-${stepId}" data-agent="${agentKey}">
                        <span class="aica-pipeline-item-icon">⏳</span>
                        <span class="aica-pipeline-item-label">${info.icon} ${info.name}</span>
                        <span class="aica-pipeline-item-status"></span>
                    </div>
                `;
                $container.append(html);
            });
        },

        updateVoicePreview() {
            const voiceId = $('#aica-voice').val();
            const $preview = $('#aica-voice-preview');
            const data = this.voiceData[voiceId];

            if (!data || !voiceId || voiceId === '0') {
                $preview.hide();
                return;
            }

            const example = data.example ? `<div class="aica-voice-preview-example">${data.example.substring(0, 200)}...</div>` : '';
            $preview.html(`
                <div class="aica-voice-preview-name">🎭 ${data.name}</div>
                <div class="aica-voice-preview-tone">Tonalität: ${data.tone}</div>
                ${example}
            `).show();
        },

        start() {
            const topic = $('#aica-topic').val().trim();
            if (!topic) { alert('Bitte ein Thema eingeben.'); return; }

            // Update pipeline selection vor dem Start
            this.updatePipelinePreview();

            const $btn = $('#aica-submit-btn');
            $btn.prop('disabled', true);
            $('#aica-loading').show();
            $('#aica-status-panel').show();
            $('#aica-result, #aica-error').hide();
            this.lastLogCount = 0;

            // Build Pipeline Status UI dynamisch
            this.buildPipelineStatusUI();

            // Agent-Status zurücksetzen
            $('[id^="aica-step-"]').removeClass('active done error')
                .find('.aica-pipeline-item-icon').text('⏳');

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:      'aica_generate_content',
                    nonce:        NONCE,
                    topic:        topic,
                    keywords:     $('#aica-keywords').val(),
                    pipeline_id:  $('#aica-pipeline').val(),
                    voice_id:     $('#aica-voice').val(),
                    post_status:  $('#aica-post-status').val(),
                    category_id:  $('#aica-category').val(),
                },
                success: (resp) => {
                    if (!resp.success) {
                        this.showError(resp.data?.message || I18N.error);
                        return;
                    }
                    this.contentId = resp.data.content_id;
                    this.pollStatus();
                },
                error: (xhr, status, error) => {
                    const detail = error ? `${status}: ${error}` : `HTTP ${xhr.status}`;
                    this.showError(`Verbindungsfehler beim Starten der Generierung (${detail}). Bitte Seite neu laden und erneut versuchen.`);
                }
            });
        },

        pollStatus() {
            if (this.pollInterval) clearInterval(this.pollInterval);
            this.pollInterval = setInterval(() => this.checkStatus(), 3000);
            this.checkStatus(); // Sofort
        },

        checkStatus() {
            if (!this.contentId) return;

            $.ajax({
                url:  AJAX_URL,
                data: { action: 'aica_get_job_status', nonce: NONCE, content_id: this.contentId },
                success: (resp) => {
                    if (!resp.success) {
                        clearInterval(this.pollInterval);
                        this.showError(resp.data?.message || 'Status-Abfrage fehlgeschlagen.');
                        return;
                    }
                    const d = resp.data;
                    this.updateLogs(d.logs || []);
                    this.updatePipelineStatus(d.logs || []);

                    if (d.status === 'completed') {
                        clearInterval(this.pollInterval);
                        this.showResult(d);
                    } else if (d.status === 'error') {
                        clearInterval(this.pollInterval);
                        const msg = d.error_message || 'Unbekannter Fehler. Details siehe Agent-Log oben.';
                        this.showError(msg);
                    }
                },
                error: (xhr, status, error) => {
                    clearInterval(this.pollInterval);
                    const detail = error ? `${status}: ${error}` : `HTTP ${xhr.status}`;
                    this.showError(`Status-Abfrage fehlgeschlagen (${detail}). Generierung läuft möglicherweise noch im Hintergrund.`);
                }
            });
        },

        updateLogs(logs) {
            if (logs.length <= this.lastLogCount) return;
            const $log = $('#aica-log-entries');
            const newLogs = logs.slice(this.lastLogCount);

            newLogs.forEach((log, idx) => {
                const time = log.created_at ? log.created_at.slice(11, 19) : '';
                const agentInfo = this.agentInfo[log.agent] || { icon: '❓', name: log.agent };

                // Formatiere unterschiedliche Log-Typen
                let icon = '📝';
                let className = log.level || 'info';

                if (log.level === 'error') {
                    icon = '❌';
                    className = 'error';
                } else if (log.level === 'warning') {
                    icon = '⚠️';
                    className = 'warning';
                } else if (log.message && log.message.includes('Abgeschlossen')) {
                    icon = '✅';
                    className = 'success';
                } else if (log.agent === 'system') {
                    icon = '⚙️';
                    className = 'system';
                }

                // Parse Log-Daten wenn vorhanden
                let logData = null;
                try {
                    if (log.data && typeof log.data === 'string') {
                        logData = JSON.parse(log.data);
                    } else if (log.data && typeof log.data === 'object') {
                        logData = log.data;
                    }
                } catch (e) {}

                const logId = `log-${this.lastLogCount + idx}`;
                let detailsHtml = '';
                let expandBtn = '';

                // Wenn Daten vorhanden sind, zeige expandierbaren Button
                if (logData) {
                    expandBtn = `<button class="aica-log-expand" data-toggle="${logId}-details" style="background:none;border:none;cursor:pointer;color:inherit;padding:0;margin-left:6px;">▼</button>`;

                    let dataContent = '';
                    if (logData.response_preview) {
                        dataContent += `<div class="aica-log-data-item"><strong>KI-Response (Vorschau):</strong><pre>${$('<div>').text(logData.response_preview).html()}</pre></div>`;
                    }
                    if (logData.tokens_input || logData.tokens_output) {
                        dataContent += `<div class="aica-log-data-item"><strong>Tokens:</strong> Input: ${logData.tokens_input}, Output: ${logData.tokens_output}</div>`;
                    }
                    if (logData.model) {
                        dataContent += `<div class="aica-log-data-item"><strong>Modell:</strong> ${logData.model}</div>`;
                    }

                    detailsHtml = `
                        <div class="aica-log-details" id="${logId}-details" style="display:none;margin-top:8px;padding:8px;background:rgba(255,255,255,.05);border-radius:4px;border-left:2px solid #666;">
                            ${dataContent}
                        </div>
                    `;
                }

                const html = `
                    <div class="aica-log-entry ${className}" id="${logId}">
                        <div class="aica-log-meta">
                            <span class="aica-log-time">${time}</span>
                            <span class="aica-log-agent">${agentInfo.icon} ${this.getAgentDisplayName(log.agent)}</span>
                            ${expandBtn}
                        </div>
                        <div class="aica-log-message">${icon} ${$('<span>').text(log.message).html()}</div>
                        ${detailsHtml}
                    </div>
                `;
                $log.append(html);

                // Toggle-Handler für expandierbare Details
                if (expandBtn) {
                    $log.find(`[data-toggle="${logId}-details"]`).on('click', function(e) {
                        e.preventDefault();
                        const $details = $(`#${logId}-details`);
                        $details.slideToggle(200);
                        $(this).text($details.is(':visible') ? '▼' : '▶');
                    });
                }
            });

            $log.scrollTop($log[0].scrollHeight);
            this.lastLogCount = logs.length;
        },

        getAgentDisplayName(agentKey) {
            if (agentKey === 'system') return 'System';
            const info = this.agentInfo[agentKey];
            return info ? info.name : agentKey;
        },

        updatePipelineStatus(logs) {
            // Zähle Abschlüsse pro Agent-Key in Log-Reihenfolge
            const doneCounters = {};
            logs.forEach(log => {
                if (log.message && log.message.includes('Abgeschlossen')) {
                    doneCounters[log.agent] = (doneCounters[log.agent] || 0) + 1;
                }
            });

            // Letzter Log-Eintrag bestimmt den aktiven Agenten
            const activeAgent = logs.length ? logs[logs.length - 1].agent : null;

            // Pro Agent-Key zählen wir, wie viele Step-Items wir bereits als
            // "done" markiert haben — so werden bei doppeltem Agenten die
            // Items von links nach rechts abgehakt statt alle auf einmal.
            const markedDone   = {};
            const markedActive = {};

            (this.currentPipelineAgents || []).forEach(step => {
                const agentKey = typeof step === 'string' ? step : step.agent;
                const stepId   = typeof step === 'string' ? agentKey : (step.id || agentKey);
                const $item    = $(`#aica-step-${stepId}`);
                if (!$item.length) return;

                markedDone[agentKey]   = markedDone[agentKey]   || 0;
                markedActive[agentKey] = markedActive[agentKey] || false;

                if (markedDone[agentKey] < (doneCounters[agentKey] || 0)) {
                    $item.removeClass('active error').addClass('done');
                    $item.find('.aica-pipeline-item-icon').text('✅');
                    markedDone[agentKey]++;
                } else if (agentKey === activeAgent && !markedActive[agentKey]) {
                    $item.removeClass('done error').addClass('active');
                    $item.find('.aica-pipeline-item-icon').html('<span class="aica-spinner"></span>');
                    markedActive[agentKey] = true;
                }
            });
        },

        showResult(data) {
            $('#aica-loading').hide();
            $('#aica-submit-btn').prop('disabled', false);

            const stats = `
                <div class="aica-result-stats">
                    <div class="aica-result-stat">
                        <div class="aica-result-stat-value">${Number(data.word_count||0).toLocaleString('de-DE')}</div>
                        <div class="aica-result-stat-label">Wörter</div>
                    </div>
                    <div class="aica-result-stat">
                        <div class="aica-result-stat-value">${data.seo_score||0}/100</div>
                        <div class="aica-result-stat-label">SEO-Score</div>
                    </div>
                    <div class="aica-result-stat">
                        <div class="aica-result-stat-value">${Number(data.tokens_used||0).toLocaleString('de-DE')}</div>
                        <div class="aica-result-stat-label">Tokens</div>
                    </div>
                </div>`;

            $('#aica-result-stats').html(stats);

            if (data.wp_post_id) {
                $('#aica-edit-post')
                    .attr('href', `${aicaData.adminUrl}?page=post&action=edit&post=${data.wp_post_id}`)
                    .show();
            } else {
                $('#aica-edit-post').hide();
            }

            $('#aica-result').show();
        },

        showError(msg) {
            $('#aica-loading').hide();
            $('#aica-submit-btn').prop('disabled', false);
            $('#aica-error-message').text(msg);
            $('#aica-error').show();
        },

        reset() {
            clearInterval(this.pollInterval);
            this.contentId = null;
            this.lastLogCount = 0;
            $('#aica-topic').val('');
            $('#aica-keywords').val('');
            $('#aica-status-panel, #aica-result, #aica-error').hide();
            $('#aica-submit-btn').prop('disabled', false);
            $('#aica-loading').hide();
            $('#aica-log-entries').empty();
        }
    };

    /* ============================================================
       API-Test
    ============================================================ */
    const ApiTest = {
        init() {
            $('#aica-test-api').on('click', function() {
                const $btn    = $(this);
                const $result = $('#aica-api-test-result');
                const apiKey  = $('#aica_api_key').val();

                $btn.prop('disabled', true).text('⏳ ' + I18N.testing);
                $result.removeClass('success error').text('');

                $.ajax({
                    url:    AJAX_URL,
                    method: 'POST',
                    data: { action: 'aica_test_api', nonce: NONCE, api_key: apiKey },
                    success: (resp) => {
                        $result.addClass(resp.success ? 'success' : 'error')
                               .text(resp.data?.message || (resp.success ? '✅ OK' : '❌ Fehler'));
                    },
                    error: () => $result.addClass('error').text('❌ Verbindungsfehler'),
                    complete: () => $btn.prop('disabled', false).text('🔌 Verbindung testen')
                });
            });

            // API-Key toggle
            $('#aica-toggle-key').on('click', function() {
                const $input = $('#aica_api_key');
                const type   = $input.attr('type') === 'password' ? 'text' : 'password';
                $input.attr('type', type);
                $(this).text(type === 'password' ? '👁' : '🙈');
            });
        }
    };

    /* ============================================================
       Tabs
    ============================================================ */
    const Tabs = {
        init() {
            $(document).on('click', '.aica-tab', function() {
                const tabId = $(this).data('tab');
                $('.aica-tab').removeClass('active');
                $('.aica-tab-content').removeClass('active');
                $(this).addClass('active');
                $(`#${tabId}`).addClass('active');
            });
        }
    };

    /* ============================================================
       Voice Profile Manager
    ============================================================ */
    const VoiceManager = {
        profiles: [],

        init() {
            const rawData = document.getElementById('aica-profiles-data');
            if (rawData) {
                try { this.profiles = JSON.parse(rawData.textContent); } catch(e) {}
            }

            $('#aica-add-voice, #aica-add-voice-empty').on('click', () => this.openModal());
            $(document).on('click', '.aica-edit-voice', (e) => this.editProfile($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-delete-voice', (e) => this.deleteProfile($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-modal-close, .aica-modal-backdrop', () => this.closeModal());
            $('#aica-save-voice').on('click', () => this.saveProfile());
        },

        openModal(profileId = null) {
            $('#aica-voice-modal-title').text(profileId ? 'Voice Profil bearbeiten' : 'Voice Profil erstellen');
            $('#aica-voice-form')[0].reset();
            $('#aica-voice-id').val(0);
            $('#aica-voice-evaluations-section').hide();

            if (profileId) {
                const p = this.profiles.find(p => p.id == profileId);
                if (p) {
                    $('#aica-voice-id').val(p.id);
                    $('#aica-voice-name').val(p.name);
                    $('#aica-voice-description').val(p.description);
                    $('#aica-voice-tone').val(p.tone);
                    $('#aica-voice-style').val(p.style);
                    $('#aica-voice-example').val(p.example);
                    $('#aica-voice-avoid').val(p.avoid);
                    $('#aica-voice-evaluations-section').show();
                    this.renderEvaluations(p.evaluations || []);
                }
            }

            $('#aica-voice-modal').show();
        },

        closeModal() { $('#aica-voice-modal, #aica-job-modal, #aica-details-modal').hide(); },

        saveProfile() {
            const $btn = $('#aica-save-voice').prop('disabled', true).text('💾 ' + I18N.saving);

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:       'aica_save_voice',
                    nonce:         NONCE,
                    voice_id:     $('#aica-voice-id').val(),
                    name:         $('#aica-voice-name').val(),
                    description:  $('#aica-voice-description').val(),
                    tone:         $('#aica-voice-tone').val(),
                    style:        $('#aica-voice-style').val(),
                    example:      $('#aica-voice-example').val(),
                    avoid:        $('#aica-voice-avoid').val(),
                },
                success: (resp) => {
                    if (resp.success) { location.reload(); }
                    else alert(resp.data?.message || I18N.error);
                },
                complete: () => $btn.prop('disabled', false).text('💾 Profil speichern')
            });
        },

        deleteProfile(id) {
            if (!confirm(I18N.confirm_del)) return;
            $.ajax({
                url: AJAX_URL, method: 'POST',
                data: { action: 'aica_delete_voice', nonce: NONCE, voice_id: id },
                success: (resp) => { if (resp.success) location.reload(); }
            });
        },

        editProfile(id) { this.openModal(id); },

        renderEvaluations(evals) {
            const $list = $('#aica-evaluations-list').empty();
            if (!evals.length) {
                $list.html('<p class="description">Noch keine Evaluierungen.</p>');
                return;
            }
            evals.forEach(e => {
                $list.append(`<div class="aica-eval-item">
                    <span>${'⭐'.repeat(e.rating)}</span>
                    <em>${$('<span>').text(e.note||'').html()}</em>
                    <p>${$('<span>').text((e.excerpt||'').substring(0,100)).html()}</p>
                </div>`);
            });
        }
    };

    /* ============================================================
       Job Manager (Cron-Jobs)
    ============================================================ */
    const JobManager = {
        jobs: [],

        init() {
            const rawData = document.getElementById('aica-jobs-data');
            if (rawData) {
                try { this.jobs = JSON.parse(rawData.textContent); } catch(e) {}
            }

            $('#aica-add-job, #aica-add-job-empty').on('click', () => this.openModal());
            $(document).on('click', '.aica-edit-job', (e) => this.editJob($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-delete-job', (e) => this.deleteJob($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-run-job-now', (e) => this.runJobNow($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-modal-close, .aica-modal-backdrop', () => { $('#aica-job-modal').hide(); });
            $('#aica-save-job').on('click', () => this.saveJob());
        },

        openModal(jobId = null) {
            $('#aica-job-modal-title').text(jobId ? 'Job bearbeiten' : 'Neuen Job erstellen');
            $('#aica-job-form')[0].reset();
            $('#aica-job-id').val(0);

            // Pipeline-Dropdown befüllen
            const $pipelineSelect = $('#aica-job-pipeline');
            if ($pipelineSelect.length) {
                $pipelineSelect.find('option:not([value="0"])').remove();
                (aicaData.pipelines || []).forEach(p => {
                    $pipelineSelect.append(`<option value="${p.id}">${p.name}</option>`);
                });
            }

            if (jobId) {
                const job = this.jobs.find(j => j.id == jobId);
                if (job) {
                    $('#aica-job-id').val(job.id);
                    $('#aica-job-name').val(job.name);
                    $('#aica-job-topic').val(job.topic);
                    $('#aica-job-keywords').val(job.keywords);
                    $('#aica-job-schedule').val(job.schedule);
                    $('#aica-job-post-status').val(job.post_status);
                    $('#aica-job-voice').val(job.voice_id || 0);
                    $('#aica-job-category').val(job.category_id || 0);
                    $pipelineSelect.val(job.pipeline_id || 0);
                }
            }

            $('#aica-job-modal').show();
        },

        saveJob() {
            const $btn = $('#aica-save-job').prop('disabled', true).text('💾 ' + I18N.saving);

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:        'aica_save_job',
                    nonce:          NONCE,
                    job_id:         $('#aica-job-id').val(),
                    name:           $('#aica-job-name').val(),
                    topic:          $('#aica-job-topic').val(),
                    keywords:       $('#aica-job-keywords').val(),
                    schedule:       $('#aica-job-schedule').val(),
                    post_status:    $('#aica-job-post-status').val(),
                    voice_id:       $('#aica-job-voice').val(),
                    category_id:    $('#aica-job-category').val(),
                    pipeline_id:    $('#aica-job-pipeline').val() || 0,
                    min_word_count: $('[name="min_word_count"]').val(),
                },
                success: (resp) => {
                    if (resp.success) { location.reload(); }
                    else alert(resp.data?.message || I18N.error);
                },
                complete: () => $btn.prop('disabled', false).text('💾 Job speichern')
            });
        },

        editJob(id) { this.openModal(id); },

        deleteJob(id) {
            if (!confirm(I18N.confirm_del)) return;
            $.ajax({
                url: AJAX_URL, method: 'POST',
                data: { action: 'aica_delete_job', nonce: NONCE, job_id: id },
                success: (resp) => { if (resp.success) $(`[data-job-id="${id}"]`).fadeOut(400, function(){ $(this).remove(); }); }
            });
        },

        runJobNow(id) {
            const $btn = $(`.aica-run-job-now[data-id="${id}"]`).prop('disabled', true).text('⏳');

            $.ajax({
                url: AJAX_URL, method: 'POST',
                data: { action: 'aica_run_job_now', nonce: NONCE, job_id: id },
                success: (resp) => {
                    if (resp.success) {
                        alert(`✅ Job gestartet! Content-ID: ${resp.data.content_id}\nStatus kann unter "Content-Verlauf" verfolgt werden.`);
                    } else {
                        alert('❌ ' + (resp.data?.message || I18N.error));
                    }
                },
                complete: () => $btn.prop('disabled', false).text('▶️ Jetzt')
            });
        }
    };

    /* ============================================================
       Content Details
    ============================================================ */
    const ContentDetails = {
        init() {
            $(document).on('click', '.aica-view-details', (e) => {
                const id = $(e.currentTarget).data('id');
                this.showDetails(id);
            });
            $(document).on('click', '.aica-delete-content', (e) => {
                const id = $(e.currentTarget).data('id');
                this.deleteContent(id);
            });
        },

        showDetails(id) {
            $('#aica-details-modal').show();
            $('#aica-details-body').html('<div class="aica-loading-spinner"><span class="aica-spinner"></span> Lade Details...</div>');

            $.ajax({
                url: AJAX_URL,
                data: { action: 'aica_get_job_status', nonce: NONCE, content_id: id },
                success: (resp) => {
                    if (!resp.success) { $('#aica-details-body').html('<p>Fehler beim Laden.</p>'); return; }
                    const d = resp.data;
                    let html = `<div class="aica-details-grid">
                        <div class="aica-details-section">
                            <h3>Status</h3>
                            <p><strong>${d.status}</strong></p>
                            ${d.word_count ? `<p>Wörter: ${d.word_count}</p>` : ''}
                            ${d.seo_score ? `<p>SEO-Score: ${d.seo_score}/100</p>` : ''}
                            ${d.tokens_used ? `<p>Tokens: ${d.tokens_used}</p>` : ''}
                            ${d.duration_sec ? `<p>Dauer: ${d.duration_sec}s</p>` : ''}
                        </div>
                        <div class="aica-details-section">
                            <h3>Agent-Logs</h3>
                            <div style="max-height:200px;overflow-y:auto;font-size:12px;font-family:monospace;background:#1d2327;color:#ccd6f6;border-radius:6px;padding:12px">`;
                    (d.logs || []).forEach(log => {
                        html += `<div class="aica-log-entry ${log.level}">[${log.agent}] ${$('<span>').text(log.message).html()}</div>`;
                    });
                    html += `</div></div></div>`;
                    if (d.error_message) {
                        html += `<div class="aica-error"><strong>Fehler:</strong> ${$('<span>').text(d.error_message).html()}</div>`;
                    }
                    $('#aica-details-body').html(html);
                }
            });
        },

        deleteContent(id) {
            if (!confirm(I18N.confirm_del)) return;
            $.ajax({
                url: AJAX_URL, method: 'POST',
                data: { action: 'aica_delete_content', nonce: NONCE, content_id: id },
                success: (resp) => {
                    if (resp.success) $(`#aica-content-row-${id}`).fadeOut(400, function(){ $(this).remove(); });
                }
            });
        }
    };

    /* ============================================================
       Pipeline Builder
    ============================================================ */
    const PipelineBuilder = {
        sortable:  null,
        steps:     [],
        pipelines: [],

        // Agenten-Map aus aicaData (key → info), wird in init() gebaut
        agentMap:     {},
        sourceOptions: {},

        init() {
            // Agenten-Map aus globalen aicaData.agents aufbauen
            (aicaData.agents || []).forEach(a => {
                this.agentMap[a.key] = a;
            });

            // Source-Options dynamisch aus Agenten bauen
            this.sourceOptions[''] = '— Quelle wählen —';
            (aicaData.agents || []).forEach(a => {
                if (a.result_key && a.result_key !== 'final_content') {
                    this.sourceOptions[a.result_key] = a.name + ' (' + a.result_key + ')';
                }
            });

            // Gespeicherte Pipelines aus DOM laden
            try {
                const pd = document.getElementById('aica-pipelines-data');
                if (pd) this.pipelines = JSON.parse(pd.textContent);
            } catch(e) {}

            // Datalist für Bedingungsquellen (result_key autocomplete)
            if (!document.getElementById('aica-result-keys-datalist')) {
                $('body').append('<datalist id="aica-result-keys-datalist"></datalist>');
            }
            this.updateResultKeyDatalist();

            // SortableJS auf Pipeline-Canvas (nur falls vorhanden)
            const canvas = document.getElementById('aica-pipeline-canvas');
            if (canvas && typeof Sortable !== 'undefined') {
                this.sortable = Sortable.create(canvas, {
                    handle:    '.aica-step-drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: () => this.syncStepsFromDOM(),
                });
            }

            // Alle Events via $(document).on() — unabhängig davon ob Elemente
            // bereits im DOM sind oder erst später erscheinen.
            $(document).on('click', '#aica-new-pipeline-btn',           () => this.openBuilder());
            $(document).on('click', '#aica-builder-close',              () => this.closeBuilder());
            $(document).on('click', '#aica-builder-cancel',             () => this.closeBuilder());
            $(document).on('click', '#aica-save-pipeline',              () => this.savePipeline());
            $(document).on('click', '.aica-palette-item',    (e) => this.addStep($(e.currentTarget).data('agent')));
            $(document).on('click', '.aica-edit-pipeline',   (e) => this.editPipeline($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-delete-pipeline', (e) => this.deletePipeline($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-pipeline-template', (e) => this.openFromTemplate(e.currentTarget));
            $(document).on('click', '.aica-remove-step',     (e) => this.removeStep($(e.currentTarget).closest('.aica-pipeline-step-item').data('step-id')));
            $(document).on('click', '.aica-add-condition-btn', (e) => this.addCondition($(e.currentTarget).closest('.aica-pipeline-step-item').data('step-id')));
            $(document).on('click', '.aica-remove-condition',  (e) => $(e.currentTarget).closest('.aica-condition-row').remove());
            $(document).on('input', '.aica-step-result-key', () => this.updateResultKeyDatalist());
            $(document).on('change', '.aica-step-enabled-toggle', (e) => {
                const $item    = $(e.currentTarget).closest('.aica-pipeline-step-item');
                const enabled  = $(e.currentTarget).prop('checked');
                $item.toggleClass('aica-step-disabled', !enabled);
            });
        },

        openBuilder(pipeline = null) {
            this.steps = [];
            $('#aica-pipeline-id').val(0);
            $('#aica-pipeline-name').val('');
            $('#aica-pipeline-description').val('');
            $('#aica-builder-title').text('Neue Pipeline');
            $('#aica-pipeline-canvas').find('.aica-pipeline-step-item').remove();
            $('#aica-canvas-placeholder').show();

            if (pipeline) {
                $('#aica-pipeline-id').val(pipeline.id);
                $('#aica-pipeline-name').val(pipeline.name);
                $('#aica-pipeline-description').val(pipeline.description || '');
                $('#aica-builder-title').text('Pipeline bearbeiten: ' + pipeline.name);
                (pipeline.steps || []).forEach(step => {
                    this.steps.push({...step});
                    this.renderStep(step);
                });
                this.updatePlaceholder();
            }

            $('#aica-pipeline-builder').show();
            $('html, body').animate({ scrollTop: $('#aica-pipeline-builder').offset().top - 40 }, 300);
        },

        closeBuilder() {
            $('#aica-pipeline-builder').hide();
            this.steps = [];
        },

        editPipeline(id) {
            const pipeline = this.pipelines.find(p => p.id == id);
            if (pipeline) this.openBuilder(pipeline);
        },

        addStep(agentKey) {
            const info = this.agentMap[agentKey];
            if (!info) return;
            const step = {
                id:         'step_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
                agent:      agentKey,
                enabled:    true,
                conditions: [],
                result_key: info.result_key || agentKey + '_result',
            };
            this.steps.push(step);
            this.renderStep(step);
            this.updatePlaceholder();
            this.updateResultKeyDatalist();
        },

        renderStep(step) {
            const info           = this.agentMap[step.agent] || { icon: '❓', name: step.agent };
            const enabled        = step.enabled !== false;
            const defaultResKey  = info.result_key || step.agent + '_result';
            const resultKey      = step.result_key || defaultResKey;

            let condHtml = '';
            (step.conditions || []).forEach(cond => {
                condHtml += this.buildConditionRowHtml(cond);
            });

            const $item = $(`
                <div class="aica-pipeline-step-item ${!enabled ? 'aica-step-disabled' : ''}"
                     data-step-id="${this.escHtml(step.id)}"
                     data-agent="${this.escHtml(step.agent)}">
                    <div class="aica-step-header">
                        <span class="aica-step-drag-handle" title="Verschieben">⠿</span>
                        <span class="aica-step-icon">${info.icon}</span>
                        <span class="aica-step-name">${this.escHtml(info.name)}</span>
                        <label class="aica-toggle aica-step-toggle" title="Aktiviert">
                            <input type="checkbox" class="aica-step-enabled-toggle" ${enabled ? 'checked' : ''}>
                            <span class="aica-toggle-slider"></span>
                        </label>
                        <button type="button" class="aica-add-condition-btn aica-btn aica-btn-small aica-btn-secondary"
                                title="Bedingung hinzufügen">
                            + Bedingung
                        </button>
                        <button type="button" class="aica-remove-step aica-btn aica-btn-small aica-btn-danger"
                                title="Entfernen">✕</button>
                    </div>
                    <div class="aica-step-meta">
                        <span class="aica-step-meta-label">Schreibt in:</span>
                        <input type="text" class="aica-input aica-step-result-key"
                               value="${this.escHtml(resultKey)}"
                               placeholder="${this.escHtml(defaultResKey)}"
                               title="Context-Key für das Ergebnis dieses Schritts">
                    </div>
                    <div class="aica-step-conditions">
                        ${condHtml}
                    </div>
                </div>
            `);

            $('#aica-canvas-placeholder').before($item);
        },

        buildConditionRowHtml(cond = {}) {
            // Source: free-text input + datalist for autocomplete (supports custom result_keys)
            const sourceInput = `<input type="text" list="aica-result-keys-datalist"
                class="aica-input aica-cond-source" value="${this.escHtml(cond.source || '')}"
                placeholder="result_key…" style="width:160px;">`;

            const operators = [
                ['contains',     'enthält'],
                ['not_contains', 'enthält nicht'],
                ['length_gt',    'Länge >'],
                ['length_lt',    'Länge <'],
            ];
            const opOpts = operators.map(([val, label]) =>
                `<option value="${val}" ${(cond.operator || 'contains') === val ? 'selected' : ''}>${label}</option>`
            ).join('');

            const actions = [['continue','weitermachen'],['skip','überspringen'],['stop','stoppen']];
            const matchOpts    = actions.map(([v,l]) => `<option value="${v}" ${(cond.on_match    || 'continue') === v ? 'selected' : ''}>${l}</option>`).join('');
            const noMatchOpts  = actions.map(([v,l]) => `<option value="${v}" ${(cond.on_no_match || 'continue') === v ? 'selected' : ''}>${l}</option>`).join('');

            return `
                <div class="aica-condition-row">
                    <span class="aica-condition-label">WENN</span>
                    ${sourceInput}
                    <select class="aica-select aica-cond-operator">${opOpts}</select>
                    <input type="text" class="aica-input aica-cond-value" value="${this.escHtml(cond.value || '')}" placeholder="Wert…">
                    <span class="aica-condition-label">→ Treffer:</span>
                    <select class="aica-select aica-cond-on-match">${matchOpts}</select>
                    <span class="aica-condition-label">Sonst:</span>
                    <select class="aica-select aica-cond-on-no-match">${noMatchOpts}</select>
                    <button type="button" class="aica-remove-condition aica-btn aica-btn-small aica-btn-danger" title="Bedingung entfernen">✕</button>
                </div>`;
        },

        addCondition(stepId) {
            const $item = $(`.aica-pipeline-step-item[data-step-id="${stepId}"]`);
            $item.find('.aica-step-conditions').append(this.buildConditionRowHtml());
        },

        removeStep(stepId) {
            $(`.aica-pipeline-step-item[data-step-id="${stepId}"]`).remove();
            this.steps = this.steps.filter(s => s.id !== stepId);
            this.updatePlaceholder();
            this.updateResultKeyDatalist();
        },

        updatePlaceholder() {
            const hasSteps = $('#aica-pipeline-canvas .aica-pipeline-step-item').length > 0;
            $('#aica-canvas-placeholder').toggle(!hasSteps);
        },

        // Aktualisiert die Datalist mit allen aktuell verwendeten result_keys
        // (Agent-Defaults + manuell geänderte). Dadurch sind Bedingungsquellen
        // immer auf dem aktuellen Stand, auch bei doppelten Agenten.
        updateResultKeyDatalist() {
            const keys = new Set();
            // Alle Agent-Defaults
            Object.values(this.agentMap).forEach(a => { if (a.result_key) keys.add(a.result_key); });
            // Aktuell im Builder eingetragene result_keys
            $('#aica-pipeline-canvas .aica-step-result-key').each(function() {
                const v = $(this).val().trim();
                if (v) keys.add(v);
            });
            const $dl = $('#aica-result-keys-datalist').empty();
            keys.forEach(k => $dl.append(`<option value="${$('<span>').text(k).html()}">`));
        },

        syncStepsFromDOM() {
            // Reihenfolge nach DOM aktualisieren
            const order = [];
            $('#aica-pipeline-canvas .aica-pipeline-step-item').each(function() {
                order.push($(this).data('step-id'));
            });
            this.steps.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
        },

        collectSteps() {
            const steps = [];
            $('#aica-pipeline-canvas .aica-pipeline-step-item').each((i, el) => {
                const $item   = $(el);
                const stepId  = $item.data('step-id');
                const agent   = $item.data('agent');
                const enabled = $item.find('.aica-step-enabled-toggle').prop('checked');

                const conditions = [];
                $item.find('.aica-condition-row').each((j, crow) => {
                    const $row = $(crow);
                    conditions.push({
                        source:      $row.find('.aica-cond-source').val(),
                        operator:    $row.find('.aica-cond-operator').val(),
                        value:       $row.find('.aica-cond-value').val(),
                        on_match:    $row.find('.aica-cond-on-match').val(),
                        on_no_match: $row.find('.aica-cond-on-no-match').val(),
                    });
                });

                const result_key = $item.find('.aica-step-result-key').val().trim();
                steps.push({ id: stepId, agent, enabled, conditions, result_key: result_key || null });
            });
            return steps;
        },

        savePipeline() {
            const name = $('#aica-pipeline-name').val().trim();
            if (!name) { alert('Bitte einen Namen eingeben.'); return; }

            const steps = this.collectSteps();
            if (!steps.length) { alert('Bitte mindestens einen Agenten hinzufügen.'); return; }

            const $btn = $('#aica-save-pipeline').prop('disabled', true).text('💾 ' + I18N.saving);

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:       'aica_save_pipeline',
                    nonce:         NONCE,
                    pipeline_id:  $('#aica-pipeline-id').val(),
                    name:          name,
                    description:  $('#aica-pipeline-description').val(),
                    steps:        JSON.stringify(steps),
                },
                success: (resp) => {
                    if (resp.success) { location.reload(); }
                    else alert(resp.data?.message || I18N.error);
                },
                complete: () => $btn.prop('disabled', false).text('💾 Pipeline speichern'),
            });
        },

        deletePipeline(id) {
            if (!confirm(I18N.confirm_del)) return;
            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data:   { action: 'aica_delete_pipeline', nonce: NONCE, pipeline_id: id },
                success: (resp) => {
                    if (resp.success) $(`#aica-pipeline-row-${id}`).fadeOut(400, function(){ $(this).remove(); });
                },
            });
        },

        openFromTemplate(el) {
            const name        = $(el).data('name') || 'Neue Pipeline';
            const description = $(el).data('description') || '';
            let   rawSteps    = [];
            try { rawSteps = JSON.parse($(el).attr('data-steps') || '[]'); } catch(e) {}

            // Step-IDs generieren
            const steps = rawSteps.map(s => ({
                id:         'step_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
                agent:      s.agent,
                enabled:    s.enabled !== false,
                conditions: [],
            }));

            this.openBuilder({ id: 0, name, description, steps });
        },

        escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        },
    };

    /* ============================================================
       Agent Manager (Custom Agents CRUD)
    ============================================================ */
    const AgentManager = {
        agents: [],

        init() {
            const rawData = document.getElementById('aica-custom-agents-data');
            if (!rawData) return;
            try { this.agents = JSON.parse(rawData.textContent); } catch(e) {}

            $('#aica-add-agent-btn').on('click', () => this.openModal());
            $(document).on('click', '.aica-edit-agent',   (e) => this.editAgent($(e.currentTarget).data('id')));
            $(document).on('click', '.aica-delete-agent', (e) => this.deleteAgent($(e.currentTarget).data('id')));
            $(document).on('click', '#aica-save-agent',   () => this.saveAgent());
            $(document).on('click', '#aica-agent-modal .aica-modal-close, #aica-agent-modal .aica-modal-backdrop', () => this.closeModal());

            // Capabilities: toggle show/hide bodies
            $(document).on('change', '#aica-cap-web-search', function() {
                $('#aica-cap-web-body').toggle($(this).prop('checked'));
            });
            $(document).on('change', '#aica-cap-git-repo', function() {
                $('#aica-cap-gitrepo-body').toggle($(this).prop('checked'));
            });

            // Populate git repo dropdown dynamically from aicaData
            this.populateGitRepoDropdown();
        },

        populateGitRepoDropdown() {
            const $sel = $('#aica-cap-git-repo-index');
            if (!$sel.length) return;
            const repos = aicaData.gitRepos || [];
            $sel.find('option:not([value=""])').remove();
            repos.forEach((repo, i) => {
                $sel.append(`<option value="${i}">${$('<span>').text(repo.name).html()} (${$('<span>').text(repo.path).html()})</option>`);
            });
        },

        openModal(agentId = null) {
            const $form = $('#aica-agent-form');
            if (!$form.length) return;

            $form[0].reset();
            $('#aica-agent-id').val(0);
            $('#aica-agent-modal-title').text('Neuen Agenten erstellen');
            $('#aica-cap-web-body, #aica-cap-gitrepo-body').hide();
            this.populateGitRepoDropdown();

            if (agentId) {
                const agent = this.agents.find(a => a.id == agentId);
                if (agent) {
                    $('#aica-agent-id').val(agent.id);
                    $('#aica-agent-modal-title').text('Agent bearbeiten: ' + agent.name);
                    $('#aica-agent-name').val(agent.name);
                    $('#aica-agent-icon').val(agent.icon);
                    $('#aica-agent-key').val(agent.agent_key);
                    $('#aica-agent-result-key').val(agent.result_key);
                    $('#aica-agent-description').val(agent.description || '');
                    $('#aica-agent-model').val(agent.model || 'claude-opus-4-6');
                    $('#aica-agent-max-tokens').val(agent.max_tokens || 2000);
                    $('#aica-agent-temperature').val(agent.temperature || 0.5);
                    $('#aica-agent-system-prompt').val(agent.system_prompt || '');

                    // Capabilities
                    const caps = agent.capabilities || {};
                    const webEnabled = caps.web_search && caps.web_search.enabled;
                    $('#aica-cap-web-search').prop('checked', !!webEnabled);
                    if (webEnabled) {
                        $('#aica-cap-web-urls').val((caps.web_search.urls || []).join('\n'));
                        $('#aica-cap-web-body').show();
                    }
                    $('#aica-cap-coding').prop('checked', !!caps.coding);
                    $('#aica-cap-claude-code').prop('checked', !!caps.claude_code);
                    const gitEnabled = caps.git_repo && caps.git_repo.enabled;
                    $('#aica-cap-git-repo').prop('checked', !!gitEnabled);
                    if (gitEnabled) {
                        $('#aica-cap-git-repo-index').val(caps.git_repo.repo_index ?? '');
                        $('#aica-cap-gitrepo-body').show();
                    }
                }
            }

            $('#aica-agent-modal').show();
        },

        closeModal() { $('#aica-agent-modal').hide(); },

        editAgent(id) { this.openModal(id); },

        deleteAgent(id) {
            if (!confirm(I18N.confirm_del)) return;
            $.ajax({
                url: AJAX_URL, method: 'POST',
                data: { action: 'aica_delete_agent', nonce: NONCE, agent_id: id },
                success: (resp) => {
                    if (resp.success) $(`#aica-agent-row-${id}`).fadeOut(400, function(){ $(this).remove(); });
                    else alert(resp.data?.message || I18N.error);
                }
            });
        },

        saveAgent() {
            const name = $('#aica-agent-name').val().trim();
            const key  = $('#aica-agent-key').val().trim();
            if (!name || !key) { alert('Name und Agent-Key sind Pflichtfelder.'); return; }

            // Capabilities zusammenbauen
            const caps = {};
            if ($('#aica-cap-web-search').prop('checked')) {
                const urlsRaw = $('#aica-cap-web-urls').val().trim();
                const urls = urlsRaw ? urlsRaw.split('\n').map(u => u.trim()).filter(u => u) : [];
                caps.web_search = { enabled: true, urls };
            }
            if ($('#aica-cap-coding').prop('checked')) {
                caps.coding = true;
            }
            if ($('#aica-cap-claude-code').prop('checked')) {
                caps.claude_code = true;
            }
            if ($('#aica-cap-git-repo').prop('checked')) {
                const repoIndex = $('#aica-cap-git-repo-index').val();
                caps.git_repo = { enabled: true, repo_index: repoIndex !== '' ? parseInt(repoIndex, 10) : 0 };
            }

            const $btn = $('#aica-save-agent').prop('disabled', true).text('💾 ' + I18N.saving);

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:         'aica_save_agent',
                    nonce:           NONCE,
                    agent_id:       $('#aica-agent-id').val(),
                    name:            name,
                    icon:           $('#aica-agent-icon').val() || '🤖',
                    agent_key:       key,
                    result_key:     $('#aica-agent-result-key').val().trim() || key + '_result',
                    description:    $('#aica-agent-description').val(),
                    model:          $('#aica-agent-model').val(),
                    max_tokens:     $('#aica-agent-max-tokens').val(),
                    temperature:    $('#aica-agent-temperature').val(),
                    system_prompt:  $('#aica-agent-system-prompt').val(),
                    capabilities:   JSON.stringify(caps),
                },
                success: (resp) => {
                    if (resp.success) { location.reload(); }
                    else alert(resp.data?.message || I18N.error);
                },
                complete: () => $btn.prop('disabled', false).text('💾 Speichern'),
            });
        },
    };

    /* ============================================================
       Builtin Agent Manager (Einstellungen für Standard-Agenten)
    ============================================================ */
    const BuiltinAgentManager = {

        init() {
            $(document).on('click', '.aica-edit-builtin-agent', (e) => {
                const key  = $(e.currentTarget).data('key');
                const name = $(e.currentTarget).data('name');
                this.openModal(key, name);
            });
            $(document).on('click', '#aica-builtin-agent-modal .aica-modal-close, #aica-builtin-agent-modal .aica-modal-backdrop', () => this.closeModal());
            $(document).on('click', '#aica-save-builtin-agent', () => this.saveSettings());
        },

        openModal(agentKey, agentName) {
            const settings = (aicaData.agentSettings && aicaData.agentSettings[agentKey]) || {};

            $('#aica-builtin-agent-modal-title').text('Einstellungen: ' + (agentName || agentKey));
            $('#aica-builtin-agent-key').val(agentKey);
            $('#aica-builtin-agent-model').val(settings.model || aicaData.defaultModel || 'claude-opus-4-6');
            $('#aica-builtin-agent-max-tokens').val(settings.max_tokens || 2000);
            $('#aica-builtin-agent-temperature').val(settings.temperature || 0.5);
            $('#aica-builtin-agent-system-prompt').val(settings.system_prompt || '');

            $('#aica-builtin-agent-modal').show();
        },

        closeModal() {
            $('#aica-builtin-agent-modal').hide();
        },

        saveSettings() {
            const $btn = $('#aica-save-builtin-agent').prop('disabled', true).text('💾 ' + I18N.saving);

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:        'aica_save_builtin_agent_settings',
                    nonce:          NONCE,
                    agent_key:     $('#aica-builtin-agent-key').val(),
                    model:         $('#aica-builtin-agent-model').val(),
                    max_tokens:    $('#aica-builtin-agent-max-tokens').val(),
                    temperature:   $('#aica-builtin-agent-temperature').val(),
                    system_prompt: $('#aica-builtin-agent-system-prompt').val(),
                },
                success: (resp) => {
                    if (resp.success) {
                        this.closeModal();
                        const $notice = $('<div class="notice notice-success is-dismissible"><p>✅ Einstellungen gespeichert!</p></div>');
                        $('.aica-header').after($notice);
                        setTimeout(() => $notice.slideUp(300, function(){ $(this).remove(); }), 2500);
                    } else {
                        alert(resp.data?.message || I18N.error);
                    }
                },
                complete: () => $btn.prop('disabled', false).text('💾 Speichern'),
            });
        },
    };

    /* ============================================================
       Reset Data
    ============================================================ */
    $('#aica-reset-data').on('click', function() {
        if (!confirm('Wirklich alle Content-Daten löschen? WordPress-Posts bleiben erhalten.')) return;
        // TODO: Implement reset AJAX action
        alert('Feature wird in einer zukünftigen Version implementiert.');
    });

    /* ============================================================
       Git Repository Settings (Einstellungsseite)
    ============================================================ */
    const GitRepoSettings = {
        rowCount: 0,

        init() {
            const $list = $('#aica-git-repos-list');
            if (!$list.length) return;

            this.rowCount = $list.find('.aica-git-repo-row').length;

            $('#aica-add-git-repo').on('click', () => this.addRow());
            $(document).on('click', '.aica-remove-git-repo', (e) => {
                $(e.currentTarget).closest('.aica-git-repo-row').remove();
            });

            // Vor dem Absenden des Formulars JSON-Feld befüllen
            $('form').on('submit', () => this.serializeToJson());
        },

        addRow() {
            const html = `<div class="aica-git-repo-row">
                <input type="text" class="aica-input aica-git-repo-name" placeholder="Name (z.B. Mein Projekt)" style="flex:1;">
                <input type="text" class="aica-input aica-git-repo-path" placeholder="Pfad (z.B. /var/www/html/meinprojekt)" style="flex:2;">
                <button type="button" class="aica-btn aica-btn-danger aica-remove-git-repo" style="flex:0 0 auto;">✕</button>
            </div>`;
            $('#aica-git-repos-list').append(html);
        },

        serializeToJson() {
            const repos = [];
            $('#aica-git-repos-list .aica-git-repo-row').each(function() {
                const name = $(this).find('.aica-git-repo-name').val().trim();
                const path = $(this).find('.aica-git-repo-path').val().trim();
                if (name && path) {
                    repos.push({ name, path });
                }
            });
            $('#aica-git-repos-json').val(JSON.stringify(repos));
        },
    };

    /* ============================================================
       Init
    ============================================================ */
    $(document).ready(function() {
        Generator.init();
        ApiTest.init();
        Tabs.init();
        VoiceManager.init();
        JobManager.init();
        ContentDetails.init();
        PipelineBuilder.init();
        AgentManager.init();
        BuiltinAgentManager.init();
        GitRepoSettings.init();

        // Modal-Backdrop close
        $(document).on('click', '.aica-modal-backdrop', function() {
            $(this).closest('.aica-modal').hide();
        });

        // Dismiss notices
        $(document).on('click', '.notice.is-dismissible .notice-dismiss', function() {
            $(this).closest('.notice').slideUp();
        });
    });

}(jQuery));
