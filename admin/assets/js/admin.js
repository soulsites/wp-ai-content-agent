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

        init() {
            const $form = $('#aica-generate-form');
            if (!$form.length) return;

            // Voice-Daten laden
            try {
                const raw = document.getElementById('aica-voice-data');
                if (raw) this.voiceData = JSON.parse(raw.textContent);
            } catch(e) {}

            $form.on('submit', (e) => { e.preventDefault(); this.start(); });
            $('#aica-generate-another').on('click', () => this.reset());
            $('#aica-voice').on('change', () => this.updateVoicePreview());
            this.updateVoicePreview();
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

            const $btn = $('#aica-submit-btn');
            $btn.prop('disabled', true);
            $('#aica-loading').show();
            $('#aica-status-panel').show();
            $('#aica-result, #aica-error').hide();
            this.lastLogCount = 0;

            // Agent-Status zurücksetzen
            $('[id^="agent-"]').removeClass('active done error')
                .find('.aica-pipeline-item-icon').text('⏳');

            $.ajax({
                url:    AJAX_URL,
                method: 'POST',
                data: {
                    action:      'aica_generate_content',
                    nonce:        NONCE,
                    topic:        topic,
                    keywords:     $('#aica-keywords').val(),
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
                error: () => this.showError(I18N.error)
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
                    if (!resp.success) return;
                    const d = resp.data;
                    this.updateLogs(d.logs || []);
                    this.updatePipelineStatus(d.logs || []);

                    if (d.status === 'completed') {
                        clearInterval(this.pollInterval);
                        this.showResult(d);
                    } else if (d.status === 'error') {
                        clearInterval(this.pollInterval);
                        this.showError(d.error_message || I18N.error);
                    }
                }
            });
        },

        updateLogs(logs) {
            if (logs.length <= this.lastLogCount) return;
            const $log = $('#aica-log-entries');
            const newLogs = logs.slice(this.lastLogCount);

            newLogs.forEach(log => {
                const time = log.created_at ? log.created_at.slice(11, 19) : '';
                $log.append(
                    `<div class="aica-log-entry ${log.level}">[${time}] [${log.agent}] ${$('<span>').text(log.message).html()}</div>`
                );
            });

            $log.scrollTop($log[0].scrollHeight);
            this.lastLogCount = logs.length;
        },

        updatePipelineStatus(logs) {
            const agentOrder = ['content_analyzer', 'audience_analyzer', 'keyword_researcher', 'researcher', 'content_writer'];
            const doneAgents = new Set();
            const activeAgent = logs.length ? logs[logs.length - 1].agent : null;

            logs.forEach(log => {
                if (log.message && log.message.includes('Abgeschlossen')) {
                    doneAgents.add(log.agent);
                }
            });

            agentOrder.forEach(key => {
                const $item = $(`#agent-${key}`);
                if (!$item.length) return;

                if (doneAgents.has(key)) {
                    $item.removeClass('active error').addClass('done');
                    $item.find('.aica-pipeline-item-icon').text('✅');
                } else if (key === activeAgent) {
                    $item.removeClass('done error').addClass('active');
                    $item.find('.aica-pipeline-item-icon').html('<span class="aica-spinner"></span>');
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
                    action:       'aica_save_job',
                    nonce:         NONCE,
                    job_id:        $('#aica-job-id').val(),
                    name:          $('#aica-job-name').val(),
                    topic:         $('#aica-job-topic').val(),
                    keywords:      $('#aica-job-keywords').val(),
                    schedule:      $('#aica-job-schedule').val(),
                    post_status:   $('#aica-job-post-status').val(),
                    voice_id:      $('#aica-job-voice').val(),
                    category_id:   $('#aica-job-category').val(),
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
       Reset Data
    ============================================================ */
    $('#aica-reset-data').on('click', function() {
        if (!confirm('Wirklich alle Content-Daten löschen? WordPress-Posts bleiben erhalten.')) return;
        // TODO: Implement reset AJAX action
        alert('Feature wird in einer zukünftigen Version implementiert.');
    });

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
