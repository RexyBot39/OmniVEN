@extends('layouts.app', ['title' => 'Help'])

@php
    // Brand marks in the guide point at the shared icon sprite.
    $sprite = \App\Support\BrandIcon::spriteUrl();
    $isAdmin = auth()->user()->can('admin');
@endphp

@section('content')
<div class="help wrap">
  <aside class="help-rail">
    <a href="{{ route('dashboard') }}" class="back">← OmniVEN</a>
    <nav aria-label="Help sections">
      <a href="#start">First ten minutes</a>
      <a href="#tabs">The seven tabs</a>
      <a href="#keys">Shortcuts</a>
      @if($isAdmin)
      <a href="#customize">Customize it <span class="admin-tag">Admin</span></a>
      <a href="#instance">Run your instance <span class="admin-tag">Admin</span></a>
      @endif
      <a href="#plays">Playbooks</a>
      <a href="#rules">House rules</a>
      @if($isAdmin)
      <a href="#checklist">Admin checklist <span class="admin-tag">Admin</span></a>
      @endif
    </nav>
  </aside>

  <main class="help-main" id="top">
    <header class="help-head">
      <span class="eyebrow">Help · OmniVEN field guide</span>
      <h1>How to get the most out of OmniVEN</h1>
      <p class="lede">Setup, every tab, shortcuts and the playbooks that save the most time{{ $isAdmin ? ', plus the admin pages for customizing and running OmniVEN' : '' }}.</p>
      <div class="help-search" role="search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input id="help-q" type="search" autocomplete="off" spellcheck="false" placeholder="Search help, e.g. star, boost, request access" aria-label="Search help" aria-describedby="help-count">
        <output id="help-count" class="sub" aria-live="polite"></output>
      </div>
      <p class="help-none" id="help-none" hidden>Nothing in Help matches that. Try a shorter word, or ask an admin in Slack.</p>
    </header>

    <section id="start" aria-labelledby="h-start">
      <span class="eyebrow">Everyone</span>
      <h2 id="h-start">Your first ten minutes</h2>
      <p class="lede">Do these once, in this order, and OmniVEN will open on what matters to you.</p>
      <ol class="steps">
        <li><div><h3>Sign in with your work Google account</h3><p>Go to <b>omniven.cc</b> and choose <b>Sign in with Google</b>. Only <b>@39dollarglasses.com</b> accounts get in. Sessions last 16 hours, then you sign in again. If it says you don't have access, ask an admin to add you on the People page.</p></div></li>
        <li><div><h3>Start on Routines</h3><p>You always land on <b>Routines</b> for all brands. Tick what you finish today; ticks reset each day, week, month or quarter (Eastern time) so the list is always current. Click the OmniVEN logo any time to come back here.</p></div></li>
        <li><div><h3>Filter to your brands</h3><p>The brand chips under the tabs (<b>39DollarGlasses, Ocusafe, Neurolux…</b>) filter every tab at once. OmniVEN remembers your choice on this computer.</p></div></li>
        <li><div><h3>Star the five things you open most</h3><p>Press <b>☆</b> on a tool card, a brand page or an account. Stars appear in <b>My launchpad</b> above the tabs, in the order you starred them, on any device you sign in from. The <b>Recent</b> line under it fills in by itself.</p></div></li>
        <li><div><h3>Learn one shortcut: <kbd>⌘K</kbd></h3><p>On Windows it's <kbd>Ctrl K</kbd>. Type a brand and platform, like <b>ocusafe insta</b>, and press Enter. Accounts and tools open in a new tab. Press <kbd>⇧</kbd><kbd>Enter</kbd> to star what's highlighted.</p></div></li>
        <li><div><h3>Ask for the logins you need</h3><p>On the <b>Access</b> tab or a brand page, press <b>Request access</b> on any tool or account and say why. An admin shares it through the password manager and marks it granted. You'll see <b>Requested</b>, then <b>✓ Granted</b>.</p></div></li>
        <li><div><h3>Open this week's action calendar</h3><p>Press <b>Calendar</b> in the top bar, or the progress strip at the top of Routines. It opens on the current week of the Q4 plan: what's due each day, for which brand, and what "done" looks like. Tick items as you finish them; the whole team sees the same checkmarks.</p></div></li>
        <li><div><h3>Make a PostIT for this week</h3><p>On <b>PostIT Notes</b>, create a colored note with your own to-dos. Only you can see your notes. Nobody else can, admins included, and they never appear in logs.</p></div></li>
      </ol>
    </section>

    <section id="tabs" aria-labelledby="h-tabs">
      <span class="eyebrow">Everyone</span>
      <h2 id="h-tabs">The seven tabs, plus the action calendar</h2>
      <div class="tabs-grid">
        <article class="tab-card"><h3>Routines <small>daily · weekly · monthly · quarterly</small></h3><p>The recurring work for every brand, from the Reddit report to the quarterly access review. Each routine links straight to the tool it needs.</p><p class="tip">Tick it the moment you finish. The tick shows who did it and when. Routines assigned to you are highlighted and marked <b>→ You</b>.</p></article>
        <article class="tab-card"><h3>Tasks <small>one-off work</small></h3><p>Assigned jobs with due dates. Urgent ones go on the <b>Hotlist</b> at the top of the page, and your hot items pop up once a day when you sign in.</p><p class="tip">Anyone can add one: press <b>+ New task</b>, pick the person (you included), a due date and details. Switch the view to just yours to see your queue.</p></article>
        <article class="tab-card"><h3>PostIT Notes <small>private</small></h3><p>Your personal sticky notes with check-off lists. Five colors. Nobody else sees them.</p><p class="tip">Use one note per week and clear the done items on Friday.</p></article>
        <article class="tab-card"><h3><span class="bi bi-md bi-slack" style="--bi:#4A154B" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-slack"></use></svg></span>Slack <small>live channels</small></h3><p>Recent messages from team channels like #reddit-monitor, with a reply box. Posts show as “Your name (via OmniVEN)”. A red <b>*</b> means there's something new.</p><p class="tip">Leave it open while you work. It refreshes every minute.</p></article>
        <article class="tab-card"><h3>Brands <small>8 hubs</small></h3><p>The <b>brand × platform grid</b> gives you one click to any brand's Instagram, LinkedIn, Meta Ads, Merchant Center and more. Each brand has a hub page with all of its accounts, tools and routines.</p><p class="tip">A faded square has no direct link yet. A corner dot means access hasn't been checked in 90 days.</p></article>
        <article class="tab-card"><h3>Tools <small>directory + bulletin</small></h3><p>Every marketing tool by category, the <b>Platform consoles</b> we open most (<span class="bi bi-xs bi-shopperapproved bi-multi" style="--bi:#E86600" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-shopperapproved"></use></svg></span>Shopper Approved, our main review platform, is first), and the team <b>Bulletin</b> for announcements.</p><p class="tip">Use the search box to find a tool by name, login or owner.</p></article>
        <article class="tab-card cal-card"><h3>Action calendar <small>top bar · Q4 plan</small></h3><p>The week-by-week social runbook for 39DollarGlasses, Ocusafe and Neurolux: daily posts, one-off setup jobs, the phase and seasonal angle for each week, the boost log and the Sunday review note.</p><p class="tip">Tick items as you go. Log every boost decision, including skips.</p></article>
        <article class="tab-card"><h3>Access <small>who owns what</small></h3><p>Which login account each tool and brand account lives under, who owns it, and how to get in. Admins also see access requests and broken links here.</p><p class="tip">Need in? Press Request access on that row.</p></article>
      </div>
    </section>

    <section id="keys" aria-labelledby="h-keys">
      <span class="eyebrow">Everyone</span>
      <h2 id="h-keys">Shortcuts</h2>
      <div class="keys">
        <div class="key"><span>Open the launcher</span><span><kbd>⌘K</kbd> or <kbd>Ctrl K</kbd></span></div>
        <div class="key"><span>Open it while not typing</span><kbd>/</kbd></div>
        <div class="key"><span>Move through results</span><span><kbd>↑</kbd> <kbd>↓</kbd></span></div>
        <div class="key"><span>Open the highlighted item</span><kbd>Enter</kbd></div>
        <div class="key"><span>Star or un-star it</span><span><kbd>⇧</kbd><kbd>Enter</kbd></span></div>
        <div class="key"><span>Close the launcher</span><kbd>Esc</kbd></div>
      </div>
      <p class="lede">Type <b>calendar</b> to jump to the action calendar, or <b>help</b> to come back to this page. Launcher searches understand short codes too: <code>ig</code>, <code>fb</code>, <code>tt</code>, <code>li</code>, <code>ga</code>, <code>mc</code>, and groups like <code>social</code> or <code>ads</code>. With nothing typed, it lists your stars first, then what you opened recently.</p>
    </section>

    @can('admin')
    <section id="customize" aria-labelledby="h-custom">
      <span class="eyebrow"><span class="admin-tag">Admins</span></span>
      <h2 id="h-custom">Customize it for your team</h2>
      <p class="lede">Everything below is done in the app, with no code or redeploy. Admins see <b>Edit</b>, <b>+ Add</b> and <b>Delete</b> controls that others don't.</p>
      <div class="how">
        <article><h3>Brands</h3><span class="path">Brands tab → + Add brand</span><p>Name, website, focus, main channels, social links and sort order.</p><ul><li>Set <b>Brand accounts</b> to <b>Same as 39DollarGlasses</b> for a brand sold on that site (as Lumeo and Lens Replacement are). Its hub and grid row then show those accounts.</li><li>Lower sort numbers come first in chips and the grid.</li></ul></article>
        <article><h3>Brand accounts</h3><span class="path">Brand hub → + Add account, or a + in the grid</span><p>One brand on one platform, for example Ocusafe on Instagram.</p><ul><li>Add the handle, a direct link, the account or ad-account ID, the login email and the owner.</li><li><span class="bi bi-xs bi-linkedin" style="--bi:#0A66C2" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-linkedin"></use></svg></span>LinkedIn accounts open the page's admin dashboard: 39DollarGlasses, Ocusafe and Ocusleep company pages, and the Neurolux showcase page. You need page admin rights to get in.</li><li><b>Mark verified</b> after you confirm the login still works.</li><li>The “Not set up yet” chips on a hub show what's missing.</li></ul></article>
        <article><h3>Tools</h3><span class="path">Tools tab → + Add tool</span><p>Name, link, category, status (Active, Verify access…), login account, owner and how to get access.</p><ul><li>Tick <b>Show in Platform consoles</b> for the handful everyone opens daily.</li><li>Limit a tool to certain brands so the brand filter hides it elsewhere.</li></ul></article>
        <article><h3>Routines</h3><span class="path">Routines or Tasks tab → + New routine or task → Routine</span><p>One form for both: pick <b>Routine</b>, then the title, how often (daily, weekly, monthly or quarterly), a short how-to, the link to open and the brands it covers.</p><ul><li>Pick a person under <b>Assigned to</b> to give the routine an owner. They see <b>→ You</b>; everyone else sees <b>→ Leona</b>. Leave it on <b>Whole team</b> for shared work.</li><li>Anyone can still tick an assigned routine to cover for someone.</li><li>Write the description as the first step: “Open Merchant Center, fix disapprovals.”</li><li>One routine per habit. Avoid catch-all routines.</li></ul></article>
        <article><h3>Staff pages</h3><span class="path">People → click a name, or ⌘K → type their name</span><p>One page per person with everything on their plate.</p><ul><li>Counts of routines still due, open and overdue tasks, and what they finished this week.</li><li>Their assigned routines marked Done or Due, their open tasks, and a feed of the last 30 days.</li><li>Use <b>Show another person</b> to switch. Their PostIT notes are never shown.</li><li>When each routine and task was assigned and completed shows here and on the Routines and Tasks tabs, to admins only.</li></ul></article>
        <article><h3>Tasks and the Hotlist</h3><span class="path">Tasks or Routines tab → + New task or routine → Task</span><p>The same form with <b>Task</b> picked: assign to a person, set a due date, add details. From a staff page, <b>+ Assign a task</b> or <b>+ Assign a routine</b> opens it with that person filled in.</p><ul><li><b>Add to Hotlist</b> pins it at the top of everyone's page and pops it up once a day for the assignee.</li><li>The assignee or any admin marks it done.</li><li>Everyone can add tasks, for themselves or a teammate. Only admins can add routines, and edit or delete tasks.</li></ul></article>
        <article><h3>Action calendar items</h3><span class="path">Calendar → + Add item, or Edit on any item</span><p>Change the weekly runbook without a redeploy.</p><ul><li>Set the day, brand, owner, an optional funnel keyword and what “done” looks like.</li><li>Choose <b>this week only</b> or <b>every week</b> of the quarter. Items that repeat say so on their Edit link.</li><li>Delete an item from its Edit page. Its checkmarks go with it.</li></ul></article>
        <article><h3>People</h3><span class="path">People (top bar)</span><p>Add teammates before their first sign-in, make someone an admin, or turn off access for someone who has left.</p><ul><li>People listed in <code>OMNIVEN_ADMIN_EMAILS</code> are always admins.</li><li>Turning off access signs them out at their next click.</li></ul></article>
        <article><h3>Slack channels</h3><span class="path">Slack tab → Manage channels</span><p>Choose which channels the Slack tab shows. Public channels join automatically.</p><ul><li>For a private channel, type <code>/invite @OmniVEN</code> in Slack first.</li></ul></article>
        <article><h3>Access requests and reviews</h3><span class="path">Access tab → Access requests · Access review</span><p>Requests wait at the top of the Access tab: share the login in the password manager, then press <b>Granted</b>.</p><ul><li>The quarterly <b>Access review</b> lists every login, overdue first, with <b>Verified ✓</b> and <b>Fix</b>.</li><li><b>Broken links</b> shows what failed the nightly check two nights running.</li></ul></article>
      </div>
    </section>
    @endcan

    @can('admin')
    <section id="instance" aria-labelledby="h-inst">
      <span class="eyebrow"><span class="admin-tag">Admins</span></span>
      <h2 id="h-inst">Run your instance</h2>
      <p class="lede">OmniVEN is a Laravel app on Railway with a Postgres database. Pushes to <code>main</code> deploy on their own and run any database updates first. The full steps are in the repository README. Here's the outline and every setting.</p>
      <div class="setup">
        <article><span class="time">About 5 minutes</span><h3><span class="bi bi-md bi-google" style="--bi:#4285F4" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-google"></use></svg></span> Google sign-in</h3><ol><li>Google Cloud Console → OAuth consent screen → <b>Internal</b>.</li><li>Create a Web OAuth client.</li><li>Redirect URI: <code>https://omniven.cc/auth/google/callback</code>.</li><li>Copy the client ID and secret into Railway.</li></ol></article>
        <article><span class="time">About 10 minutes</span><h3>Railway web service</h3><ol><li>New project → Deploy from GitHub → this repo.</li><li>Add a PostgreSQL database.</li><li>Fill in the variables below.</li><li>Starter brands, tools and routines load on the first deploy only.</li></ol></article>
        <article><span class="time">About 5 minutes</span><h3><span class="bi bi-md bi-slack" style="--bi:#4A154B" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-slack"></use></svg></span> Slack app</h3><ol><li>Create an app from <code>slack-app-manifest.yml</code>.</li><li>Install it and copy the <code>xoxb-</code> bot token.</li><li>Pick the admin alerts channel and <code>/invite @OmniVEN</code> there.</li></ol></article>
        <article><span class="time">About 5 minutes</span><h3>Nightly link check</h3><ol><li>New service from the same repo, named <code>omniven-link-check</code>.</li><li>Config file: <code>railway.cron.json</code> (runs at 07:00 UTC).</li><li>Reference the web service's variables.</li><li>The first log line should read “Checked N links…”.</li></ol></article>
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>Setting</th><th>What it does</th><th>Value for 39DollarGlasses</th></tr></thead>
          <tbody>
            <tr><td><code>OMNIVEN_ALLOWED_DOMAIN</code></td><td>The only Google Workspace domain that can sign in.</td><td>39dollarglasses.com <span class="def">(default)</span></td></tr>
            <tr><td><code>OMNIVEN_ADMIN_EMAILS</code></td><td>Comma-separated emails that are always admins.</td><td>drmarcweinstein@, drmarlow@, leona.p@</td></tr>
            <tr><td><code>OMNIVEN_ACTIVITY_VIEWERS</code></td><td>Who can open the Activity report. Separate from admin.</td><td>drmarcweinstein@, drmarlow@</td></tr>
            <tr><td><code>OMNIVEN_SESSION_HOURS</code></td><td>Everyone is signed out this long after signing in.</td><td>16 <span class="def">(default)</span></td></tr>
            <tr><td><code>SLACK_BOT_TOKEN</code></td><td>Turns on the Slack tab. Leave empty to hide it.</td><td><span class="def">xoxb-… from the Slack app</span></td></tr>
            <tr><td><code>OMNIVEN_SLACK_CHANNELS</code></td><td>Channels loaded the first time the Slack tab opens.</td><td>reddit-monitor, marketing <span class="def">(default)</span></td></tr>
            <tr><td><code>OMNIVEN_ACCESS_SLACK_CHANNEL</code></td><td>Where admins get “X requested access to Y” and broken-link notes. Empty means no Slack notes.</td><td><span class="def">Your admin channel name</span></td></tr>
            <tr><td><code>APP_URL</code> · <code>APP_TIMEZONE</code></td><td>The site address, and the timezone routines reset in.</td><td>https://omniven.cc · America/New_York</td></tr>
          </tbody>
        </table>
      </div>
    </section>
    @endcan

    <section id="plays" aria-labelledby="h-plays">
      <span class="eyebrow">Optimized use cases</span>
      <h2 id="h-plays">Playbooks that save the most time</h2>
      <div class="plays">
        <article class="play"><div class="when"><b>Monday morning sweep</b><span>10 minutes · start of week</span><span class="who">Everyone</span></div><div><ol><li>Open OmniVEN. It lands on <b>Routines</b>, all brands.</li><li>Clear anything on the <b>Hotlist</b> with your name first.</li><li>Work down <b>Weekly</b>: <span class="bi bi-xs bi-google" style="--bi:#4285F4" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-google"></use></svg></span>Merchant Center disapprovals, <span class="bi bi-xs bi-googlesearchconsole" style="--bi:#458CF5" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-googlesearchconsole"></use></svg></span>Search Console, <span class="bi bi-xs bi-meta" style="--bi:#0467DF" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-meta"></use></svg></span>ad spend. Each <b>Open ↗</b> link goes straight to the right console.</li><li>Tick each one as you finish, then check <b>Slack</b> for anything flagged over the weekend.</li></ol><p class="why">Why it works: the routine is the checklist and the link, so you never hunt for a URL or wonder whether someone already did it.</p></div></article>
        <article class="play"><div class="when"><b><span class="bi bi-md bi-meta" style="--bi:#0467DF" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-meta"></use></svg></span> Launching a campaign for one brand</b><span>e.g. Ocusafe fall safety-glasses push</span><span class="who">Everyone</span></div><div><ol><li>Press <kbd>⌘K</kbd>, type <b>ocusafe</b>, and open the brand hub.</li><li>Every Ocusafe account is there, grouped by Social, Advertising and Search. Press <b>Open ↗</b> on <span class="bi bi-xs bi-meta" style="--bi:#0467DF" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-meta"></use></svg></span>Meta Ads and <span class="bi bi-xs bi-instagram" style="--bi:#FF0069" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-instagram"></use></svg></span>Instagram.</li><li>Missing a login? Press <b>Request access</b> on that card before you need it.</li><li>Star the hub so it sits in My launchpad for the length of the campaign. Un-star it when you're done.</li></ol><p class="why">Why it works: the hub is the whole brand on one screen, and stars keep this week's focus one click away.</p></div></article>
        <article class="play"><div class="when"><b>Onboarding a new marketer</b><span>Day one</span><span class="who adm">Admin</span></div><div><ol><li><b>People → Add person</b> with their @39dollarglasses.com email, before they sign in.</li><li>Create a Hotlist task: “Read Help in OmniVEN and star your top five tools.”</li><li>Point them to <b>Help</b> in the top bar. They request each login from the Access tab, and you grant each one from the requests panel after sharing it in the password manager.</li></ol><p class="why">Why it works: every grant is recorded with who asked, for what and who approved it. You never have to rebuild that from email threads.</p></div></article>
        <article class="play"><div class="when"><b>Running the weekly social runbook</b><span>All week · Monday to Sunday</span><span class="who">Everyone</span></div><div><ol><li>Open <b>Calendar</b>. It lands on this week, with the phase and seasonal angle at the top. Do the "One-off this week" items first.</li><li>Work each day's items: <span class="bi bi-xs bi-feedly" style="--bi:#2BB24C" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-feedly"></use></svg></span>Feedly triage Monday, <span class="bi bi-xs bi-instagram" style="--bi:#FF0069" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-instagram"></use></svg></span><span class="bi bi-xs bi-tiktok bi-dark" style="--bi:#000000" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-tiktok"></use></svg></span>Instagram and TikTok posts, <span class="bi bi-xs bi-linkedin" style="--bi:#0A66C2" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-linkedin"></use></svg></span>Ocusafe LinkedIn, <span class="bi bi-xs bi-pinterest" style="--bi:#BD081C" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-pinterest"></use></svg></span>Pinterest pins on Friday. Each one says when it counts as done.</li><li>Wednesday and Friday, apply the <b>36-hour rule</b> and record the decision in the <b>Boost log</b>, boost or skip.</li><li>Sunday, write the one decision in <b>Sunday review</b> and check next week is queued.</li></ol><p class="why">Why it works: the plan, the checkmarks and the spend live in one place, so anyone can pick up a day and the quarter shows its own progress.</p></div></article>
        <article class="play"><div class="when"><b>Weekly one-on-one</b><span>15 minutes · once a week per person</span><span class="who adm">Admin</span></div><div><ol><li>Press <kbd>⌘K</kbd>, type their name and open their staff page.</li><li>Start with the four counts: anything overdue or still due comes first.</li><li>Go through <b>Open tasks</b> together and fix due dates or the Hotlist right there.</li><li>Scroll <b>Done in the last 30 days</b> to call out wins, and reassign any routine that no longer fits.</li></ol><p class="why">Why it works: their routines, tasks and recent work are on one page, so the meeting starts from facts instead of memory.</p></div></article>
        <article class="play"><div class="when"><b>Offboarding someone</b><span>Same day they leave</span><span class="who adm">Admin</span></div><div><ol><li><b>People</b> → turn off their access. They're signed out at their next click.</li><li>Filter the <b>Access</b> tab by their name in the search box to see every tool where they're the owner or login.</li><li>Reassign the owner, change those passwords in the password manager, and press <b>Mark verified</b>.</li><li>Open their staff page and reassign their routines and open tasks before turning off access.</li></ol><p class="why">Why it works: the Access tab's search covers the login and owner fields, so their footprint shows in one view.</p></div></article>
        <article class="play"><div class="when"><b>Quarterly access review</b><span>~45 minutes · once a quarter</span><span class="who adm">Admin</span></div><div><ol><li>Open the <b>Quarterly access review</b> routine's link, or <kbd>⌘K</kbd> → <b>Access review</b>.</li><li>Work from the top. Never-verified and overdue logins come first.</li><li>For each one, confirm the login and owner are right, then press <b>Verified ✓</b>, or <b>Fix</b> to edit it.</li><li>When the bar reads <b>N of N verified</b>, tick the routine.</li></ol><p class="why">Why it works: one list, sorted by risk, with a progress bar. It's done when the bar is full.</p></div></article>
        <article class="play"><div class="when"><b>A link broke overnight</b><span>When Slack posts ⚠</span><span class="who adm">Admin</span></div><div><ol><li>Open the <b>Broken links</b> panel on the Access tab. It shows the error (404, site not found…) and since when.</li><li>Press <b>Fix</b>, paste the new URL, and save.</li><li>Press <b>Check now</b> to confirm it's back to OK.</li></ol><p class="why">Why it works: a link is flagged only after failing two nights running, so a brief outage doesn't raise an alarm. Instagram, Facebook and LinkedIn block robots and show as “couldn't check”, not broken.</p></div></article>
        <article class="play"><div class="when"><b><span class="bi bi-md bi-reddit" style="--bi:#FF4500" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-reddit"></use></svg></span> Daily Reddit and reviews</b><span>15 minutes · every weekday</span><span class="who">Everyone</span></div><div><ol><li>Open <span class="bi bi-xs bi-shopperapproved bi-multi" style="--bi:#E86600" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-shopperapproved"></use></svg></span><b>Shopper Approved</b>, our main review platform, first. It's first in Platform consoles, and the <b>Check new reviews and escalate</b> routine links straight to its dashboard. Then check Trustpilot and ConsumerAffairs.</li><li>Tick through <b>Work the Reddit report</b> and <b>Check new reviews and escalate</b> on Routines.</li><li>Read #reddit-monitor on the <span class="bi bi-xs bi-slack" style="--bi:#4A154B" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="{{ $sprite }}#bi-slack"></use></svg></span><b>Slack</b> tab and reply from OmniVEN.</li><li>Check LinkedIn page comments and messages for 39DollarGlasses, Ocusafe, Ocusleep and Neurolux: <kbd>⌘K</kbd>, type <b>li</b>, pick the brand.</li><li>Escalate any chargeback, BBB, legal or prescription-error mention the same day as a Hotlist task.</li></ol><p class="why">Why it works: monitoring, replying and escalating happen in one tab, and the tick records that it was done.</p></div></article>
      </div>
    </section>

    <section id="rules" aria-labelledby="h-rules">
      <span class="eyebrow">Everyone</span>
      <h2 id="h-rules">House rules</h2>
      <div class="rules">
        <div class="rule"><h3>No passwords. Ever.</h3><p>Never type a password or 2FA code into OmniVEN, including in notes and requests. List the login email and the owner. Credentials stay in the password manager.</p></div>
        <div class="rule"><h3>PostIT Notes are private</h3><p>Only their author sees them. Their text never goes into the Activity report or logs.</p></div>
        <div class="rule"><h3>Work accounts only</h3><p>Sign-in is limited to @39dollarglasses.com Google accounts, checked on the server.</p></div>
        <div class="rule"><h3>Activity is need-to-know</h3><p>The Activity report is visible only to drmarcweinstein@ and drmarlow@.</p></div>
        <div class="rule"><h3>Spell the brands right</h3><p>It's <b>Ocusafe</b> and <b>Ocusleep</b>, everywhere, including routines, tasks and Slack.</p></div>
      </div>
    </section>

    @can('admin')
    <section id="checklist" aria-labelledby="h-check">
      <span class="eyebrow"><span class="admin-tag">Admins</span></span>
      <h2 id="h-check">New-instance checklist</h2>
      <p class="lede">Tick these off as you go. Your ticks are saved in this browser only.</p>
      <div class="check">
        <div class="check-head"><div class="meter" aria-hidden="true"><span id="help-meter"></span></div><output id="help-checked" aria-live="polite">0 of 13 done</output><button type="button" id="help-reset">Clear ticks</button></div>
        <ul id="help-checks">
          <li><label><input type="checkbox" id="c1"><div><b>Google sign-in works for a teammate</b><span>Internal consent screen, callback URL set.</span></div></label></li>
          <li><label><input type="checkbox" id="c2"><div><b>Admin and Activity emails set</b><span>OMNIVEN_ADMIN_EMAILS and OMNIVEN_ACTIVITY_VIEWERS in Railway.</span></div></label></li>
          <li><label><input type="checkbox" id="c3"><div><b>Everyone added on People</b><span>Admins ticked for Marc, Dr. Marlow and Leona.</span></div></label></li>
          <li><label><input type="checkbox" id="c4"><div><b>Brands checked</b><span>Websites, focus and channels. Lumeo and Lens Replacement set to share 39DollarGlasses' accounts.</span></div></label></li>
          <li><label><input type="checkbox" id="c5"><div><b>Grid gaps filled</b><span>Each brand's Meta Ads, Google Ads, Merchant Center, GA4, Search Console and Klaviyo accounts added, plus LinkedIn for any brand that gets a page.</span></div></label></li>
          <li><label><input type="checkbox" id="c6"><div><b>Logins and owners filled in</b><span>No “Add” left in the Login account and Owner columns on Access.</span></div></label></li>
          <li><label><input type="checkbox" id="c7"><div><b>Platform consoles chosen</b><span>Four to six tools everyone opens daily, with Shopper Approved first.</span></div></label></li>
          <li><label><input type="checkbox" id="c8"><div><b>Routines reviewed</b><span>Old monthly “Review access and owners” removed now that the quarterly review exists.</span></div></label></li>
          <li><label><input type="checkbox" id="c13"><div><b>Routines have owners</b><span>Each recurring job assigned to one person under “Assigned to”, or left on Whole team on purpose.</span></div></label></li>
          <li><label><input type="checkbox" id="c9"><div><b>Slack connected</b><span>Bot token set, channels added, bot invited to private ones.</span></div></label></li>
          <li><label><input type="checkbox" id="c10"><div><b>Admin alerts channel set</b><span>OMNIVEN_ACCESS_SLACK_CHANNEL, with @OmniVEN invited.</span></div></label></li>
          <li><label><input type="checkbox" id="c11"><div><b>Nightly link check running</b><span>omniven-link-check cron service, first run log reviewed.</span></div></label></li>
          <li><label><input type="checkbox" id="c12"><div><b>First access review done</b><span>Every login verified, quarterly routine ticked.</span></div></label></li>
        </ul>
      </div>
    </section>
    @endcan

    <footer class="help-foot sub">Questions go to an admin in Slack. Something wrong or missing here? Tell Marc and it'll be updated.</footer>
  </main>
</div>
@endsection
