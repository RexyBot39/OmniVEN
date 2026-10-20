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

  // Selects that save as soon as they change (e.g. a person's task board column).
  document.querySelectorAll('select[data-autosubmit]').forEach((s) => s.addEventListener('change', () => s.form.submit()));

  // Selects that jump to a page (e.g. the person switcher on a staff page).
  document.querySelectorAll('select[data-nav]').forEach((s) => s.addEventListener('change', () => { if (s.value) location.href = s.value; }));

  // ---- Help page: search, contents highlight, admin checklist ----
  const helpMain = document.querySelector('.help-main');
  if (helpMain) {
    const hq = document.getElementById('help-q');
    const sections = [...helpMain.querySelectorAll('section[id]')];
    const railLinks = [...document.querySelectorAll('.help-rail nav a')];
    const ITEMS = '.steps > li, .tab-card, .key, .lede, .how > article, .setup > article, tbody tr, .play, .rule, .check li';
    // Keep step numbers fixed while some steps are filtered out.
    helpMain.querySelectorAll('.steps').forEach((ol) => [...ol.children].forEach((li, i) => { li.dataset.n = i + 1; }));

    const unmark = () => helpMain.querySelectorAll('mark').forEach((m) => {
      const p = m.parentNode; p.replaceChild(document.createTextNode(m.textContent), m); p.normalize();
    });
    const markIn = (el, terms) => {
      const re = new RegExp('(' + terms.map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')', 'gi');
      const walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
      const nodes = [];
      while (walk.nextNode()) if (walk.currentNode.nodeValue.trim()) nodes.push(walk.currentNode);
      nodes.forEach((n) => {
        if (!re.test(n.nodeValue)) return;
        re.lastIndex = 0;
        const frag = document.createDocumentFragment();
        n.nodeValue.split(re).forEach((part, i) => {
          if (!part) return;
          if (i % 2) { const m = document.createElement('mark'); m.textContent = part; frag.appendChild(m); } else frag.appendChild(document.createTextNode(part));
        });
        n.parentNode.replaceChild(frag, n);
      });
    };

    function search() {
      unmark();
      const terms = hq.value.toLowerCase().split(/\s+/).filter(Boolean);
      let hits = 0, secs = 0;
      sections.forEach((sec) => {
        const items = [...sec.querySelectorAll(ITEMS)];
        const title = (sec.querySelector('h2')?.textContent || '').toLowerCase();
        // A search that names the section (e.g. "shortcuts") shows all of it.
        const whole = terms.length && terms.every((t) => title.includes(t));
        let n = 0;
        items.forEach((it) => {
          const text = (it.textContent + ' ' + title).toLowerCase();
          const ok = !terms.length || whole || terms.every((t) => text.includes(t));
          it.hidden = !ok;
          if (ok && terms.length) { if (!it.classList.contains('lede')) n++; markIn(it, terms); }
        });
        sec.querySelectorAll('.tablewrap').forEach((w) => { w.hidden = !!terms.length && !w.querySelector('tbody tr:not([hidden])'); });
        const show = !terms.length || whole || n > 0;
        sec.hidden = !show;
        hits += n; if (show && terms.length) secs++;
        railLinks.forEach((a) => { if (a.getAttribute('href') === '#' + sec.id) a.hidden = !show; });
      });
      document.getElementById('help-none').hidden = !terms.length || secs > 0;
      document.getElementById('help-count').textContent = terms.length
        ? (secs ? hits + ' ' + (hits === 1 ? 'match' : 'matches') + ' in ' + secs + ' ' + (secs === 1 ? 'section' : 'sections') : 'No matches')
        : '';
    }
    let ht;
    hq.addEventListener('input', () => { clearTimeout(ht); ht = setTimeout(search, 120); });
    hq.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && hq.value) { e.preventDefault(); hq.value = ''; search(); }
      if (e.key === 'Enter') { e.preventDefault(); helpMain.querySelector('section:not([hidden])')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
    // /help?q=boost opens with a search already run.
    const preset = new URLSearchParams(location.search).get('q');
    if (preset) { hq.value = preset; search(); }

    // Highlight the section in view in the contents rail.
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => entries.forEach((en) => {
        if (en.isIntersecting) railLinks.forEach((a) => a.classList.toggle('g-on', a.getAttribute('href') === '#' + en.target.id));
      }), { rootMargin: '-30% 0px -60% 0px' });
      sections.forEach((s) => io.observe(s));
    }

    // Admin checklist: ticks are kept in this browser only.
    const boxes = [...document.querySelectorAll('#help-checks input')];
    if (boxes.length) {
      let saved = {};
      try { saved = JSON.parse(store.get('help-checks') || '{}') || {}; } catch { saved = {}; }
      boxes.forEach((b) => { b.checked = !!saved[b.id]; });
      const update = () => {
        const n = boxes.filter((b) => b.checked).length;
        document.getElementById('help-checked').textContent = n + ' of ' + boxes.length + ' done';
        document.getElementById('help-meter').style.width = (n / boxes.length * 100) + '%';
      };
      const save = () => { const s = {}; boxes.forEach((b) => { if (b.checked) s[b.id] = 1; }); store.set('help-checks', JSON.stringify(s)); };
      boxes.forEach((b) => b.addEventListener('change', () => { save(); update(); }));
      document.getElementById('help-reset').addEventListener('click', () => { boxes.forEach((b) => { b.checked = false; }); save(); update(); });
      update();
    }
  }

  // New routine or task: show the fields for the chosen type; hidden ones aren't sent.
  const workForm = document.getElementById('work-form');
  if (workForm) {
    const who = workForm.querySelector('#assignee_id');
    const team = who.querySelector('[data-team]');
    const submit = workForm.querySelector('button[type=submit]');
    const syncKind = () => {
      const kind = workForm.querySelector('input[name=kind]:checked')?.value || 'task';
      workForm.querySelectorAll('[data-kind]').forEach((el) => {
        const on = el.dataset.kind === kind;
        el.hidden = !on;
        el.querySelectorAll('input,select,textarea').forEach((i) => { i.disabled = !on; });
      });
      team.textContent = kind === 'task' ? 'Choose a person' : 'Whole team (anyone can tick it)';
      team.disabled = kind === 'task';
      who.required = kind === 'task';
      submit.textContent = submit.dataset['label' + kind[0].toUpperCase() + kind.slice(1)];
    };
    workForm.querySelectorAll('input[name=kind]').forEach((r) => r.addEventListener('change', syncKind));
    syncKind();
  }

  // Stuck sticky notes: click the title to fold one away. Remembered per note; folded by default on phones.
  document.querySelectorAll('details[data-stuck]').forEach((d) => {
    const key = 'stuck-open:' + d.dataset.stuck;
    const saved = store.get(key);
    d.open = saved === null ? !window.matchMedia('(max-width: 640px)').matches : saved === '1';
    d.addEventListener('toggle', () => store.set(key, d.open ? '1' : '0'));
  });

  // Show/hide toggles (e.g. the note form).
  document.querySelectorAll('[data-toggle]').forEach((b) => b.addEventListener('click', () => {
    const el = document.getElementById(b.dataset.toggle);
    if (!el) return;
    el.hidden = !el.hidden;
    document.querySelectorAll('[data-toggle="' + b.dataset.toggle + '"][aria-expanded]').forEach((t) => t.setAttribute('aria-expanded', String(!el.hidden)));
    if (!el.hidden) el.querySelector('input:not([type=hidden]),textarea')?.focus();
  }));
  // "Show all" buttons: reveal the rest of a trimmed list, and back.
  document.querySelectorAll('[data-showall]').forEach((b) => {
    const more = b.textContent;
    b.addEventListener('click', () => {
      const el = document.getElementById(b.dataset.showall);
      if (!el) return;
      const all = el.classList.toggle('show-all');
      b.textContent = all ? (b.dataset.less || 'Show fewer') : more;
    });
  });
  // Small ⋯ menus close when you click elsewhere or press Esc.
  const lite = [...document.querySelectorAll('details[data-menu-lite]')];
  if (lite.length) {
    document.addEventListener('click', (e) => lite.forEach((d) => { if (d.open && !d.contains(e.target)) d.open = false; }));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') lite.forEach((d) => { if (d.open) { d.open = false; d.querySelector('summary').focus(); } }); });
  }

  // Sign the page out on its own when the 16-hour session ends, even if left open.
  const exp = document.getElementById('session-expiry');
  if (exp) {
    const ms = Number(exp.dataset.ends) * 1000 - Date.now();
    if (ms > 0 && ms < 2147483647) setTimeout(() => location.reload(), ms + 2000);
  }


  // ---- My launchpad: stars and recently opened (every page) ----
  const lpList = document.getElementById('ln-list');
  const lpCsrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const lp = {
    onChange: null, // the launcher hooks in here to refresh its marks
    async toggle(key) {
      const [type, id] = key.split(':');
      const res = await fetch(lpList.dataset.fav, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': lpCsrf() },
        body: JSON.stringify({ type, id: Number(id) }),
      });
      if (!res.ok) throw new Error('star failed');
      const data = await res.json();
      document.querySelectorAll('form[data-star="' + key + '"] .star').forEach((b) => {
        b.setAttribute('aria-pressed', String(data.starred));
        b.textContent = data.starred ? '★' : '☆';
        b.title = (data.starred ? 'Remove from' : 'Add to') + ' My launchpad';
      });
      const strip = document.getElementById('launchpad');
      if (strip && data.html) strip.outerHTML = data.html;
      lp.onChange?.(key, data.starred);
      return data.starred;
    },
    // Brand hubs record themselves on the server, so only tools and accounts are sent.
    opened(key) {
      if (!key || !lpList || !/^(tool|account):/.test(key)) return;
      const [type, id] = key.split(':');
      fetch(lpList.dataset.open, {
        method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': lpCsrf() },
        body: JSON.stringify({ type, id: Number(id) }),
      }).catch(() => {});
    },
  };
  document.addEventListener('submit', (e) => {
    const f = e.target.closest('form[data-star]');
    if (!f || !lpList) return;
    e.preventDefault();
    lp.toggle(f.dataset.star).catch(() => f.submit());
  });
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-lp]');
    if (a) lp.opened(a.dataset.lp);
  });
  // Arrange My launchpad: drag a star or use ‹ ›. Each change saves, and Jump to follows the same order.
  let lpDrag = null;
  async function lpSave(arranging, focusKey, focusMv) {
    const strip = document.getElementById('launchpad');
    if (!strip) return;
    const keys = [...strip.querySelectorAll('.lp-chip')].map((c) => c.dataset.key || c.dataset.lp).filter(Boolean);
    const res = await fetch(strip.dataset.order, {
      method: 'POST', credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': lpCsrf() },
      body: JSON.stringify({ keys, arranging }),
    });
    if (!res.ok) return;
    strip.outerHTML = (await res.json()).html;
    const fresh = document.getElementById('launchpad');
    const chip = focusKey && fresh.querySelector('.lp-chip[data-key="' + focusKey + '"]');
    const btn = chip && (chip.querySelector('[data-mv="' + focusMv + '"]:not(:disabled)') || chip.querySelector('.lp-mv:not(:disabled)'));
    (btn || fresh.querySelector('[data-arrange]'))?.focus();
  }
  document.addEventListener('click', (e) => {
    const arr = e.target.closest('[data-arrange]');
    if (arr) { lpSave(!document.getElementById('launchpad').classList.contains('arranging')); return; }
    const mv = e.target.closest('.lp-mv');
    if (!mv) return;
    const chip = mv.closest('.lp-move');
    const d = Number(mv.dataset.mv);
    const other = d < 0 ? chip.previousElementSibling : chip.nextElementSibling;
    if (!other || !other.classList.contains('lp-move')) return;
    if (d < 0) other.before(chip); else other.after(chip);
    lpSave(true, chip.dataset.key, mv.dataset.mv);
  });
  document.addEventListener('dragstart', (e) => {
    const chip = e.target.closest?.('.lp-move');
    if (!chip) return;
    lpDrag = chip; chip.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', chip.dataset.key);
  });
  document.addEventListener('dragover', (e) => {
    const over = lpDrag && e.target.closest?.('.lp-move');
    if (!over) return;
    e.preventDefault();
    document.querySelectorAll('.lp-move.over').forEach((c) => c.classList.remove('over'));
    if (over !== lpDrag) over.classList.add('over');
  });
  document.addEventListener('drop', (e) => {
    const over = lpDrag && e.target.closest?.('.lp-move');
    if (!over) return;
    e.preventDefault();
    if (over !== lpDrag) {
      const after = e.clientX > over.getBoundingClientRect().left + over.offsetWidth / 2;
      if (after) over.after(lpDrag); else over.before(lpDrag);
      lpSave(true, lpDrag.dataset.key);
    }
  });
  document.addEventListener('dragend', () => {
    document.querySelectorAll('.lp-move.dragging,.lp-move.over').forEach((c) => c.classList.remove('dragging', 'over'));
    lpDrag = null;
  });

  // Middle-click opens in a new tab without a click event.
  document.addEventListener('auxclick', (e) => {
    const a = e.target.closest('a[data-lp]');
    if (a && e.button === 1) lp.opened(a.dataset.lp);
  });

  // ---- ⌘K quick launcher (every page) ----
  (function launcher() {
    const dlg = document.getElementById('launcher');
    if (!dlg) return;
    const input = document.getElementById('ln-q');
    const list = document.getElementById('ln-list');
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform);
    document.documentElement.classList.add(isMac ? 'is-mac' : 'is-pc');
    let items = null, shown = [], sel = 0;

    async function load() {
      if (items) return;
      try {
        const res = await fetch(list.dataset.src, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        items = (await res.json()).map((it) => ({ ...it, hay: (it.t + ' ' + it.k + ' ' + (it.s || '') + ' ' + (it.a || '')).toLowerCase(), tl: it.t.toLowerCase() }));
      } catch { items = []; }
    }
    const order = { Brand: 0, Account: 1, Page: 2, Category: 3, Tool: 3, Action: 4 };
    function score(it, words) {
      let sc = 0;
      for (const w of words) {
        const i = it.hay.indexOf(w);
        if (i < 0) return -1;
        sc += it.tl.startsWith(w) ? 30 : (it.tl.includes(' ' + w) ? 20 : (it.tl.includes(w) ? 12 : 4));
      }
      // Your stars and recent opens rank higher.
      return sc - order[it.k] + (it.f !== undefined ? 15 : 0) + (it.r !== undefined ? 8 - Math.min(it.r, 7) : 0);
    }
    function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    // Brand mark (Instagram, Google Ads…) from the shared sprite, in its brand color.
    function mark(ic) {
      if (!ic || !/^[a-z0-9]+$/.test(ic.s) || !/^#[0-9A-Fa-f]{6}$/.test(ic.h)) return '';
      return '<span class="bi bi-xs ' + esc(ic.c) + '" style="--bi:' + ic.h + '" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="' + esc(list.dataset.icons) + '#bi-' + ic.s + '"></use></svg></span>';
    }
    function render() {
      const words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
      shown = !words.length
        ? [
            ...items.filter((it) => it.f !== undefined).sort((a, b) => a.f - b.f),
            ...items.filter((it) => it.f === undefined && it.r !== undefined).sort((a, b) => a.r - b.r).slice(0, 6),
            ...items.filter((it) => it.f === undefined && it.r === undefined && (it.k === 'Brand' || it.k === 'Page')),
          ].slice(0, 15)
        : items.map((it) => [score(it, words), it]).filter(([sc]) => sc >= 0).sort((a, b) => b[0] - a[0]).slice(0, 12).map(([, it]) => it);
      sel = 0;
      list.innerHTML = shown.length ? shown.map((it, i) =>
        '<li role="option" id="ln-' + i + '" aria-selected="' + (i === sel) + '" data-i="' + i + '"><span class="ln-k ln-' + it.k.toLowerCase() + '">' + it.k + '</span><span class="ln-t"><b>' + (it.f !== undefined ? '<span class="ln-star" aria-label="starred">★</span> ' : '') + mark(it.ic) + esc(it.t) + '</b>' + (it.s ? '<small>' + esc(it.s) + '</small>' : '') + '</span>' + (it.x ? '<span class="ln-ext" aria-hidden="true">↗</span>' : '') + '</li>'
      ).join('') : '<li class="ln-empty">Nothing matches. Try a brand name or a platform like “instagram”.</li>';
      input.setAttribute('aria-activedescendant', shown.length ? 'ln-0' : '');
    }
    function move(d) {
      if (!shown.length) return;
      sel = (sel + d + shown.length) % shown.length;
      list.querySelectorAll('li[data-i]').forEach((li) => li.setAttribute('aria-selected', String(Number(li.dataset.i) === sel)));
      list.querySelector('#ln-' + sel)?.scrollIntoView({ block: 'nearest' });
      input.setAttribute('aria-activedescendant', 'ln-' + sel);
    }
    function go(i) {
      const it = shown[i];
      if (!it) return;
      dlg.close();
      lp.opened(it.key);
      if (it.x) { window.open(it.u, '_blank', 'noopener'); return; }
      const here = location.origin + location.pathname;
      if (it.u.split('#')[0] === here && it.u.includes('#')) { location.hash = it.u.split('#')[1]; window.dispatchEvent(new HashChangeEvent('hashchange')); }
      else location.href = it.u;
    }
    // ⇧Enter stars or un-stars the highlighted page, category, brand, account or tool.
    async function star(i) {
      const it = shown[i];
      if (!it?.key) return;
      const keep = sel;
      try { await lp.toggle(it.key); } catch { return; }
      render();
      move(Math.min(keep, shown.length - 1) - sel);
    }
    lp.onChange = (key, on) => {
      if (!items) return;
      const it = items.find((x) => x.key === key);
      if (!it) return;
      if (on) it.f = Math.max(-1, ...items.map((x) => x.f ?? -1)) + 1; else delete it.f;
    };
    async function open() {
      if (dlg.open) return;
      dlg.showModal(); input.value = '';
      await load(); render(); input.focus();
    }
    document.getElementById('launch-open')?.addEventListener('click', open);
    document.addEventListener('keydown', (e) => {
      const typing = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); open(); }
      else if (e.key === '/' && !typing && !dlg.open) { e.preventDefault(); open(); }
    });
    input.addEventListener('input', () => items && render());
    input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter' && e.shiftKey) { e.preventDefault(); star(sel); }
      else if (e.key === 'Enter') { e.preventDefault(); go(sel); }
    });
    list.addEventListener('click', (e) => { const li = e.target.closest('li[data-i]'); if (li) go(Number(li.dataset.i)); });
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  })();

  const tabs = [...document.querySelectorAll('.tab')];
  if (!tabs.length) return;
  const views = [...document.querySelectorAll('[data-view]')];
  const q = document.getElementById('q');
  // Home: right after sign-in, or via the logo, start on Routines for all brands.
  const goHome = document.getElementById('session-expiry')?.dataset.home === '1';
  if (goHome) {
    store.set('tab', 'tasks');
    store.set('brand', 'all');
    history.replaceState(null, '', location.pathname);
  }
  const state = { tab: 'tasks', brand: store.get('brand') || 'all', q: '' };

  // ---- Omni search: the search box also looks across all of OmniVEN ----
  const omni = document.getElementById('omni');
  if (q && omni) {
    let oTimer, oReq = 0, oLinks = [], oSel = -1;
    const oEsc = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };
    const oMark = (text, words) => {
      let h = oEsc(text);
      words.forEach((w) => { if (w.length > 1) h = h.replace(new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>'); });
      return h;
    };
    const oIcon = (ic) => (!ic || !/^[a-z0-9]+$/.test(ic.s) || !/^#[0-9A-Fa-f]{6}$/.test(ic.h)) ? ''
      : '<span class="bi bi-sm ' + oEsc(ic.c) + '" style="--bi:' + ic.h + '" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="' + oEsc(omni.dataset.icons) + '#bi-' + ic.s + '"></use></svg></span>';
    const oClose = () => { omni.hidden = true; q.setAttribute('aria-expanded', 'false'); oSel = -1; };
    const oPick = (i) => {
      oSel = i;
      oLinks.forEach((a, j) => a.setAttribute('aria-selected', String(j === i)));
      if (oLinks[i]) { oLinks[i].scrollIntoView({ block: 'nearest' }); q.setAttribute('aria-activedescendant', oLinks[i].id); }
    };
    function oRender(data) {
      const words = q.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
      const groups = data.groups || [];
      let n = 0;
      const real = groups.filter((g) => g.label !== 'Help');
      omni.innerHTML = (real.length ? '' : '<div class="omni-empty">Nothing in OmniVEN matches “' + oEsc(q.value.trim()) + '”.</div>')
        + groups.map((g) => '<div class="omni-g" role="group" aria-label="' + oEsc(g.label) + '"><div class="omni-h"><span>' + oEsc(g.label) + '</span>' + (g.total > g.items.length ? '<span>' + g.items.length + ' of ' + g.total + '</span>' : '') + '</div>'
          + g.items.map((it) => '<a id="om-' + (n++) + '" role="option" aria-selected="false" href="' + oEsc(it.u) + '"' + (it.x ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + oIcon(it.ic)
            + '<span class="ot"><b>' + oMark(it.t, words) + '</b>' + (it.s ? '<small>' + oMark(it.s, words) + '</small>' : '') + '</span>' + (it.x ? '<span class="ox" aria-hidden="true">↗</span>' : '') + '</a>').join('') + '</div>').join('')
        + '<div class="omni-foot">↑ ↓ to move · Enter to open · Esc to close. This tab is filtered too.</div>';
      oLinks = [...omni.querySelectorAll('a[role=option]')];
      omni.hidden = false; q.setAttribute('aria-expanded', 'true');
      oPick(oLinks.length ? 0 : -1);
    }
    async function oSearch() {
      const term = q.value.trim();
      if (term.length < 2) { oClose(); return; }
      const mine = ++oReq;
      try {
        const res = await fetch(omni.dataset.src + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok || mine !== oReq) return;
        oRender(await res.json());
      } catch {}
    }
    q.addEventListener('input', () => { clearTimeout(oTimer); oTimer = setTimeout(oSearch, 180); });
    q.addEventListener('focus', () => { if (q.value.trim().length > 1 && omni.innerHTML) { omni.hidden = false; q.setAttribute('aria-expanded', 'true'); } });
    q.addEventListener('keydown', (e) => {
      if (omni.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); oPick(Math.min(oSel + 1, oLinks.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); oPick(Math.max(oSel - 1, 0)); }
      else if (e.key === 'Enter' && oLinks[oSel]) { e.preventDefault(); oLinks[oSel].click(); }
      else if (e.key === 'Escape') { e.preventDefault(); oClose(); }
    });
    omni.addEventListener('click', (e) => {
      const a = e.target.closest('a[role=option]');
      if (!a) return;
      oClose();
      // Same-page tab links switch tabs instead of reloading.
      const u = new URL(a.href);
      if (!a.target && u.origin + u.pathname + u.search === location.origin + location.pathname + location.search && u.hash) {
        e.preventDefault(); location.hash = u.hash; window.dispatchEvent(new HashChangeEvent('hashchange'));
      }
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('.omni-wrap')) oClose(); });
  }

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
    if (!views.some((v) => v.dataset.view === t)) t = 'tasks';
    if (t === 'slack') loadSlack();
    state.tab = t;
    tabs.forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === t)));
    views.forEach((v) => { v.hidden = v.dataset.view !== t; });
    // Brand chips filter tasks, routines, brands, tools and access.
    const pick = document.getElementById('brandpick');
    if (pick) pick.hidden = t === 'postit' || t === 'slack';
    store.set('tab', t);
    if (push) history.replaceState(null, '', t === 'tasks' ? location.pathname + location.search : '#' + t);
  }

  function apply() {
    const chips = document.querySelectorAll('.chip');
    if (![...chips].some((c) => c.dataset.brand === state.brand)) state.brand = 'all';
    chips.forEach((c) => c.setAttribute('aria-pressed', String(c.dataset.brand === state.brand)));
    // The brand picker's button names the brand in force, with its color dot.
    const on = [...chips].find((c) => c.dataset.brand === state.brand);
    const label = document.getElementById('brandpick-label');
    if (label && on) {
      label.textContent = on.textContent.trim();
      const dot = on.querySelector('.dot');
      document.getElementById('brandpick-dot').style.background = dot ? dot.style.background : '';
      document.getElementById('brandpick').classList.toggle('on', state.brand !== 'all');
    }

    const match = (el) => {
      const brands = (el.dataset.brands || 'all').split(' ');
      const okBrand = state.brand === 'all' || brands.includes('all') || brands.includes(state.brand);
      const okQ = !state.q || (el.dataset.search || '').includes(state.q);
      return okBrand && okQ;
    };
    document.querySelectorAll('[data-brands]').forEach((el) => { el.hidden = !match(el); });
    // While filtering, long columns show everything that matches.
    document.body.classList.toggle('is-filtering', state.brand !== 'all' || !!state.q);
    document.querySelectorAll('[data-brand-row]').forEach((el) => {
      el.hidden = !(state.brand === 'all' || el.dataset.brandRow === state.brand);
    });
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
    if (chip) { state.brand = chip.dataset.brand; store.set('brand', state.brand); apply(); chip.closest('details')?.removeAttribute('open'); return; }
    const pick = document.getElementById('brandpick');
    if (pick?.open && !pick.contains(e.target)) pick.removeAttribute('open');
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

  // Admins: assignment and completion times stay folded away until asked for.
  const stampBtns = document.querySelectorAll('[data-stamps]');
  if (stampBtns.length) {
    const showStamps = (on) => {
      document.body.classList.toggle('show-stamps', on);
      stampBtns.forEach((b) => b.setAttribute('aria-pressed', String(on)));
      store.set('stamps', on ? '1' : '0');
    };
    showStamps(store.get('stamps') === '1');
    stampBtns.forEach((b) => b.addEventListener('click', () => showStamps(!document.body.classList.contains('show-stamps'))));
  }
  document.addEventListener('keydown', (e) => {
    const pick = document.getElementById('brandpick');
    if (e.key === 'Escape' && pick?.open) { pick.removeAttribute('open'); pick.querySelector('summary').focus(); }
  });

  // Tasks: Mine / Everyone.
  const me = exp?.dataset.me;
  // Mine shows only your column and your finished tasks; Everyone shows every column.
  // (Brand chips and the search box filter the items themselves.)
  function filterTasks(mode) {
    store.set('taskfilter', mode);
    document.querySelectorAll('[data-taskfilter]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.taskfilter === mode)));
    document.querySelector('section[data-view="tasks"]')?.classList.toggle('only-mine', mode === 'mine');
  }
  document.querySelectorAll('[data-taskfilter]').forEach((b) => b.addEventListener('click', () => filterTasks(b.dataset.taskfilter)));

  window.addEventListener('hashchange', () => { const t = location.hash.slice(1); if (t) setTab(t, false); });
  setTab(goHome ? 'tasks' : (location.hash.slice(1) || store.get('tab') || 'tasks'), false);
  // A completed-tasks date range was just picked: go straight to the list.
  if (/[?&]done_(from|to)=/.test(location.search)) document.getElementById('done-list')?.scrollIntoView({ block: 'start' });
  apply();
  checkUnread();
  filterTasks(store.get('taskfilter') || 'all');

  // A starred tool category (?cat=affiliate#tools): show every brand and scroll to that section.
  const catSlug = new URLSearchParams(location.search).get('cat');
  const catEl = catSlug && document.querySelector('[data-cat="' + CSS.escape(catSlug) + '"]');
  if (catEl) {
    if (catEl.hidden) { state.brand = 'all'; state.q = ''; q.value = ''; apply(); }
    catEl.scrollIntoView({ block: 'start' });
    catEl.classList.add('flash-cat');
    setTimeout(() => catEl.classList.remove('flash-cat'), 2200);
  }

  // Daily Hotlist popup.
  const pop = document.getElementById('hotlist-popup');
  if (pop && typeof pop.showModal === 'function') pop.showModal();
})();

// Copy buttons: data-copy="#input-id" copies that input's value.
document.addEventListener('click', (e) => {
  const b = e.target.closest('[data-copy]');
  if (!b) return;
  const el = document.querySelector(b.dataset.copy);
  if (!el) return;
  const done = () => { const t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => { b.textContent = t; }, 1600); };
  if (navigator.clipboard?.writeText) navigator.clipboard.writeText(el.value).then(done, () => { el.select(); document.execCommand('copy'); done(); });
  else { el.select(); document.execCommand('copy'); done(); }
});

// Campaign links: show the fields a preset needs, fill in the brand's site, and preview the link.
(() => {
  const form = document.getElementById('link-form');
  if (!form) return;
  const $ = (n) => form.elements[n];
  const clean = (v) => String(v || '').trim().toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9._-]+/g, '').replace(/-{2,}/g, '-').replace(/^-|-$/g, '');
  const hint = document.getElementById('preset-hint');
  const preview = document.getElementById('utm-preview');
  const opt = () => $('preset').selectedOptions[0];

  function update() {
    const o = opt();
    const fixedSource = o?.dataset.source || '', fixedMedium = o?.dataset.medium || '';
    form.querySelector('[data-utm="source"]').hidden = !!fixedSource;
    form.querySelector('[data-utm="medium"]').hidden = !!fixedMedium;
    $('source').required = !fixedSource; $('medium').required = !fixedMedium;
    hint.textContent = o?.dataset.channel ? 'Counts as ' + o.dataset.channel + ' on the Sales page.' : '';

    const utm = { source: clean(fixedSource || $('source').value), medium: clean(fixedMedium || $('medium').value), campaign: clean($('campaign').value), content: clean($('content').value), term: clean($('term').value) };
    let url;
    try { url = new URL($('base_url').value.trim()); } catch { preview.textContent = '—'; return; }
    [...url.searchParams.keys()].filter((k) => k.toLowerCase().startsWith('utm_')).forEach((k) => url.searchParams.delete(k));
    Object.entries(utm).forEach(([k, v]) => { if (v) url.searchParams.set('utm_' + k, v); });
    preview.textContent = url.toString();
  }

  $('brand_id').addEventListener('change', () => {
    const site = $('brand_id').selectedOptions[0]?.dataset.site;
    if (site && !$('base_url').value.trim()) { $('base_url').value = /^https?:\/\//.test(site) ? site : 'https://' + site; }
    update();
  });
  form.addEventListener('input', update);
  form.addEventListener('change', update);
  update();
})();

// AI answers: color a cell by what the assistant said as soon as it's picked.
document.addEventListener('change', (e) => {
  const s = e.target.closest('select[data-aians]');
  if (!s) return;
  const td = s.closest('td');
  td.className = td.className.replace(/\br-\S+/, '') + ' r-' + (s.value || 'none');
});

// Launch templates: show each step's due date as the start day changes.
(() => {
  const form = document.getElementById('tpl-run');
  if (!form) return;
  const steps = JSON.parse(form.dataset.steps || '[]');
  const fmt = new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' });
  const update = () => {
    const v = form.elements.starts_on.value;
    steps.forEach((s) => {
      const cell = form.querySelector('[data-due="' + s.id + '"]');
      if (!cell) return;
      if (!v) { cell.textContent = '—'; return; }
      const d = new Date(v + 'T00:00:00Z');
      d.setUTCDate(d.getUTCDate() + s.offset);
      cell.textContent = fmt.format(d);
    });
  };
  form.elements.starts_on.addEventListener('input', update);
  // Show step titles with the launch name filled in.
  const names = () => {
    const n = form.elements.name.value.trim() || '{name}';
    form.querySelectorAll('[data-tpl]').forEach((el) => { el.textContent = el.dataset.tpl.split('{name}').join(n); });
  };
  form.elements.name.addEventListener('input', names);
  update();
  names();
})();

// "Print or save as PDF" buttons.
document.addEventListener('click', (e) => { if (e.target.closest('[data-print]')) window.print(); });

// A task result was just saved (or needs fixing): bring that finished task into view.
document.querySelector('[data-scroll-here]')?.scrollIntoView({ block: 'center' });

// Affiliate asset form: show the fields the chosen kind needs.
(() => {
  const form = document.getElementById('asset-form');
  if (!form) return;
  const sync = () => {
    const kind = form.querySelector('input[name="kind"]:checked')?.value || 'image';
    form.querySelectorAll('[data-for]').forEach((el) => { el.hidden = !el.dataset.for.split(' ').includes(kind); });
  };
  form.addEventListener('change', (e) => { if (e.target.name === 'kind') sync(); });
  sync();
})();

// Assistant: check back on answers still being worked out, send with Ctrl/⌘+Enter, copy an answer.
(() => {
  const poll = (el) => {
    let tries = 0;
    const tick = () => {
      tries++;
      fetch(el.dataset.poll, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
        .then((d) => {
          if (d.status === 'pending') { if (tries < 200) setTimeout(tick, tries < 10 ? 2000 : 4000); return; }
          const tpl = document.createElement('template');
          tpl.innerHTML = d.html.trim();
          el.replaceWith(tpl.content);
        })
        .catch(() => { if (tries < 200) setTimeout(tick, 6000); });
    };
    setTimeout(tick, 2000);
  };
  document.querySelectorAll('.as-msg[data-poll]').forEach(poll);
  const q = document.getElementById('as-q');
  if (q) {
    q.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); q.form.requestSubmit(); } });
    if (!document.querySelector('.as-thread')) q.focus();
  }
  document.getElementById('as-form')?.addEventListener('submit', (e) => {
    const b = e.target.querySelector('button[type=submit]');
    if (b) { b.disabled = true; b.textContent = 'Sending…'; }
  });
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-copy-answer]');
    if (!b) return;
    const el = document.querySelector(b.dataset.copyAnswer);
    if (!el) return;
    const done = () => { const t = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => { b.textContent = t; }, 1600); };
    navigator.clipboard?.writeText(el.innerText).then(done, () => {});
  });
  const last = document.querySelector('.as-thread .as-msg:last-child');
  if (last && !location.hash) last.scrollIntoView({ block: 'start' });
  const keep = (k, v) => { try { localStorage.setItem('omniven:' + k, v); } catch {} };
  // ---- Sidebar (TailAdmin-style): collapses to icons on desktop, slides out on phones; account menu in the top bar ----
  const shell = document.getElementById('shell');
  if (shell) {
    const root = document.documentElement;
    const toggle = document.getElementById('nav-toggle');
    const scrim = document.getElementById('nav-scrim');
    const sidebar = document.getElementById('sidebar');
    const small = window.matchMedia('(max-width: 1023px)');
    const me = document.querySelector('.me-menu');
    const open = (on) => {
      shell.classList.toggle('sb-open', on);
      scrim.hidden = !on;
      toggle.setAttribute('aria-expanded', String(on));
      if (on) sidebar.querySelector('a, summary')?.focus();
    };
    toggle.addEventListener('click', () => {
      if (small.matches) { open(!shell.classList.contains('sb-open')); return; }
      const collapsed = root.classList.toggle('sb-collapsed');
      keep('sidebar', collapsed ? 'collapsed' : 'open');
      toggle.setAttribute('aria-expanded', String(!collapsed));
    });
    if (!small.matches) toggle.setAttribute('aria-expanded', String(!root.classList.contains('sb-collapsed')));
    scrim.addEventListener('click', () => open(false));
    sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', () => { open(false); toggle.focus(); });
    small.addEventListener('change', () => open(false));
    // Following a link (including a same-page dashboard tab) closes the phone menu.
    sidebar.addEventListener('click', (e) => { if (e.target.closest('a') && small.matches) open(false); });
    // Groups: one open at a time.
    const groups = [...sidebar.querySelectorAll('details.sb-group')];
    groups.forEach((g) => g.addEventListener('toggle', () => { if (g.open) groups.forEach((o) => { if (o !== g) o.open = false; }); }));
    // Dashboard tab links light up for the tab on screen.
    const markTab = () => {
      const onDash = location.pathname === '/' || location.pathname === '';
      const tab = onDash ? (location.hash.slice(1) || document.querySelector('.tab[aria-selected="true"]')?.dataset.tab || '') : '';
      sidebar.querySelectorAll('[data-sb-hash]').forEach((a) => {
        const on = onDash && a.dataset.sbHash === tab;
        if (on) a.setAttribute('aria-current', 'page'); else if (a.getAttribute('aria-current') === 'page') a.removeAttribute('aria-current');
        if (on && !groups.some((g) => g.open)) a.closest('details').open = true;
      });
    };
    markTab();
    window.addEventListener('hashchange', markTab);
    document.addEventListener('click', (e) => { if (e.target.closest('.tab')) setTimeout(markTab, 0); });
    // Account menu: click outside or Esc closes; Esc also closes the phone menu.
    document.addEventListener('click', (e) => { if (me && me.open && !me.contains(e.target)) me.open = false; });
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      if (me && me.open) { me.open = false; me.querySelector('summary').focus(); } else if (shell.classList.contains('sb-open')) { open(false); toggle.focus(); }
    });
  }
  // Light or dark: remembered on this device; with no choice it follows the device.
  document.querySelectorAll('[data-theme-toggle]').forEach((b) => b.addEventListener('click', () => {
    const root = document.documentElement;
    const now = root.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const next = now === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    keep('theme', next);
  }));
  // ---- Date range picker: a preset or comparison applies at once; editing a date switches to custom dates ----
  document.querySelectorAll('form[data-range-picker]').forEach((f) => {
    const preset = f.querySelector('[data-rp-preset]');
    const dates = f.querySelectorAll('[data-rp-date]');
    const sync = () => dates.forEach((d) => { d.disabled = preset.value !== 'custom'; });
    preset.addEventListener('change', () => {
      sync();
      if (preset.value === 'custom') dates[0].focus(); else f.requestSubmit();
    });
    dates.forEach((d) => {
      d.disabled = false;
      d.addEventListener('change', () => { preset.value = 'custom'; });
    });
    // Preset links leave the dates out of the address; custom keeps them.
    f.addEventListener('submit', sync);
    f.querySelector('[data-rp-compare]').addEventListener('change', () => { sync(); f.requestSubmit(); });
  });

  // ---- Trend charts: crosshair and tooltip on hover, arrow keys to step through points ----
  document.querySelectorAll('figure[data-trend]').forEach((fig) => {
    let d;
    try { d = JSON.parse(fig.dataset.trend); } catch { return; }
    const pts = d.pts || [];
    if (!pts.length) return;
    const area = fig.querySelector('.trend-area');
    const cross = fig.querySelector('.trend-cross');
    const dot = fig.querySelector('.trend-dot');
    const tip = fig.querySelector('.trend-tip');
    let at = -1;
    const show = (i) => {
      at = Math.max(0, Math.min(pts.length - 1, i));
      const p = pts[at];
      const x = pts.length > 1 ? at / (pts.length - 1) : 0.5;
      cross.style.left = dot.style.left = (x * 100) + '%';
      cross.hidden = false;
      dot.hidden = p.y === null;
      if (p.y !== null) dot.style.top = ((1 - p.y) * 100) + '%';
      tip.replaceChildren();
      const add = (cls, label, value) => {
        const row = document.createElement('div'); row.className = cls;
        const k = document.createElement('span'); k.textContent = label;
        const v = document.createElement('b'); v.textContent = value;
        row.append(k, v); tip.append(row);
      };
      const head = document.createElement('div'); head.className = 'tt-h'; head.textContent = p.l; tip.append(head);
      add('tt-now', d.name.split(',')[0], p.v);
      if (d.cmp && p.c !== null) add('tt-cmp', 'Then', p.c);
      tip.hidden = false;
      tip.classList.toggle('flip', x > 0.6);
      tip.style.left = (x * 100) + '%';
    };
    const hide = () => { cross.hidden = dot.hidden = tip.hidden = true; at = -1; };
    area.addEventListener('pointermove', (e) => {
      const r = area.getBoundingClientRect();
      show(Math.round(((e.clientX - r.left) / r.width) * (pts.length - 1)));
    });
    area.addEventListener('pointerleave', hide);
    area.addEventListener('blur', hide);
    area.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); show((at < 0 ? (e.key === 'ArrowRight' ? -1 : pts.length) : at) + (e.key === 'ArrowRight' ? 1 : -1)); }
      else if (e.key === 'Home') { e.preventDefault(); show(0); }
      else if (e.key === 'End') { e.preventDefault(); show(pts.length - 1); }
      else if (e.key === 'Escape') hide();
    });
  });
  // ---- TaskBuddy AI: wait for the list, then reload; keep the Add button's count in step ----
  const tbWait = document.querySelector('[data-tb-poll]');
  if (tbWait) {
    let tries = 0;
    const check = () => {
      tries++;
      fetch(tbWait.dataset.tbPoll, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => { if (d && d.status !== 'pending') location.reload(); else if (tries < 150) setTimeout(check, tries < 10 ? 2500 : 5000); })
        .catch(() => { if (tries < 150) setTimeout(check, 5000); });
    };
    setTimeout(check, 2500);
  }
  document.querySelectorAll('form[data-tb-review]').forEach((f) => {
    const btn = f.querySelector('[data-tb-count]');
    const sync = () => {
      const n = f.querySelectorAll('[data-tb-keep]:checked').length;
      btn.textContent = n ? 'Add ' + n + (n === 1 ? ' task' : ' tasks') : 'Tick a task to add';
      btn.disabled = n === 0;
      f.querySelectorAll('[data-tb-keep]').forEach((c) => c.closest('.tb-task').classList.toggle('off', !c.checked));
    };
    f.addEventListener('change', (e) => { if (e.target.matches('[data-tb-keep]')) sync(); });
    f.addEventListener('submit', () => { btn.disabled = true; btn.textContent = 'Adding…'; });
    sync();
  });
})();

// Staff guide: bigger screenshots, the "I want to…" finder, copyable questions and the chapter in view.
(function () {
    var lb = document.getElementById('g-lb');
    if (!lb) return;
    var img = document.getElementById('g-lb-img'), cap = document.getElementById('g-lb-cap');
    document.querySelectorAll('.guide [data-zoom]').forEach(function (b) {
        b.addEventListener('click', function () {
            img.src = b.dataset.zoom; img.alt = (b.querySelector('img') || {}).alt || ''; cap.textContent = b.dataset.cap || '';
            if (lb.showModal) lb.showModal();
        });
    });
    document.getElementById('g-lb-close').addEventListener('click', function () { lb.close(); });
    lb.addEventListener('click', function (e) { if (e.target === lb) lb.close(); });

    var q = document.getElementById('g-finder-q'), none = document.getElementById('g-finder-none');
    var items = Array.prototype.slice.call(document.querySelectorAll('#g-finder-list li'));
    q.addEventListener('input', function () {
        var words = q.value.toLowerCase().split(/\s+/).filter(Boolean), shown = 0;
        items.forEach(function (li) {
            var text = li.textContent.toLowerCase();
            li.hidden = !words.every(function (w) { return text.indexOf(w) !== -1; });
            if (!li.hidden) shown++;
        });
        none.hidden = shown > 0;
    });

    document.querySelectorAll('.guide .g-copy').forEach(function (b) {
        b.addEventListener('click', function () {
            var span = b.parentNode.querySelector('span');
            var done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); };
            var select = function () { var r = document.createRange(); r.selectNodeContents(span); var s = getSelection(); s.removeAllRanges(); s.addRange(r); };
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(span.textContent).then(done, select);
            else select();
        });
    });

    var links = Array.prototype.slice.call(document.querySelectorAll('.guide .g-chapters a'));
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) {
                if (e.isIntersecting) links.forEach(function (a) { a.classList.toggle('g-on', a.getAttribute('href') === '#' + e.target.id); });
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        document.querySelectorAll('.guide section.g-ch').forEach(function (s) { io.observe(s); });
    }
})();
