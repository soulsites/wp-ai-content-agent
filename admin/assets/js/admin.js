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
            };
            this.steps.push(step);
            this.renderStep(step);
            this.updatePlaceholder();
        },

        renderStep(step) {
            const info    = this.agentMap[step.agent] || { icon: '❓', name: step.agent };
            const enabled = step.enabled !== false;

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
                    <div class="aica-step-conditions">
                        ${condHtml}
                    </div>
                </div>
            `);

            $('#aica-canvas-placeholder').before($item);
        },

        buildConditionRowHtml(cond = {}) {
            const sourceOpts = Object.entries(this.sourceOptions).map(([val, label]) =>
                `<option value="${this.escHtml(val)}" ${cond.source === val ? 'selected' : ''}>${this.escHtml(label)}</option>`
            ).join('');

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
                    <select class="aica-select aica-cond-source">${sourceOpts}</select>
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
        },

        updatePlaceholder() {
            const hasSteps = $('#aica-pipeline-canvas .aica-pipeline-step-item').length > 0;
            $('#aica-canvas-placeholder').toggle(!hasSteps);
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

                steps.push({ id: stepId, agent, enabled, conditions });
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

            // Capabilities: Web-Search toggle shows/hides URL body
            $(document).on('change', '#aica-cap-web-search', function() {
                $('#aica-cap-web-body').toggle($(this).prop('checked'));
            });
        },

        openModal(agentId = null) {
            const $form = $('#aica-agent-form');
            if (!$form.length) return;

            $form[0].reset();
            $('#aica-agent-id').val(0);
            $('#aica-agent-modal-title').text('Neuen Agenten erstellen');
            $('#aica-cap-web-body').hide();

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
