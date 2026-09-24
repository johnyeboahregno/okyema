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
        };
    },
    computed: {
        userResults() { return this.results.filter(r => r.role === 'user'); },
        assistantResults() { return this.results.filter(r => r.role === 'assistant'); },
    },
    mounted() {
        this.theme = localStorage.getItem('okyema.theme') || 'system';
        this.applyTheme();
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
