# slugify() — URL slug generation

`slugify()` converte una stringa in uno **slug URL-safe**.

## Regole

1. **Lowercase** — `mb_strtolower()` (UTF-8 safe)
2. **Traslitterazione** — accenti e caratteri speciali → ASCII (`é` → `e`, `ñ` → `n`, …)
3. **Suffisso `.md`** — rimosso (evita slug tipo `cv-2026-md`)
4. **Pulizia** — rimuove tutto ciò che non è `[a-z0-9\s\-_]`
5. **Spazi e underscore** → trattino `-`
6. **Trattini multipli** collassati in uno singolo
7. **Trim** — rimuove trattini iniziali e finali

## Esempi

| Input | Output |
|---|---|
| `1885: tutto è andato male` | `1885-tutto-e-andato-male` |
| `Café au lait!` | `cafe-au-lait` |
| `Hello_World` | `hello-world` |
| `What’s up?` | `whats-up` |
| `  --spazi e trattini--  ` | `spazi-e-trattini` |
| `file.md` | `file` |
| `CV 2026` | `cv-2026` |

## Utilizzo in euclasio.php

```php
$target = $this->slugify($node['target'] ?? '');
```

`[[1885: tutto è andato male]]` produce:

```html
<a href="/post/1885-tutto-e-andato-male" class="type-wikilink">1885: tutto è andato male</a>
```
