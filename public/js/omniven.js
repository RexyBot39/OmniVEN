// OmniVEN dashboard: tabs, brand filter and search run in the browser over server-rendered cards.
(function () {
  const store = {
    get(k) { try { return localStorage.getItem('omniven:' + k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem('omniven:' + k, v); } catch {} },
  };

  // Two-step delete buttons (no browser confirm dialog).
  document.querySelectorAll('form[data-confirm]').forEach((f) => {
    f.addEventListener('submit', (e) => {
      const b = f.querySelector('button');
      if (!f.dataset.armed) {
        e.preventDefault();
        f.dataset.armed = '1';
        b.textContent = 'Click again to confirm';
        setTimeout(() => { delete f.dataset.armed; b.textContent = b.dataset.label; }, 4000);
      }
    });
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
  const state = { tab: 'tools', brand: store.get('brand') || 'all', q: '' };

  function setTab(t, push) {
    if (!views.some((v) => v.dataset.view === t)) t = 'tools';
    state.tab = t;
    tabs.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === t)));
    views.forEach((v) => { v.hidden = v.dataset.view !== t; });
    // Brand chips only filter tools, brands, routines and access.
    const chips = document.querySelector('.chips');
    if (chips) chips.hidden = t === 'tasks' || t === 'postit';
    store.set('tab', t);
    if (push) history.replaceState(null, '', t === 'tools' ? location.pathname : '#' + t);
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
    const chip = e.target.closest('.chip');
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

  setTab(location.hash.slice(1) || store.get('tab') || 'tools', false);
  apply();
  filterTasks(store.get('taskfilter') || 'all');

  // Daily Hotlist popup.
  const pop = document.getElementById('hotlist-popup');
  if (pop && typeof pop.showModal === 'function') pop.showModal();
})();
