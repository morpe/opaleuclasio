# ✨ Opale & Euclasio

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

**Opale** and **Euclasio** are two lightweight, standalone PHP scripts that work together to convert **Obsidian Markdown notes** into structured JSON, and then into semantic HTML.

- **Opale** (`opale.php`) – parses Obsidian-flavoured Markdown and outputs a clean JSON AST.
- **Euclasio** (`euclasio.php`) – reads that JSON and renders it as HTML with sensible CSS classes.

Perfect for static site generators, custom blogs, or headless CMSs that rely on Obsidian as a source of truth.

---

## ✨ Features

### Opale (Markdown → JSON)

- Frontmatter extraction (simple YAML)
- Headings, paragraphs, horizontal rules, tables, blockquotes
- **Callouts** (foldable, with metadata)
- **Task lists** (nested checkboxes – `- [ ]` and `- [x]`)
- **Nested lists** (unordered, ordered, mixed)
- Inline formatting: bold, italic, strikethrough, highlight (`==text==`), underline, subscript, superscript, inline code, inline math (`$...$`)
- **Wikilinks** `[[Link]]` and standard Markdown links
- Embed support (images `![[image.png]]` and generic attachments)
- Code blocks (with language hint)
- Math blocks (`$$ ... $$`)
- Coloured text (`<font color="#...">`) and background highlights (`<mark>`)

### Euclasio (JSON → HTML)

- Converts the JSON AST back to **semantic HTML**
- Adds useful CSS classes like `type-heading`, `type-paragraph`, `type-callout`, `type-taskItem`, etc.
- Maps hex colours to **human-readable class names** (e.g., `text-red`, `bg-green`)
- Respects `align` attributes (center, left, right, justify) as CSS classes
- Handles **nested task lists** recursively
- Callouts are rendered as `<blockquote>` with optional toggle button and icon placeholder
- **Lazy‑friendly** – you can easily cache the resulting HTML

---

> 📘 **Note on frontmatter**: Opale’s YAML parser is intentionally **basic**. It only recognises top‑level `key: value` pairs. It does **not** support nested YAML structures, arrays, or multi‑line strings. If you need full YAML parsing, combine Opale with a proper YAML library (e.g., Symfony YAML or Spyc) after extracting the frontmatter block.

---

## 🚀 Quick Start

### 1. Clone or download

````bash
git clone https://github.com/morpe/opaleuclasio.git
cd opaleuclasio

### 2. Convert a Markdown file to JSON (Opale)
```bash
php opale.php my-note.md > my-note.json
````

Or pipe from stdin:

```bash
cat my-note.md | php opale.php > my-note.json
```

### 3. Convert the JSON to HTML (Euclasio)

```bash
php euclasio.php my-note.json > my-note.html
```

Or pipe:

```bash
cat my-note.json | php euclasio.php > my-note.html
```

---

## 📝 Example

**Input** (`todo.md`):

```markdown
- [x] Buy milk
- [ ] Write blog post
    - [ ] Outline
    - [x] Draft
```

**Command**:

```bash
php opale.php todo.md | php euclasio.php
```

**Output (HTML)**:

```html
<ul class="type-taskList">
	<li class="type-taskItem"><input type="checkbox" checked /> Buy milk</li>
	<li class="type-taskItem">
		<input type="checkbox" /> Write blog post
		<ul class="type-taskList">
			<li class="type-taskItem"><input type="checkbox" /> Outline</li>
			<li class="type-taskItem">
				<input type="checkbox" checked /> Draft
			</li>
		</ul>
	</li>
</ul>
```

---

## 🧠 JSON AST structure (simplified)

Opale produces a JSON object with two keys:

- `metadata` – extracted frontmatter (simple key‑value pairs)
- `content` – array of block nodes

Each block has a `type` and type‑specific fields. Inline nodes are recursively nested inside `children` arrays.

Example snippet:

```json
{
	"metadata": { "title": "My note" },
	"content": [
		{
			"type": "heading",
			"level": 1,
			"children": [{ "type": "text", "value": "Hello world" }]
		},
		{
			"type": "taskList",
			"items": [
				{
					"type": "taskItem",
					"checked": false,
					"children": [{ "type": "text", "value": "Euclasio rocks" }]
				}
			]
		}
	]
}
```

---

## 🎨 Customisation

- **CSS classes** – Euclasio uses class names like `type-paragraph`, `callout-note`, `text-red`. You can style them in your own stylesheet.
- **Callout icons** – The renderer adds classes like `icon-note`. You can implement icons via CSS pseudo‑elements or a font icon library.
- **Colour mapping** – Modify the `$colorMap` array inside `euclasio.php` to match your design system (Tailwind, Bootstrap, etc.).

---

## 📦 Requirements

- PHP 8.0 or later (7.4 might work, but not tested)
- No external dependencies (pure PHP)

---

## 🤝 Contributing

Issues and pull requests are welcome! Feel free to improve the parsers, add new Markdown features, or enhance the HTML output.

---

## 📄 License

MIT © [morpe](https://github.com/morpe)

---

## 🙏 Acknowledgements

Inspired by the Obsidian community and the need for a simple, portable Markdown → HTML pipeline.  
Named after the gemstones **Opale** (for the colourful JSON) and **Euclasio** (for the rare, polished HTML).
