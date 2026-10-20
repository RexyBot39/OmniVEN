@extends('layouts.app', ['title' => 'Staff guide'])

@php
    // Chapters for pages a person can't open are left out, so the guide only shows what they can use.
    $user = auth()->user();
    $rev = $user->can('see-revenue');
    $admin = $user->can('admin');
    $ai = \App\Support\Assistant\Assistant::configured() && $user->can('use-assistant');
@endphp

@section('content')
<div class="guide">
<div class="g-cover" id="g-top">
  <div class="g-wrap">
    <div>
      <div class="g-eyebrow">For the 39DollarGlasses marketing team</div>
      <h1>OmniVEN Staff Guide @include('partials.star', ['key' => 'page:32', 'label' => 'Staff guide'])</h1>
      <p class="g-lede">OmniVEN does a lot. You don’t need all of it on day one. This guide starts with the five things everyone uses, then walks through every section in the same order as the sidebar, so you can come back to a chapter when you need it.</p>
      <div class="g-cover-facts">
        <span class="g-fact"><b>5</b>things to learn first</span>
        <span class="g-fact"><b>6</b>sections in the sidebar</span>
        <span class="g-fact"><b>15</b>minutes a day, done well</span>
      </div>
    </div>
    <button class="g-shot g-cover-shot" type="button" data-zoom="{{ asset('img/guide/home.jpg') }}" data-cap="The home page: your Hotlist, then a column of tasks for each person."><img src="{{ asset('img/guide/home.jpg') }}" alt="The OmniVEN home page with the sidebar, the Hotlist and the task board" width="1440" height="900"></button>
  </div>
</div>

<nav class="g-chapters" aria-label="Chapters">
  <div class="g-wrap">
    <a href="#start" style="--c:var(--teal)"><i></i>Start here</a>
    <a href="#day" style="--c:var(--teal)"><i></i>Your day</a>
    <a href="#find" style="--c:var(--navy)"><i></i>Finding things</a>
    <a href="#work" style="--c:var(--c-work)"><i></i>Work</a>
@if($rev)
    <a href="#revenue" style="--c:var(--c-rev)"><i></i>Revenue</a>
@endif
    <a href="#growth" style="--c:var(--c-growth)"><i></i>Growth</a>
    <a href="#search" style="--c:var(--c-search)"><i></i>Search &amp; AI</a>
    <a href="#reputation" style="--c:var(--c-rep)"><i></i>Reputation</a>
    <a href="#brands" style="--c:var(--c-brands)"><i></i>Brands &amp; tools</a>
@if($ai)
    <a href="#ai" style="--c:var(--c-ai)"><i></i>AI helpers</a>
@endif
    <a href="#finder" style="--c:var(--muted)"><i></i>I want to…</a>
    <a href="#playbooks" style="--c:var(--muted)"><i></i>Playbooks</a>
    <a href="#rules" style="--c:var(--c-rep)"><i></i>House rules</a>
  </div>
</nav>

<div class="g-main g-wrap">

{{-- START HERE --}}
<section class="g-ch" id="start" style="--c:var(--teal)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg></span>
    <span class="g-ch-kicker">Day one · 15 minutes</span>
    <h2>Start here: learn these five</h2>
  </div>
  <div class="g-start">
    <ol class="g-steps">
      <li><b>Sign in at omniven.cc</b><span>Use <b>Sign in with Google</b> and your @39dollarglasses.com account. You stay signed in for 16 hours. No access? Ask an admin to add you on People.</span></li>
      <li><b>Find your column on the home page</b><span>Every person has a column of open tasks, plus the routines due for them now. Tick the box when a task is done. Click the OmniVEN logo to come back here any time.</span></li>
      <li><b>Clear the Hotlist first</b><span>The Hotlist at the top holds the urgent work. Anything with your name on it comes before everything else.</span></li>
      <li><b>Press <kbd>Ctrl K</kbd> (<kbd>⌘K</kbd> on a Mac)</b><span>Type a brand and a platform, like <i>ocusafe insta</i>, and press Enter. It opens the account in a new tab. This one shortcut replaces most bookmarks.</span></li>
      <li><b>Star what you open most</b><span>Press ☆ next to any page, tool, brand or account. Your stars line up under the Hotlist and at the top of Jump to, on every device.</span></li>
    </ol>
    <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
      <figure>
        <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/home-board.jpg') }}" data-cap="Each column is one person: their tasks (with a colored brand edge) and their routines due now. The + adds a task for that person."><img src="{{ asset('img/guide/home-board.jpg') }}" alt="Task board with a column for each person" width="1440" height="820" loading="lazy"></button>
        <figcaption>The task board. Each column is one person. A thin colored edge shows the brand; a small coral dot means Hotlist.</figcaption>
      </figure>
      <div class="g-ignore"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg><span><b>You can ignore the rest until you need it.</b> Everything else in this guide is a tool for a specific job. When the job comes up, open that chapter, or type what you want into the <a href="#finder">I want to…</a> finder.</span></div>
    </div>
  </div>
</section>

{{-- YOUR DAY --}}
<section class="g-ch" id="day" style="--c:var(--teal)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
    <span class="g-ch-kicker">The rhythm that keeps it simple</span>
    <h2>A day with OmniVEN</h2>
  </div>
  <div class="g-day">
    <div class="g-slot" style="--c:var(--c-work)">
      <h3>Morning <small>10 minutes</small></h3>
      <ul>
        <li>Read your <b>Slack morning summary</b>: your Hotlist, anything overdue or due today, and your routines.</li>
        <li>Open OmniVEN and clear the <b>Hotlist</b> items with your name.</li>
        <li>Work down your column: today’s tasks first, then routines. Each routine’s <b>Open ↗</b> link goes straight to the right tool.</li>
      </ul>
    </div>
    <div class="g-slot" style="--c:var(--c-growth)">
      <h3>During the day <small>as it comes</small></h3>
      <ul>
        <li>Someone asks for something? Press <b>+</b> on their column (or yours) and type it in, so it doesn’t live in a chat thread.</li>
@if($ai)
        <li>Got meeting notes or a brief? Drop them into <b>TaskBuddy AI</b> and it writes the task list for you.</li>
@endif
        <li>Need a login? Press <b>Request access</b> on the account. Never paste passwords anywhere.</li>
      </ul>
    </div>
    <div class="g-slot" style="--c:var(--c-rev)">
      <h3>Before you leave <small>2 minutes</small></h3>
      <ul>
        <li>Tick what you finished. When you tick a task, an <b>Add result</b> box opens: paste the link to what you shipped.</li>
        <li>Anything slipping? Change its due date now, so tomorrow’s summary is accurate.</li>
        <li>Jot private reminders on a <b>Sticky Note</b>. Only you can see it.</li>
      </ul>
    </div>
  </div>
  <div class="g-cadence">
    <div class="g-cad"><b>Every week</b>Monday sweep of weekly routines (Merchant Center, Search Console, ad spend, email). Work the <a href="#work">Action calendar</a> day by day. Admins write the weekly revenue note on Sales.</div>
    <div class="g-cad"><b>Every month</b>Run the AI answers check, look at Content gaps, and review the Monthly report with leadership.</div>
    <div class="g-cad"><b>Every quarter</b>Admins run the Access review: confirm every login and owner until the bar reads N of N verified.</div>
  </div>
</section>

{{-- FINDING THINGS --}}
<section class="g-ch" id="find" style="--c:var(--navy)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true" style="--c:var(--c-brands)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg></span>
    <span class="g-ch-kicker" style="--c:var(--c-brands)">Get anywhere in two clicks</span>
    <h2>Finding your way around</h2>
  </div>
  <div class="g-nav-grid">
    <figure>
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/sidebar.jpg') }}" data-cap="The sidebar: six sections, then Others (Help and admin pages). The section you’re in opens by itself."><img src="{{ asset('img/guide/sidebar.jpg') }}" alt="OmniVEN sidebar menu" width="290" height="900" loading="lazy"></button>
      <figcaption>The sidebar. The section you’re in opens and your page is highlighted.</figcaption>
    </figure>
    <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
      <div class="g-nav-points">
        <div class="g-np"><h4>☰ The sidebar</h4><p>Six sections, in the order this guide follows: Work, Revenue, Growth, Search &amp; AI, Reputation, Brands &amp; tools. The ☰ button shrinks it to icons; hover to see the labels again.</p></div>
        <div class="g-np"><h4><kbd>Ctrl K</kbd> Jump to</h4><p>The search box in the top bar. Type a brand, tool or account (short codes work: <i>ig, fb, tt, li, ga, mc</i>). <kbd>Enter</kbd> opens it, <kbd>⇧Enter</kbd> stars it.</p></div>
        <div class="g-np"><h4>🔍 Search everything</h4><p>The box above the home tabs searches brands, pages, tools, accounts, routines, tasks, calendar items, bulletin posts and your own sticky notes.</p></div>
        <div class="g-np"><h4>All brands</h4><p>Next to that search box. Pick one brand and every tab filters to it. OmniVEN remembers your choice on that computer.</p></div>
        <div class="g-np"><h4>☆ Stars</h4><p>Star anything you open often. Press <b>Arrange</b> on your launchpad row to put them in your order. <b>Recent</b> fills in by itself.</p></div>
        <div class="g-np"><h4>☾ Light or dark</h4><p>The moon button in the top bar switches themes and remembers your choice. Your name opens My day, Help and Sign out.</p></div>
      </div>
      <figure>
        <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/launcher.jpg') }}" data-cap="Jump to (Ctrl K): type “ocusafe” and every Ocusafe account and page is one Enter away."><img src="{{ asset('img/guide/launcher.jpg') }}" alt="Jump to launcher with Ocusafe results" width="1440" height="900" loading="lazy"></button>
        <figcaption>Jump to, after typing “ocusafe”. Accounts and tools open in a new tab.</figcaption>
      </figure>
    </div>
  </div>
  <div class="g-phones">
    <figure>
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/phone-home.jpg') }}" data-cap="On a phone, the home page is the same: Hotlist first, then the tabs and your column."><img src="{{ asset('img/guide/phone-home.jpg') }}" alt="OmniVEN on a phone" width="780" height="1688" loading="lazy"></button>
      <figcaption>On a phone: Hotlist first, then your column.</figcaption>
    </figure>
    <figure>
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/phone-menu.jpg') }}" data-cap="Tap ☰ for the full menu. Tap a page and the menu closes by itself."><img src="{{ asset('img/guide/phone-menu.jpg') }}" alt="The phone menu open" width="780" height="1688" loading="lazy"></button>
      <figcaption>Tap ☰ for the full menu.</figcaption>
    </figure>
    <div class="g-np" style="flex:1.4;align-self:center"><h4>Works on your phone</h4><p>Everything in this guide works on a phone. Tick tasks from the Hotlist on the way in, check a rating, or reply in Slack. The menu slides in from the left and closes when you pick a page.</p></div>
  </div>
</section>

{{-- WORK --}}
<section class="g-ch" id="work" style="--c:var(--c-work)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="17" rx="2.5"/><path d="M9 3h6v3H9zM8 11l2 2 4-4M8 17h8"/></svg></span>
    <span class="g-ch-kicker">Sidebar · Work</span>
    <h2>Work: tasks, routines and the runbook</h2>
  </div>
  <p style="color:var(--ink-2);max-width:72ch">This is where most of your day happens. <b>Tasks</b> are one-off jobs with an owner and a due date. <b>Routines</b> are the recurring jobs (daily, weekly, monthly, quarterly) that keep every brand healthy. Both land in your column on the home page when they’re due.</p>
  <div class="g-features">
    <article class="g-feat g-wide">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/add-task.jpg') }}" data-cap="Press + on a column, type the task, pick a brand and due date, then Add."><img src="{{ asset('img/guide/add-task.jpg') }}" alt="Adding a task from a person's column" width="760" height="340" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Tasks <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where">Home page · <b>Work → Tasks</b><a class="g-go" href="{{ route('dashboard').'#tasks' }}">Open →</a></div>
        <p>Give anyone a task, yourself included. Press <b>+</b> on their column for a quick one, or <b>+ New task</b> for details, a brand, a campaign and the Hotlist. Finished tasks stay crossed out below the board for 14 days; <b>Other dates</b> looks further back.</p>
        <div class="g-when"><b>Use it when</b> anything needs doing by someone, by a date. If it lives in chat, it gets lost.</div>
        <ul class="g-tips">
          <li><b>Hotlist</b> is only for urgent work: it pins the task to the top of everyone’s page and pops up once a day for its owner.</li>
          <li><b>Mine</b> shows only your column. The <b>⋯</b> menu has <i>From a template</i> and the color key.</li>
          <li>After ticking, paste the result link. It shows in the Monthly report under What we shipped.</li>
        </ul>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/routines.jpg') }}" data-cap="Routines by cadence. Each has an Open link to its tool and shows who ticked it."><img src="{{ asset('img/guide/routines.jpg') }}" alt="Routines tab" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Routines <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where">Home → <b>Routines</b> tab<a class="g-go" href="{{ route('dashboard').'#routines' }}">Open →</a></div>
        <p>The recurring checklist for every brand, from the Reddit report to the quarterly access review. Each links to the tool it needs, and the tick records who did it and when.</p>
        <div class="g-when"><b>Use it when</b> you start a day or a week. Tick each one the moment it’s done.</div>
        <ul class="g-tips"><li>Some routines need a tick per brand; they’re done when every brand is ticked.</li></ul>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/calendar.jpg') }}" data-cap="The Q4 Action calendar: this week’s phase, daily items by brand, the boost log and the Sunday review."><img src="{{ asset('img/guide/calendar.jpg') }}" alt="Action calendar" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Action calendar <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Work → Action calendar</b><a class="g-go" href="{{ route('calendar') }}">Open →</a></div>
        <p>The week-by-week social runbook for the Q4 plan: what’s due each day, for which brand, and what “done” looks like. Everyone sees the same checkmarks.</p>
        <div class="g-when"><b>Use it when</b> you post or plan social. Log every boost decision, including skips.</div>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/myday.jpg') }}" data-cap="My day: a preview of your morning Slack summary, with a switch to turn it on or off."><img src="{{ asset('img/guide/myday.jpg') }}" alt="My day page" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>My day and the morning summary <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Work → My day</b>, or your name in the top bar<a class="g-go" href="{{ route('my-day') }}">Open →</a></div>
        <p>Every weekday morning OmniVEN sends you a Slack message with your Hotlist, what’s overdue or due today, and your routines. Days with nothing on your plate are skipped. Preview it, send it now, or turn it off here.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/templates.jpg') }}" data-cap="Launch templates: a full checklist for a product launch or a seasonal sale, with dates worked out."><img src="{{ asset('img/guide/templates.jpg') }}" alt="Launch templates" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Launch templates <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Work → Launch templates</b>, or <b>⋯ → From a template</b><a class="g-go" href="{{ route('templates.index') }}">Open →</a></div>
        <p>Ready-made checklists for a <b>Product launch</b> or a <b>Seasonal sale</b>. Name it, pick the brand, start day and who does what, and every step lands on the board with its due date.</p>
        <div class="g-when"><b>Use it when</b> a launch or sale is coming. It saves an hour of planning and nothing gets forgotten.</div>
      </div>
    </article>
  </div>
  <div class="g-mini-feats">
    <div class="g-mini"><h4>Sticky Notes</h4><p>Private colored notes with checklists that float at the top right of every page. Nobody else can see them, admins included.</p></div>
    <div class="g-mini"><h4>Slack</h4><p>Team channels like #reddit-monitor with a reply box, right inside OmniVEN. A red * means something new.</p></div>
@if($ai)
    <div class="g-mini"><h4>TaskBuddy AI</h4><p>Turns a document or notes into a ready-to-add task list. See <a href="#ai">AI helpers</a>.</p></div>
@endif
  </div>
</section>

@if($rev)
{{-- REVENUE --}}
<section class="g-ch" id="revenue" style="--c:var(--c-rev)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg></span>
    <span class="g-ch-kicker">Sidebar · Revenue · admins and revenue viewers</span>
    <h2>Revenue: how sales are doing, and why</h2>
  </div>
  <p style="color:var(--ink-2);max-width:72ch">Sales numbers come from Google Analytics 4, email from Klaviyo, and affiliates from CJ and Awin. Only admins and the people they add as revenue viewers can open these pages. Every report page has the same date picker: click the date line to choose <b>any dates</b> and what to <b>compare with</b> (same dates last year, or the period just before).</p>
  <div class="g-features">
    <article class="g-feat g-wide">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/sales.jpg') }}" data-cap="Sales: pick the dates and comparison, then read revenue, orders, sessions, conversion and average order, each with its change and the number then."><img src="{{ asset('img/guide/sales.jpg') }}" alt="Sales page with tiles and a chart" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Sales <span class="g-tag g-rev">Revenue viewers</span></h3>
        <div class="g-where"><b>Revenue → Sales</b><a class="g-go" href="{{ route('sales') }}">Open →</a></div>
        <p>Revenue, orders, sessions, conversion rate and average order for any dates, against last year or the previous period. <b>Over time</b> charts it (dashed line = the comparison), and <b>By channel</b> shows organic search, paid, shopping, email, affiliate and AI assistants.</p>
        <div class="g-when"><b>Use it when</b> someone asks “how are we doing?” or a number looks off. Pick the brand chip to see one brand.</div>
        <ul class="g-tips"><li>GA4 counts a little less than the store does. Use it for trends and the channel mix.</li><li>Admins set each brand’s monthly goal; the bar shows whether you’re on pace.</li></ul>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/email.jpg') }}" data-cap="Email & SMS over any dates: emails, open and click rates, orders and revenue, split into campaigns and flows."><img src="{{ asset('img/guide/email.jpg') }}" alt="Email and SMS page" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Email &amp; SMS <span class="g-tag g-rev">Revenue viewers</span></h3>
        <div class="g-where"><b>Revenue → Email &amp; SMS</b><a class="g-go" href="{{ route('email') }}">Open →</a></div>
        <p>Klaviyo results for any dates, split into campaigns and flows, then every campaign and flow from the last 30 days. Red <b>Fix</b> or amber <b>Watch</b> lines name anything hurting results.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/affiliates.jpg') }}" data-cap="Affiliate scoreboard: CJ and Awin sales, commission and pending commission by month, with top publishers."><img src="{{ asset('img/guide/affiliates.jpg') }}" alt="Affiliate scoreboard" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Affiliate scoreboard <span class="g-tag g-rev">Revenue viewers</span></h3>
        <div class="g-where"><b>Revenue → Affiliate scoreboard</b><a class="g-go" href="{{ route('affiliates') }}">Open →</a></div>
        <p>CJ and Awin side by side: sales, orders, commission and pending commission, top 15 publishers, month by month. <b>Cost of sale</b> is commission as a share of sales; lower is better value.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/monthly.jpg') }}" data-cap="Monthly report: one page per month for leadership."><img src="{{ asset('img/guide/monthly.jpg') }}" alt="Monthly report" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Monthly report <span class="g-tag g-rev">Revenue viewers</span></h3>
        <div class="g-where"><b>Revenue → Monthly report</b><a class="g-go" href="{{ route('reports.monthly') }}">Open →</a></div>
        <p>One page per month for leadership: revenue against last year and the goal, each brand, top channels, affiliates, reviews, AI answers, work finished, launches and the weekly notes.</p>
        <div class="g-when"><b>Use it when</b> you report up. Use the month arrows to look back.</div>
      </div>
    </article>
  </div>
</section>
@endif

{{-- GROWTH --}}
<section class="g-ch" id="growth" style="--c:var(--c-growth)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></svg></span>
    <span class="g-ch-kicker">Sidebar · Growth</span>
    <h2>Growth: campaigns, links and partners</h2>
  </div>
  <div class="g-features">
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/campaigns.jpg') }}" data-cap="Campaigns: each push with its dates, tracked links and tasks in one place."><img src="{{ asset('img/guide/campaigns.jpg') }}" alt="Campaigns page" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Campaigns <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Growth → Campaigns</b><a class="g-go" href="{{ route('campaigns.index') }}">Open →</a></div>
        <p>A campaign is a named push with dates, like “Black Friday 2026”. Its page gathers its tracked links, tasks and results. From it you can add a task, make a tracked link, or run a launch template.</p>
        <div class="g-when"><b>Use it when</b> you plan anything with a start and end date.</div>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/links.jpg') }}" data-cap="Campaign links: the UTM link builder. Pick the brand, page, where it’s posted and the campaign."><img src="{{ asset('img/guide/links.jpg') }}" alt="Campaign links builder" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Campaign links <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Growth → Campaign links</b><a class="g-go" href="{{ route('links.index') }}">Open →</a></div>
        <p>Builds tracked (UTM) links so visits land in the right channel on Sales. Pick the brand, the page, where it will be posted and a campaign name; every link is saved in a shared list.</p>
        <div class="g-when"><b>Use it when</b> you post any link outside the website (except Google and Microsoft Ads, which tag themselves). Reuse campaign names from the suggestions.</div>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/outreach.jpg') }}" data-cap="Outreach: who we’re pitching, where each stands, and follow-ups due at the top."><img src="{{ asset('img/guide/outreach.jpg') }}" alt="Outreach pipeline" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Outreach <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Growth → Outreach</b><a class="g-go" href="{{ route('outreach.index') }}">Open →</a></div>
        <p>The blogs, review sites, affiliates, creators and press we pitch: To contact → Contacted → Replied → Working it out → Live. Log each step with one click; follow-ups that are due sit at the top.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/assets.jpg') }}" data-cap="Affiliate assets: approved banners, codes and copy, plus share-a-kit links for publishers."><img src="{{ asset('img/guide/assets.jpg') }}" alt="Affiliate assets" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Affiliate assets <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Growth → Affiliate assets</b><a class="g-go" href="{{ route('assets.index') }}">Open →</a></div>
        <p>Approved banners, discount codes, copy and links that partners may use. Copy a code with one click. <b>Share a kit</b> makes a link a publisher can open without signing in, for 7, 30 or 90 days.</p>
      </div>
    </article>
  </div>
</section>

{{-- SEARCH & AI --}}
<section class="g-ch" id="search" style="--c:var(--c-search)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2M11 8v6M8 11h6"/></svg></span>
    <span class="g-ch-kicker">Sidebar · Search &amp; AI</span>
    <h2>Search &amp; AI: being found on Google and by AI assistants</h2>
  </div>
  <div class="g-features">
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/seo.jpg') }}" data-cap="Search: clicks, impressions, click-through rate and position for any dates in the last 16 months."><img src="{{ asset('img/guide/seo.jpg') }}" alt="Search page" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Search <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Search &amp; AI → Search</b><a class="g-go" href="{{ route('seo') }}">Open →</a></div>
        <p>Each brand’s Google Search Console: clicks, impressions, click-through rate, average position and branded searches, then the searches and pages losing clicks. Press <b>Make a task</b> on a falling one and the task is pre-filled.</p>
        <div class="g-when"><b>Use it when</b> traffic drops, or every Monday as part of the weekly sweep.</div>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/gaps.jpg') }}" data-cap="Content gaps: searches and AI questions that need a page, a better page, or a better title."><img src="{{ asset('img/guide/gaps.jpg') }}" alt="Content gaps" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Content gaps <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Search &amp; AI → Content gaps</b><a class="g-go" href="{{ route('content-gaps') }}">Open →</a></div>
        <p>A to-do list of content to write or fix: <b>Few clicks</b> (rewrite the title), <b>Almost there</b> (position 8–20), <b>Unanswered question</b> and <b>AI leaves us out</b>. Pick who and when, then <b>Make a task</b>.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/aianswers.jpg') }}" data-cap="AI answers: how often ChatGPT, Google AI, Perplexity, Gemini and Copilot recommend or mention us."><img src="{{ asset('img/guide/aianswers.jpg') }}" alt="AI answers" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>AI answers <span class="g-tag g-all">Everyone</span> <span class="g-tag">Monthly</span></h3>
        <div class="g-where"><b>Search &amp; AI → AI answers</b><a class="g-go" href="{{ route('ai-answers') }}">Open →</a></div>
        <p>Once a month, ask each AI assistant our shopper questions and record whether it <b>Recommended us</b>, <b>Mentioned us</b> or didn’t. Use a private window so past chats don’t sway the answer.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/sitecheck.jpg') }}" data-cap="Site check: structured data and Google Shopping feed problems, red first."><img src="{{ asset('img/guide/sitecheck.jpg') }}" alt="Site check" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Site check <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Search &amp; AI → Site check</b><a class="g-go" href="{{ route('site-check') }}">Open →</a></div>
        <p>Daily check of the hidden labels that let Google show price, stock and stars, and of each brand’s product feed. Red means Google can’t show rich results; amber means they could be better. <b>Make a task</b> sends the fix list to the developer.</p>
      </div>
    </article>
  </div>
</section>

{{-- REPUTATION --}}
<section class="g-ch" id="reputation" style="--c:var(--c-rep)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg></span>
    <span class="g-ch-kicker">Sidebar · Reputation</span>
    <h2>Reputation: reviews and Reddit</h2>
  </div>
  <div class="g-features">
    <article class="g-feat g-wide">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/reviews.jpg') }}" data-cap="Reviews: ratings on every platform with the 30-day change, and low reviews waiting on a reply."><img src="{{ asset('img/guide/reviews.jpg') }}" alt="Reviews page" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Reviews <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Reputation → Reviews</b><a class="g-go" href="{{ route('reviews') }}">Open →</a></div>
        <p>Star ratings for Shopper Approved, Trustpilot, Google, ConsumerAffairs and BBB, with the change over 30 days. Every new review of 2★ or less becomes a Hotlist task, due the next day, with the review and a link.</p>
        <div class="g-when"><b>Use it when</b> you do the daily reviews routine. Press <b>Update rating</b> on Google, ConsumerAffairs and BBB after you check them.</div>
        <ul class="g-tips"><li>“Waiting on a reply” clears when the review’s task is ticked.</li></ul>
      </div>
    </article>
  </div>
  <div class="g-mini-feats">
    <div class="g-mini" style="grid-column:span 2"><h4>Reddit <span class="g-tag g-all">Everyone</span></h4><p>Every hour OmniVEN finds Reddit threads from the past week worth a reply, best first: unhappy customers, people who mention us, and people asking for an alternative to Zenni or Warby Parker. <b>Make a task</b> hands it to someone (complaints go on the Hotlist). Always say you work for the brand, and check the subreddit’s rules.</p></div>
    <div class="g-mini"><h4>Escalate same day</h4><p>Any chargeback, BBB, legal or prescription-error mention becomes a Hotlist task the same day.</p></div>
  </div>
</section>

{{-- BRANDS & TOOLS --}}
<section class="g-ch" id="brands" style="--c:var(--c-brands)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg></span>
    <span class="g-ch-kicker">Sidebar · Brands &amp; tools</span>
    <h2>Brands &amp; tools: every account, one click away</h2>
  </div>
  <div class="g-features">
    <article class="g-feat g-wide">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/hub.jpg') }}" data-cap="A brand hub: every account for the brand, grouped. Open goes straight in; ⋯ has Request access."><img src="{{ asset('img/guide/hub.jpg') }}" alt="39DollarGlasses brand hub" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Brand hubs <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Brands &amp; tools → Brands</b>, then a brand<a class="g-go" href="{{ route('dashboard').'#brands' }}">Open →</a></div>
        <p>Each brand on one page: every account (social, ads, shopping, search, email, reviews, blog) with an <b>Open ↗</b> button, plus its tools and routines. The <b>⋯</b> on an account has <b>Request access</b>.</p>
        <div class="g-when"><b>Use it when</b> you’re working on one brand. Star the hub for the length of a campaign.</div>
        <ul class="g-tips"><li>A small amber dot means access hasn’t been confirmed recently.</li><li>“Not set up yet” lists platforms the brand doesn’t have.</li></ul>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/brands.jpg') }}" data-cap="The brand × platform grid: one click to any brand’s Instagram, Meta Ads, Merchant Center and more."><img src="{{ asset('img/guide/brands.jpg') }}" alt="Brands grid" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>The brand grid <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where">Home → <b>Brands</b> tab<a class="g-go" href="{{ route('dashboard').'#brands' }}">Open →</a></div>
        <p>Every brand against every platform. Click a square to open that account. A faded square has no direct link yet.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/tools.jpg') }}" data-cap="Tools: the team bulletin, the platform consoles we open most, and every tool by category."><img src="{{ asset('img/guide/tools.jpg') }}" alt="Tools directory" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Tools and the Bulletin <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where">Home → <b>Tools</b> tab<a class="g-go" href="{{ route('dashboard').'#tools' }}">Open →</a></div>
        <p>Every marketing tool by category, the platform consoles we open most, and the team <b>Bulletin</b> for announcements. Hover a tool for its brands and the ☆.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/access.jpg') }}" data-cap="Access: which login each tool lives under, who owns it, and how to get in."><img src="{{ asset('img/guide/access.jpg') }}" alt="Access tab" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Access <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where">Home → <b>Access</b> tab<a class="g-go" href="{{ route('dashboard').'#access' }}">Open →</a></div>
        <p>Which login account each tool lives under, who owns it, and how to get in. Need in? Press <b>Request access</b> and say why; an admin shares it through the password manager. You’ll see <b>Requested</b>, then <b>✓ Granted</b>.</p>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/coverage.jpg') }}" data-cap="Coverage: every brand against every platform, with a score and the gaps to fix."><img src="{{ asset('img/guide/coverage.jpg') }}" alt="Coverage" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Coverage <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Brands &amp; tools → Coverage</b><a class="g-go" href="{{ route('coverage') }}">Open →</a></div>
        <p>Which brands are missing which accounts, with a score per brand. ✓ opens the account; + on a gap makes a task. <b>Need attention</b> lists accounts to fix.</p>
      </div>
    </article>
  </div>
</section>

@if($ai)
{{-- AI HELPERS --}}
<section class="g-ch" id="ai" style="--c:var(--c-ai)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4zM19 14l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9zM5 15l.7 1.8 1.8.7-1.8.7L5 20l-.7-1.8-1.8-.7 1.8-.7z"/></svg></span>
    <span class="g-ch-kicker">Top bar · Ask OmniVEN AI</span>
    <h2>Two AI helpers that do the digging</h2>
  </div>
  <div class="g-features">
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/assistant.jpg') }}" data-cap="Ask OmniVEN AI: pick a focus, ask in plain words, and it looks things up across OmniVEN before answering."><img src="{{ asset('img/guide/assistant.jpg') }}" alt="Ask OmniVEN AI" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>Ask OmniVEN AI <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where"><b>Ask OmniVEN AI</b> button in the top bar<a class="g-go" href="{{ route('assistant') }}">Open →</a></div>
        <p>Ask about any brand, number or task. It reads OmniVEN’s data first, compares periods and brands, explains causes, and ends with next steps you can paste in as tasks. Pick a <b>Focus</b> to steer it, and ask follow-ups in the same conversation.</p>
        <ul class="g-tips"><li>It can only read; it never changes anything.</li><li>It sees what you can see. Your conversations are private to you.</li></ul>
      </div>
    </article>
    <article class="g-feat">
      <button class="g-shot" type="button" data-zoom="{{ asset('img/guide/taskbuddy.jpg') }}" data-cap="TaskBuddy AI: paste notes or upload a file; it drafts the tasks, asks if anything is unclear, and you add the ones you keep."><img src="{{ asset('img/guide/taskbuddy.jpg') }}" alt="TaskBuddy AI" width="1440" height="900" loading="lazy"></button>
      <div class="g-feat-body">
        <h3>TaskBuddy AI <span class="g-tag g-all">Everyone</span></h3>
        <div class="g-where">Ask OmniVEN AI → <b>TaskBuddy AI</b> tab, or <b>Work → TaskBuddy AI</b><a class="g-go" href="{{ route('taskbuddy') }}">Open →</a></div>
        <p>Paste meeting notes, an email or a plan, or upload a PDF, Word, PowerPoint, Excel file or screenshot. It drafts every task with the owner, brand, due date and steps, asks if something is unclear, and nothing is added until you review it.</p>
        <div class="g-when"><b>Use it when</b> a meeting ends or a brief arrives. It turns a page of notes into assigned work in a minute.</div>
      </div>
    </article>
  </div>
  <h3 style="font-size:19px">Questions worth asking</h3>
  <div class="g-prompts">
    <div class="g-prompt"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4z"/></svg><span>What should the team focus on this week to grow sales, and why?</span><button class="g-copy" type="button">Copy</button></div>
    <div class="g-prompt"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4z"/></svg><span>Give me a full health check of Ocusafe: search, reviews, accounts and open work. What are the top three fixes?</span><button class="g-copy" type="button">Copy</button></div>
    <div class="g-prompt"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4z"/></svg><span>Which work is overdue or slipping, and what should be reassigned?</span><button class="g-copy" type="button">Copy</button></div>
    <div class="g-prompt"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 5.6L19.5 9l-5.7 1.6L12 16l-1.8-5.4L4.5 9l5.7-1.4z"/></svg><span>Where are we losing search clicks, and which content gaps would win back the most traffic?</span><button class="g-copy" type="button">Copy</button></div>
  </div>
</section>
@endif

{{-- FINDER --}}
<section class="g-ch" id="finder" style="--c:var(--accent)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg></span>
    <span class="g-ch-kicker">Not sure where to go?</span>
    <h2>I want to…</h2>
  </div>
  <div class="g-finder">
    <div class="g-finder-in"><input id="g-finder-q" type="search" placeholder="Type what you want to do, e.g. tracked link, login, reviews" aria-label="What do you want to do?" autocomplete="off"></div>
    <ul id="g-finder-list">
      <li style="--c:var(--c-work)"><span>Give someone a task, or add one for myself</span><a href="{{ route('dashboard').'#tasks' }}"><i></i>Work → Tasks (+ on a column)</a></li>
      <li style="--c:var(--c-work)"><span>See what’s due for me today</span><a href="{{ route('my-day') }}"><i></i>Home page, or Work → My day</a></li>
@if($ai)
      <li style="--c:var(--c-work)"><span>Turn meeting notes or a brief into tasks</span><a href="{{ route('taskbuddy') }}"><i></i>TaskBuddy AI</a></li>
@endif
      <li style="--c:var(--c-work)"><span>Plan a product launch or seasonal sale</span><a href="{{ route('templates.index') }}"><i></i>Work → Launch templates</a></li>
      <li style="--c:var(--c-work)"><span>Know what to post on social this week</span><a href="{{ route('calendar') }}"><i></i>Work → Action calendar</a></li>
      <li style="--c:var(--c-work)"><span>Keep a private to-do list</span><a href="{{ route('dashboard').'#postit' }}"><i></i>Work → Sticky Notes</a></li>
      <li style="--c:var(--c-work)"><span>Turn the morning Slack message on or off</span><a href="{{ route('my-day') }}"><i></i>Work → My day</a></li>
@if($rev)
      <li style="--c:var(--c-rev)"><span>See how sales are doing, by brand or channel</span><a href="{{ route('sales') }}"><i></i>Revenue → Sales</a></li>
      <li style="--c:var(--c-rev)"><span>Compare this year with last year</span><a href="{{ route('sales') }}"><i></i>Any report → click the date line</a></li>
      <li style="--c:var(--c-rev)"><span>Check how an email campaign or flow performed</span><a href="{{ route('email') }}"><i></i>Revenue → Email &amp; SMS</a></li>
      <li style="--c:var(--c-rev)"><span>See which affiliates sell the most</span><a href="{{ route('affiliates') }}"><i></i>Revenue → Affiliate scoreboard</a></li>
      <li style="--c:var(--c-rev)"><span>Send results to leadership</span><a href="{{ route('reports.monthly') }}"><i></i>Revenue → Monthly report</a></li>
@endif
      <li style="--c:var(--c-growth)"><span>Make a tracked link for a post, email or partner</span><a href="{{ route('links.index') }}"><i></i>Growth → Campaign links</a></li>
      <li style="--c:var(--c-growth)"><span>Plan a campaign and keep its links and tasks together</span><a href="{{ route('campaigns.index') }}"><i></i>Growth → Campaigns</a></li>
      <li style="--c:var(--c-growth)"><span>Track a blogger, creator or review site we’re pitching</span><a href="{{ route('outreach.index') }}"><i></i>Growth → Outreach</a></li>
      <li style="--c:var(--c-growth)"><span>Send a partner our banners and discount codes</span><a href="{{ route('assets.index') }}"><i></i>Growth → Affiliate assets → Share a kit</a></li>
      <li style="--c:var(--c-search)"><span>Find out why search traffic dropped</span><a href="{{ route('seo') }}"><i></i>Search &amp; AI → Search</a></li>
      <li style="--c:var(--c-search)"><span>Decide what blog post or page to write next</span><a href="{{ route('content-gaps') }}"><i></i>Search &amp; AI → Content gaps</a></li>
      <li style="--c:var(--c-search)"><span>See if ChatGPT recommends us</span><a href="{{ route('ai-answers') }}"><i></i>Search &amp; AI → AI answers</a></li>
      <li style="--c:var(--c-search)"><span>Fix product feed or rich result problems</span><a href="{{ route('site-check') }}"><i></i>Search &amp; AI → Site check</a></li>
      <li style="--c:var(--c-rep)"><span>Reply to a bad review</span><a href="{{ route('reviews') }}"><i></i>Reputation → Reviews (it’s on your Hotlist)</a></li>
      <li style="--c:var(--c-rep)"><span>Find Reddit threads worth a reply</span><a href="{{ route('reddit') }}"><i></i>Reputation → Reddit</a></li>
      <li style="--c:var(--c-brands)"><span>Open a brand’s Instagram, Meta Ads or Merchant Center</span><a href="#find"><i></i>Ctrl K, type “brand platform”</a></li>
      <li style="--c:var(--c-brands)"><span>Get a login I don’t have</span><a href="{{ route('dashboard').'#access' }}"><i></i>Access tab → Request access</a></li>
      <li style="--c:var(--c-brands)"><span>See everything for one brand</span><a href="{{ route('dashboard').'#brands' }}"><i></i>Brands &amp; tools → Brands → the brand</a></li>
      <li style="--c:var(--c-brands)"><span>Find which brands are missing an account</span><a href="{{ route('coverage') }}"><i></i>Brands &amp; tools → Coverage</a></li>
@if($ai)
      <li style="--c:var(--c-ai)"><span>Get an analysis with next steps</span><a href="{{ route('assistant') }}"><i></i>Ask OmniVEN AI</a></li>
@endif
      <li style="--c:var(--c-brands)"><span>Post an announcement for the team</span><a href="{{ route('dashboard').'#tools' }}"><i></i>Tools tab → Bulletin → + Post a note</a></li>
    </ul>
    <p class="g-none" id="g-finder-none" hidden>Nothing matched. Try one word, like “link”, “review” or “login”, or ask Ask OmniVEN AI.</p>
  </div>
</section>

{{-- PLAYBOOKS --}}
<section class="g-ch" id="playbooks" style="--c:var(--muted)">
  <div class="g-ch-head">
    <span class="g-ch-badge" aria-hidden="true" style="--c:var(--c-growth)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M8 7h6M8 11h6"/></svg></span>
    <span class="g-ch-kicker" style="--c:var(--c-growth)">Step-by-step, for the jobs that come up most</span>
    <h2>Playbooks</h2>
  </div>
  <div class="g-plays">
    <details class="g-play"><summary><b>Monday morning sweep</b><small>10 minutes · start of the week · everyone</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Open OmniVEN. This week’s routines are already in your column.</li><li>Clear anything on the Hotlist with your name first.</li><li>Work down the weekly routines: Merchant Center disapprovals, Search Console, ad spend, email performance. Each <b>Open ↗</b> goes straight to the right console.</li><li>Tick each as you finish, then check Slack for anything flagged over the weekend.</li></ol>
      <p class="g-why">The routine is the checklist and the link, so you never hunt for a URL or wonder whether someone already did it.</p></details>
    <details class="g-play"><summary><b>Daily reviews and Reddit</b><small>15 minutes · every weekday · everyone</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Open Shopper Approved first (it’s first in Platform consoles), then Trustpilot and ConsumerAffairs.</li><li>Tick <i>Work the Reddit report</i> and <i>Check new reviews and escalate</i> on Routines.</li><li>Read #reddit-monitor on the Slack tab and reply from OmniVEN.</li><li>Escalate any chargeback, BBB, legal or prescription-error mention the same day as a Hotlist task.</li></ol>
      <p class="g-why">Monitoring, replying and escalating happen in one place, and the tick records that it was done.</p></details>
    <details class="g-play"><summary><b>Launching a campaign for one brand</b><small>e.g. an Ocusafe safety-glasses push · everyone</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Create the campaign on <b>Growth → Campaigns</b> with its dates.</li><li>From the campaign, <b>Use a launch template</b> to put every step on the board.</li><li>Make every outside link on <b>Campaign links</b> with the campaign name.</li><li>Press <kbd>Ctrl K</kbd>, type the brand, open the hub and star it for the campaign. Request any missing login now.</li></ol>
      <p class="g-why">The plan, the links and the results end up in one place, and they show up in the Monthly report.</p></details>
    <details class="g-play"><summary><b>Running the weekly social runbook</b><small>Monday to Sunday · everyone</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Open <b>Work → Action calendar</b>. It lands on this week, with the phase and angle at the top. Do the one-off items first.</li><li>Work each day’s items; each says when it counts as done.</li><li>Wednesday and Friday, apply the 36-hour rule and log the boost decision, boost or skip.</li><li>Sunday, write the one decision in Sunday review and check next week is queued.</li></ol>
      <p class="g-why">Anyone can pick up a day, and the quarter shows its own progress.</p></details>
    <details class="g-play"><summary><b>Publishing a blog post</b><small>weekly · 39DollarGlasses and Ocusafe · everyone</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Check <b>Content gaps</b> for the topic most likely to win traffic.</li><li>Press <kbd>Ctrl K</kbd> and type <i>contentful</i> to open the editor.</li><li>Publish, open the live post to check it, and tick <i>Update the blogs in Contentful</i>.</li><li>Add the post’s link as the task’s result.</li></ol>
      <p class="g-why">The idea, the editor and the follow-up are one click apart, so a post never stalls in drafts.</p></details>
@if($admin)
    <details class="g-play"><summary><b>Weekly one-on-one</b><small>15 minutes per person · admins</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Press <kbd>Ctrl K</kbd>, type their name and open their staff page.</li><li>Start with the four counts: anything overdue comes first.</li><li>Go through open tasks together and fix due dates or the Hotlist right there.</li><li>Agree next steps with <b>+ Assign a task</b>, then call out wins in Done in the last 30 days.</li></ol>
      <p class="g-why">Routines, tasks and recent work are on one page, so the meeting starts from facts.</p></details>
    <details class="g-play"><summary><b>Onboarding a new marketer</b><small>day one · admins</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li><b>People → Add person</b> with their @39dollarglasses.com email, before they sign in.</li><li>Send them this guide and give them a Hotlist task: “Do Start here and star your top five tools.”</li><li>They request each login from Access; grant each after sharing it in the password manager.</li></ol>
      <p class="g-why">Every grant is recorded with who asked, for what and who approved it.</p></details>
    <details class="g-play"><summary><b>Quarterly access review</b><small>about 45 minutes · admins</small><svg class="g-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
      <ol><li>Open <b>Others → Access review</b>.</li><li>Work from the top: never-verified and overdue logins come first.</li><li>Confirm each login and owner, then press <b>Verified ✓</b>, or <b>Fix</b> to edit it.</li><li>When the bar reads N of N verified, tick the routine.</li></ol>
      <p class="g-why">One list, sorted by risk, with a progress bar. It’s done when the bar is full.</p></details>
@endif
  </div>
</section>

{{-- SHORTCUTS + RULES --}}
<section class="g-ch" id="rules" style="--c:var(--c-rep)">
  <div class="g-two">
    <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
      <h2 style="font-size:26px">Shortcuts</h2>
      <table class="g-keys">
        <tr><td>Open Jump to</td><td><kbd>Ctrl K</kbd> or <kbd>⌘K</kbd></td></tr>
        <tr><td>Open it while not typing</td><td><kbd>/</kbd></td></tr>
        <tr><td>Move through results</td><td><kbd>↑</kbd> <kbd>↓</kbd></td></tr>
        <tr><td>Open the highlighted item</td><td><kbd>Enter</kbd></td></tr>
        <tr><td>Star or un-star it</td><td><kbd>⇧Enter</kbd></td></tr>
        <tr><td>Close a menu or Jump to</td><td><kbd>Esc</kbd></td></tr>
      </table>
      <p style="font-size:15px;color:var(--muted)">In Jump to, short codes work: <i>ig, fb, tt, li, ga, mc</i>, and groups like <i>social</i> or <i>ads</i>. Type <i>calendar</i> or <i>help</i> to jump there.</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
      <h2 style="font-size:26px">House rules</h2>
      <div class="g-rules">
        <div class="g-rule"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg><b>No passwords. Ever.</b><span>Never type a password or 2FA code into OmniVEN, including notes and requests. Credentials stay in the password manager.</span></div>
        <div class="g-rule"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><b>Sticky notes are private</b><span>Only you see them. Their text never goes into the Activity report or logs.</span></div>
        <div class="g-rule"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg><b>Work accounts only</b><span>Sign-in is limited to @39dollarglasses.com Google accounts.</span></div>
        <div class="g-rule"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 12h4l3 7 4-14 3 7h4"/></svg><b>Activity is need-to-know</b><span>The Activity report is visible only to two named admins.</span></div>
        <div class="g-rule"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h10M4 17h7"/></svg><b>Spell the brands right</b><span>It’s Ocusafe and Ocusleep, everywhere: routines, tasks and Slack included.</span></div>
      </div>
    </div>
  </div>
</section>

</div>

<div class="g-foot">
  <div class="g-wrap">
    <span>Stuck? The <a href="{{ route('help') }}">Help</a> page has every detail. @if($ai) Or ask <a href="{{ route('assistant') }}">Ask OmniVEN AI</a>. @endif Screenshots show example data.</span>
    <a href="#g-top">Back to the top ↑</a>
  </div>
</div>

<dialog class="g-lb" id="g-lb" aria-label="Screenshot">
  <button type="button" id="g-lb-close" aria-label="Close">×</button>
  <img id="g-lb-img" alt="">
  <p id="g-lb-cap"></p>
</dialog>
</div>
@endsection
