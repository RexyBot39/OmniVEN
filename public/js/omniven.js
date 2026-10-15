// OmniVEN dashboard: tabs, brand filter and search run in the browser over server-rendered cards.
(function () {
  const store = {
    get(k) { try { return localStorage.getItem('omniven:' + k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem('omniven:' + k, v); } catch {} },
  };

  // Two-step delete buttons (no browser confirm dialog). Delegated so loaded fragments work too.
  document.addEventListener('submit', (e) => {
    const f = e.target.closest('form[data-confirm]');
    if (!f) return;
    const b = f.querySelector('button');
    if (!f.dataset.armed) {
      e.preventDefault();
      f.dataset.armed = '1';
      b.textContent = 'Click again to confirm';
      setTimeout(() => { delete f.dataset.armed; b.textContent = b.dataset.label; }, 4000);
    }
  });

  // Show/hide toggles (e.g. the note form).
  document.querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', () => {
    const el = document.getElementById(b.dataset.toggle);
    if (!el) return;
    el.hidden = !el.hidden;
    if (!el.hidden) el.querySelector('input,textarea')?.focus();
  }));

  // Sign the page out on its own when the 16-hour session ends, even if left open.
  const exp = document.getElementById('session-expiry');
  if (exp) {
    const ms = Number(exp.dataset.ends) * 1000 - Date.now();
    if (ms > 0 && ms < 2147483647) setTimeout(() => location.reload(), ms + 2000);
  }

  const tabs = [...document.querySelectorAll('.tab')];
  if (!tabs.length) return;
  const views = [...document.querySelectorAll('[data-view]')];
  const q = document.getElementById('q');
  const state = { tab: 'routines', brand: store.get('brand') || 'all', q: '' };

  // ---- Slack tab ----
  const slackBox = document.getElementById('slack-feed');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  let slackChannel = store.get('slackChannel') || '';
  let slackBusy = false;
  let lastSlackLoad = 0;
  async function loadSlack(keepDraft) {
    if (!slackBox || slackBusy) return;
    slackBusy = true;
    lastSlackLoad = Date.now();
    const draft = keepDraft ? slackBox.querySelector('#sl-text')?.value : '';
    try {
      const url = slackBox.dataset.url + (slackChannel ? '?channel=' + encodeURIComponent(slackChannel) : '');
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (res.redirected || res.status === 401) { location.reload(); return; }
      slackBox.innerHTML = await res.text();
      const list = slackBox.querySelector('.sl-list');
      if (list) list.scrollTop = list.scrollHeight;
      if (draft) { const t = slackBox.querySelector('#sl-text'); if (t) t.value = draft; }
      checkUnread();
    } catch {
      slackBox.innerHTML = '<div class="empty">Couldn\'t reach Slack. Check your connection and try again.</div>';
    } finally { slackBusy = false; }
  }
  // Unread asterisks: on the Slack tab and next to each channel. Checked on load and every minute.
  const slackStar = document.getElementById('slack-star');
  const baseTitle = document.title.replace(/^\* /, '');
  async function checkUnread() {
    if (!slackBox?.dataset.unreadUrl) return;
    try {
      const res = await fetch(slackBox.dataset.unreadUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      const viewing = state.tab === 'slack' && document.visibilityState === 'visible';
      const current = slackBox.querySelector('.slack-pane')?.dataset.channel;
      // The channel on screen is being read right now, so it doesn't count.
      const others = Object.keys(data.channels || {}).filter((id) => !(viewing && id === current));
      if (slackStar) slackStar.hidden = others.length === 0;
      document.title = (others.length ? '* ' : '') + baseTitle;
      slackBox.querySelectorAll('[data-slack-channel]').forEach((b) => {
        const star = b.querySelector('.unread-star');
        if (star) star.hidden = !others.includes(b.dataset.slackChannel);
      });
      if (viewing && current && data.channels?.[current] && !slackBox.querySelector('#sl-text')?.value && Date.now() - lastSlackLoad > 50000) loadSlack();
    } catch { /* offline: try again next minute */ }
  }
  setInterval(() => {
    if (document.visibilityState !== 'visible') return;
    if (state.tab === 'slack' && !slackBox?.querySelector('#sl-text')?.value) loadSlack(); else checkUnread();
  }, 60000);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') checkUnread(); });
  slackBox?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-slack-channel]');
    if (!b) return;
    slackChannel = b.dataset.slackChannel; store.set('slackChannel', slackChannel); loadSlack();
  });
  slackBox?.addEventListener('keydown', (e) => {
    if (e.target.id === 'sl-text' && e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.target.form.requestSubmit(); }
  });
  slackBox?.addEventListener('submit', async (e) => {
    const f = e.target.closest('form[data-slack-post]');
    if (!f) return;
    e.preventDefault();
    const btn = f.querySelector('button'); const status = f.querySelector('.sl-status');
    const text = f.querySelector('textarea').value.trim();
    if (!text) return;
    btn.disabled = true; status.textContent = 'Sending…';
    try {
      const res = await fetch(f.dataset.slackPost, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ text }),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(json.message || 'Message not sent. Try again.');
      await loadSlack();
    } catch (err) {
      status.textContent = err.message; btn.disabled = false;
    }
  });

  function setTab(t, push) {
    if (!views.some((v) => v.dataset.view === t)) t = 'routines';
    if (t === 'slack') loadSlack();
    state.tab = t;
    tabs.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === t)));
    views.forEach((v) => { v.hidden = v.dataset.view !== t; });
    // Brand chips only filter tools, brands, routines and access.
    const chips = document.querySelector('.chips');
    if (chips) chips.hidden = t === 'tasks' || t === 'postit' || t === 'slack';
    store.set('tab', t);
    if (push) history.replaceState(null, '', t === 'routines' ? location.pathname : '#' + t);
  }

  function apply() {
    const chips = document.querySelectorAll('.chip');
    if (![...chips].some((c) => c.dataset.brand === state.brand)) state.brand = 'all';
    chips.forEach((c) => c.setAttribute('aria-pressed', String(c.dataset.brand === state.brand)));

    const match = (el) => {
      const brands = (el.dataset.brands || 'all').split(' ');
      const okBrand = state.brand === 'all' || brands.includes('all') || brands.includes(state.brand);
      const okQ = !state.q || (el.dataset.search || '').includes(state.q);
      return okBrand && okQ;
    };
    document.querySelectorAll('[data-brands]').forEach((el) => { el.hidden = !match(el); });
    document.querySelectorAll('[data-brand-card]').forEach((el) => {
      const okBrand = state.brand === 'all' || el.dataset.brandCard === state.brand;
      el.hidden = !(okBrand && (!state.q || el.dataset.search.includes(state.q)));
    });
    let anyTool = false;
    document.querySelectorAll('[data-group]').forEach((g) => {
      const vis = [...g.querySelectorAll('.tool')].some((t) => !t.hidden);
      g.hidden = !vis; anyTool = anyTool || vis;
    });
    const fe = document.querySelector('.filter-empty');
    if (fe) fe.hidden = anyTool;
  }

  tabs.forEach((b) => b.addEventListener('click', () => setTab(b.dataset.tab, true)));
  document.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip[data-brand]');
    if (chip) { state.brand = chip.dataset.brand; store.set('brand', state.brand); apply(); return; }
    const show = e.target.closest('[data-showtools]');
    if (show) { state.brand = show.dataset.showtools; store.set('brand', state.brand); setTab('tools', true); apply(); window.scrollTo({ top: 0 }); }
  });
  let t;
  q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => { state.q = q.value.trim().toLowerCase(); apply(); }, 100); });

  // Buttons that jump to a tab (Hotlist "See all", popup "Open Tasks").
  document.querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => {
    setTab(b.dataset.goto, true);
    document.querySelector('.bar')?.scrollIntoView({ block: 'start' });
  }));

  // Tasks: Mine / Everyone.
  const me = exp?.dataset.me;
  function filterTasks(mode) {
    store.set('taskfilter', mode);
    document.querySelectorAll('[data-taskfilter]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.taskfilter === mode)));
    let anyOpen = false;
    document.querySelectorAll('.task[data-assignee]').forEach((t) => {
      const show = mode === 'all' || t.dataset.assignee === me;
      t.hidden = !show || (state.q && !(t.dataset.search || '').includes(state.q));
      if (!t.hidden && !t.classList.contains('done')) anyOpen = true;
    });
    const none = document.querySelector('.task-none');
    if (none) none.hidden = anyOpen || mode === 'all';
  }
  document.querySelectorAll('[data-taskfilter]').forEach((b) => b.addEventListener('click', () => filterTasks(b.dataset.taskfilter)));
  q.addEventListener('input', () => setTimeout(() => filterTasks(store.get('taskfilter') || 'all'), 150));

  setTab(location.hash.slice(1) || store.get('tab') || 'routines', false);
  apply();
  checkUnread();
  filterTasks(store.get('taskfilter') || 'all');

  // Daily Hotlist popup.
  const pop = document.getElementById('hotlist-popup');
  if (pop && typeof pop.showModal === 'function') pop.showModal();
})();
