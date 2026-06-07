<?php
// Served via router.php, which provides $db and $q.
$entries = $q !== '' ? lookup($q, $db) : [];

// masculine/feminine/neuter -> m/f/n (drives the only color accents on the page)
$genderLetter = fn(?string $g) => ['masculine' => 'm', 'feminine' => 'f', 'neuter' => 'n'][$g] ?? null;

// the headword whose lookup we record into "recent searches" (client-side)
$current = null;
if ($entries) {
    $e0 = $entries[0];
    $current = [
        'word' => $e0['formWord'] ?: $e0['word'],
        'art'  => $e0['article'],
        'cls'  => $genderLetter($e0['gender']),
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $entries ? h($entries[0]['word']) . ' — ' : '' ?>DeDict</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,600;1,400&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3/dist/tabler-icons.min.css">
<style>
  :root {
    --paper: #FAFAF7;
    --card: #FFFFFF;
    --border: rgba(0,0,0,0.08);
    --border-strong: rgba(0,0,0,0.14);
    --ink: #1A1916;
    --ink-2: #4A4844;
    --ink-3: #8A8780;
    --m: #1B5DA8;
    --f: #B3344A;
    --n: #2A7A52;
    --accent: #2A5BA8;
    --accent-bg: #EEF3FC;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { -webkit-text-size-adjust: 100%; }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--paper);
    color: var(--ink);
    min-height: 100vh;
  }
  a { color: inherit; text-decoration: none; }

  /* ---- topbar ---------------------------------------------------- */
  .dd-topbar {
    background: #FFFFFF;
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    height: 56px;
    position: sticky;
    top: 0;
    z-index: 20;
  }
  .dd-logo {
    font-family: 'Lora', serif;
    font-weight: 600;
    font-size: 18px;
    letter-spacing: -0.02em;
    color: var(--ink);
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .dd-logo-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); display: inline-block; margin-bottom: 1px; }

  .dd-search-wrap { flex: 1; max-width: 380px; position: relative; }
  .dd-search-input {
    width: 100%;
    height: 36px;
    border: 1px solid var(--border-strong);
    border-radius: 8px;
    padding: 0 36px 0 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--ink);
    background: var(--paper);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .dd-search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-bg); }
  .dd-search-icon {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--ink-3);
    font-size: 15px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px 6px;
    line-height: 1;
    transition: color 0.15s;
  }
  .dd-search-icon:hover { color: var(--accent); }

  .dd-nav-links { margin-left: auto; display: flex; gap: 4px; align-items: center; }
  .dd-nav-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ink-3);
    padding: 5px 10px;
    border-radius: 6px;
    border: none;
    background: none;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    display: inline-block;
  }
  .dd-nav-btn:hover { background: var(--paper); color: var(--ink); }
  .dd-nav-btn.active { background: var(--accent-bg); color: var(--accent); }

  /* about dropdown */
  .dd-about { position: relative; }
  .dd-about > summary { list-style: none; }
  .dd-about > summary::-webkit-details-marker { display: none; }
  .dd-about[open] > summary { background: var(--accent-bg); color: var(--accent); }
  .dd-about-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 280px;
    background: var(--card);
    border: 1px solid var(--border-strong);
    border-radius: 10px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.10);
    padding: 16px;
    z-index: 30;
  }
  .dd-about-panel h3 {
    font-family: 'Lora', serif;
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 8px;
  }
  .dd-about-panel p { font-size: 13px; line-height: 1.5; color: var(--ink-2); margin-bottom: 8px; }
  .dd-about-panel p:last-child { margin-bottom: 0; }
  .dd-about-panel a { color: var(--accent); }
  .dd-about-panel a:hover { text-decoration: underline; }

  /* ---- layout ---------------------------------------------------- */
  .dd-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    min-height: calc(100vh - 56px);
  }

  .dd-sidebar { border-right: 1px solid var(--border); padding: 20px 0; background: #FDFDFA; }
  .dd-sidebar-section { padding: 0 16px 20px; margin-bottom: 12px; border-bottom: 1px solid var(--border); }
  .dd-sidebar-section:last-child { border-bottom: none; margin-bottom: 0; }
  .dd-sidebar-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-3);
    margin-bottom: 10px;
  }

  .dd-wotd-card { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; }
  .dd-wotd-word { font-family: 'Lora', serif; font-weight: 600; font-size: 20px; letter-spacing: -0.02em; line-height: 1.1; }
  .dd-wotd-meta { display: flex; align-items: center; gap: 6px; margin: 4px 0 8px; }
  .dd-wotd-def { font-size: 12px; color: var(--ink-2); line-height: 1.4; margin-bottom: 8px; }
  .dd-wotd-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--accent); border: 1px solid var(--accent); background: none;
    border-radius: 5px; padding: 4px 10px; cursor: pointer; transition: background 0.15s;
    display: inline-block;
  }
  .dd-wotd-btn:hover { background: var(--accent-bg); }

  .dd-art { font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 500; padding: 2px 6px; border-radius: 4px; }
  .dd-art.m { color: var(--m); background: #E8F0FB; }
  .dd-art.f { color: var(--f); background: #FBEAED; }
  .dd-art.n { color: var(--n); background: #E7F4EE; }
  .dd-pos-pill { font-family: 'JetBrains Mono', monospace; font-size: 9px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink-3); }

  .dd-recent-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 5px 2px; font-size: 13px; cursor: pointer; border-radius: 5px;
    transition: background 0.12s, padding 0.12s;
  }
  .dd-recent-item:hover { background: var(--accent-bg); padding-left: 6px; }
  .dd-recent-word { color: var(--ink-2); }
  .dd-recent-art { font-family: 'JetBrains Mono', monospace; font-size: 9px; }
  .dd-recent-art.m { color: var(--m); }
  .dd-recent-art.f { color: var(--f); }
  .dd-recent-art.n { color: var(--n); }
  .dd-recent-empty { font-size: 12px; color: var(--ink-3); font-style: italic; }

  .dd-legend { display: flex; flex-direction: column; gap: 5px; }
  .dd-legend-row { display: flex; align-items: center; gap: 7px; font-size: 11px; color: var(--ink-2); }
  .dd-legend-swatch { width: 20px; height: 3px; border-radius: 2px; }

  /* ---- main ------------------------------------------------------ */
  .dd-main { padding: 24px 28px; overflow-y: auto; }

  .dd-result-header { display: flex; align-items: baseline; gap: 12px; margin-bottom: 6px; }
  .dd-result-count {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px; letter-spacing: 0.08em; color: var(--ink-3); text-transform: uppercase;
  }

  /* the only requested change to the entry box: NO colored left border */
  .dd-entry { background: var(--card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 14px; overflow: hidden; }
  .dd-entry-2 { opacity: 0.75; }

  .dd-entry-head { padding: 14px 18px 12px; display: flex; align-items: flex-start; gap: 12px; }
  .dd-entry-title-col { flex: 1; min-width: 0; }
  .dd-headword { font-family: 'Lora', serif; font-weight: 600; font-size: 26px; letter-spacing: -0.02em; line-height: 1; color: var(--ink); word-break: break-word; }
  .dd-headword.form { font-size: 20px; }
  .dd-entry-meta-row { display: flex; align-items: center; gap: 8px; margin-top: 5px; flex-wrap: wrap; }
  .dd-ipa { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--ink-3); }
  .dd-hyphen { font-size: 11px; color: var(--ink-3); }

  .dd-audio-btn {
    width: 24px; height: 24px; border-radius: 50%;
    border: 1px solid var(--border-strong); background: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--ink-3); font-size: 10px;
    transition: color 0.15s, border-color 0.15s;
  }
  .dd-audio-btn:hover { color: var(--accent); border-color: var(--accent); }
  .dd-entry-m .dd-audio-btn:hover { color: var(--m); border-color: var(--m); }
  .dd-entry-f .dd-audio-btn:hover { color: var(--f); border-color: var(--f); }
  .dd-entry-n .dd-audio-btn:hover { color: var(--n); border-color: var(--n); }

  .dd-entry-body { border-top: 1px solid var(--border); padding: 14px 18px 16px; }

  .dd-sense-list { list-style: none; }
  .dd-sense { display: grid; grid-template-columns: 22px 1fr; gap: 0 8px; padding: 10px 0; border-top: 1px dashed var(--border); }
  .dd-sense:first-child { border-top: none; padding-top: 0; }
  .dd-sense-num { font-family: 'Lora', serif; font-weight: 600; font-size: 13px; line-height: 1.7; color: var(--ink-3); }
  .dd-entry-m .dd-sense-num { color: var(--m); }
  .dd-entry-f .dd-sense-num { color: var(--f); }
  .dd-entry-n .dd-sense-num { color: var(--n); }

  .dd-def { font-family: 'Lora', serif; font-size: 15px; line-height: 1.55; color: var(--ink); }
  .dd-simple { font-size: 12.5px; color: var(--ink-3); border-left: 2px solid var(--border-strong); padding-left: 8px; margin-top: 4px; font-style: italic; }
  .dd-gloss { font-size: 13px; color: var(--ink-2); margin-top: 2px; }
  .dd-gloss::before { content: "→ "; color: var(--ink-3); }
  .dd-entry-m .dd-gloss::before { color: var(--m); }
  .dd-entry-f .dd-gloss::before { color: var(--f); }
  .dd-entry-n .dd-gloss::before { color: var(--n); }

  /* examples (native <details> for free toggling) */
  details.dd-ex { margin-top: 4px; }
  details.dd-ex > summary {
    list-style: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase;
    color: var(--ink-3); cursor: pointer; padding: 4px 0;
    display: inline-flex; align-items: center; gap: 4px; transition: color 0.15s;
  }
  details.dd-ex > summary::-webkit-details-marker { display: none; }
  details.dd-ex > summary .tri { display: inline-block; transition: transform 0.18s; }
  details.dd-ex[open] > summary .tri { transform: rotate(90deg); }
  .dd-entry-m details.dd-ex > summary:hover { color: var(--m); }
  .dd-entry-f details.dd-ex > summary:hover { color: var(--f); }
  .dd-entry-n details.dd-ex > summary:hover { color: var(--n); }

  .dd-examples { margin-top: 2px; }
  .dd-example { border-left: 2px solid var(--border); padding: 5px 0 5px 10px; margin-top: 4px; }
  .dd-ex-de { font-family: 'Lora', serif; font-style: italic; font-size: 13px; color: var(--ink-2); line-height: 1.4; }
  .dd-ex-en { font-size: 12px; color: var(--ink-3); margin-top: 2px; }

  .dd-meta-row { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--border); }
  .dd-meta-group { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
  .dd-meta-label { font-family: 'JetBrains Mono', monospace; font-size: 9px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-3); }
  .dd-pill { font-size: 12px; color: var(--ink-2); background: #F3F2EE; padding: 2px 8px; border-radius: 4px; border: none; cursor: pointer; transition: background 0.12s, color 0.12s; }
  .dd-pill:hover { background: var(--accent-bg); color: var(--accent); }
  .dd-entry-m .dd-pill:hover { background: #E8F0FB; color: var(--m); }
  .dd-entry-f .dd-pill:hover { background: #FBEAED; color: var(--f); }
  .dd-entry-n .dd-pill:hover { background: #E7F4EE; color: var(--n); }

  .dd-phon-pending { font-size: 13px; color: var(--ink-3); font-style: italic; }
  .dd-notice { text-align: center; color: var(--ink-3); font-size: 15px; padding: 4rem 1rem; line-height: 1.6; }
  .dd-notice strong { color: var(--ink); font-weight: 600; }

  /* ---- responsive ------------------------------------------------ */
  @media (max-width: 760px) {
    .dd-layout { grid-template-columns: 1fr; }
    .dd-sidebar { order: 2; border-right: none; border-top: 1px solid var(--border); }
    .dd-main { order: 1; padding: 18px 16px; }
    .dd-topbar { gap: 12px; padding: 0 14px; flex-wrap: wrap; }
    .dd-search-wrap { order: 3; flex-basis: 100%; max-width: none; }
  }
</style>
</head>
<body>

  <header class="dd-topbar">
    <a class="dd-logo" href="/">
      <span class="dd-logo-dot"></span>
      DeDict
    </a>
    <form class="dd-search-wrap" method="get" action="/">
      <input class="dd-search-input" type="search" name="q" value="<?= h($q) ?>"
             placeholder="Ein Wort suchen…" autofocus autocomplete="off" spellcheck="false">
      <button class="dd-search-icon" type="submit" aria-label="Suchen"><i class="ti ti-search" aria-hidden="true"></i></button>
    </form>
    <nav class="dd-nav-links">
      
      <details class="dd-about">
        <summary class="dd-nav-btn">About</summary>
        <div class="dd-about-panel">
          <h3>About DeDict</h3>
          <p>A German learner's dictionary built from <a href="https://kaikki.org/dewiktionary/" target="_blank" rel="noopener">Wiktionary</a> data.</p>
          <p>Definitions are kept in their original German, then translated into English and restated in simple German with clear examples — so you can learn a word the way you'd actually use it.</p>
          <p>der / die / das are color-coded so gender sticks.</p>
        </div>
      </details>
    </nav>
  </header>

  <div class="dd-layout">

    <aside class="dd-sidebar">
      <div class="dd-sidebar-section">
        <div class="dd-sidebar-label">Word of the day</div>
        <div class="dd-wotd-card">
          <div class="dd-wotd-word">Fernweh</div>
          <div class="dd-wotd-meta">
            <span class="dd-art n">das</span>
            <span class="dd-pos-pill">noun</span>
          </div>
          <div class="dd-wotd-def">Longing for distant places; the opposite of homesickness.</div>
          <a class="dd-wotd-btn" href="/?q=Fernweh">Look up →</a>
        </div>
      </div>

      <div class="dd-sidebar-section">
        <div class="dd-sidebar-label">Recent searches</div>
        <div id="dd-recent"><div class="dd-recent-empty">No searches yet.</div></div>
      </div>

      <div class="dd-sidebar-section">
        <div class="dd-sidebar-label">Gender colors</div>
        <div class="dd-legend">
          <div class="dd-legend-row"><span class="dd-legend-swatch" style="background:var(--m)"></span>der — masculine</div>
          <div class="dd-legend-row"><span class="dd-legend-swatch" style="background:var(--f)"></span>die — feminine</div>
          <div class="dd-legend-row"><span class="dd-legend-swatch" style="background:var(--n)"></span>das — neuter</div>
        </div>
      </div>
      
      <div class="dd-sidebar-section">
        <!-- <div class="dd-sidebar-label">open-source</div> -->
        <div class="dd-legend">
          <div class="dd-legend-row">
            DeDict on <a class="dd-nav-btn" href="https://github.com/smoqadam/DeDict" target="_blank" rel="noopener" aria-label="GitHub"><i class="ti ti-brand-github" aria-hidden="true" style="font-size: 15px;"></i> GitHub <i class="ti ti-external-link" aria-hidden="true" style="font-size: 12px; margin-left: 2px;"></i></a>
          </div>
            <div class="dd-legend-row">
            
            </div>
          </div>
        
        </div>
    </aside>

    <main class="dd-main">

<?php if ($q === ''): ?>

      <p class="dd-notice">Search a German word to begin.</p>

<?php elseif (!$entries): ?>

      <p class="dd-notice">No entry for <strong><?= h($q) ?></strong>.</p>

<?php else: ?>

      <div class="dd-result-header">
        <span class="dd-result-count"><?= count($entries) ?> <?= count($entries) === 1 ? 'result' : 'results' ?> for &ldquo;<?= h($q) ?>&rdquo;</span>
      </div>

<?php foreach ($entries as $i => $e):
        $g          = $genderLetter($e['gender']);
        $entryClass = $g ? "dd-entry-$g" : '';
        $isForm     = (bool)$e['formWord'];
        $headword   = $isForm ? $e['formWord'] : $e['word'];
        $mp3        = $e['audio'][0]['mp3_url'] ?? null;

        $posLabel = $e['pos'];
        if ($isForm) {
            $posLabel .= ' · ← ' . $e['word'];
        } elseif ($e['gender'] && $e['gender'] !== 'none') {
            $posLabel .= ' · ' . $e['gender'];
        }
?>
      <article class="dd-entry <?= $entryClass ?><?= $isForm ? ' dd-entry-2' : '' ?>">
        <div class="dd-entry-head">
          <div class="dd-entry-title-col">
            <div class="dd-headword<?= $isForm ? ' form' : '' ?>"><?= h($headword) ?></div>
            <div class="dd-entry-meta-row">
              <?php if ($mp3): ?>
              <button class="dd-audio-btn" type="button" title="Aussprache abspielen" aria-label="Play pronunciation" data-mp3="<?= h($mp3) ?>">
                <i class="ti ti-player-play" aria-hidden="true"></i>
              </button>
              <?php endif ?>
              <?php if ($e['article']): ?><span class="dd-art <?= $g ?>"><?= h($e['article']) ?></span><?php endif ?>
              <span class="dd-pos-pill"><?= h($posLabel) ?></span>
              <?php if ($e['ipa']): ?><span class="dd-ipa"><?= h($e['ipa']) ?></span><?php endif ?>
              <?php if ($e['hyphen']): ?><span class="dd-hyphen"><?= h($e['hyphen']) ?></span><?php endif ?>
            </div>
          </div>
        </div>

        <?php if ($e['senses'] || $e['synonyms'] || $e['antonyms']): ?>
        <div class="dd-entry-body">
          <?php if ($e['senses']): ?>
          <ol class="dd-sense-list">
            <?php foreach ($e['senses'] as $j => $sense): ?>
            <li class="dd-sense">
              <span class="dd-sense-num"><?= $j + 1 ?></span>
              <div>
                <div class="dd-def"><?= h($sense['definition']) ?></div>
                <?php if (!empty($sense['simple_de'])): ?>
                  <div class="dd-simple"><?= h($sense['simple_de']) ?></div>
                <?php endif ?>
                <?php if (!empty($sense['english'])): ?>
                  <div class="dd-gloss"><?= h(implode(', ', $sense['english'])) ?></div>
                <?php endif ?>
                <?php if (!empty($sense['examples'])): $n = count($sense['examples']); ?>
                <details class="dd-ex">
                  <summary><span class="tri">▸</span> <?= $n === 1 ? '1 example' : "$n examples" ?></summary>
                  <div class="dd-examples">
                    <?php foreach ($sense['examples'] as $ex): ?>
                    <div class="dd-example">
                      <div class="dd-ex-de"><?= h($ex['text']) ?></div>
                      <?php if (!empty($ex['en'])): ?>
                        <div class="dd-ex-en"><?= h($ex['en']) ?></div>
                      <?php elseif (!empty($ex['ref'])): ?>
                        <div class="dd-ex-en"><?= h($ex['ref']) ?></div>
                      <?php endif ?>
                    </div>
                    <?php endforeach ?>
                  </div>
                </details>
                <?php endif ?>
              </div>
            </li>
            <?php endforeach ?>
          </ol>
          <?php endif ?>

          <?php if ($e['synonyms'] || $e['antonyms']): ?>
          <div class="dd-meta-row">
            <?php if ($e['synonyms']): ?>
            <div class="dd-meta-group">
              <span class="dd-meta-label">Syn</span>
              <?php foreach ($e['synonyms'] as $s): ?><a class="dd-pill" href="/?q=<?= h(urlencode($s)) ?>"><?= h($s) ?></a><?php endforeach ?>
            </div>
            <?php endif ?>
            <?php if ($e['antonyms']): ?>
            <div class="dd-meta-group">
              <span class="dd-meta-label">Ant</span>
              <?php foreach ($e['antonyms'] as $a): ?><a class="dd-pill" href="/?q=<?= h(urlencode($a)) ?>"><?= h($a) ?></a><?php endforeach ?>
            </div>
            <?php endif ?>
          </div>
          <?php endif ?>
        </div>
        <?php endif ?>
      </article>

<?php endforeach; ?>
<?php endif; ?>

    </main>
  </div>

<script>
// audio playback
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.dd-audio-btn');
  if (!btn) return;
  e.preventDefault();
  const audio = new Audio(btn.dataset.mp3);
  audio.play().catch(() => {});
});

// close the About dropdown on outside click / Escape
(() => {
  const about = document.querySelector('.dd-about');
  if (!about) return;
  document.addEventListener('click', (e) => { if (!about.contains(e.target)) about.removeAttribute('open'); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') about.removeAttribute('open'); });
})();

// recent searches, backed by localStorage
(() => {
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // --- recent searches ---
  const RECENT = 'dedict:recent';
  const read = (k) => { try { return JSON.parse(localStorage.getItem(k)) || []; } catch { return []; } };

  const current = <?= $current ? json_encode($current, JSON_UNESCAPED_UNICODE) : 'null' ?>;
  let recent = read(RECENT);
  if (current && current.word) {
    recent = recent.filter((x) => x.word.toLowerCase() !== current.word.toLowerCase());
    recent.unshift(current);
    recent = recent.slice(0, 8);
    localStorage.setItem(RECENT, JSON.stringify(recent));
  }

  const box = document.getElementById('dd-recent');
  if (box) {
    if (!recent.length) {
      box.innerHTML = '<div class="dd-recent-empty">No searches yet.</div>';
    } else {
      box.innerHTML = recent.map((x) => {
        const art = x.art ? `<span class="dd-recent-art ${x.cls || ''}">${esc(x.art)}</span>` : '';
        return `<a class="dd-recent-item" href="/?q=${encodeURIComponent(x.word)}"><span class="dd-recent-word">${esc(x.word)}</span>${art}</a>`;
      }).join('');
    }
  }
})();
</script>

</body>
</html>
