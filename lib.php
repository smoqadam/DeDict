<?php

function mb_ucfirst_safe(string $s): string {
    $first = mb_substr($s, 0, 1);
    $rest  = mb_substr($s, 1);
    return mb_strtoupper($first) . $rest;
}

function buildEntry(array $row): array {
    return [
        'id'       => (int)$row['id'],
        'word'     => $row['word'],
        'formWord' => null,
        'pos'      => $row['pos'],
        'gender'   => $row['gender'],
        'article'  => article($row['gender']),
        'ipa'      => $row['ipa'],
        'audio'    => json_decode($row['audio'], true) ?: [],
        'hyphen'   => decodeUnicodeEscapes($row['hyphenation']),
        'senses'   => [],  // attached by lookup() from de_senses
        'synonyms' => json_decode($row['synonyms'], true) ?: [],
        'antonyms' => json_decode($row['antonyms'], true) ?: [],
    ];
}

function article(?string $gender): ?string {
    if (!$gender) return null;
    return ['masculine' => 'der', 'feminine' => 'die', 'neuter' => 'das'][$gender] ?? null;
}

function lookup(string $q, SQLite3 $db): array {
    $qLower = mb_strtolower($q);
    $qCap   = mb_ucfirst_safe($qLower);
    $cands  = array_values(array_unique([$qLower, $q, $qCap]));
    $results = [];
    $seenIds = [];

    $stmtW = $db->prepare("SELECT * FROM de_words WHERE word = :w");
    foreach ($cands as $c) {
        $stmtW->bindValue(':w', $c);
        $res = $stmtW->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if (in_array($row['id'], $seenIds)) continue;
            $seenIds[] = $row['id'];
            $results[] = buildEntry($row);
        }
    }

    $stmtF = $db->prepare(
        "SELECT f.word AS form_word,
                w.id, w.word, w.pos, w.gender, w.ipa, w.audio,
                w.hyphenation, w.synonyms, w.antonyms, w.tags
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
            $results[] = $entry;
        }
    }

    attachSenses($db, $results);

    return $results;
}

function attachSenses(SQLite3 $db, array &$results): void {
    if (!$results) return;

    $ids = array_column($results, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT word_id, definition, simple_de, en_translation, examples
         FROM de_senses WHERE word_id IN ($ph) ORDER BY word_id, idx"
    );
    foreach ($ids as $i => $id) {
        $stmt->bindValue($i + 1, $id, SQLITE3_INTEGER);
    }

    $byWord = [];
    $res = $stmt->execute();
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $byWord[$r['word_id']][] = [
            'definition' => $r['definition'],
            'simple_de'  => $r['simple_de'],
            'english'    => json_decode($r['en_translation'], true) ?: [],
            'examples'   => json_decode($r['examples'], true) ?: [],
        ];
    }

    foreach ($results as &$e) {
        $e['senses'] = $byWord[$e['id']] ?? [];
    }
    unset($e);
}

// Some fields (e.g. hyphenation) were stored with literal \uXXXX escapes
// rather than the decoded character. Turn them back into real text.
function decodeUnicodeEscapes(?string $s): ?string {
    if ($s === null || $s === '' || strpos($s, '\\u') === false) {
        return $s;
    }
    $decoded = json_decode('"' . str_replace('"', '\\"', $s) . '"');
    return is_string($decoded) ? $decoded : $s;
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
