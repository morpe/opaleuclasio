# Update Notes

## 2026-05-23 — Wikilink prefix now configurable

`euclasio.php`: Added constructor parameter `$wikiLinkPrefix` (default `'/post/'`). Lo slash finale è opzionale — viene normalizzato automaticamente.

```php
new Euclasio();              // /post/target (backward compat)
new Euclasio('/blog');       // /blog/target
new Euclasio('/blog/');      // /blog/target
new Euclasio('');            // target (no prefix)
```

CLI usage unchanged.
