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
- `update.sh` downloads fresh copies of both scripts from GitHub `main` branch (overwrites local files).

---

All 11 AGENTS.md claims verified ✅
High-signal additions worth documenting:

- Text nodes wrapped in `<!-- text IN -->...<!-- text OUT -->` HTML comments (debug artifact)
- softBreak renders as space, not <br>
- Dead code: old_parseList() and convertMarkdownCheckbox() never called
- Table cells are NOT processed for inline formatting
- Blockquote/callout content is inline-only (no nested blocks)
- No trailing newline from euclasio output
- code fences not supported (only triple backticks)

Bugs discovered:

1. Link titles not extracted correctly — included in URL
2. Callout titles duplicated in rendered content
3. Ordered/unordered lists can merge incorrectly
   Would you like me to update AGENTS.md with these findings, particularly the quirks and bugs that agents should be aware of?
