# slugify() — Euclidean URL slug generation

`slugify()` converte una stringa in uno **slug URL-safe**, con le stesse regole di `sanitize_title()` di WordPress.

## Regole

1. **Lowercase** — `mb_strtolower()`
2. **Traslitterazione** — accenti e caratteri speciali vengono convertiti all'equivalente ASCII
   - `àáâãäå` → `a`, `æ` → `ae`
   - `èéêë` → `e`, `ìíîï` → `i`, `òóôõöø` → `o`, `œ` → `oe`
   - `ùúûü` → `u`, `ýÿ` → `y`
   - `ç` → `c`, `ñ` → `n`, `š` → `s`, `ž` → `z`, `đ` → `d`
3. **Pulizia** — rimuove tutto ciò che non è `[a-z0-9\s\-_]`
4. **Spazi e underscore** → trattino `-`
5. **Trattini multipli** collassati in uno singolo
6. **Trim** — rimuove trattini iniziali e finali

## Esempi

| Input | Output |
|---|---|
| `1885: tutto è andato male` | `1885-tutto-e-andato-male` |
| `Café au lait!` | `cafe-au-lait` |
| `Hello_World` | `hello-world` |
| `What’s up?` | `whats-up` |
| `  --spazi e trattini--  ` | `spazi-e-trattini` |

## Utilizzo in euclasio.php

`renderWikilink()` applica `slugify()` al target prima di passarlo a `urlencode()`:

```php
$target = urlencode($this->slugify($node['target'] ?? ''));
```

Quindi `[[1885: tutto è andato male]]` produce:

```html
<a href="/post/1885-tutto-e-andato-male" class="type-wikilink">1885: tutto è andato male</a>
```
