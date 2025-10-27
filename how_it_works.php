<?php /* how_it_works.php — public, no auth, no custom helpers */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>How Safety Tours Works — ELI5</title>
<meta name="theme-color" content="#0ea5e9">
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{
    --bg:#0b1220;--card:#0f172a;--panel:#0c1628;--text:#e5e7eb;--muted:#94a3b8;--border:#1f2937;
    --a1:#0ea5e9;--a2:#22d3ee;--ok:#16a34a;--warn:#f59e0b;--hi:#ef4444;--radius:18px
  }
  *{box-sizing:border-box}
  html,body{margin:0;padding:0}
  body{
    background:
      radial-gradient(1200px 800px at 85% -150px, rgba(14,165,233,.18), transparent 60%),
      radial-gradient(900px 600px at -10% 20%, rgba(34,211,238,.10), transparent 60%),
      var(--bg);
    color:var(--text);
    font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;
  }

  /* Top Hero */
  .hero{position:relative;overflow:hidden}
  .wrap{max-width:1100px;margin:0 auto;padding:24px 16px}
  .brand{display:flex;align-items:center;gap:10px}
  .brand img{height:30px}
  .nav{display:flex;justify-content:space-between;align-items:center;gap:12px}
  .nav a{color:#cfe9ff;text-decoration:none;opacity:.9}
  .cta{display:inline-flex;gap:10px}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 16px;border-radius:14px;border:1px solid var(--border);
       background:linear-gradient(180deg, rgba(14,165,233,.25), rgba(2,132,199,.12));color:#dbeafe;font-weight:800;text-decoration:none}
  .btn.primary{background:linear-gradient(180deg, var(--a1), var(--a2));color:#00131a;box-shadow:0 20px 40px rgba(2,132,199,.25) inset}
  .btn.ghost{background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03));color:#cfe9ff}

  .hero-main{display:grid;grid-template-columns:1.25fr 1fr;gap:20px;align-items:center;margin-top:24px}
  @media (max-width:900px){.hero-main{grid-template-columns:1fr}}
  .headline{font-size:2.2rem;line-height:1.15;margin:0 0 10px;font-weight:900}
  .sub{color:var(--muted);max-width:50ch}
  .glow{
    position:absolute;inset:-30% -10% auto auto;height:420px;width:420px;filter:blur(40px);
    background:conic-gradient(from 180deg at 50% 50%, rgba(14,165,233,.45), rgba(34,211,238,.25), rgba(14,165,233,.45));
    opacity:.25;border-radius:50%;
    animation:spin 16s linear infinite;
    pointer-events:none
  }
  @keyframes spin{to{transform:rotate(360deg)}}

  /* Cards / Panels */
  .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
  @media (max-width:960px){.grid3{grid-template-columns:1fr 1fr}}
  @media (max-width:640px){.grid3{grid-template-columns:1fr}}
  .card{background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));
        border:1px solid var(--border);border-radius:var(--radius);padding:14px}
  .card h3{margin:4px 0 4px;font-size:1.05rem}
  .muted{color:var(--muted)}
  .k{display:inline-flex;gap:8px;align-items:center;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#0d1a2b;font-weight:700}
  .ok{color:#b7f7cf;background:#052e1a;border-color:#0c5e36}
  .warn{color:#ffefb5;background:#3f2e00;border-color:#6b4e00}
  .hi{color:#fecaca;background:#3b0a0a;border-color:#7f1d1d}

  /* How it works steps */
  .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:6px}
  @media (max-width:960px){.steps{grid-template-columns:1fr}}
  .step{background:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));border:1px dashed #223047;border-radius:16px;padding:14px}
  .step strong{display:block;margin-bottom:4px}
  .num{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:8px;background:#0e223a;border:1px solid #24405e;font-weight:900;margin-right:8px}

  /* Split visuals */
  .split{display:grid;grid-template-columns:1.2fr 1fr;gap:18px;align-items:center}
  @media (max-width:980px){.split{grid-template-columns:1fr}}
  .panel{background:
        radial-gradient(600px 300px at -10% -40%, rgba(14,165,233,.12), transparent 60%),
        var(--panel);
        border:1px solid var(--border);border-radius:18px;padding:14px}

  /* Mini “live” preview blocks */
  .preview{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:720px){.preview{grid-template-columns:1fr}}
  .tile{border:1px solid var(--border);border-radius:12px;padding:10px;background:#0b1526}
  .tile h4{margin:2px 0 6px;font-size:.98rem}
  .chip{display:inline-flex;gap:8px;align-items:center;padding:6px 10px;border-radius:999px;background:#0b1220;border:1px solid var(--border);margin:4px 6px 0 0}

  /* FAQ */
  details{border:1px solid var(--border);border-radius:14px;padding:10px;background:#0f172a}
  summary{cursor:pointer;font-weight:800}
  details+details{margin-top:10px}

  /* Footer */
  .footer{color:var(--muted);margin:26px 0 8px}
  a.link{color:#93c5fd;text-decoration:none}
</style>
</head>
<body>

<!-- HERO -->
<section class="hero">
  <div class="glow"></div>
  <div class="wrap">
    <nav class="nav">
      <div class="brand">
        <img src="/assets/img/logo.png" alt="McGoff" onerror="this.style.display='none'">
        <strong>Safety Tours</strong>
      </div>
      <div class="cta">
        <a class="btn" href="/dashboard.php">Dashboard</a>
        <a class="btn" href="/actions.php">Actions</a>
        <a class="btn primary" href="/form.php">New Tour</a>
      </div>
    </nav>

    <div class="hero-main">
      <div>
        <h1 class="headline">Not another boring safety app.<br>It’s a <em>2-minute</em> safety tour that emails a PDF, creates actions, and gets things done.</h1>
        <p class="sub">
          Walk the site, tap answers, add photos, sign, and hit send. The system builds a clean PDF, logs your actions,
          emails everyone that matters, and tracks closure. No fuss. No spreadsheets. No chasing.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
          <a class="btn primary" href="/form.php">Start a tour →</a>
          <a class="btn ghost" href="#how">How it works</a>
          <span class="k ok">Fast</span>
          <span class="k warn">Practical</span>
          <span class="k hi">Audit-ready</span>
        </div>
      </div>

      <div class="card">
        <h3 style="margin:0 0 6px">What you’ll see</h3>
        <div class="preview" aria-hidden="true">
          <div class="tile">
            <h4>Tour Form (mobile)</h4>
            <div class="chip">Pass / Improve / Fail</div>
            <div class="chip">Add photos</div>
            <div class="chip">Signature</div>
            <div class="chip">Auto-emails</div>
          </div>
          <div class="tile">
            <h4>Actions Register</h4>
            <div class="chip">Overdue filter</div>
            <div class="chip">Priority badges</div>
            <div class="chip">Close with photos</div>
            <div class="chip">Email on closure</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section id="how" class="wrap" style="margin-top:10px">
  <div class="grid3">
    <div class="card">
      <h3>ELI5: Explain it like I’m 5</h3>
      <p class="muted">You walk around the site with your phone. You tick boxes. If something needs fixing, you write it, snap a photo, and say who’s fixing it. You sign. The app sends a neat PDF to everyone and creates an action to track it. That’s it.</p>
    </div>
    <div class="card">
      <h3>Why it’s different</h3>
      <ul class="muted" style="margin:6px 0 0;padding-left:18px">
        <li>2-minute flow, not 20.</li>
        <li>Photos inline. No copy/paste pain.</li>
        <li>Actions that actually close, with proof.</li>
      </ul>
    </div>
    <div class="card">
      <h3>Works anywhere</h3>
      <p class="muted">No installs. Open the link. Offline-friendly form. UK date formats. Dark mode. Looks good on-site and on a board pack.</p>
    </div>
  </div>

  <div class="split" style="margin-top:14px">
    <div class="panel">
      <h3 style="margin:0 0 8px">The 3-step loop</h3>
      <div class="steps">
        <div class="step">
          <strong><span class="num">1</span> Do the tour</strong>
          Pick site + area → tap results → add photos → sign. Add recipients (chips). Hit <em>Save & Email PDF</em>.
        </div>
        <div class="step">
          <strong><span class="num">2</span> Actions happen</strong>
          Every improvement/fail becomes an action with responsible + due date. The register shows <em>Open / Overdue / Closed</em>.
        </div>
        <div class="step">
          <strong><span class="num">3</span> Close the loop</strong>
          When fixed, add closure photos + note and click <em>Close</em>. The system can email the same recipients with the proof.
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
        <a class="btn primary" href="/form.php">Run a demo tour</a>
        <a class="btn" href="/actions.php">Open Actions</a>
        <a class="btn ghost" href="/dashboard.php">Dashboard</a>
      </div>
    </div>

    <div class="panel">
      <h3 style="margin:0 0 8px">What the PDF looks like</h3>
      <div class="tile">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <div class="muted">Cover</div>
            <ul class="muted" style="margin:6px 0 0;padding-left:18px">
              <li>Project, area, date</li>
              <li>Lead + participants</li>
              <li>Signature included</li>
            </ul>
          </div>
          <div>
            <div class="muted">Body</div>
            <ul class="muted" style="margin:6px 0 0;padding-left:18px">
              <li>Findings with photos</li>
              <li>Action list & priorities</li>
              <li>Clear, board-ready layout</li>
            </ul>
          </div>
        </div>
        <div style="margin-top:10px">
          <span class="chip">UK format</span>
          <span class="chip">Crisp typography</span>
          <span class="chip">Small file size</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="wrap" style="margin-top:14px">
  <div class="grid3">
    <div class="card">
      <h3>Actions that stick</h3>
      <p class="muted">Every “Improve/Fail” becomes an action with due date and owner. Overdue filters keep attention where it counts.</p>
    </div>
    <div class="card">
      <h3>Proof on closure</h3>
      <p class="muted">Add closure photos + note. Click close. Optional emails go to the same recipients with the fix evidence.</p>
    </div>
    <div class="card">
      <h3>Made for real sites</h3>
      <p class="muted">One-hand friendly, works on older phones, nothing to install, and looks great on a TV in the site office.</p>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="wrap" style="margin-top:14px">
  <div class="card">
    <h3 style="margin:0 0 8px">FAQ</h3>
    <details open>
      <summary>Do I need to install an app?</summary>
      <div class="muted">No. It’s a web app. Open the link, add to home screen if you like, and you’re set.</div>
    </details>
    <details>
      <summary>Who gets the emails?</summary>
      <div class="muted">You choose recipients on the form (chips). The system remembers frequently used addresses for one-tap add.</div>
    </details>
    <details>
      <summary>Can I filter actions?</summary>
      <div class="muted">Yes — by open/closed/overdue, search by site or responsible, and view each action with all related images.</div>
    </details>
    <details>
      <summary>Is the PDF audit-ready?</summary>
      <div class="muted">Yes. Clean structure, embedded photos, signature, timestamps, and clear actions.</div>
    </details>
  </div>
</section>

<section class="wrap" style="margin:14px 0 20px">
  <div class="card" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
    <div>
      <h3 style="margin:0 0 4px">Ready to try it on today’s walk?</h3>
      <div class="muted">It’s quicker than your coffee going cold.</div>
    </div>
    <div class="cta">
      <a class="btn primary" href="/form.php">Start a tour</a>
      <a class="btn" href="/actions.php">Review actions</a>
    </div>
  </div>
  <div class="footer">© Defect Tracker. Built for real sites, by real people. Developed And Maintained By Chris Irlam · <a class="link" href="/dashboard.php">Dashboard</a></div>
</section>

<script>
  // Smooth scroll for "How it works" link (no framework)
  document.querySelectorAll('a[href^="#"]').forEach(a=>{
    a.addEventListener('click', e=>{
      const id = a.getAttribute('href').slice(1);
      const el = document.getElementById(id);
      if(!el) return;
      e.preventDefault();
      el.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
</script>
</body>
</html>
