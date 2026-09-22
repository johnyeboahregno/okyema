<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Okyema</title>
<link rel="icon" href="<?= e($base) ?>/assets/favicon/favicon.ico" sizes="32x32">
<link rel="icon" href="<?= e($base) ?>/assets/favicon/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e($base) ?>/css/okyema.css?v=<?= e(config('okyema.app.version')) ?>">
    <?php include resource_path('views/partials/pwa-head.php'); ?>
</head>
<body>
<div id="app" v-cloak>
    <header class="appbar">
        <div class="appbar__left">
            <button class="appbar__logo-btn" type="button" @click="tab='today'" aria-label="Go to Today">
                <img class="appbar__logo" src="<?= e($base) ?>/assets/okyema-mark.svg" alt="Okyema">
            </button>
            <button class="context-chip" type="button" @click="contextMenu = !contextMenu" aria-label="Switch workspace context">
                <span class="context-chip__dot"></span>
                <span>{{ activeContext ? activeContext.name : '…' }}</span>
            </button>
            <div class="user-menu" v-if="contextMenu" style="top:56px;right:auto;left:120px" @click.self="contextMenu = false">
                <button class="user-menu__item" v-for="c in contexts" :key="c.id"
                        :class="{'user-menu__item--muted': c.is_active}"
                        @click="activateContext(c)">
                    {{ c.name }} <span v-if="c.is_active">✓</span>
                </button>
            </div>
        </div>
        <div class="appbar__user">
            <button class="avatar" type="button" @click="toggleTheme" :aria-label="'Theme: ' + themeLabel">
                {{ themeIcon }}
            </button>
            <button class="avatar" type="button" @click="userMenu = !userMenu" aria-label="Account menu">
                <img v-if="gravatarUrl" :src="gravatarUrl" @error="gravatarUrl = ''" alt="">
                <span v-else>{{ initials }}</span>
            </button>
            <div class="user-menu" v-if="userMenu" @click.self="userMenu = false">
                <div class="user-menu__head">
                    <strong>{{ me.name }}</strong>
                    <span class="muted">{{ me.email }}</span>
                </div>
                <button class="user-menu__item" @click="userMenu = false; tab='settings'">Settings</button>
                <p class="user-menu__item user-menu__item--muted" v-if="install.installed">✓ Installed on this device</p>
                <button class="user-menu__item" v-else @click="installApp">Install on device</button>
                <button class="user-menu__item user-menu__item--danger" @click="logout">Log out</button>
                <p class="user-menu__version">v<?= e(config('okyema.app.version')) ?></p>
            </div>
        </div>
    </header>

    <main class="screen">

        <!-- ── TODAY ─────────────────────────────────────────── -->
        <section v-if="tab === 'today'" class="view">
            <p class="greet">Good {{ greeting }}, {{ firstName }}</p>
            <p class="date-line">{{ todayLabel }}</p>

            <div class="hero" role="button" tabindex="0" aria-label="Open today briefing" @keydown.enter="tab='today'">
                <template v-if="dash.next_meeting">
                    <span class="hero__label">Next meeting · {{ dash.next_meeting.time_label }}</span>
                    <span class="hero__title">{{ dash.next_meeting.title }}</span>
                    <span class="hero__sub">{{ dash.next_meeting.location || dash.next_meeting.calendar.name }} · {{ dash.next_meeting.day_label }}</span>
                </template>
                <template v-else>
                    <span class="hero__label">Today's briefing</span>
                    <span class="hero__title">Nothing scheduled yet</span>
                    <span class="hero__sub">Connect a calendar in Settings to bring your day into view.</span>
                </template>
            </div>

            <h3 class="section-title" v-if="agenda.events && agenda.events.length">Today</h3>
            <div class="event-list" v-if="agenda.events && agenda.events.length">
                <div class="event" v-for="ev in agenda.events" :key="ev.id" :class="{'event--conflict': ev.has_conflict}">
                    <span class="event__time">{{ ev.is_all_day ? 'All day' : ev.time_label }}</span>
                    <span class="event__body">
                        <span class="event__title">{{ ev.title }}</span>
                        <span class="event__sub">{{ ev.location || ev.calendar.name }} <template v-if="ev.has_conflict">· conflict</template></span>
                    </span>
                </div>
            </div>
            <p class="muted small" v-else-if="!offlineAgenda">Nothing on today in {{ activeContext ? activeContext.name : '' }}.</p>
            <p class="muted small" v-else>Offline — showing the last agenda we cached.</p>

            <div class="card">
                <div class="card__row">
                    <strong>Overdue actions</strong><span class="muted">{{ dash.overdue_actions }}</span>
                </div>
            </div>
            <div class="card">
                <div class="card__row">
                    <strong>Messages needing a reply</strong><span class="muted">{{ dash.messages_needing_reply }}</span>
                </div>
            </div>
            <div class="card">
                <div class="card__row">
                    <strong>Unprocessed receipts</strong><span class="muted">{{ dash.unprocessed_receipts }}</span>
                </div>
            </div>

            <div class="card" v-if="dash.briefing">
                <strong>Briefing</strong>
                <p class="muted small">{{ dash.briefing }}</p>
            </div>
            <div class="card" v-if="dash.recent_decisions && dash.recent_decisions.length">
                <strong>Recent decisions</strong>
                <p class="muted small" v-for="d in dash.recent_decisions" :key="d.id">{{ d.title }}</p>
            </div>
        </section>

        <!-- ── TIMELINE ───────────────────────────────────────── -->
        <section v-else-if="tab === 'timeline'" class="view">
            <p class="greet">Timeline</p>
            <p class="date-line">{{ timeline.from }} → {{ timeline.to }}</p>

            <div class="event-list" v-if="timeline.events && timeline.events.length">
                <div class="event" v-for="ev in timeline.events" :key="ev.id" :class="{'event--conflict': ev.has_conflict}">
                    <span class="event__time">{{ ev.day_label }} · {{ ev.time_label }}</span>
                    <span class="event__body">
                        <span class="event__title">{{ ev.title }}</span>
                        <span class="event__sub">{{ ev.location || ev.calendar.name }} <template v-if="ev.has_conflict">· conflict</template></span>
                    </span>
                </div>
            </div>
            <p class="muted small" v-else>No events in this period for {{ activeContext ? activeContext.name : '' }}.</p>
        </section>

        <!-- ── MEETINGS ───────────────────────────────────────── -->
        <section v-else-if="tab === 'meetings'" class="view">
            <p class="greet">Meetings</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <button class="btn btn--ghost btn--block" @click="showMeetingForm = !showMeetingForm">
                {{ showMeetingForm ? 'Cancel' : '+ New meeting' }}
            </button>
            <form v-if="showMeetingForm" class="inline-form" @submit.prevent="submitMeeting">
                <label class="field"><span>Title</span><input v-model="meetingDraft.title" required></label>
                <label class="field"><span>When (optional)</span><input type="datetime-local" v-model="meetingDraft.starts_at"></label>
                <button type="submit" class="btn btn--primary btn--block">Create meeting</button>
            </form>

            <div class="list" v-if="meetings.length">
                <button class="list__item" v-for="m in meetings" :key="m.id" @click="openMeeting(m)">
                    <span class="list__main">
                        <span class="list__title">{{ m.title }}</span>
                        <span class="list__sub">{{ m.starts_at ? m.starts_at.slice(0, 10) : 'No date' }} · {{ m.participants_count }} people · {{ m.decisions_count }} decisions · {{ m.actions_count }} actions</span>
                    </span>
                    <span class="list__chev">›</span>
                </button>
            </div>
            <p class="muted small" v-else>No meetings yet.</p>

            <div v-if="meetingDetail" class="card meeting-detail">
                <button class="btn btn--ghost" @click="closeMeeting">← Back</button>
                <h3 class="meeting-detail__title">{{ meetingDetail.meeting.title }}</h3>
                <p class="muted small" v-if="meetingDetail.agenda">{{ meetingDetail.agenda }}</p>

                <h4 class="section-title">Participants</h4>
                <p class="muted small">{{ (meetingDetail.participants || []).map(p => p.name).join(', ') || 'None added' }}</p>

                <h4 class="section-title">Notes</h4>
                <div v-for="n in meetingDetail.notes" :key="n.id" class="note">{{ n.body }}</div>
                <form @submit.prevent="addMeetingNote" class="inline-form">
                    <label class="field"><span>Add note — use "Decision: …" and "Action: …" lines</span>
                        <textarea v-model="meetingNoteDraft" rows="3" required></textarea>
                    </label>
                    <button type="submit" class="btn btn--primary">Add note</button>
                </form>

                <button class="btn btn--ghost btn--block" @click="generateMeeting" :disabled="generating">
                    {{ generating ? 'Generating…' : 'Generate summary' }}
                </button>

                <div v-if="meetingGenerated">
                    <h4 class="section-title">Summary</h4>
                    <p class="note">{{ meetingGenerated.summary.body }}</p>

                    <h4 class="section-title">Decisions</h4>
                    <div v-for="d in meetingGenerated.decisions" :key="d.id" class="card">
                        <strong>{{ d.title }}</strong>
                        <button class="btn btn--ghost" @click="convertDecision(d)">Convert to action</button>
                    </div>

                    <h4 class="section-title">Proposed actions</h4>
                    <div v-for="(a, i) in meetingGenerated.proposed_actions" :key="i" class="card card__row">
                        <span>{{ a.title }}<template v-if="a.owner"> · {{ a.owner }}</template><template v-if="a.due_date"> · due {{ a.due_date }}</template></span>
                        <button class="btn btn--ghost" @click="acceptAction(a)">Add</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── ACTIONS ────────────────────────────────────────── -->
        <section v-else-if="tab === 'actions'" class="view">
            <p class="greet">Actions</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <div class="chips">
                <button v-for="v in actionViews" :key="v" type="button" class="chip" :class="{'chip--active': actionView === v}" @click="setActionView(v)">{{ v }}</button>
            </div>

            <button class="btn btn--ghost btn--block" @click="showActionForm = !showActionForm">
                {{ showActionForm ? 'Cancel' : '+ New action' }}
            </button>
            <form v-if="showActionForm" class="inline-form" @submit.prevent="submitAction">
                <label class="field"><span>Title</span><input v-model="actionDraft.title" required></label>
                <label class="field"><span>Due date</span><input type="date" v-model="actionDraft.due_date"></label>
                <label class="field"><span>Priority</span>
                    <select v-model="actionDraft.priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
                <button type="submit" class="btn btn--primary btn--block">Create action</button>
            </form>

            <div class="list" v-if="actions.length">
                <div v-for="a in actions" :key="a.id" class="list__item">
                    <span class="list__main">
                        <span class="list__title">{{ a.title }} <span v-if="a.overdue" class="badge badge--warn">overdue</span></span>
                        <span class="list__sub">{{ a.priority }} · {{ a.due_date || 'no due date' }}</span>
                    </span>
                    <button v-if="a.status !== 'completed'" class="btn btn--ghost" @click="completeAction(a)">Done</button>
                    <span v-else class="badge badge--ok">done</span>
                </div>
            </div>
            <p class="muted small" v-else>No actions in this view.</p>
        </section>

        <!-- ── EXPENSES ───────────────────────────────────────── -->
        <section v-else-if="tab === 'expenses'" class="view">
            <p class="greet">Expenses</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }} · {{ expenses.month || 'this month' }}</p>

            <input type="file" ref="receiptInput" accept="image/*,.pdf" capture="environment" class="hidden" @change="onReceiptPicked">
            <button class="btn btn--primary btn--block" @click="$refs.receiptInput.click()">Capture receipt</button>

            <h3 class="section-title">Receipts to confirm</h3>
            <div v-for="r in unconfirmedReceipts" :key="r.id" class="card">
                <template v-if="confirming && confirming.id === r.id">
                    <form class="inline-form" @submit.prevent="submitConfirm(r)">
                        <label class="field"><span>Merchant</span><input v-model="confirmDraft.merchant" required></label>
                        <label class="field"><span>Total</span><input v-model="confirmDraft.total" required></label>
                        <label class="field"><span>Date</span><input type="date" v-model="confirmDraft.expense_date" required></label>
                        <button type="submit" class="btn btn--primary">Save expense</button>
                        <button type="button" class="btn btn--ghost" @click="cancelConfirm">Cancel</button>
                    </form>
                </template>
                <template v-else>
                    <div class="card__row">
                        <strong>Receipt #{{ r.id }}</strong>
                        <span class="muted">{{ r.file_status }}</span>
                    </div>
                    <button class="btn btn--ghost" @click="startConfirm(r)">Confirm</button>
                </template>
            </div>
            <p class="muted small" v-if="!unconfirmedReceipts.length">No receipts waiting.</p>

            <h3 class="section-title">This month</h3>
            <div class="card">
                <div class="card__row"><strong>Total</strong><span>{{ money(expenses.total_minor) }}</span></div>
                <div class="card__row"><strong>Expenses</strong><span>{{ expenses.count }}</span></div>
                <div class="card__row"><strong>Missing receipts</strong><span class="muted">{{ expenses.missing_receipts }}</span></div>
            </div>
            <div class="event-list" v-if="expenses.expenses && expenses.expenses.length">
                <div class="event" v-for="e in expenses.expenses" :key="e.id">
                    <span class="event__time">{{ e.expense_date }}</span>
                    <span class="event__body">
                        <span class="event__title">{{ e.merchant }}</span>
                        <span class="event__sub">{{ e.currency }} {{ money(e.total_minor) }} · {{ e.status }}</span>
                    </span>
                </div>
            </div>
        </section>

        <!-- ── INBOX ─────────────────────────────────────────── -->
        <section v-else-if="tab === 'inbox'" class="view">
            <p class="greet">Inbox</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <div class="list" v-if="conversations.length">
                <button class="list__item" v-for="c in conversations" :key="c.id" @click="openConversation(c)">
                    <span class="list__main">
                        <span class="list__title">{{ c.subject || c.person }} <span v-if="c.needs_reply" class="badge badge--warn">reply</span> <span v-if="c.is_vip" class="badge badge--ok">vip</span></span>
                        <span class="list__sub">{{ c.channel }} · {{ c.person }} · {{ c.snippet }}</span>
                    </span>
                    <span class="list__chev">›</span>
                </button>
            </div>
            <p class="muted small" v-else>Nothing in the inbox for this context.</p>

            <div v-if="inboxDetail" class="card meeting-detail">
                <button class="btn btn--ghost" @click="inboxDetail = null">← Back</button>
                <h3 class="meeting-detail__title">{{ inboxDetail.subject || inboxDetail.person?.name }}</h3>
                <div v-for="m in (inboxDetail.messages || [])" :key="m.id" class="note">
                    <strong>{{ m.direction }}</strong> · {{ m.snippet }}
                </div>
                <form @submit.prevent="sendReply" class="inline-form">
                    <label class="field"><span>Reply</span><textarea v-model="replyDraft" rows="3" required></textarea></label>
                    <button type="submit" class="btn btn--primary">Draft reply (needs approval)</button>
                </form>
                <p v-if="replyNotice" class="muted small">{{ replyNotice }}</p>
            </div>
        </section>

        <!-- ── TRAVEL ────────────────────────────────────────── -->
        <section v-else-if="tab === 'travel'" class="view">
            <p class="greet">Travel</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <button class="btn btn--ghost btn--block" @click="showTripForm = !showTripForm">{{ showTripForm ? 'Cancel' : '+ New trip' }}</button>
            <form v-if="showTripForm" class="inline-form" @submit.prevent="submitTrip">
                <label class="field"><span>Title</span><input v-model="tripDraft.title" required></label>
                <label class="field"><span>Starts</span><input type="date" v-model="tripDraft.starts_on"></label>
                <label class="field"><span>Ends</span><input type="date" v-model="tripDraft.ends_on"></label>
                <button type="submit" class="btn btn--primary btn--block">Create trip</button>
            </form>

            <div class="list" v-if="trips.length">
                <div v-for="t in trips" :key="t.id" class="list__item">
                    <span class="list__main">
                        <span class="list__title">{{ t.title }}</span>
                        <span class="list__sub">{{ t.starts_on || '?' }} → {{ t.ends_on || '?' }} · {{ (t.segments || []).length }} segments</span>
                    </span>
                    <span class="badge badge--ok">{{ t.status }}</span>
                </div>
            </div>
            <p class="muted small" v-else>No trips yet.</p>
        </section>

        <!-- ── FILES ──────────────────────────────────────────── -->
        <section v-else-if="tab === 'files'" class="view">
            <p class="greet">Files</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <form class="inline-form" @submit.prevent="searchDocuments">
                <label class="field"><span>Search</span><input v-model="documentQuery" placeholder="e.g. board pack"></label>
                <button type="submit" class="btn btn--primary">Search</button>
            </form>

            <div class="list" v-if="documents.length">
                <a v-for="d in documents" :key="d.id" class="list__item" :href="d.deep_link || '#'" target="_blank" rel="noopener">
                    <span class="list__main">
                        <span class="list__title">{{ d.title }}</span>
                        <span class="list__sub">{{ d.provider }}</span>
                    </span>
                </a>
            </div>
            <p class="muted small" v-else>No documents found.</p>
        </section>

        <!-- ── PEOPLE ─────────────────────────────────────────── -->
        <section v-else-if="tab === 'people'" class="view">
            <p class="greet">People</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <div class="list" v-if="people.length">
                <div v-for="p in people" :key="p.id" class="list__item">
                    <span class="list__main">
                        <span class="list__title">{{ p.name }}</span>
                        <span class="list__sub">{{ p.organisation || '—' }} · {{ (p.identities || []).map(i => i.email).filter(Boolean).join(', ') || 'no identities' }}</span>
                    </span>
                </div>
            </div>
            <p class="muted small" v-else>No people in this context yet.</p>
        </section>

        <!-- ── AUTOMATIONS ────────────────────────────────────── -->
        <section v-else-if="tab === 'automations'" class="view">
            <p class="greet">Automations</p>
            <p class="date-line">{{ activeContext ? activeContext.name : '' }}</p>

            <button class="btn btn--ghost btn--block" @click="showAutomationForm = !showAutomationForm">{{ showAutomationForm ? 'Cancel' : '+ New rule' }}</button>
            <form v-if="showAutomationForm" class="inline-form" @submit.prevent="submitAutomation">
                <label class="field"><span>Name</span><input v-model="automationDraft.name" required></label>
                <label class="field"><span>Trigger</span>
                    <select v-model="automationDraft.trigger">
                        <option value="action_due_soon">Actions due soon</option>
                        <option value="morning_briefing">Morning briefing</option>
                        <option value="weekly_review">Weekly review</option>
                    </select>
                </label>
                <button type="submit" class="btn btn--primary btn--block">Create rule</button>
            </form>

            <div class="list" v-if="automations.length">
                <div v-for="r in automations" :key="r.id" class="list__item">
                    <span class="list__main">
                        <span class="list__title">{{ r.name }}</span>
                        <span class="list__sub">{{ r.trigger }} · {{ r.runs_count }} runs</span>
                    </span>
                    <button class="btn btn--ghost" @click="runAutomation(r)">Run</button>
                </div>
            </div>
            <p class="muted small" v-else>No automation rules yet.</p>
        </section>

        <!-- ── SETTINGS ───────────────────────────────────────── -->
        <section v-else-if="tab === 'settings'" class="view">
            <p class="greet">Settings</p>
            <p class="date-line">{{ me.email }}</p>

            <form class="inline-form" @submit.prevent="saveSettings">
                <label class="field"><span>Display name</span><input v-model="settings.display_name"></label>
                <label class="field"><span>Timezone</span><input v-model="settings.timezone" placeholder="Europe/London"></label>
                <button type="submit" class="btn btn--primary">Save profile</button>
            </form>

            <h3 class="section-title">Connections</h3>
            <div class="list" v-if="connectors.length">
                <div v-for="c in connectors" :key="c.id" class="list__item">
                    <span class="list__main">
                        <span class="list__title">{{ c.provider }}</span>
                        <span class="list__sub">{{ c.status }} · {{ (c.capabilities || []).join(', ') }} · synced {{ c.last_synced_at || 'never' }}</span>
                    </span>
                    <span class="badge" :class="c.status === 'connected' ? 'badge--ok' : 'badge--warn'">{{ c.status }}</span>
                </div>
            </div>
            <p class="muted small" v-else>No connectors connected yet.</p>

            <p class="muted small" style="margin-top:16px">Okyema v<?= e(config('okyema.app.version')) ?> · Powered by <img class="regnoai-logo regnoai-logo--light" src="<?= e($base) ?>/assets/regnoai.png" alt="Regno AI"><img class="regnoai-logo regnoai-logo--dark" src="<?= e($base) ?>/assets/regnoai-white.png" alt="Regno AI"></p>
        </section>

        <!-- ── PLACEHOLDER TABS ─────────────────────────────── -->
        <section v-else class="view placeholder">
            <h2 class="placeholder__title">{{ tabTitle }}</h2>
            <p>This module lands in a later milestone. The shell, workspace contexts and calendar are in place first.</p>
            <button class="btn btn--ghost" @click="tab='today'">Back to Today</button>
        </section>

    </main>

    <!-- Navigation -->
    <nav class="nav" aria-label="Primary">
        <button v-for="item in navItems" :key="item.key"
                class="nav__item" :class="{'nav__item--active': tab === item.key}"
                type="button" @click="tab = item.key"
                :aria-current="tab === item.key ? 'page' : undefined">
            <span class="nav__icon" aria-hidden="true">{{ item.icon }}</span>
            <span>{{ item.label }}</span>
        </button>
        <button class="nav__capture" type="button" aria-label="Quick capture" @click="capture()">+</button>
    </nav>
</div>

<script src="https://cdn.jsdelivr.net/npm/vue@3.4.38/dist/vue.global.prod.js"></script>
<script>
const BASE_URL = <?= json_encode($base) ?>;

async function api(method, path, body) {
    const init = { method, headers: {} };
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
            me: { name: '', email: '' },
            contexts: [],
            activeContext: null,
            dash: {
                overdue_actions: 0, messages_needing_reply: 0,
                unprocessed_receipts: 0, meetings_today: 0,
            },
            tab: 'today',
            contextMenu: false,
            userMenu: false,
            gravatarUrl: '',
            theme: 'system',
            install: { installed: false, prompt: null },
            agenda: { date: null, events: [] },
            timeline: { from: '', to: '', events: [] },
            offlineAgenda: false,
            meetings: [],
            meetingDetail: null,
            meetingGenerated: null,
            showMeetingForm: false,
            meetingDraft: { title: '', starts_at: '' },
            meetingNoteDraft: '',
            generating: false,
            actions: [],
            actionView: 'inbox',
            actionViews: ['inbox', 'today', 'upcoming', 'overdue', 'completed'],
            showActionForm: false,
            actionDraft: { title: '', due_date: '', priority: 'medium' },
            receipts: [],
            expenses: { month: '', total_minor: 0, count: 0, expenses: [], missing_receipts: 0 },
            confirming: null,
            confirmDraft: { merchant: '', total: '', expense_date: '' },
            conversations: [],
            inboxDetail: null,
            replyDraft: '',
            replyNotice: '',
            trips: [],
            showTripForm: false,
            tripDraft: { title: '', starts_on: '', ends_on: '' },
            documents: [],
            documentQuery: '',
            people: [],
            automations: [],
            showAutomationForm: false,
            automationDraft: { name: '', trigger: 'action_due_soon' },
            settings: { display_name: '', timezone: '' },
            connectors: [],
            navItems: [
                { key: 'today', label: 'Today', icon: '◉' },
                { key: 'timeline', label: 'Timeline', icon: '🗓' },
                { key: 'meetings', label: 'Meetings', icon: '◎' },
                { key: 'actions', label: 'Actions', icon: '✓' },
                { key: 'inbox', label: 'Inbox', icon: '✉' },
                { key: 'travel', label: 'Travel', icon: '✈' },
                { key: 'expenses', label: 'Expenses', icon: '£' },
                { key: 'files', label: 'Files', icon: '▤' },
                { key: 'people', label: 'People', icon: '◎' },
                { key: 'automations', label: 'Automations', icon: '⚙' },
                { key: 'settings', label: 'Settings', icon: '☰' },
            ],
        };
    },
    computed: {
        firstName() { return (this.me.name || 'there').split(' ')[0]; },
        initials() {
            const n = (this.me.name || 'O').trim();
            const parts = n.split(/\s+/);
            return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() || 'O';
        },
        greeting() {
            const h = new Date().getHours();
            if (h < 12) return 'morning';
            if (h < 18) return 'afternoon';
            return 'evening';
        },
        todayLabel() {
            return new Date().toLocaleDateString(undefined, {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            });
        },
        tabTitle() {
            return (this.navItems.find(i => i.key === this.tab) || {}).label || 'Module';
        },
        themeIcon() { return this.theme === 'dark' ? '◐' : (this.theme === 'light' ? '◑' : '◐'); },
        themeLabel() { return this.theme; },
        unconfirmedReceipts() { return (this.receipts || []).filter(r => !r.expense_id); },
    },
    methods: {
        async loadAll() {
            const [me, contexts, dash] = await Promise.all([
                api('GET', '/api/me'),
                api('GET', '/api/contexts'),
                api('GET', '/api/dashboard'),
            ]);
            this.me = me;
            this.gravatarUrl = me.gravatar_url || '';
            this.contexts = contexts;
            this.activeContext = contexts.find(c => c.is_active) || contexts[0] || null;
            this.dash = dash;
            await this.loadAgenda();
        },
        async loadAgenda() {
            try {
                this.agenda = await api('GET', '/api/agenda');
                this.offlineAgenda = false;
                localStorage.setItem('okyema.agenda', JSON.stringify(this.agenda));
            } catch (e) {
                const cached = localStorage.getItem('okyema.agenda');
                if (cached) { this.agenda = JSON.parse(cached); this.offlineAgenda = true; }
            }
        },
        async loadTimeline() {
            try {
                this.timeline = await api('GET', '/api/timeline');
            } catch (e) { /* offline — show the empty state */ }
        },
        async loadMeetings() {
            try { this.meetings = await api('GET', '/api/meetings'); } catch (e) { /* ignore */ }
        },
        async openMeeting(m) {
            this.meetingDetail = await api('GET', `/api/meetings/${m.id}`);
            this.meetingGenerated = this.meetingDetail.summary ? {
                summary: this.meetingDetail.summary,
                decisions: this.meetingDetail.decisions || [],
                proposed_actions: [],
            } : null;
        },
        closeMeeting() { this.meetingDetail = null; this.meetingGenerated = null; },
        async submitMeeting() {
            const payload = { title: this.meetingDraft.title };
            if (this.meetingDraft.starts_at) payload.starts_at = new Date(this.meetingDraft.starts_at).toISOString();
            await api('POST', '/api/meetings', payload);
            this.showMeetingForm = false;
            this.meetingDraft = { title: '', starts_at: '' };
            await this.loadMeetings();
        },
        async addMeetingNote() {
            await api('POST', `/api/meetings/${this.meetingDetail.meeting.id}/notes`, { body: this.meetingNoteDraft });
            this.meetingNoteDraft = '';
            this.meetingDetail = await api('GET', `/api/meetings/${this.meetingDetail.meeting.id}`);
        },
        async generateMeeting() {
            this.generating = true;
            try {
                this.meetingGenerated = await api('POST', `/api/meetings/${this.meetingDetail.meeting.id}/generate`);
                this.meetingDetail = await api('GET', `/api/meetings/${this.meetingDetail.meeting.id}`);
            } finally {
                this.generating = false;
            }
        },
        async convertDecision(d) {
            await api('POST', `/api/decisions/${d.id}/convert`);
            this.meetingDetail = await api('GET', `/api/meetings/${this.meetingDetail.meeting.id}`);
            await this.loadActions();
        },
        async acceptAction(proposal) {
            await api('POST', '/api/actions', {
                title: proposal.title,
                owner: proposal.owner,
                due_date: proposal.due_date,
                priority: proposal.priority,
                meeting_id: proposal.meeting_id,
            });
            this.meetingGenerated.proposed_actions = this.meetingGenerated.proposed_actions.filter(a => a !== proposal);
            await this.loadActions();
        },
        async loadActions() {
            try { this.actions = await api('GET', `/api/actions?view=${this.actionView}`); } catch (e) { /* ignore */ }
        },
        async setActionView(v) { this.actionView = v; await this.loadActions(); },
        async submitAction() {
            await api('POST', '/api/actions', {
                title: this.actionDraft.title,
                due_date: this.actionDraft.due_date || null,
                priority: this.actionDraft.priority,
            });
            this.showActionForm = false;
            this.actionDraft = { title: '', due_date: '', priority: 'medium' };
            await this.loadActions();
        },
        async completeAction(a) {
            await api('POST', `/api/actions/${a.id}/transition`, { status: 'completed' });
            await this.loadActions();
        },
        money(minor) {
            if (minor == null) return '—';
            const sign = minor < 0 ? '-' : '';
            const abs = Math.abs(minor);
            const whole = Math.floor(abs / 100);
            const frac = String(abs % 100).padStart(2, '0');
            return sign + whole + '.' + frac;
        },
        async loadReceipts() {
            try { this.receipts = await api('GET', '/api/receipts'); } catch (e) { /* ignore */ }
        },
        async loadExpenses() {
            try { this.expenses = await api('GET', '/api/expenses'); } catch (e) { /* ignore */ }
        },
        async onReceiptPicked(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;
            const form = new FormData();
            form.append('receipt', file);
            try {
                await fetch(BASE_URL + '/api/receipts', { method: 'POST', body: form });
            } catch (e) { /* ignore */ }
            event.target.value = '';
            await this.loadReceipts();
        },
        startConfirm(r) {
            this.confirming = r;
            this.confirmDraft = {
                merchant: r.merchant || '',
                total: r.total_minor ? this.money(r.total_minor) : '',
                expense_date: r.expense_date || new Date().toISOString().slice(0, 10),
            };
        },
        cancelConfirm() { this.confirming = null; },
        async submitConfirm(r) {
            await api('POST', `/api/receipts/${r.id}/confirm`, {
                merchant: this.confirmDraft.merchant,
                total: this.confirmDraft.total,
                expense_date: this.confirmDraft.expense_date,
            });
            this.confirming = null;
            await this.loadReceipts();
            await this.loadExpenses();
        },
        async loadInbox() {
            try { this.conversations = await api('GET', '/api/inbox'); } catch (e) { /* ignore */ }
        },
        async openConversation(c) {
            this.inboxDetail = await api('GET', `/api/inbox/${c.id}`);
        },
        async sendReply() {
            const draft = await api('POST', `/api/inbox/${this.inboxDetail.id}/draft`, { body: this.replyDraft });
            this.replyDraft = '';
            this.replyNotice = 'Draft saved — pending your approval before it is sent.';
        },
        async loadTrips() {
            try { this.trips = await api('GET', '/api/trips'); } catch (e) { /* ignore */ }
        },
        async submitTrip() {
            await api('POST', '/api/trips', this.tripDraft);
            this.showTripForm = false;
            this.tripDraft = { title: '', starts_on: '', ends_on: '' };
            await this.loadTrips();
        },
        async searchDocuments() {
            try {
                const q = encodeURIComponent(this.documentQuery);
                this.documents = await api('GET', `/api/documents?q=${q}`);
            } catch (e) { /* ignore */ }
        },
        async loadPeople() {
            try { this.people = await api('GET', '/api/people'); } catch (e) { /* ignore */ }
        },
        async loadAutomations() {
            try { this.automations = await api('GET', '/api/automations'); } catch (e) { /* ignore */ }
        },
        async submitAutomation() {
            await api('POST', '/api/automations', this.automationDraft);
            this.showAutomationForm = false;
            this.automationDraft = { name: '', trigger: 'action_due_soon' };
            await this.loadAutomations();
        },
        async runAutomation(r) {
            await api('POST', `/api/automations/${r.id}/run`);
            await this.loadAutomations();
        },
        loadSettings() {
            this.settings = {
                display_name: (this.me.profile && this.me.profile.display_name) || '',
                timezone: (this.me.profile && this.me.profile.timezone) || '',
            };
        },
        async saveSettings() {
            const profile = await api('PATCH', '/api/profile', this.settings);
            this.me.profile = profile;
            this.settings = { display_name: profile.display_name || '', timezone: profile.timezone || '' };
        },
        async loadConnectors() {
            try { this.connectors = await api('GET', '/api/connectors'); } catch (e) { /* ignore */ }
        },
        async activateContext(context) {
            await api('POST', `/api/contexts/${context.id}/activate`);
            this.activeContext = context;
            this.contexts = this.contexts.map(c => ({ ...c, is_active: c.id === context.id }));
            this.contextMenu = false;
            this.dash = await api('GET', '/api/dashboard');
            await this.loadAgenda();
            this.timeline = { from: '', to: '', events: [] };
            // Refresh whatever tab is open so no stale context data lingers.
            if (this.tab === 'timeline') this.loadTimeline();
            if (this.tab === 'meetings') { this.meetings = []; this.meetingDetail = null; this.loadMeetings(); }
            if (this.tab === 'actions') this.loadActions();
            if (this.tab === 'expenses') { this.loadReceipts(); this.loadExpenses(); }
            if (this.tab === 'inbox') { this.conversations = []; this.inboxDetail = null; this.loadInbox(); }
            if (this.tab === 'travel') { this.trips = []; this.loadTrips(); }
            if (this.tab === 'people') { this.people = []; this.loadPeople(); }
            if (this.tab === 'automations') { this.automations = []; this.loadAutomations(); }
            if (this.tab === 'settings') this.loadConnectors();
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
        },
        capture() { this.tab = 'expenses'; },
        async logout() {
            try { await api('POST', '/api/logout'); } catch (e) { /* ignore */ }
            window.location.href = BASE_URL + '/login';
        },
        async installApp() {
            this.userMenu = false;
            const prompt = this.install.prompt || window.__okyemaInstallPrompt;
            if (!prompt) return;
            this.install.prompt = null;
            window.__okyemaInstallPrompt = null;
            try {
                prompt.prompt();
                const choice = await prompt.userChoice;
                if (choice?.outcome === 'accepted') this.install.installed = true;
            } catch (e) { /* browser refused */ }
        },
        isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        },
    },
    watch: {
        tab(newTab) {
            if (newTab === 'timeline' && !this.timeline.events.length) this.loadTimeline();
            if (newTab === 'meetings' && !this.meetings.length) this.loadMeetings();
            if (newTab === 'actions') this.loadActions();
            if (newTab === 'expenses') { this.loadReceipts(); this.loadExpenses(); }
            if (newTab === 'inbox' && !this.conversations.length) this.loadInbox();
            if (newTab === 'travel' && !this.trips.length) this.loadTrips();
            if (newTab === 'people' && !this.people.length) this.loadPeople();
            if (newTab === 'automations' && !this.automations.length) this.loadAutomations();
            if (newTab === 'settings') { this.loadSettings(); this.loadConnectors(); }
        },
    },
    mounted() {
        this.theme = localStorage.getItem('okyema.theme') || 'system';
        this.applyTheme();
        this.install.installed = this.isStandalone();
        this.install.prompt = window.__okyemaInstallPrompt || null;
        window.addEventListener('okyema:installable', () => { this.install.prompt = window.__okyemaInstallPrompt; });
        this.loadAll().catch(e => { window.location.href = BASE_URL + '/login'; });
    },
}).mount('#app');
</script>
</body>
</html>
