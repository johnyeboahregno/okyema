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
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema-simple.css?v=<?= e(config('okyema.app.version')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body>
<div id="app" class="big-app" v-cloak>
    <header class="big-header">
        <img class="big-header__logo big-logo--light" src="<?= e($base) ?>/assets/logos/okyema-logo-light.svg" alt="Okyema">
        <img class="big-header__logo big-logo--dark" src="<?= e($base) ?>/assets/logos/okyema-logo-dark.svg" alt="Okyema">

        <div class="big-header__actions">
            <button class="icon-btn" type="button" @click="toggleTheme" :aria-label="'Theme: ' + theme">◐</button>
            <button class="text-btn" type="button" @click="logout">Sign out</button>
        </div>
    </header>

    <main class="big-main">
        <p class="big-notice" v-if="notice" role="status" @click="notice = ''">{{ notice }}</p>

        <div class="big-card" @dragover.prevent @drop.prevent="onDrop">
            <textarea ref="ask" class="big-input" v-model="query" aria-label="Ask Okyema"
                      placeholder="Type it, paste it, or drop documents here…"
                      @keydown.enter.exact.prevent="send()"></textarea>

            <div class="big-meta">
                <span class="big-count" :class="{ 'is-over': charCount > maxChars }">{{ charCount }} / {{ maxChars }}</span>
            </div>

            <div class="big-chips" v-if="files.length">
                <span class="big-chip" v-for="(f, i) in files" :key="f.name + i">
                    📄 {{ f.name }}
                    <button class="big-chip__x" type="button" :aria-label="'Remove ' + f.name" @click="removeFile(i)">×</button>
                </span>
            </div>

            <div class="big-tools">
                <label class="big-attach">
                    📎 Attach documents
                    <input type="file" multiple accept=".txt,.md,.csv,.json,.log,.html,.htm,.rtf,.xml,.yaml,.yml,.ts,.js,.css,.php,.env,text/plain,text/markdown,text/csv,application/json" hidden @change="onFiles">
                </label>
                <button class="big-clear" type="button" v-if="query || files.length" @click="clearAll">Clear</button>
            </div>

            <div class="big-button-zone">
                <button class="big-button" type="button"
                        :disabled="loading || (!query.trim() && !files.length)"
                        :aria-label="loading ? 'Thinking…' : 'Ask Okyema'"
                        @click="send()">
                    <span class="big-button__label">{{ loading ? '…' : 'ASK' }}</span>
                    <span class="big-button__sub">{{ loading ? 'Thinking' : 'Okyema' }}</span>
                </button>
            </div>

            <p class="big-hint">One button. Type, paste or attach — then press it.</p>
        </div>

        <section class="big-results" aria-live="polite">
            <p class="big-empty" v-if="!results.length && !loading">What would you like Okyema to do?</p>

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
        </section>
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

Vue.createApp({
    data() {
        return {
            me: { name: '', email: '', profile: null },
            contexts: [],
            activeContext: null,
            query: '',
            files: [],
            results: [],
            loading: false,
            error: '',
            notice: <?= json_encode($connectorNotice ?? '') ?>,
            theme: 'system',
            ai: <?= json_encode($aiInfo) ?>,
            maxChars: <?= (int) $maxRequest ?>,
        };
    },
    computed: {
        userResults() { return this.results.filter(r => r.role === 'user'); },
        assistantResults() { return this.results.filter(r => r.role === 'assistant'); },
        charCount() { return this.composedRequest().length; },
    },
    mounted() {
        this.theme = localStorage.getItem('okyema.theme') || 'system';
        this.applyTheme();
        this.loadAll()
            .catch(() => { window.location.href = BASE_URL + '/login'; });
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
        async send() {
            const text = this.composedRequest().trim();
            if (!text || this.loading) return;
            this.error = '';
            this.notice = '';
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