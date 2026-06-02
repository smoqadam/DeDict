# DeDict

A simple German learner's dictionary, served from a local SQLite database
built from [Wiktionary](https://kaikki.org/dewiktionary/) data.

## Setup

Build the database (one time):

```
cd scripts
wget https://kaikki.org/dewiktionary/Deutsch/kaikki.org-dictionary-Deutsch.jsonl
php ingest_dewiktionary.php
```

This writes `data/dedict.db`.

## Run

```
php -S localhost:8000 router.php
```

- `/` — web page with a search box
- `/api?q=Haus` — JSON lookup

## API

`GET /api?q=<word>`

```
200  { "results": [ ... ] }   word found
400  { "error": ... }         q is missing
404  { "error": ... }         word not in dictionary
```
