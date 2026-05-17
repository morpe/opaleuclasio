# AGENTS.md

## Project

Two standalone PHP scripts (zero dependencies) that convert Obsidian Markdown → JSON AST → HTML.

- `opale.php` — Markdown → JSON (reads file arg or stdin, writes JSON to stdout)
- `euclasio.php` — JSON → HTML (reads file arg or stdin, writes HTML to stdout)

## Commands

```bash
php opale.php note.md > note.json        # Markdown → JSON
php euclasio.php note.json > note.html   # JSON → HTML
php opale.php note.md | php euclasio.php # full pipeline (pipe)
cat note.md | php opale.php              # stdin input
```

Requires PHP 8.0+. No composer, no build step, no tests.

## JSON AST shape

Output from `opale.php` is always:

```json
{ "metadata": { ... }, "content": [ /* block nodes */ ] }
```

`euclasio.php` accepts either this full object or a bare `content` array.

## Architecture notes

- Both scripts are single-file classes (`Opale`, `Euclasio`) with a CLI bootstrap at the bottom guarded by `PHP_SAPI === 'cli'`.
- `opale.php` uses a line-by-line recursive descent parser — no external libraries.
- `euclasio.php` uses PHP 8 `match` expressions for block/inline dispatch.
- Frontmatter YAML parsing is intentionally basic: only top-level `key: value` pairs.
- Wikilinks render as `/post/<urlencoded-target>` — hardcoded URL pattern.
- `update.sh` downloads fresh copies of both scripts from GitHub `main` branch (overwrites local files without backup).

## Quirks

- Text nodes wrapped in `<!-- text IN -->...<!-- text OUT -->` HTML comments (debug artifact in euclasio output).
- `softBreak` renders as space, not `<br>`.
- No trailing newline from `euclasio` output.
- Table cells are NOT processed for inline formatting — `htmlspecialchars()` only.
- Blockquote/callout content is inline-only — no nested block support.
- Only triple-backtick code fences supported — `~~~` not recognized.
- Unclosed code fence or math block consumes to EOF silently.
- Single-row tables (header only, no separator) silently become paragraphs.
- `<p align>` and `<center>` in same paragraph block: `parseParagraph()` returns on first match, losing subsequent lines.
- Dead code: `old_parseList()` (opale.php:511) and `convertMarkdownCheckbox()` (euclasio.php:346) are never called.

## Known bugs

1. **Link titles included in URL** (`opale.php:208`) — regex captures title as part of URL; `title` field is always empty.
2. **Callout title duplicated in content** (`opale.php:389`) — title text appended to `$lines` and joined into content, appearing twice in rendered HTML.
3. **Ordered/unordered list merge** (`opale.php:612-703`) — list type determined by first item only; mixed lists render entirely as `<ul>` or `<ol>`.
