<!doctype html>
<?php
$aiInfo = [
    'enabled' => (bool) config('okyema.ai.enabled'),
    'provider' => (string) config('okyema.ai.provider', ''),
    'model' => (string) config('okyema.ai.model', ''),
];
// Keep in sync with the "request" validation in AssistantController.
$maxRequest = 60000;
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
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema-simple.css?v=<?= e(config('okyema.app.version')) ?>-<?= (int) @filemtime(public_path('css/okyema-simple.css')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body>
<div id="app" class="big-app" v-cloak @dragover.prevent @drop.prevent="onDrop">
    <header class="big-header">
        <div class="big-brand">
            <svg class="big-mark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <g stroke="#43C7A7" stroke-width="2.4" stroke-linecap="round">
                    <path d="M12 12 24 24 36 12"/>
                    <path d="M12 36 24 24 36 36"/>
                    <path d="M24 8v32"/>
                </g>
                <g fill="#43C7A7">
                    <circle cx="12" cy="12" r="4.2"/>
                    <circle cx="36" cy="12" r="4.2"/>
                    <circle cx="12" cy="36" r="4.2"/>
                    <circle cx="36" cy="36" r="4.2"/>
                    <circle cx="24" cy="8" r="4.2"/>
                    <circle cx="24" cy="40" r="4.2"/>
                    <circle cx="24" cy="24" r="5.2"/>
                </g>
            </svg>
            <div class="big-wordmark">
                <span class="big-wordmark__name">OKYEMA</span>
                <span class="big-wordmark__tag"><?= e((string) config('okyema.app.tagline', 'Your intelligent chief of staff')) ?></span>
            </div>
        </div>

        <div class="big-header__actions">
            <button class="big-theme" type="button" @click="toggleTheme" :aria-label="'Theme: ' + theme">◐</button>
            <button class="big-signout" type="button" @click="logout" aria-label="Sign out">
                <svg class="big-signout__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </header>

    <main class="big-main">
        <p class="big-notice" v-if="notice" role="status" @click="notice = ''">{{ notice }}</p>

        <h1 class="big-headline">Ready!</h1>
        <p class="big-sub">{{ subtext }}</p>

        <div class="big-inputrow">
            <textarea class="big-input" rows="1" v-model="query" aria-label="Ask Okyema"
                      placeholder="Type, paste or drop a document…"
                      @keydown.enter.exact.prevent="send()"
                      @input="hint = ''"></textarea>
            <label class="big-attach" aria-label="Attach documents">
                <svg class="big-attach__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <input type="file" multiple accept=".txt,.md,.csv,.json,.log,.html,.htm,.rtf,.xml,.yaml,.yml,.ts,.js,.css,.php,.env,text/plain,text/markdown,text/csv,application/json" hidden @change="onFiles">
            </label>
        </div>

        <div class="big-chips" v-if="files.length">
            <span class="big-chip" v-for="(f, i) in files" :key="f.name + i">
                📄 {{ f.name }}
                <button class="big-chip__x" type="button" :aria-label="'Remove ' + f.name" @click="removeFile(i)">×</button>
            </span>
        </div>

        <div class="big-orb-zone">
            <button class="big-orb" type="button" :class="{ 'is-recording': recorderActive }" :disabled="loading"
                    :aria-label="recorderActive ? 'Recording — let go to send' : 'Hold to talk, tap to send'"
                    @pointerdown="beginHold($event)"
                    @pointerup="endHold"
                    @pointercancel="endHold"
                    @contextmenu.prevent>
                <span class="big-orb__halo"></span>
                <span class="big-orb__ring">
                    <svg v-if="!loading && !recorderActive" class="big-orb__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    <span v-else-if="loading" class="big-orb__dots">…</span>
                    <span v-else class="big-orb__rec">●</span>
                </span>
            </button>
        </div>

        <div class="big-connect">
            <span class="big-connect__ok" v-if="googleConnected">✓ Google Calendar connected</span>
            <button class="big-connect__btn" type="button" v-else @click="connectGoogle">
                <svg class="big-connect__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Connect Google Calendar
            </button>
        </div>

        <div class="big-results" v-if="results.length || error" aria-live="polite">
            <div class="big-msg big-msg--user" v-for="m in userResults" :key="m.id">{{ m.text }}</div>

            <template v-for="m in assistantResults" :key="m.id">
                <div class="big-msg big-msg--assistant">{{ m.text }}</div>

                <div class="big-sources" v-if="m.sources && m.sources.length">
                    <a v-for="s in m.sources" :key="s.url" :href="s.url" target="_blank" rel="noopener noreferrer">↗ {{ s.title }}</a>
                </div>

                <div class="big-approval" v-if="m.approval && !m.approvalState">
                    <div class="big-approval__title">{{ m.approval.title }}</div>
                    <div class="big-approval__summary">This change needs your approval before it is made.</div>
                    <div class="big-approval__actions">
                        <button class="btn btn--primary" type="button" @click="approve(m)">Approve</button>
                        <button class="btn btn--ghost" type="button" @click="reject(m)">Cancel</button>
                    </div>
                </div>
                <div class="big-state" v-if="m.approvalState === 'approved'">✓ Change made.</div>
                <div class="big-state" v-if="m.approvalState === 'cancelled'">Change cancelled.</div>
                <div class="big-error" v-if="m.approvalState === 'failed'">{{ m.approvalError || 'The change could not be made.' }}</div>

                <div class="big-state" v-if="m.notice">{{ m.notice }}</div>
            </template>

            <div class="big-error" v-if="error">{{ error }}</div>
        </div>
    </main>
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

let recorder = null;
let recorderTimer = null;
let holdTimer = null;
let holdCancelled = false;
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
            connectors: [],
            notice: <?= json_encode($connectorNotice ?? '') ?>,
            query: '',
            files: [],
            results: [],
            loading: false,
            error: '',
            hint: '',
            recorderActive: false,
            recorderStatus: '',
            theme: 'system',
            maxChars: <?= (int) $maxRequest ?>,
        };
    },
    computed: {
        userResults() { return this.results.filter(r => r.role === 'user'); },
        assistantResults() { return this.results.filter(r => r.role === 'assistant'); },
        googleConnected() {
            return (this.connectors || []).some(c => c.provider === 'google' && c.status === 'connected');
        },
        subtext() {
            if (this.recorderActive) return 'Keep holding to record…';
            if (this.loading) return 'Thinking…';
            if (this.recorderStatus) return this.recorderStatus;
            if (this.hint) return this.hint;
            return '';
        },
    },
    mounted() {
        this.theme = localStorage.getItem('okyema.theme') || 'system';
        this.applyTheme();
        this.loadAll()
            .catch(() => { window.location.href = BASE_URL + '/login'; });
    },
    methods: {
        async loadAll() {
            const [me, contexts, connectors] = await Promise.all([
                api('GET', '/api/me'),
                api('GET', '/api/contexts'),
                api('GET', '/api/connectors'),
            ]);
            this.me = me;
            this.contexts = contexts;
            this.connectors = connectors;
            this.activeContext = contexts.find(c => c.is_active) || contexts[0] || null;
        },
        connectGoogle() {
            window.location.href = BASE_URL + '/connectors/google/redirect';
        },
        composedRequest() {
            const parts = [];
            const typed = (this.query || '').trim();
            if (typed) parts.push(typed);
            for (const f of this.files) {
                const content = (f.text || '').trim();
                if (content) parts.push('--- ' + f.name + ' ---\n' + content);
            }
            let text = parts.join('\n\n');
            if (text.length > this.maxChars) text = text.slice(0, this.maxChars);
            return text;
        },
        send() {
            const text = this.composedRequest().trim();
            if (!text) {
                this.hint = 'Hold the button to talk, or type something first.';
                return;
            }
            this.askWith(text);
        },
        async askWith(text) {
            if (this.loading) return;
            this.error = '';
            this.hint = '';
            const preview = text.length > 400 ? text.slice(0, 400) + '…' : text;
            this.results.push({ id: Date.now(), role: 'user', text: preview });
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
                this.query = '';
                this.files = [];
            } catch (e) {
                this.error = e.message || 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },
        beginHold(e) {
            if (this.loading) return;
            if (e && e.target && e.target.setPointerCapture) {
                try { e.target.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
            }
            this.hint = '';
            this.error = '';
            this.recorderStatus = '';
            holdCancelled = false;
            holdTimer = setTimeout(() => { this.startRecorder(); }, 280);
        },
        endHold() {
            if (holdTimer) { clearTimeout(holdTimer); holdTimer = null; }
            holdCancelled = true;
            if (this.recorderActive) {
                this.stopRecorder();
            } else if (!this.loading) {
                this.send();
            }
        },
        async startRecorder() {
            this.recorderStatus = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.hint = 'Recording is not supported in this browser.';
                return;
            }
            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.hint = 'Microphone access was denied. Allow it and try again.';
                return;
            }

            if (holdCancelled) { stream.getTracks().forEach(t => t.stop()); return; }

            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx || !AudioCtx.prototype.createScriptProcessor) {
                stream.getTracks().forEach(t => t.stop());
                this.hint = 'Recording is not supported in this browser.';
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
                this.hint = 'No audio captured.';
                return;
            }

            const total = chunks.reduce((n, c) => n + c.length, 0);
            const pcm = new Float32Array(total);
            let offset = 0;
            for (const c of chunks) { pcm.set(c, offset); offset += c.length; }

            this.recorderStatus = 'Loading speech model… (first use takes a moment)';
            const transcriber = await getTranscriber();
            this.recorderStatus = 'Transcribing…';

            const audio = await this.resample(pcm, sampleRate, 16000);
            const output = await transcriber(audio);
            const transcript = ((output && output.text) || '').trim();

            if (!transcript) {
                this.recorderStatus = '';
                this.hint = 'No speech detected.';
                return;
            }

            this.recorderStatus = '';
            await this.askWith(transcript);
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
        onFiles(e) {
            const input = e.target;
            const list = Array.from(input.files || []);
            input.value = '';
            for (const file of list) this.readFile(file);
        },
        onDrop(e) {
            const list = Array.from((e.dataTransfer && e.dataTransfer.files) || []);
            for (const file of list) this.readFile(file);
        },
        readFile(file) {
            if (!file) return;
            const reader = new FileReader();
            reader.onload = () => {
                this.files.push({ name: file.name, text: String(reader.result || '') });
            };
            reader.onerror = () => {
                this.files.push({ name: file.name, text: '' });
            };
            reader.readAsText(file);
        },
        removeFile(i) { this.files.splice(i, 1); },
        clearAll() { this.query = ''; this.files = []; },
        async approve(m) {
            try {
                const data = await api('POST', '/api/approvals/' + m.approval.id + '/approve');
                m.approvalState = data && data.ok === false ? 'failed' : 'approved';
                m.approvalError = data && data.ok === false ? data.reason : '';
            } catch (e) {
                m.approvalState = 'failed';
                m.approvalError = e.message || 'Could not approve.';
            }
        },
        async reject(m) {
            try {
                await api('POST', '/api/approvals/' + m.approval.id + '/reject');
                m.approvalState = 'cancelled';
            } catch (e) {
                m.approvalState = 'failed';
                m.approvalError = e.message || 'Could not cancel.';
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
            if (themeColor) themeColor.setAttribute('content', dark ? '#0E1116' : '#F6F4EF');
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