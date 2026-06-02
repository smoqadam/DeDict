### DeDict: a german learner dictionary

# installation

```
cd scripts
wget https://kaikki.org/dewiktionary/Deutsch/kaikki.org-dictionary-Deutsch.jsonl
php ingest_dewiktionary.php
```
this will give you a data/dedict.db sqlite database. you can use the db in your project or serve the web page:

```
cd ..
php -S localhost:8000 router.php
```

