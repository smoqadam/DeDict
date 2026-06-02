<?php

$dbPath = 'data/dedict.db';
$db = new SQLite3($dbPath);
$db->busyTimeout(3000);

function mb_ucfirst_safe(string $s): string {
    $first = mb_substr($s, 0, 1);
    $rest  = mb_substr($s, 1);
    return mb_strtoupper($first) . $rest;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function posGenderLabel(string $pos, ?string $gender): string {
    $parts = [ucfirst($pos)];
    if ($gender && $gender !== 'none') {
        $map = ['masculine' => 'm.', 'feminine' => 'f.', 'neuter' => 'n.'];
        $parts[] = $map[$gender] ?? $gender;
    }
    return implode(' · ', $parts);
}

$q = trim($_GET['q'] ?? '');
$entries = [];

if ($q !== '') {
    $qLower = mb_strtolower($q);
    $qCap   = mb_ucfirst_safe($qLower);
    $cands  = array_unique([$qLower, $q, $qCap]);

     // first de_words
    $seenIds = [];
    $stmtW = $db->prepare("SELECT * FROM de_words WHERE word = :w");
    foreach ($cands as $c) {
        $stmtW->bindValue(':w', $c);
        $res = $stmtW->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if (in_array($row['id'], $seenIds)) continue;
            $seenIds[] = $row['id'];
            $entries[] = buildEntry($row);
        }
    }

    // other forms from de_forms 
    $stmtF = $db->prepare(
        "SELECT f.word AS form_word,
                w.id, w.word, w.pos, w.gender, w.ipa, w.audio,
                w.hyphenation, w.senses, w.synonyms, w.antonyms, w.tags
         FROM de_forms f
         JOIN de_words w ON w.id = f.lemma_id
         WHERE f.word = :w"
    );
    foreach ($cands as $c) {
        $stmtF->bindValue(':w', $c);
        $res = $stmtF->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if (in_array($row['id'], $seenIds)) continue;
            $seenIds[] = $row['id'];
            $entry = buildEntry($row);
            $entry['formWord'] = $row['form_word'];
            $entries[] = $entry;
        }
    }
}

function buildEntry(array $row): array {
    return [
        'id'       => $row['id'],
        'word'     => $row['word'],
        'formWord' => null,
        'pos'      => $row['pos'],
        'gender'   => $row['gender'],
        'ipa'      => $row['ipa'],
        'audio'    => json_decode($row['audio'], true) ?: [],
        'hyphen'   => $row['hyphenation'],
        'senses'   => json_decode($row['senses'], true) ?: [],
        'synonyms' => json_decode($row['synonyms'], true) ?: [],
        'antonyms' => json_decode($row['antonyms'], true) ?: [],
    ];
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $entries ? h($entries[0]['word']) . ' – ' : '' ?>German Dictionary</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Source+Serif+4:ital,opsz,wght@0,8..60,300;0,8..60,400;1,8..60,300&display=swap" rel="stylesheet">
<style>
  :root {
    --ink:       #1a1410;
    --ink-mid:   #4a3f35;
    --ink-faint: #9e8e80;
    --paper:     #f7f3ee;
    --paper-alt: #efe9e0;
    --rule:      #d6cfc5;
    --accent:    #8b3a2a;
    --accent-lo: #c97b69;
    --tag-bg:    #e8e0d4;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--paper);
    color: var(--ink);
    font-family: 'Source Serif 4', Georgia, serif;
    font-size: 15px;
    line-height: 1.55;
    min-height: 100vh;
    padding: 2.5rem 1rem;
  }

  .search-wrap {
    max-width: 680px;
    margin: 0 auto 1.5rem;
    display: flex;
    gap: 0.5rem;
  }

  .search-wrap input[type=search] {
    flex: 1;
    font-family: 'Source Serif 4', Georgia, serif;
    font-size: 1rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--rule);
    background: #fff;
    color: var(--ink);
    outline: none;
    border-radius: 2px;
    transition: border-color 0.15s;
  }
  .search-wrap input[type=search]:focus { border-color: var(--accent-lo); }

  .search-wrap button {
    font-family: inherit;
    font-size: 0.85rem;
    padding: 0.5rem 1.1rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 2px;
    cursor: pointer;
    letter-spacing: 0.04em;
    transition: background 0.15s;
  }
  .search-wrap button:hover { background: var(--ink-mid); }

  .entry {
    max-width: 680px;
    margin: 0 auto 1rem;
    background: #fff;
    border: 1px solid var(--rule);
    border-top: 3px solid var(--accent);
    padding: 1.5rem 1.75rem 1.75rem;
  }

  .entry-head {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--rule);
  }

  .headword {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 2rem;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: -0.01em;
    line-height: 1.1;
  }

  .lemma-label {
    font-size: 0.78rem;
    font-style: italic;
    color: var(--ink-faint);
  }

  .pos-gender {
    font-size: 0.78rem;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--accent);
    align-self: center;
    border: 1px solid var(--accent-lo);
    padding: 0.15em 0.55em;
    border-radius: 2px;
  }

  .phon-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.55rem;
    flex-wrap: wrap;
  }

  .ipa {
    font-size: 0.9rem;
    color: var(--ink-mid);
    font-style: italic;
    letter-spacing: 0.02em;
  }

  .hyphen { font-size: 0.8rem; color: var(--ink-faint); }
  .hyphen::before { content: "· "; }

  .audio-btn {
    background: none;
    border: 1px solid var(--rule);
    border-radius: 50%;
    width: 28px; height: 28px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--ink-mid);
    transition: background 0.15s, color 0.15s, border-color 0.15s;
    flex-shrink: 0;
  }
  .audio-btn:hover { background: var(--paper-alt); border-color: var(--accent-lo); color: var(--accent); }
  .audio-btn svg { width: 13px; height: 13px; }

  .senses { margin-top: 1rem; list-style: none; }

  .sense {
    display: grid;
    grid-template-columns: 1.4rem 1fr;
    gap: 0 0.6rem;
    padding: 0.6rem 0;
    border-bottom: 1px dashed var(--rule);
  }
  .sense:last-child { border-bottom: none; }

  .sense-num {
    font-family: 'Playfair Display', serif;
    font-size: 0.85rem;
    color: var(--accent);
    font-weight: 700;
    line-height: 1.55;
  }

  .def-de { font-size: 0.92rem; color: var(--ink); }
  .def-en { font-size: 0.82rem; color: var(--ink-mid); margin-top: 0.1rem; }
  .def-en::before { content: "→ "; color: var(--accent-lo); }

  .examples { margin-top: 0.5rem; }

  .example {
    background: var(--paper-alt);
    border-left: 2px solid var(--rule);
    padding: 0.4rem 0.65rem;
    margin-top: 0.35rem;
    font-size: 0.83rem;
  }

  .example-text { font-style: italic; color: var(--ink-mid); line-height: 1.4; }
  .example-ref { font-size: 0.74rem; color: var(--ink-faint); margin-top: 0.2rem; }

  .ex-toggle {
    background: none; border: none; cursor: pointer;
    font-size: 0.75rem; color: var(--ink-faint);
    padding: 0.15rem 0; margin-top: 0.3rem;
    display: flex; align-items: center; gap: 0.3em;
    font-family: inherit;
    transition: color 0.15s;
  }
  .ex-toggle:hover { color: var(--accent); }
  .ex-toggle .arrow { font-size: 0.65rem; display: inline-block; transition: transform 0.2s; }
  .ex-toggle.open .arrow { transform: rotate(90deg); }
  .ex-toggle.open { color: var(--ink-mid); }
  .examples[hidden] { display: none; }

  .meta-row {
    margin-top: 1rem;
    padding-top: 0.7rem;
    border-top: 1px solid var(--rule);
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
  }

  .meta-group { display: flex; align-items: baseline; gap: 0.4rem; flex-wrap: wrap; }

  .meta-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ink-faint);
    white-space: nowrap;
  }

  .pill {
    background: var(--tag-bg);
    color: var(--ink-mid);
    font-size: 0.77rem;
    padding: 0.1em 0.5em;
    border-radius: 2px;
  }

  .notice {
    max-width: 680px;
    margin: 0 auto;
    color: var(--ink-faint);
    font-size: 0.9rem;
    padding: 1rem 0;
  }
</style>
</head>
<body>

<form class="search-wrap" method="get" action="">
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search a word…" autofocus autocomplete="off" spellcheck="false">
  <button type="submit">Search</button>
</form>

<?php if ($q === ''): ?>
  <p class="notice">Enter a German word above.</p>

<?php elseif (!$entries): ?>
  <p class="notice">No entry found for <strong><?= h($q) ?></strong>.</p>

<?php else: foreach ($entries as $e): ?>

<article class="entry">
  <header class="entry-head">
    <span class="headword"><?= h($e['formWord'] ?: $e['word']) ?></span>
    <?php if ($e['formWord']): ?>
      <span class="lemma-label">← <?= h($e['word']) ?></span>
    <?php endif ?>
    <span class="pos-gender"><?= h(posGenderLabel($e['pos'], $e['gender'])) ?></span>
  </header>

  <div class="phon-row">
    <?php if (!empty($e['audio'][0]['mp3_url'])): ?>
    <button class="audio-btn" title="Play pronunciation"
            onclick="playAudio(this, <?= json_encode($e['audio'][0]['mp3_url']) ?>)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
           stroke-linecap="round" stroke-linejoin="round">
        <polygon points="5 3 19 12 5 21 5 3"/>
      </svg>
    </button>
    <?php endif ?>
    <?php if ($e['ipa']): ?>
      <span class="ipa"><?= h($e['ipa']) ?></span>
    <?php endif ?>
    <?php if ($e['hyphen']): ?>
      <span class="hyphen"><?= h($e['hyphen']) ?></span>
    <?php endif ?>
  </div>

  <?php if ($e['senses']): ?>
  <ol class="senses">
    <?php foreach ($e['senses'] as $i => $sense): ?>
    <li class="sense">
      <span class="sense-num"><?= $i + 1 ?></span>
      <div class="sense-body">
        <div class="def-de"><?= h($sense['definition']) ?></div>
        <?php if (!empty($sense['english'])): ?>
          <div class="def-en"><?= h(implode(', ', $sense['english'])) ?></div>
        <?php endif ?>
        <?php if (!empty($sense['examples'])): ?>
        <button class="ex-toggle" onclick="toggleExamples(this)">
          <span class="arrow">▸</span>
          <?= count($sense['examples']) === 1 ? '1 example' : count($sense['examples']) . ' examples' ?>
        </button>
        <div class="examples" hidden>
          <?php foreach ($sense['examples'] as $ex): ?>
          <blockquote class="example">
            <p class="example-text"><?= h($ex['text']) ?></p>
            <?php if (!empty($ex['ref'])): ?>
            <p class="example-ref"><?= h($ex['ref']) ?></p>
            <?php endif ?>
          </blockquote>
          <?php endforeach ?>
        </div>
        <?php endif ?>
      </div>
    </li>
    <?php endforeach ?>
  </ol>
  <?php endif ?>

  <?php if ($e['synonyms'] || $e['antonyms']): ?>
  <footer class="meta-row">
    <?php if ($e['synonyms']): ?>
    <div class="meta-group">
      <span class="meta-label">Syn</span>
      <?php foreach ($e['synonyms'] as $s): ?>
      <span class="pill"><?= h($s) ?></span>
      <?php endforeach ?>
    </div>
    <?php endif ?>
    <?php if ($e['antonyms']): ?>
    <div class="meta-group">
      <span class="meta-label">Ant</span>
      <?php foreach ($e['antonyms'] as $a): ?>
      <span class="pill"><?= h($a) ?></span>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </footer>
  <?php endif ?>
</article>

<?php endforeach; endif; ?>

<script>
function toggleExamples(btn) {
  const examples = btn.nextElementSibling;
  const hidden = examples.hasAttribute('hidden');
  hidden ? examples.removeAttribute('hidden') : examples.setAttribute('hidden', '');
  btn.classList.toggle('open', hidden);
}

function playAudio(btn, url) {
  const audio = new Audio(url);
  audio.play().catch(() => {});
  btn.style.color = 'var(--accent)';
  audio.addEventListener('ended', () => { btn.style.color = ''; });
}
</script>

</body>
</html>
