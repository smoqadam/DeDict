<?php


$src = 'kaikki.org-dictionary-Deutsch.jsonl';
$dbPath = '../data/dedict.db';
if (!file_exists($dbPath)) {
   touch($dbPath); 
}

$dir = dirname($dbPath);

$db = new SQLite3($dbPath);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA synchronous=OFF');

$db->exec("
    DROP TABLE IF EXISTS de_senses;
    DROP TABLE IF EXISTS de_forms;
    DROP TABLE IF EXISTS de_words;

    CREATE TABLE de_words (
        id INTEGER PRIMARY KEY,
        word TEXT NOT NULL,
        pos TEXT NOT NULL,
        gender TEXT,
        ipa TEXT,
        audio TEXT,
        hyphenation TEXT,
        synonyms TEXT,
        antonyms TEXT,
        tags TEXT,
        UNIQUE(word, pos)
    );

    CREATE TABLE de_senses (
        id INTEGER PRIMARY KEY,
        word_id INTEGER NOT NULL REFERENCES de_words(id),
        idx INTEGER NOT NULL,
        definition TEXT NOT NULL,
        simple_de TEXT,
        en_translation TEXT,
        examples TEXT
    );

    CREATE TABLE de_forms (
        id INTEGER PRIMARY KEY,
        word TEXT NOT NULL,
        lemma_id INTEGER NOT NULL REFERENCES de_words(id),
        descriptions TEXT
    );

    CREATE INDEX idx_de_words_word ON de_words(word);
    CREATE INDEX idx_de_senses_word ON de_senses(word_id);
    CREATE INDEX idx_de_forms_word ON de_forms(word);
    CREATE INDEX idx_de_forms_lemma ON de_forms(lemma_id);
");

$insertWord = $db->prepare(
    "INSERT OR IGNORE INTO de_words
     (word, pos, gender, ipa, audio, hyphenation, synonyms, antonyms, tags)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

$insertSense = $db->prepare(
    "INSERT INTO de_senses
     (word_id, idx, definition, simple_de, en_translation, examples)
     VALUES (?, ?, ?, ?, ?, ?)"
);

$insertForm = $db->prepare(
    "INSERT OR IGNORE INTO de_forms (word, lemma_id, descriptions) VALUES (?, ?, ?)"
);

function normalize(string $word): string {
    return mb_strtolower(trim($word));
}

function gender(array $entry): ?string {
    foreach ($entry['tags'] ?? [] as $tag) {
        if (in_array($tag, ['masculine', 'feminine', 'neuter'], true)) {
            return $tag;
        }
    }
    return null;
}

function ipa(array $entry): ?string {
    foreach ($entry['sounds'] ?? [] as $s) {
        if (!empty($s['ipa'])) {
            return $s['ipa'];
        }
    }
    return null;
}

function audio(array $entry): string {
    $out = [];
    foreach ($entry['sounds'] ?? [] as $s) {
        if (!empty($s['ogg_url'])) {
            $out[] = ['ogg_url' => $s['ogg_url'], 'mp3_url' => $s['mp3_url'] ?? null];
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

function hyphenation(array $entry): ?string {
    $hyphens = $entry['hyphenations'] ?? [];
    if (!empty($hyphens)) {
        $parts = $hyphens[0]['parts'] ?? [];
        if (!empty($parts)) {
            return implode("\u00b7", $parts);
        }
    }
    return null;
}

function sensesList(array $entry): array {
    $out = [];
    foreach ($entry['senses'] ?? [] as $sense) {
        $tags = $sense['tags'] ?? [];
        if (in_array('form-of', $tags, true)) continue;

        // glosses can be a parent->refinement chain that together form one
        // definition; join them rather than dropping all but the first.
        $glosses = array_values(array_filter(
            array_map('trim', $sense['glosses'] ?? []),
            fn($g) => $g !== ''
        ));
        if (empty($glosses)) continue;
        if (str_ends_with($glosses[0], ':')) continue;  // label-only sense

        $eng = englishTranslations($entry, $sense['sense_index'] ?? null);
        $examples = [];
        foreach ($sense['examples'] ?? [] as $ex) {
            $examples[] = ['text' => $ex['text'] ?? '', 'ref' => $ex['ref'] ?? null];
        }
        $out[] = [
            'definition' => implode(' ', $glosses),
            'english' => $eng,
            'examples' => $examples,
        ];
    }
    return $out;
}

function englishTranslations(array $entry, ?string $senseIndex): array {
    if ($senseIndex === null) return [];
    $out = [];
    foreach ($entry['translations'] ?? [] as $t) {
        if (($t['lang_code'] ?? '') !== 'en' || ($t['sense_index'] ?? null) !== $senseIndex) {
            continue;
        }
        // kaikki strips German qualifiers into raw_tags and leaves empty "( )"
        // behind, and packs several alternatives into one comma-separated word.
        $w = preg_replace('/\s*\(\s*\)/', '', $t['word'] ?? '');
        foreach (explode(',', $w) as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $out, true)) {
                $out[] = $part;
            }
        }
    }
    return $out;
}

function related(array $entry, string ...$fields): string {
    $out = [];
    foreach ($fields as $field) {
        foreach ($entry[$field] ?? [] as $item) {
            $w = $item['word'] ?? '';
            if ($w !== '' && !in_array($w, $out, true)) {
                $out[] = $w;
            }
        }
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

function formOfLemma(array $entry): ?string {
    foreach ($entry['senses'] ?? [] as $sense) {
        $tags = $sense['tags'] ?? [];
        if (in_array('form-of', $tags, true)) {
            $fo = $sense['form_of'] ?? [];
            if (!empty($fo)) {
                return $fo[0]['word'] ?? null;
            }
        }
    }
    return null;
}

function formDescriptions(array $entry): string {
    $descs = [];
    foreach ($entry['senses'] ?? [] as $sense) {
        $tags = $sense['tags'] ?? [];
        if (in_array('form-of', $tags, true)) {
            $gloss = $sense['glosses'][0] ?? null;
            if ($gloss !== null) {
                $clean = trim(rtrim(trim($gloss), '.'));
                if ($clean !== '') {
                    $descs[] = $clean;
                }
            }
        }
    }
    return json_encode($descs, JSON_UNESCAPED_UNICODE);
}

echo "pass 1: inserting lemma entries …\n";

$nWords = 0;

$batchW = [];

$handle = fopen($src, 'r');
if (!$handle) {
    fwrite(STDERR, "cannot open: $src\n");
    exit(1);
}

while (($line = fgets($handle)) !== false) {
    $entry = json_decode($line, true);
    if (!is_array($entry)) continue;
    if (formOfLemma($entry) !== null) continue;

    $word = $entry['word'] ?? '';
    if ($word === '') continue;

    $pos = $entry['pos'] ?? 'unknown';
    $batchW[] = [
        $word,
        $pos,
        gender($entry),
        ipa($entry),
        audio($entry),
        hyphenation($entry),
        related($entry, 'synonyms'),
        related($entry, 'antonyms'),
        json_encode($entry['tags'] ?? [], JSON_UNESCAPED_UNICODE),
    ];
    $nWords++;

    if (count($batchW) >= 5000) {
        flushWords($db, $insertWord, $batchW);
        $batchW = [];
    }
}
if (!empty($batchW)) {
    flushWords($db, $insertWord, $batchW);
}
fclose($handle);
echo "  $nWords lemma entries\n";

// Build lemma_map: (normalized_word, pos) => id
echo "  building lemma lookup …\n";
$lemmaMap = [];
$wordMap = [];
$result = $db->query("SELECT id, word, pos FROM de_words");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $nw = normalize($row['word']);
    $lemmaMap[$nw . "\0" . $row['pos']] = $row['id'];
    $wordMap[$nw][] = $row['id'];
}
echo '  ' . count($lemmaMap) . " entries in map\n";

////////
echo "pass 1b: inserting senses …\n";
$nSenses = 0;
$seenWordIds = [];
$batchS = [];
$handle = fopen($src, 'r');
if (!$handle) {
    fwrite(STDERR, "cannot open: $src\n");
    exit(1);
}

while (($line = fgets($handle)) !== false) {
    $entry = json_decode($line, true);
    if (!is_array($entry)) continue;
    if (formOfLemma($entry) !== null) continue;

    $word = $entry['word'] ?? '';
    if ($word === '') continue;

    $pos = $entry['pos'] ?? 'unknown';
    $nw = normalize($word);
    $wid = $lemmaMap[$nw . "\0" . $pos] ?? ($wordMap[$nw][0] ?? null);
    if ($wid === null) continue;

    // INSERT OR IGNORE kept only the first de_words row per (word, pos);
    // attach senses to that row once, from the first matching entry.
    if (isset($seenWordIds[$wid])) continue;
    $seenWordIds[$wid] = true;

    foreach (sensesList($entry) as $idx => $s) {
        $batchS[] = [
            $wid,
            $idx,
            $s['definition'],
            null,
            json_encode($s['english'], JSON_UNESCAPED_UNICODE),
            json_encode($s['examples'], JSON_UNESCAPED_UNICODE),
        ];
        $nSenses++;
    }

    if (count($batchS) >= 5000) {
        flushSenses($db, $insertSense, $batchS);
        $batchS = [];
    }
}
if (!empty($batchS)) {
    flushSenses($db, $insertSense, $batchS);
}
fclose($handle);
echo "  $nSenses sense entries\n";



////////
echo "pass 2: inserting inflected-form entries …\n";
$nForms = 0;
$skipped = 0;
$saved = 0;
$batchF = [];
$handle = fopen($src, 'r');
if (!$handle) {
    fwrite(STDERR, "cannot open: $src\n");
    exit(1);
}

while (($line = fgets($handle)) !== false) {
    $entry = json_decode($line, true);
    if (!is_array($entry)) continue;

    $lemmaWord = formOfLemma($entry);
    if ($lemmaWord === null) continue;

    $word = $entry['word'] ?? '';
    if ($word === '') continue;

    $pos = $entry['pos'] ?? 'unknown';
    $key = normalize($lemmaWord) . "\0" . $pos;
    $lemmaId = $lemmaMap[$key] ?? null;

    if ($lemmaId === null) {
        $nw = normalize($lemmaWord);
        if (isset($wordMap[$nw])) {
            $lemmaId = $wordMap[$nw][0];
            $saved++;
        } else {
            $skipped++;
            continue;
        }
    }

    $descs = formDescriptions($entry);
    $batchF[] = [$word, $lemmaId, $descs];
    $nForms++;

    if (count($batchF) >= 5000) {
        flushForms($db, $insertForm, $batchF);
        $batchF = [];
    }
}
if (!empty($batchF)) {
    flushForms($db, $insertForm, $batchF);
}
fclose($handle);

echo "  $nForms form entries, $skipped skipped (lemma not found), $saved recovered via word-only match\n";

$db->close();
echo "done → $dbPath\n";

function flushWords(SQLite3 $db, SQLite3Stmt $stmt, array $batch): void {
    $db->exec('BEGIN TRANSACTION');
    foreach ($batch as $row) {
        $stmt->reset();
        for ($i = 0; $i < 9; $i++) {
            $stmt->bindValue($i + 1, $row[$i]);
        }
        $stmt->execute();
    }
    $db->exec('COMMIT');
}

function flushSenses(SQLite3 $db, SQLite3Stmt $stmt, array $batch): void {
    $db->exec('BEGIN TRANSACTION');
    foreach ($batch as $row) {
        $stmt->reset();
        for ($i = 0; $i < 6; $i++) {
            $stmt->bindValue($i + 1, $row[$i]);
        }
        $stmt->execute();
    }
    $db->exec('COMMIT');
}

function flushForms(SQLite3 $db, SQLite3Stmt $stmt, array $batch): void {
    $db->exec('BEGIN TRANSACTION');
    foreach ($batch as $row) {
        $stmt->reset();
        $stmt->bindValue(1, $row[0]);
        $stmt->bindValue(2, $row[1]);
        $stmt->bindValue(3, $row[2]);
        $stmt->execute();
    }
    $db->exec('COMMIT');
}
