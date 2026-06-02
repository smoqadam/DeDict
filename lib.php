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
        'hyphen'   => $row['hyphenation'],
        'senses'   => json_decode($row['senses'], true) ?: [],
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
            $results[] = $entry;
        }
    }

    return $results;
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
