<!doctype html>
<?php
$aiInfo = [
    'enabled' => (bool) config('okyema.ai.enabled'),
    'provider' => (string) config('okyema.ai.provider', ''),
    'model' => (string) config('okyema.ai.model', ''),
];
?>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Okyema</title>
<link rel="icon" href="<?= e($base) ?>/assets/favicon/favicon.ico" sizes="32x32">
<link rel="icon" href="<?= e($base) ?>/assets/favicon/favicon-32.png" sizes="32x32" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema-simple.css?v=<?= e(config('okyema.app.version')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body>
<div id="app" class="simple-app" v-cloak>
    <header class="simple-header">
        <img class="simple-header__logo simple-logo--light" src="<?= e($base) ?>/assets/logos/okyema-logo-light.svg" alt="Okyema">
        <img class="simple-header__logo simple-logo--dark" src="<?= e($base) ?>/assets/logos/okyema-logo-dark.svg" alt="Okyema">

        <div class="simple-header__actions">
            <button class="context-chip" type="button" @click.stop="contextMenu = !contextMenu" aria-haspopup="menu" :aria-expanded="contextMenu ? 'true' : 'false'" aria-label="Switch workspace context">
                <span class="context-chip__dot"></span>
                <span class="context-chip__label">{{ activeContext ? activeContext.name : '…' }}</span>
            </button>

            <button class="icon-btn" type="button" @click="toggleTheme" :aria-label="'Theme: ' + theme">◐</button>
            <button class="icon-btn" type="button" @click="openSettings" aria-label="Open settings">⚙</button>
        </div>

        <div class="context-menu" v-if="contextMenu" @click.stop role="menu">
            <button class="context-menu__item" v-for="c in contexts" :key="c.key"
                    :class="{'context-menu__item--active': c.is_active}"
                    type="button" role="menuitemradio" :aria-checked="c.is_active ? 'true' : 'false'"
                    @click="activateContext(c)">
                <span>{{ c.name }}</span>
                <span v-if="c.is_active" aria-hidden="true">✓</span>
            </button>
        </div>
    </header>

    <main class="simple-main">
        <p class="simple-notice" v-if="notice" role="status" @click="notice = ''">{{ notice }}</p>

        <nav class="page-dots" aria-label="Pages">
            <button class="page-dot" v-for="(p, i) in pages" :key="p.key" type="button"
                    :class="{'is-active': i === currentPage}"
                    :aria-label="p.label" :aria-current="i === currentPage ? 'page' : undefined"
                    @click="goToPage(i)">
                <svg v-if="p.key === 'ask'" class="page-dot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                <svg v-else-if="p.key === 'notes'" class="page-dot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                <svg v-else-if="p.key === 'calendar'" class="page-dot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <svg v-else-if="p.key === 'transcripts'" class="page-dot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <svg v-else class="page-dot__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
            </button>
        </nav>

        <div class="pages" ref="pages" @scroll.passive="onPagesScroll">
            <section class="page page--ask">
                <section class="simple-main__results" aria-live="polite">
                    <p class="empty-hint" v-if="!results.length && !loading">Ask a question, find a detail or get something moving.</p>

                    <div class="message message--user" v-for="m in userResults" :key="m.id">
                        <div class="message__bubble">{{ m.text }}</div>
                    </div>

                    <template v-for="m in assistantResults" :key="m.id">
                        <div class="message message--assistant">
                            <div class="message__bubble">{{ m.text }}</div>
                            <div class="sources" v-if="m.sources && m.sources.length">
                                <a class="source" v-for="s in m.sources" :key="s.url" :href="s.url" target="_blank" rel="noopener noreferrer">↗ {{ s.title }}</a>
                            </div>

                            <div class="approval" v-if="m.approval && !m.approvalState">
                                <div class="approval__title">{{ m.approval.title }}</div>
                                <div class="approval__summary">This change needs your approval before it is made.</div>
                                <div class="approval__actions">
                                    <button class="btn btn--primary" type="button" @click="approve(m)">Approve</button>
                                    <button class="btn btn--ghost" type="button" @click="reject(m)">Cancel</button>
                                </div>
                            </div>
                            <div class="approval__state" v-if="m.approvalState === 'approved'">✓ Change made.</div>
                            <div class="approval__state" v-if="m.approvalState === 'cancelled'">Change cancelled.</div>
                            <div class="error-bar" v-if="m.approvalState === 'failed'">{{ m.approvalError || 'The change could not be made.' }}</div>

                            <div class="notice-bar" v-if="m.notice">{{ m.notice }}</div>
                        </div>
                    </template>

                    <div class="error-bar" v-if="error">{{ error }}</div>
                </section>

                <section class="simple-main__composer">
                    <div class="composer">
                        <h1>What’s on your mind<em>?</em></h1>
                        <p class="composer__sub">Type it. Say it. Okyema takes it from there.</p>

                        <textarea id="ask" ref="ask" v-model="query" aria-label="Ask Okyema"
                                  placeholder="Ask Okyema anything…"
                                  @keydown.enter.exact.prevent="send()"></textarea>

                        <div class="transcript-note" v-if="transcribed && query">
                            <span>Transcribed — review and edit, then send.</span>
                        </div>

                        <div class="composer__foot">
                            <span class="composer__status" :class="{'is-live': loading}">{{ loading ? 'Thinking…' : '✦ Assistant ready' }}</span>
                            <button class="btn btn--primary" type="button" :disabled="loading || !query.trim()" @click="send()">Send ↑</button>
                        </div>

                        <button class="mic-btn" type="button" :class="{'is-recording': recording}"
                                :aria-label="recording ? 'Stop recording' : 'Start voice input'"
                                @click="startVoice">
                            <span aria-hidden="true">🎤</span>
                            <span>{{ recording ? 'Listening… tap to stop' : 'Voice' }}</span>
                        </button>

                        <div class="error-bar" v-if="voiceError">{{ voiceError }}</div>

                        <div class="suggestions" v-if="!results.length && !loading">
                            <button class="suggestion" type="button" @click="suggest('Plan my day')">✦ Plan my day</button>
                            <button class="suggestion" type="button" @click="suggest('What did we decide in the last meeting?')">◷ Last meeting</button>
                            <button class="suggestion" type="button" @click="suggest('Find my outstanding actions')">✓ My actions</button>
                        </div>
                    </div>
                </section>
            </section>

            <section class="page page--notes">
                <div class="composer">
                    <div class="page__head">
                        <h2>Note Taker</h2>
                        <button class="modal__close" type="button" aria-label="Back to ask" @click="goToPage(0)">×</button>
                    </div>

                    <label class="field"><span>Title</span><input v-model="recorderTitle" placeholder="e.g. Weekly team sync"></label>

                    <div class="recorder">
                        <button class="recorder__btn" type="button" :class="{'is-recording': recorderActive}" @click="toggleRecorder">
                            <span v-if="!recorderActive">● Start</span>
                            <span v-else>■ Stop</span>
                        </button>
                        <span class="recorder__time" v-if="recorderActive">{{ recorderTime }}</span>
                    </div>

                    <p class="readonly-note" v-if="recorderStatus">{{ recorderStatus }}</p>
                    <p class="readonly-note">Audio is transcribed on-device (Whisper) and filed as a meeting with a transcript note. The speech model downloads once on first use.</p>
                </div>
            </section>

            <section class="page page--calendar">
                <div class="page__head">
                    <h2>Calendar</h2>
                    <p class="muted">{{ calendarDate }}</p>
                </div>

                <p class="empty-hint" v-if="!calendarEvents.length && !transcripts.length">Nothing scheduled today.</p>

                <div class="agenda__item" v-for="e in calendarEvents" :key="'event-' + e.id">
                    <span class="agenda__time">{{ e.is_all_day ? 'All day' : e.time_label }}</span>
                    <div class="agenda__body">
                        <div class="agenda__title">{{ e.title }}</div>
                        <div class="agenda__meta" v-if="e.location">{{ e.location }}</div>
                    </div>
                </div>

                <h3 class="section-title" v-if="transcripts.length">Recordings</h3>

                <div class="agenda__item" v-for="t in transcripts" :key="'recording-' + t.id">
                    <span class="agenda__time">{{ t.starts_at ? timeLabel(t.starts_at) : 'Recording' }}</span>
                    <div class="agenda__body">
                        <div class="agenda__title">{{ t.title }}</div>
                        <div class="agenda__meta">{{ t.transcript }}</div>
                    </div>
                </div>
            </section>

            <section class="page page--transcripts">
                <div class="page__head">
                    <h2>Transcripts</h2>
                </div>

                <p class="empty-hint" v-if="!transcripts.length">No recorded meetings yet. Record one from the Note Taker page.</p>

                <div class="transcript" v-for="t in transcripts" :key="t.id" @click="toggleTranscript(t.id)">
                    <div class="transcript__head">
                        <div class="transcript__title">{{ t.title }}</div>
                        <div class="transcript__actions" @click.stop>
                            <button class="transcript__action" type="button" :aria-label="copiedId === t.id ? 'Copied' : 'Copy transcript'" @click="copyTranscript(t)">
                                <svg v-if="copiedId !== t.id" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                            <button class="transcript__action" type="button" aria-label="Share transcript" @click="shareTranscript(t)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            </button>
                            <button class="transcript__action transcript__action--danger" type="button" aria-label="Delete transcript" @click="deleteTranscript(t)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="transcript__meta" v-if="expandedTranscript !== t.id">Tap to read</div>
                    <template v-if="expandedTranscript === t.id">
                        <div v-if="t.formatted_html" class="transcript__html" v-html="t.formatted_html"></div>
                        <div v-else class="transcript__body">{{ t.transcript }}</div>
                    </template>
                </div>
            </section>

            <section class="page page--actions">
                <div class="page__head">
                    <h2>Actions</h2>
                </div>

                <p class="empty-hint" v-if="!actions.length">No actions today.</p>

                <div class="action" v-for="a in actions" :key="a.id">
                    <div class="action__title">{{ a.title }}</div>
                    <div class="action__meta" v-if="a.due_date">Due {{ a.due_date }}</div>
                </div>
            </section>
        </div>
    </main>

    <!-- Settings -->
    <div class="modal-backdrop" v-if="settingsOpen" @click.self="settingsOpen = false">
        <div class="modal" role="dialog" aria-modal="true" aria-label="Settings" @keydown.esc="settingsOpen = false">
            <div class="modal__head">
                <h2>Settings</h2>
                <button class="modal__close" type="button" aria-label="Close settings" @click="settingsOpen = false">×</button>
            </div>

            <form @submit.prevent="saveSettings">
                <label class="field"><span>Display name</span><input v-model="settings.display_name"></label>
                <label class="field"><span>Timezone</span><input v-model="settings.timezone" placeholder="Europe/London"></label>
                <button class="btn btn--primary" type="submit">Save profile</button>
            </form>

            <h3 class="section-title">Connections</h3>

            <div class="connection-row" v-for="c in connectors" :key="c.id">
                <div>
                    <div>{{ c.provider }}</div>
                    <div class="muted">{{ c.status }} · {{ (c.capabilities || []).join(', ') }}</div>
                </div>
                <button v-if="c.status === 'connected'" class="btn btn--ghost" type="button" @click="disconnectConnector(c)">Disconnect</button>
                <span v-else class="state">{{ c.status }}</span>
            </div>

            <div class="connection-row" v-if="!hasConnector('google')">
                <span>Google Calendar</span>
                <button class="btn btn--primary" type="button" @click="connectConnector('google')">Connect</button>
            </div>
            <div class="connection-row" v-if="!hasConnector('microsoft')">
                <span>Outlook Calendar</span>
                <button class="btn btn--primary" type="button" @click="connectConnector('microsoft')">Connect</button>
            </div>
            <div class="connection-row" v-if="!hasConnector('notion')">
                <span>Notion</span>
                <button class="btn btn--primary" type="button" @click="connectConnector('notion')">Connect</button>
            </div>

            <h3 class="section-title">AI provider</h3>
            <p class="readonly-note">
                {{ ai.enabled ? 'Live answers are on (' + ai.provider + (ai.model ? ' · ' + ai.model : '') + ').' : 'Live answers are off — configure AI_ENABLED, AI_PROVIDER and AI_MODEL on the deployment.' }}
            </p>

            <p class="readonly-note">Okyema v<?= e(config('okyema.app.version')) ?> · Powered by Regno AI</p>
        </div>
    </div>

    <!-- Delete transcript confirmation -->
    <div class="modal-backdrop" v-if="deleteTarget" @click.self="cancelDelete">
        <div class="modal" role="dialog" aria-modal="true" aria-label="Delete transcript" @keydown.esc="cancelDelete">
            <div class="modal__head">
                <h2>Delete transcript?</h2>
                <button class="modal__close" type="button" aria-label="Close" @click="cancelDelete">×</button>
            </div>

            <p class="readonly-note">This will permanently delete "{{ deleteTarget.title }}". This cannot be undone.</p>

            <div class="modal__actions">
                <button class="btn btn--ghost" type="button" @click="cancelDelete">Cancel</button>
                <button class="btn btn--danger" type="button" @click="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/vue@3.4.38/dist/vue.global.prod.js"></script>
<script>
const BASE_URL = <?= json_encode($base) ?>;

function csrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function api(method, path, body) {
    const init = { method, headers: {}, credentials: 'include' };
    const token = csrfToken();
    if (token) init.headers['X-XSRF-TOKEN'] = token;
    if (body !== undefined) {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    }
    const res = await fetch(BASE_URL + path, init);
    if (res.status === 204) return null;
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || 'Request failed');
    return json.data;
}

let recognition = null;
let recorderTimer = null;
let recorder = null;
let transcriber = null;

async function getTranscriber() {
    if (transcriber) return transcriber;
    const { pipeline } = await import('https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.3.3');
    transcriber = await pipeline('automatic-speech-recognition', 'Xenova/whisper-base.en');
    return transcriber;
}

Vue.createApp({
    data() {
        return {
            me: { name: '', email: '', profile: null },
            contexts: [],
            activeContext: null,
            contextMenu: false,
            query: '',
            transcribed: false,
            results: [],
            loading: false,
            error: '',
            recording: false,
            voiceError: '',
            theme: 'system',
            settingsOpen: false,
            settings: { display_name: '', timezone: '' },
            connectors: [],
            ai: <?= json_encode($aiInfo) ?>,
            notice: <?= json_encode($connectorNotice ?? '') ?>,
            currentPage: 0,
            pages: [
                { key: 'ask', label: "What's on your mind" },
                { key: 'notes', label: 'Note Taker' },
                { key: 'calendar', label: 'Calendar' },
                { key: 'transcripts', label: 'Transcripts' },
                { key: 'actions', label: 'Actions' },
            ],
            calendarEvents: [],
            calendarDate: '',
            actions: [],
            transcripts: [],
            expandedTranscript: null,
            copiedId: null,
            deleteTarget: null,
            recorderTitle: '',
            recordingStartedAt: '',
            recorderActive: false,
            recorderSeconds: 0,
            recorderStatus: '',
        };
    },
    computed: {
        userResults() { return this.results.filter(r => r.role === 'user'); },
        assistantResults() { return this.results.filter(r => r.role === 'assistant'); },
        recorderTime() {
            const m = Math.floor(this.recorderSeconds / 60);
            const s = String(this.recorderSeconds % 60).padStart(2, '0');
            return m + ':' + s;
        },
    },
    mounted() {
        this.theme = localStorage.getItem('okyema.theme') || 'system';
        this.applyTheme();
        this.loadCalendar();
        this.loadActions();
        this.loadTranscripts();
        document.addEventListener('click', this.onDocumentClick);
        this.loadAll()
            .then(() => {
                // Connector OAuth returns here with ?tab=settings — open the
                // settings modal so the result notice and connections show.
                if (new URLSearchParams(window.location.search).get('tab') === 'settings') {
                    this.openSettings();
                }
            })
            .catch(() => { window.location.href = BASE_URL + '/login'; });
    },
    beforeUnmount() {
        document.removeEventListener('click', this.onDocumentClick);
    },
    methods: {
        async loadAll() {
            const [me, contexts] = await Promise.all([
                api('GET', '/api/me'),
                api('GET', '/api/contexts'),
            ]);
            this.me = me;
            this.contexts = contexts;
            this.activeContext = contexts.find(c => c.is_active) || contexts[0] || null;
        },
        onDocumentClick() {
            this.contextMenu = false;
        },
        async activateContext(context) {
            try {
                const path = context.id === null
                    ? '/api/contexts/all/activate'
                    : `/api/contexts/${context.id}/activate`;
                await api('POST', path);
                this.activeContext = context;
                this.contexts = this.contexts.map(c => ({ ...c, is_active: c.id === context.id }));
                this.contextMenu = false;
            } catch (e) {
                this.error = e.message || 'Could not switch workspace.';
            }
        },
        suggest(text) {
            this.query = text;
            this.$nextTick(() => this.$refs.ask && this.$refs.ask.focus());
        },
        async send() {
            const text = (this.query || '').trim();
            if (!text || this.loading) return;
            this.query = '';
            this.transcribed = false;
            this.voiceError = '';
            this.error = '';
            this.results.push({ id: Date.now(), role: 'user', text });
            this.loading = true;
            try {
                const data = await api('POST', '/api/assistant', {
                    request: text,
                    workspace_context_id: this.activeContext && this.activeContext.id !== null ? this.activeContext.id : null,
                });
                this.results.push({
                    id: Date.now() + 1,
                    role: 'assistant',
                    text: data.answer,
                    sources: data.sources || [],
                    approval: data.approval || null,
                    notice: data.notice || null,
                    approvalState: null,
                    approvalError: '',
                });
            } catch (e) {
                this.error = e.message || 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        startVoice() {
            if (this.recording) { this.stopVoice(); return; }

            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                this.voiceError = 'Voice input is not supported in this browser. You can type your request instead.';
                return;
            }

            this.voiceError = '';
            recognition = new SR();
            recognition.lang = navigator.language || 'en-GB';
            recognition.interimResults = false;

            recognition.onresult = (e) => {
                const transcript = (e.results && e.results[0] && e.results[0][0] && e.results[0][0].transcript) || '';
                if (transcript) { this.query = transcript; this.transcribed = true; }
            };
            recognition.onend = () => { recognition = null; this.recording = false; };
            recognition.onerror = (e) => {
                this.voiceError = this.voiceErrorMessage(e.error);
                recognition = null;
                this.recording = false;
            };

            try {
                recognition.start();
                this.recording = true;
            } catch (e) {
                this.voiceError = 'Could not start the microphone. Please check permission and try again.';
                this.recording = false;
            }
        },
        stopVoice() {
            if (recognition) { try { recognition.stop(); } catch (e) { /* ignore */ } }
        },
        voiceErrorMessage(code) {
            switch (code) {
                case 'not-allowed':
                case 'service-not-allowed':
                    return 'Microphone access was denied. Allow microphone permission and try again.';
                case 'no-speech':
                    return 'I did not hear anything. Please try speaking again.';
                case 'audio-capture':
                    return 'No microphone was found. You can type your request instead.';
                case 'network':
                    return 'Speech recognition failed on the network. Please try again.';
                default:
                    return 'Could not capture your voice. Please try again or type your request.';
            }
        },
        async approve(m) {
            try {
                const data = await api('POST', `/api/approvals/${m.approval.id}/approve`);
                m.approvalState = data && data.ok === false ? 'failed' : 'approved';
                m.approvalError = data && data.ok === false ? data.reason : '';
            } catch (e) {
                m.approvalState = 'failed';
                m.approvalError = e.message || 'Could not approve.';
            }
        },
        async reject(m) {
            try {
                await api('POST', `/api/approvals/${m.approval.id}/reject`);
                m.approvalState = 'cancelled';
            } catch (e) {
                m.approvalState = 'failed';
                m.approvalError = e.message || 'Could not cancel.';
            }
        },
        openSettings() {
            this.settingsOpen = true;
            this.settings = {
                display_name: (this.me.profile && this.me.profile.display_name) || '',
                timezone: (this.me.profile && this.me.profile.timezone) || '',
            };
            this.loadConnectors();
        },
        async saveSettings() {
            try {
                const profile = await api('PATCH', '/api/profile', this.settings);
                this.me.profile = profile;
                this.settings = { display_name: profile.display_name || '', timezone: profile.timezone || '' };
            } catch (e) {
                this.error = e.message || 'Could not save.';
            }
        },
        async loadConnectors() {
            try { this.connectors = await api('GET', '/api/connectors'); } catch (e) { /* ignore */ }
        },
        hasConnector(provider) {
            return (this.connectors || []).some(c => c.provider === provider && c.status === 'connected');
        },
        connectConnector(provider) {
            window.location.href = BASE_URL + '/connectors/' + provider + '/redirect';
        },
        async disconnectConnector(c) {
            try {
                await api('DELETE', `/api/connectors/${c.id}`);
                await this.loadConnectors();
            } catch (e) {
                this.error = e.message || 'Could not disconnect.';
            }
        },
        goToPage(i) {
            const el = this.$refs.pages;
            if (!el) return;
            el.scrollTo({ left: i * el.clientWidth, behavior: 'smooth' });
            this.currentPage = i;
        },
        onPagesScroll() {
            const el = this.$refs.pages;
            if (!el || !el.clientWidth) return;
            this.currentPage = Math.round(el.scrollLeft / el.clientWidth);
        },
        async loadCalendar() {
            try {
                const data = await api('GET', '/api/agenda');
                this.calendarEvents = (data && data.events) || [];
                this.calendarDate = (data && data.date) || '';
            } catch (e) { /* ignore */ }
        },
        async loadActions() {
            try {
                this.actions = await api('GET', '/api/actions?view=today');
            } catch (e) { /* ignore */ }
        },
        async loadTranscripts() {
            try {
                this.transcripts = await api('GET', '/api/transcripts');
            } catch (e) { /* ignore */ }
        },
        timeLabel(iso) {
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return '';
            return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        },
        toggleTranscript(id) {
            this.expandedTranscript = this.expandedTranscript === id ? null : id;
        },
        deleteTranscript(t) {
            this.deleteTarget = t;
        },
        cancelDelete() {
            this.deleteTarget = null;
        },
        async confirmDelete() {
            const t = this.deleteTarget;
            if (!t) return;
            this.deleteTarget = null;
            try {
                await api('DELETE', `/api/transcripts/${t.id}`);
                this.transcripts = this.transcripts.filter(x => x.id !== t.id);
                if (this.expandedTranscript === t.id) this.expandedTranscript = null;
            } catch (e) {
                this.error = e.message || 'Could not delete.';
            }
        },
        async copyTranscript(t) {
            try {
                await navigator.clipboard.writeText(t.transcript || '');
                this.copiedId = t.id;
                setTimeout(() => { if (this.copiedId === t.id) this.copiedId = null; }, 1500);
            } catch (e) {
                this.error = 'Could not copy.';
            }
        },
        async shareTranscript(t) {
            if (!navigator.share) {
                this.notice = 'Sharing is not supported on this device — use Copy.';
                return;
            }
            try {
                await navigator.share({ title: t.title, text: t.transcript || '' });
            } catch (e) {
                if (e && e.name !== 'AbortError') {
                    this.notice = 'Could not share.';
                }
            }
        },
        toggleRecorder() {
            if (this.recorderActive) this.stopRecorder();
            else this.startRecorder();
        },
        async startRecorder() {
            this.recorderStatus = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.recorderStatus = 'Recording is not supported in this browser.';
                return;
            }
            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.recorderStatus = 'Microphone access was denied. Please allow it and try again.';
                return;
            }

            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx || !AudioCtx.prototype.createScriptProcessor) {
                stream.getTracks().forEach(t => t.stop());
                this.recorderStatus = 'Recording is not supported in this browser.';
                return;
            }

            const audioCtx = new AudioCtx();
            const source = audioCtx.createMediaStreamSource(stream);
            const processor = audioCtx.createScriptProcessor(4096, 1, 1);
            const chunks = [];

            processor.onaudioprocess = (e) => {
                const channel = e.inputBuffer.getChannelData(0);
                if (channel && channel.length) chunks.push(new Float32Array(channel));
            };

            source.connect(processor);
            const mute = audioCtx.createGain();
            mute.gain.value = 0;
            processor.connect(mute);
            mute.connect(audioCtx.destination);

            recorder = { audioCtx, chunks, stream, sampleRate: audioCtx.sampleRate };
            this.recordingStartedAt = new Date().toISOString();

            this.recorderActive = true;
            this.recorderSeconds = 0;
            recorderTimer = setInterval(() => { this.recorderSeconds += 1; }, 1000);
        },
        stopRecorder() {
            if (recorderTimer) { clearInterval(recorderTimer); recorderTimer = null; }
            if (!recorder) return;

            const { audioCtx, chunks, stream, sampleRate } = recorder;
            recorder = null;
            this.recorderActive = false;

            stream.getTracks().forEach(t => t.stop());
            try { audioCtx.close(); } catch (e) { /* ignore */ }

            this.transcribePcm(chunks, sampleRate);
        },
        async transcribePcm(chunks, sampleRate) {
            if (!chunks.length) {
                this.recorderStatus = 'No audio was captured.';
                return;
            }

            const total = chunks.reduce((n, c) => n + c.length, 0);
            const pcm = new Float32Array(total);
            let offset = 0;
            for (const c of chunks) { pcm.set(c, offset); offset += c.length; }

            this.recorderStatus = 'Loading speech model… (first use may take a minute)';
            const transcriber = await getTranscriber();
            this.recorderStatus = 'Transcribing…';

            const audio = await this.resample(pcm, sampleRate, 16000);
            const output = await transcriber(audio);
            const transcript = ((output && output.text) || '').trim();

            if (!transcript) {
                this.recorderStatus = 'No speech was detected.';
                return;
            }

            await api('POST', '/api/transcripts', {
                title: (this.recorderTitle || '').trim() || 'Meeting recording',
                transcript,
                starts_at: this.recordingStartedAt || null,
                ends_at: new Date().toISOString(),
            });
            this.recorderStatus = 'Saved — transcript filed as a meeting.';
            this.recorderTitle = '';
        },
        resample(pcm, fromRate, toRate) {
            if (fromRate === toRate) return Promise.resolve(pcm);
            const length = Math.max(1, Math.ceil(pcm.length * toRate / fromRate));
            const offline = new OfflineAudioContext(1, length, toRate);
            const buffer = offline.createBuffer(1, pcm.length, fromRate);
            buffer.copyToChannel(pcm, 0);
            const source = offline.createBufferSource();
            source.buffer = buffer;
            source.connect(offline.destination);
            source.start();
            return offline.startRendering().then(rendered => rendered.getChannelData(0));
        },
        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : this.theme === 'dark' ? 'system' : 'light';
            this.applyTheme();
        },
        applyTheme() {
            const stored = this.theme;
            localStorage.setItem('okyema.theme', stored);
            const dark = stored === 'dark' || (stored === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            const themeColor = document.querySelector('meta[name="theme-color"]');
            if (themeColor) themeColor.setAttribute('content', dark ? '#0B1020' : '#F5F7FB');
        },
        async logout() {
            try { await api('POST', '/api/logout'); } catch (e) { /* ignore */ }
            window.location.href = BASE_URL + '/login';
        },
    },
}).mount('#app');
</script>
</body>
</html>
