<?php
// Served via router.php, which provides $db and $q.
$entries = $q !== '' ? lookup($q, $db) : [];

// der/die/das is the one place we allow color — learners memorize gender by it.
$genderClass = fn(?string $g) => in_array($g, ['masculine', 'feminine', 'neuter'], true) ? "g-$g" : 'g-none';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $entries ? h($entries[0]['word']) . ' — ' : '' ?>DeDict</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,400&family=Newsreader:ital,opsz,wght@0,6..72,300;0,6..72,400;0,6..72,500;1,6..72,400&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --ink:       #1c1a17;
    --ink-mid:   #5b554c;
    --ink-faint: #9c948857;
    --ink-soft:  #8b8378;
    --paper:     #f3efe7;
    --card:      #fbf9f4;
    --rule:      #e2dcd1;
    --rule-soft: #ece6db;

    /* the only hues in the whole page — grammatical gender */
    --masculine: #2563a8;
    --feminine:  #b23a48;
    --neuter:    #3a8a5f;

    --g: var(--ink-soft);   /* per-entry gender accent, overridden below */

    --space: 1.5rem;
    --radius: 4px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html { -webkit-text-size-adjust: 100%; }

  body {
    background: var(--paper);
    background-image:
      radial-gradient(circle at 18% 12%, #fdfbf6 0, transparent 55%),
      radial-gradient(circle at 88% 88%, #ece6da 0, transparent 60%);
    background-attachment: fixed;
    color: var(--ink);
    font-family: 'Newsreader', Georgia, serif;
    font-size: 17px;
    font-weight: 400;
    line-height: 1.5;
    min-height: 100vh;
    padding: 0 1rem 4rem;
  }

  .wrap { max-width: 640px; margin: 0 auto; }

  /* ---- masthead + search ---------------------------------------- */

  header.top {
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 1.1rem 0 0.9rem;
    margin-bottom: 1.6rem;
    background: color-mix(in srgb, var(--paper) 86%, transparent);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-bottom: 1px solid var(--rule);
  }

  .brand {
    display: flex;
    align-items: baseline;
    gap: 0.55rem;
    margin-bottom: 0.85rem;
  }
  .brand b {
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 600;
    font-size: 1.35rem;
    letter-spacing: -0.015em;
  }
  .brand small {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.66rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--ink-soft);
  }

  form.search { display: flex; gap: 0.5rem; }

  form.search input {
    flex: 1;
    font-family: 'Newsreader', Georgia, serif;
    font-size: 1.15rem;
    padding: 0.5rem 0.85rem;
    color: var(--ink);
    background: var(--card);
    border: 1px solid var(--rule);
    border-radius: var(--radius);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  form.search input::placeholder { color: var(--ink-soft); font-style: italic; }
  form.search input:focus {
    border-color: var(--ink-mid);
    box-shadow: 0 0 0 3px #1c1a1712;
  }

  form.search button {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.72rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0 1.1rem;
    color: var(--paper);
    background: var(--ink);
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: background 0.15s;
  }
  form.search button:hover { background: #000; }

  /* ---- entry (collapsible) --------------------------------------- */

  .entry {
    background: var(--card);
    border: 1px solid var(--rule);
    border-left: 3px solid var(--g);
    border-radius: var(--radius);
    margin-bottom: 0.85rem;
    overflow: hidden;
    animation: rise 0.4s cubic-bezier(.2,.7,.3,1) backwards;
  }
  @keyframes rise {
    from { opacity: 0; transform: translateY(8px); }
  }

  .entry > summary {
    list-style: none;
    cursor: pointer;
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.35rem 0.7rem;
    padding: 0.85rem 1rem;
    user-select: none;
  }
  .entry > summary::-webkit-details-marker { display: none; }
  .entry > summary:hover .headword { color: var(--g); }

  .headword {
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 600;
    font-size: 1.7rem;
    line-height: 1.05;
    letter-spacing: -0.02em;
    transition: color 0.15s;
  }

  .lemma {
    font-size: 0.85rem;
    font-style: italic;
    color: var(--ink-soft);
  }

  /* der / die / das — the colored cue */
  .gender {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.72rem;
    font-weight: 500;
    letter-spacing: 0.02em;
    padding: 0.12em 0.5em;
    border-radius: 2px;
    color: var(--g);
    background: color-mix(in srgb, var(--g) 12%, transparent);
    align-self: center;
  }

  .pos {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.66rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--ink-soft);
    align-self: center;
  }

  .chev {
    margin-left: auto;
    align-self: center;
    color: var(--ink-soft);
    font-size: 0.8rem;
    transition: transform 0.2s ease;
  }
  .entry[open] > summary .chev { transform: rotate(90deg); }

  .body {
    padding: 0 1rem 1rem;
    border-top: 1px solid var(--rule-soft);
    margin-top: -1px;
  }

  /* ---- phonetics ------------------------------------------------- */

  .phon {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    flex-wrap: wrap;
    padding: 0.7rem 0 0.2rem;
  }
  .ipa {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.82rem;
    color: var(--ink-mid);
  }
  .hyphen { font-size: 0.8rem; color: var(--ink-soft); }
  .hyphen::before { content: "·\00a0"; }

  .audio {
    width: 26px; height: 26px;
    flex-shrink: 0;
    display: grid; place-items: center;
    background: none;
    border: 1px solid var(--rule);
    border-radius: 50%;
    color: var(--ink-mid);
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
  }
  .audio:hover { color: var(--g); border-color: var(--g); background: color-mix(in srgb, var(--g) 8%, transparent); }
  .audio svg { width: 12px; height: 12px; }

  /* ---- senses ---------------------------------------------------- */

  .senses { list-style: none; margin-top: 0.6rem; }

  .sense {
    display: grid;
    grid-template-columns: 1.4rem 1fr;
    gap: 0 0.6rem;
    padding: 0.65rem 0;
    border-top: 1px dotted var(--rule);
  }
  .sense:first-child { border-top: none; }

  .num {
    font-family: 'Fraunces', serif;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--g);
    line-height: 1.6;
  }

  .def    { font-size: 1rem; line-height: 1.5; }
  .gloss  {
    font-size: 0.86rem;
    color: var(--ink-mid);
    margin-top: 0.15rem;
  }
  .gloss::before { content: "→\00a0"; color: var(--g); }

  /* nested collapsible examples */
  details.ex { margin-top: 0.45rem; }
  details.ex > summary {
    list-style: none;
    cursor: pointer;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.66rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--ink-soft);
    display: inline-flex;
    align-items: center;
    gap: 0.4em;
    padding: 0.1rem 0;
    transition: color 0.15s;
  }
  details.ex > summary::-webkit-details-marker { display: none; }
  details.ex > summary:hover { color: var(--g); }
  details.ex > summary .tri { transition: transform 0.18s; display: inline-block; }
  details.ex[open] > summary .tri { transform: rotate(90deg); }

  blockquote.example {
    border-left: 2px solid var(--rule);
    padding: 0.15rem 0 0.15rem 0.7rem;
    margin-top: 0.45rem;
  }
  .example p { font-style: italic; font-size: 0.9rem; color: var(--ink-mid); line-height: 1.45; }
  .example cite {
    display: block;
    font-style: normal;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.64rem;
    color: var(--ink-soft);
    margin-top: 0.25rem;
  }

  /* ---- synonyms / antonyms --------------------------------------- */

  .meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 1.2rem;
    margin-top: 0.9rem;
    padding-top: 0.7rem;
    border-top: 1px solid var(--rule-soft);
  }
  .meta-group { display: flex; align-items: baseline; flex-wrap: wrap; gap: 0.35rem; }
  .meta-label {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.62rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--ink-soft);
  }
  .pill {
    font-size: 0.82rem;
    color: var(--ink-mid);
    background: var(--rule-soft);
    padding: 0.08em 0.5em;
    border-radius: 2px;
    text-decoration: none;
    transition: color 0.15s, background 0.15s;
  }
  a.pill:hover {
    color: var(--g);
    background: color-mix(in srgb, var(--g) 12%, transparent);
  }

  /* ---- empty / notice states ------------------------------------- */

  .notice {
    text-align: center;
    color: var(--ink-soft);
    font-style: italic;
    font-size: 1.05rem;
    padding: 3rem 0;
  }
  .notice strong { font-style: normal; color: var(--ink); }

  .legend {
    display: flex;
    justify-content: center;
    gap: 1.4rem;
    margin-top: 2.5rem;
    font-family: 'IBM Plex Mono', monospace;
    font-size: 0.68rem;
    letter-spacing: 0.04em;
  }
  .legend span::before {
    content: "";
    display: inline-block;
    width: 0.6em; height: 0.6em;
    border-radius: 50%;
    margin-right: 0.45em;
    vertical-align: baseline;
    background: currentColor;
  }
  .legend .m { color: var(--masculine); }
  .legend .f { color: var(--feminine); }
  .legend .n { color: var(--neuter); }

  /* per-entry gender accent */
  .g-masculine { --g: var(--masculine); }
  .g-feminine  { --g: var(--feminine); }
  .g-neuter    { --g: var(--neuter); }
  .g-none      { --g: var(--ink-soft); }

  @media (prefers-reduced-motion: reduce) {
    .entry { animation: none; }
  }
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <div class="brand">
      <b>DeDict</b>
      <small>Deutsches Wörterbuch</small>
    </div>
    <form class="search" method="get" action="/">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="Ein Wort suchen…"
             autofocus autocomplete="off" spellcheck="false">
      <button type="submit">Suchen</button>
    </form>
  </header>

<?php if ($q === ''): ?>

  <p class="notice">Search a German word to begin.</p>
  <div class="legend">
    <span class="m">der</span>
    <span class="f">die</span>
    <span class="n">das</span>
  </div>

<?php elseif (!$entries): ?>

  <p class="notice">No entry for <strong><?= h($q) ?></strong>.</p>

<?php else: foreach ($entries as $i => $e): ?>

  <details class="entry <?= $genderClass($e['gender']) ?>"<?= $i === 0 ? ' open' : '' ?>
           style="animation-delay: <?= min($i, 8) * 0.04 ?>s">
    <summary>
      <span class="headword"><?= h($e['formWord'] ?: $e['word']) ?></span>
      <?php if ($e['formWord']): ?>
        <span class="lemma">← <?= h($e['word']) ?></span>
      <?php endif ?>
      <?php if ($e['article']): ?>
        <span class="gender"><?= h($e['article']) ?></span>
      <?php endif ?>
      <span class="pos"><?= h($e['pos']) ?></span>
      <span class="chev">▸</span>
    </summary>

    <div class="body">
      <?php if (!empty($e['audio'][0]['mp3_url']) || $e['ipa'] || $e['hyphen']): ?>
      <div class="phon">
        <?php if (!empty($e['audio'][0]['mp3_url'])): ?>
        <button class="audio" type="button" title="Aussprache abspielen"
                data-mp3="<?= h($e['audio'][0]['mp3_url']) ?>">
          <svg viewBox="0 0 24 24" fill="currentColor"><polygon points="6 4 20 12 6 20 6 4"/></svg>
        </button>
        <?php endif ?>
        <?php if ($e['ipa']): ?><span class="ipa"><?= h($e['ipa']) ?></span><?php endif ?>
        <?php if ($e['hyphen']): ?><span class="hyphen"><?= h($e['hyphen']) ?></span><?php endif ?>
      </div>
      <?php endif ?>

      <?php if ($e['senses']): ?>
      <ol class="senses">
        <?php foreach ($e['senses'] as $j => $sense): ?>
        <li class="sense">
          <span class="num"><?= $j + 1 ?></span>
          <div>
            <div class="def"><?= h($sense['definition']) ?></div>
            <?php if (!empty($sense['english'])): ?>
              <div class="gloss"><?= h(implode(', ', $sense['english'])) ?></div>
            <?php endif ?>
            <?php if (!empty($sense['examples'])): $n = count($sense['examples']); ?>
            <details class="ex">
              <summary><span class="tri">▸</span><?= $n === 1 ? '1 example' : "$n examples" ?></summary>
              <?php foreach ($sense['examples'] as $ex): ?>
              <blockquote class="example">
                <p><?= h($ex['text']) ?></p>
                <?php if (!empty($ex['ref'])): ?><cite><?= h($ex['ref']) ?></cite><?php endif ?>
              </blockquote>
              <?php endforeach ?>
            </details>
            <?php endif ?>
          </div>
        </li>
        <?php endforeach ?>
      </ol>
      <?php endif ?>

      <?php if ($e['synonyms'] || $e['antonyms']): ?>
      <div class="meta">
        <?php if ($e['synonyms']): ?>
        <div class="meta-group">
          <span class="meta-label">Syn</span>
          <?php foreach ($e['synonyms'] as $s): ?><a class="pill" href="/?q=<?= h(urlencode($s)) ?>"><?= h($s) ?></a><?php endforeach ?>
        </div>
        <?php endif ?>
        <?php if ($e['antonyms']): ?>
        <div class="meta-group">
          <span class="meta-label">Ant</span>
          <?php foreach ($e['antonyms'] as $a): ?><a class="pill" href="/?q=<?= h(urlencode($a)) ?>"><?= h($a) ?></a><?php endforeach ?>
        </div>
        <?php endif ?>
      </div>
      <?php endif ?>
    </div>
  </details>

<?php endforeach; endif; ?>

</div>

<script>
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.audio');
  if (!btn) return;
  e.preventDefault();
  const audio = new Audio(btn.dataset.mp3);
  audio.play().catch(() => {});
  btn.style.color = 'var(--g)';
  audio.addEventListener('ended', () => { btn.style.color = ''; });
});
</script>

</body>
</html>
