<?php

// On-demand LLM enrichment of dictionary senses.
//
// The German `definition` from Wiktionary is authoritative and is NEVER
// changed — it is the anchor that keeps the model from hallucinating. For each
// sense we ask the model only to (a) translate that definition into natural
// English, (b) restate it in simple German for learners, and (c) give one or
// two clear example sentences. A non-empty `simple_de` marks a sense as done,
// so each word is enriched at most once (the first time it is looked up).
//
// Everything here degrades gracefully: no API key, a network error, a non-200,
// or unparseable output all leave the row as-is and return the original data.
// The next lookup of the same word simply tries again.

const OPENAI_MODEL = 'gpt-5-mini';
const OPENAI_URL   = 'https://api.openai.com/v1/chat/completions';
const OPENAI_TIMEOUT = 30;

function openaiKey(): ?string {
    loadDotEnv();
    $key = getenv('OPENAI_API_KEY');
    return ($key !== false && $key !== '') ? $key : null;
}

// PHP does not read .env files on its own. Load the project's .env once,
// without clobbering anything already set in the real environment (so an
// explicit `OPENAI_API_KEY=... php -S ...` still wins). Minimal parser:
// KEY=VALUE per line, '#' comments and blank lines ignored, optional quotes.
function loadDotEnv(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    $path = __DIR__ . '/.env';
    if (!is_readable($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if ($name === '' || getenv($name) !== false) continue;  // don't override the real env

        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

function entryNeedsEnrichment(array $entry): bool {
    foreach ($entry['senses'] as $s) {
        if (trim((string)($s['simple_de'] ?? '')) === '') {
            return true;
        }
    }
    return false;
}

function enrichEntry(SQLite3 $db, array &$entry): void {
    if (!$entry['senses']) return;

    $data = openaiEnrich(buildEnrichPayload($entry));
    if ($data === null) return;  // failure already logged; retry next lookup

    // Index the model's senses by idx so we match them to our rows safely,
    // regardless of order or any extra/missing entries it returns.
    $byIdx = [];
    foreach ($data['senses'] ?? [] as $r) {
        if (isset($r['idx'])) $byIdx[(int)$r['idx']] = $r;
    }
    if (!$byIdx) return;

    $upd = $db->prepare(
        "UPDATE de_senses SET simple_de = :sd, en_translation = :en, examples = :ex WHERE id = :id"
    );

    foreach ($entry['senses'] as &$s) {
        $r = $byIdx[(int)$s['idx']] ?? null;
        if ($r === null) continue;

        $simpleDe = trim((string)($r['simple_de'] ?? ''));
        if ($simpleDe === '') continue;  // never mark a sense done without the sentinel

        $english = array_values(array_filter(
            array_map(fn($w) => trim((string)$w), $r['en'] ?? []),
            fn($w) => $w !== ''
        ));

        $examples = [];
        foreach ($r['examples'] ?? [] as $ex) {
            $de = trim((string)($ex['de'] ?? ''));
            if ($de === '') continue;
            $examples[] = ['text' => $de, 'en' => trim((string)($ex['en'] ?? ''))];
        }

        $upd->bindValue(':sd', $simpleDe);
        $upd->bindValue(':en', json_encode($english, JSON_UNESCAPED_UNICODE));
        $upd->bindValue(':ex', json_encode($examples, JSON_UNESCAPED_UNICODE));
        $upd->bindValue(':id', (int)$s['id'], SQLITE3_INTEGER);
        $upd->execute();
        $upd->reset();

        // Reflect the change in the entry we are about to hand back.
        $s['simple_de'] = $simpleDe;
        $s['english']   = $english;
        $s['examples']  = $examples;
    }
    unset($s);
}

// The grounding we hand the model: enough to identify the word precisely, with
// each sense's authoritative German definition and a few raw examples it may
// simplify. We deliberately do NOT ask it to alter the definition.
function buildEnrichPayload(array $entry): array {
    $senses = [];
    foreach ($entry['senses'] as $s) {
        $raw = [];
        foreach (array_slice($s['examples'], 0, 3) as $ex) {
            $t = trim((string)($ex['text'] ?? ''));
            if ($t !== '') $raw[] = $t;
        }
        $senses[] = [
            'idx'               => (int)$s['idx'],
            'definition_de'     => $s['definition'],
            'existing_examples' => $raw,
        ];
    }

    return [
        'word'     => $entry['word'],
        'pos'      => $entry['pos'],
        'article'  => $entry['article'],   // der/die/das, or null
        'ipa'      => $entry['ipa'],
        'synonyms' => $entry['synonyms'],
        'senses'   => $senses,
    ];
}

function enrichSchema(): array {
    $example = [
        'type' => 'object',
        'properties' => [
            'de' => ['type' => 'string'],
            'en' => ['type' => 'string'],
        ],
        'required' => ['de', 'en'],
        'additionalProperties' => false,
    ];
    $sense = [
        'type' => 'object',
        'properties' => [
            'idx'       => ['type' => 'integer'],
            'en'        => ['type' => 'array', 'items' => ['type' => 'string']],
            'simple_de' => ['type' => 'string'],
            'examples'  => ['type' => 'array', 'items' => $example],
        ],
        'required' => ['idx', 'en', 'simple_de', 'examples'],
        'additionalProperties' => false,
    ];
    return [
        'type' => 'object',
        'properties' => ['senses' => ['type' => 'array', 'items' => $sense]],
        'required' => ['senses'],
        'additionalProperties' => false,
    ];
}

function enrichSystemPrompt(): string {
    return implode(' ', [
        "You are a lexicographer for a German learner's dictionary.",
        "For each sense you are given its authoritative German definition (definition_de).",
        "Base the English translation and the simplified German strictly on that definition —",
        "never introduce a meaning that is not in it.",
        "Return, per sense, keeping the same idx:",
        "`en`: 1–3 short, natural English equivalents — the kind of word or short phrase that heads a",
        "dictionary gloss, not a full sentence.",
        "`simple_de`: one sentence of simple German (about A2–B1) that a learner can understand;",
        "avoid rare vocabulary and do not merely repeat the headword.",
        "`examples`: one or two short, natural German sentences that clearly show this sense in use,",
        "each with a faithful English translation. Prefer to simplify one of the provided",
        "existing_examples, but write a fresh, simple sentence if the provided ones are long, archaic,",
        "or unclear. Never include citations, sources, or quotation marks around the whole sentence.",
    ]);
}

function openaiEnrich(array $word): ?array {
    $key = openaiKey();
    if ($key === null) return null;

    $n = max(1, count($word['senses']));
    $payload = [
        'model'            => OPENAI_MODEL,
        'reasoning_effort' => 'low',
        'max_completion_tokens' => min(8000, 1500 + 700 * $n),
        'response_format'  => [
            'type' => 'json_schema',
            'json_schema' => [
                'name'   => 'enriched_word',
                'strict' => true,
                'schema' => enrichSchema(),
            ],
        ],
        'messages' => [
            ['role' => 'system', 'content' => enrichSystemPrompt()],
            ['role' => 'user',   'content' => json_encode($word, JSON_UNESCAPED_UNICODE)],
        ],
    ];

    [$status, $body] = httpPostJson(OPENAI_URL, $payload, $key);
    if ($status !== 200) {
        error_log("dedict enrich: OpenAI HTTP $status: " . substr((string)$body, 0, 500));
        return null;
    }

    $resp = json_decode((string)$body, true);
    $content = $resp['choices'][0]['message']['content'] ?? null;
    if (!is_string($content)) {
        error_log('dedict enrich: missing message content in response');
        return null;
    }

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        error_log('dedict enrich: model content was not valid JSON');
        return null;
    }
    return $parsed;
}

function httpPostJson(string $url, array $payload, string $key): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => OPENAI_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log("dedict enrich: curl error: $err");
        return [0, ''];
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $body];
}
