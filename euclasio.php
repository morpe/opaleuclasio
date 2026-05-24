<?php
/**
 * euclasio.php - Converte AST JSON in HTML con classi CSS semantiche.
 * 
 * Uso CLI: php euclasio.php file.json
 */

require_once __DIR__ . '/slugify.php';

class Euclasio
{
    use Slugify;
    private string $wikiLinkPrefix;

    public function __construct(string $wikiLinkPrefix = '/post/')
    {
        $prefix = rtrim($wikiLinkPrefix, '/');
        $this->wikiLinkPrefix = $prefix === '' ? '' : $prefix . '/';
    }

    // Mappa icone per callout
    private array $calloutIcons = [
        'note' => 'icon-note', 'abstract' => 'icon-abstract', 'info' => 'icon-info',
        'todo' => 'icon-todo', 'tip' => 'icon-tip', 'success' => 'icon-success',
        'question' => 'icon-question', 'warning' => 'icon-warning', 'failure' => 'icon-failure',
        'danger' => 'icon-danger', 'bug' => 'icon-bug', 'example' => 'icon-example',
        'quote' => 'icon-quote',
    ];

    // Mappa colori esadecimali → classi CSS (Tailwind/Bootstrap ispirate)
    private array $colorMap = [
        '#ff0000' => 'red', '#ff4d4f' => 'red', '#ff3333' => 'red',
        '#92d050' => 'green', '#00ff00' => 'green',
        '#245bdb' => 'blue', '#0000ff' => 'blue',
        '#ffff00' => 'yellow', '#ffa500' => 'orange',
        '#d4b106' => 'yellow', '#9254de' => 'purple', '#ff88ff' => 'pink',
        '#ffffff' => 'white', '#000000' => 'black',
    ];

    public function render(array $ast): string
    {
        $blocks = $ast['content'] ?? $ast;
        return $this->renderBlocks($blocks);
    }

    private function renderBlocks(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }
        return $html;
    }

    private function renderBlock(array $block): string
    {
        $type = $block['type'] ?? 'unknown';
        return match($type) {
            'heading'        => $this->renderHeading($block),
            'paragraph'      => $this->renderParagraph($block),
            'horizontalRule' => '<hr class="type-horizontalRule">',
            'list'           => $this->renderList($block),
            'taskList'       => $this->renderTaskList($block),
            'table'          => $this->renderTable($block),
            'blockquote'     => $this->renderBlockquote($block),
            'callout'        => $this->renderCallout($block),
            'codeBlock'      => $this->renderCodeBlock($block),
            'mathBlock'      => $this->renderMathBlock($block),
            'image'          => $this->renderImage($block),
            'embed'          => $this->renderEmbed($block),
            'div'            => $this->renderDiv($block),
            default          => "<!-- unknown block: {$type} -->",
        };
    }

    private function renderHeading(array $block): string
    {
        $level = $block['level'];
        $content = $this->renderInline($block['children'] ?? []);
        return "<h{$level} class=\"type-heading level-{$level}\">{$content}</h{$level}>";
    }

    private function renderParagraph(array $block): string
    {
        $content = $this->renderInline($block['children'] ?? []);
        return "<p class=\"type-paragraph\">{$content}</p>";
    }

    // --- LISTE e TASK LIST (con supporto a children misti) ---
		private function renderList(array $block): string
		{
				$tag = ($block['ordered'] ?? false) ? 'ol' : 'ul';
				$itemsHtml = '';
				foreach ($block['items'] as $item) {
						if ($item['type'] === 'taskItem') {
								$checked = ($item['checked'] ?? false) ? 'checked' : '';
								$content = $this->renderListItemChildren($item['children'] ?? []);
								$itemsHtml .= "<li class=\"type-taskItem\"><input type=\"checkbox\" {$checked}> {$content}</li>";
						} else {
								$content = $this->renderListItemChildren($item['children'] ?? []);
								$itemsHtml .= "<li class=\"type-listItem\">{$content}</li>";
						}
				}
				$classes = "type-list " . ($tag === 'ol' ? 'ordered' : 'unordered');
				return "<{$tag} class=\"{$classes}\">{$itemsHtml}</{$tag}>";
		}

		private function renderTaskList(array $block): string
		{
				$itemsHtml = '';
				foreach ($block['items'] as $item) {
						$checked = ($item['checked'] ?? false) ? 'checked' : '';
						$content = $this->renderListItemChildren($item['children'] ?? []);
						$itemsHtml .= "<li class=\"type-taskItem\"><input type=\"checkbox\" {$checked}> {$content}</li>";
				}
				return "<ul class=\"type-taskList\">{$itemsHtml}</ul>";
		}




		 /**
     * Renderizza i children di un listItem/taskItem, che possono essere sia inline (testo)
     * che blocchi (sottoliste). Gestisce anche softBreak.
     */
    private function renderListItemChildren(array $children): string
    {
        $html = '';
        foreach ($children as $child) {
            if (is_array($child) && isset($child['type']) && in_array($child['type'], ['list', 'taskList'])) {
                // Sottolista: renderizzata come blocco (ricorsivo)
                $html .= $this->renderBlock($child);
            } else {
                // Nodo inline (testo, strong, softBreak, ecc.)
                $html .= $this->renderInlineNode($child);
            }
        }
        return $html;
    }

    private function renderTable(array $block): string
    {
        $headers = $block['headers'] ?? [];
        $rows = $block['rows'] ?? [];
        $html = '<table class="type-table">';
        if ($headers) {
            $html .= '<thead><tr class="type-tr">';
            foreach ($headers as $th) {
                $html .= '<th class="type-th">' . htmlspecialchars($th) . '</th>';
            }
            $html .= '</tr></thead>';
        }
        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr class="type-tr">';
            foreach ($row as $cell) {
                $html .= '<td class="type-td">' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    private function renderBlockquote(array $block): string
    {
        $content = $this->renderInline($block['children'] ?? []);
        return "<blockquote class=\"type-blockquote\">{$content}</blockquote>";
    }

    private function renderCallout(array $block): string
    {
        $kind = $block['kind'] ?? 'note';
        $title = htmlspecialchars($block['title'] ?? ucfirst($kind));
        $foldable = $block['foldable'] ?? false;
        // Se foldable è true e open non è specificato, default chiuso (open = false)
        $open = $block['open'] ?? ($foldable ? false : true);
        
        $iconClass = $this->calloutIcons[$kind] ?? 'icon-default';
        
        $toggleButton = '';
        if ($foldable) {
            // Bottone toggle solo se foldable
            $toggleButton = '<button class="callout-toggle" aria-label="toggle">∨</button>';
        }
        
        // Data attribute per stato iniziale
        $stateAttr = $foldable ? " data-state=\"" . ($open ? "open" : "closed") . "\"" : "";
        
        $contentHtml = $this->renderInline($block['children'] ?? []);
        
        // Se callout todo, converte in checklist
        if (str_starts_with($kind, 'todo')) {
            $contentHtml = $this->convertToChecklist($contentHtml);
        }
        
        return <<<HTML
        <blockquote class="type-callout callout-{$kind}" data-foldable="{$foldable}"{$stateAttr}>
            <div class="callout-title">
                <span class="callout-icon {$iconClass}"></span>
                <span class="callout-title-text">{$title}</span>
                {$toggleButton}
            </div>
            <div class="callout-content" data-open="{$open}">
                {$contentHtml}
            </div>
        </blockquote>
        HTML;
    }

		private function convertToChecklist(string $html): string
		{
				// Suddivido per paragrafi e <br>
				$lines = preg_split('/(<br\s*\/?>|<\/p>|<p>)/', $html);
				$checklist = '';
				foreach ($lines as $line) {
						$line = trim(strip_tags($line));
						if ($line === '') continue;
						$checklist .= '<label class="callout-todo-item"><input type="checkbox"> ' . htmlspecialchars($line) . '</label>';
				}
				return $checklist ?: $html;
		}

    private function renderCodeBlock(array $block): string
    {
        $lang = htmlspecialchars($block['language'] ?? '');
        $code = htmlspecialchars($block['code'] ?? '');
        $class = $lang ? "language-{$lang}" : '';
        return "<pre class=\"type-codeBlock\"><code class=\"{$class}\">{$code}</code></pre>";
    }

    private function renderMathBlock(array $block): string
    {
        $value = htmlspecialchars($block['value'] ?? '');
        return "<div class=\"type-mathBlock math-block\">\\[ {$value} \\]</div>";
    }

    private function renderImage(array $block): string
    {
        $src = htmlspecialchars($block['src'] ?? '');
        $alt = htmlspecialchars($block['alt'] ?? '');
        $width = isset($block['width']) ? " width=\"{$block['width']}\"" : '';
        return "<img src=\"{$src}\" alt=\"{$alt}\"{$width} class=\"type-image\">";
    }

    private function renderEmbed(array $block): string
    {
        $src = htmlspecialchars($block['src'] ?? '');
        $kind = $block['kind'] ?? 'attachment';
        return "<a href=\"{$src}\" class=\"type-embed embed-{$kind}\">📎 {$src}</a>";
    }

    private function renderDiv(array $block): string
    {
        $attrs = '';
        if (isset($block['attrs']['align'])) {
            $align = $block['attrs']['align'];
            $attrs .= ' class="type-div type-align-' . htmlspecialchars($align) . '"';
        } else {
            $attrs .= ' class="type-div"';
        }
        $children = $this->renderBlocks($block['children'] ?? []);
        return "<div{$attrs}>{$children}</div>";
    }

    // ==================== INLINE ====================

    private function renderInline(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderInlineNode($node);
        }
        return $html;
    }

    private function renderInlineNode(array $node): string
    {
        $type = $node['type'] ?? 'text';
        return match($type) {
            'text'          => $this->renderText($node),
            'softBreak'     => ' ',
            'strong'        => $this->wrap('strong', $node),
            'emphasis'      => $this->wrap('em', $node),
            'strikethrough' => $this->wrap('del', $node),
            'underline'     => $this->wrap('u', $node),
            'subscript'     => $this->wrap('sub', $node),
            'superscript'   => $this->wrap('sup', $node),
            'inlineCode'    => '<code class="type-inlineCode">' . htmlspecialchars($node['value'] ?? '') . '</code>',
            'inlineMath'    => '<span class="type-inlineMath math-inline">\\( ' . htmlspecialchars($node['value'] ?? '') . ' \\)</span>',
            'wikilink'      => $this->renderWikilink($node),
            'link'          => $this->renderLink($node),
            'colored'       => $this->renderColored($node),
            'highlight'     => $this->renderHighlight($node),
            'embed'         => $this->renderEmbedInline($node),
            'image'         => $this->renderImage($node),
            default         => "<!-- unknown inline: {$type} -->",
        };
    }

    private function renderText(array $node): string
    {
        $value = htmlspecialchars($node['value'] ?? '');
        return "<!-- text IN -->{$value}<!-- text OUT -->";
    }

    private function wrap(string $tag, array $node): string
    {
        $inner = $this->renderInline($node['children'] ?? []);
        return "<{$tag} class=\"type-{$tag}\">{$inner}</{$tag}>";
    }

    private function renderWikilink(array $node): string
    {
        $target = $this->slugify($node['target'] ?? '');
        $content = $this->renderInline($node['children'] ?? []);
        return "<a href=\"{$this->wikiLinkPrefix}{$target}\" class=\"type-wikilink\">{$content}</a>";
    }

    private function renderLink(array $node): string
    {
        $url = htmlspecialchars($node['url'] ?? '');
        $title = htmlspecialchars($node['title'] ?? '');
        $titleAttr = $title ? " title=\"{$title}\"" : '';
        $extAttr = $this->isExternalUrl($node['url'] ?? '') ? ' target="_blank" rel="noopener noreferrer"' : '';
        $content = $this->renderInline($node['children'] ?? []);
        return "<a href=\"{$url}\"{$titleAttr}{$extAttr} class=\"type-link\">{$content}</a>";
    }

    private function isExternalUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function renderColored(array $node): string
    {
        $color = $node['color'] ?? 'inherit';
        $className = $this->colorToClass($color, 'text');
        if ($className) {
            return "<span class=\"{$className}\">{$this->renderInline($node['children'] ?? [])}</span>";
        }
        // fallback inline style
        return "<span class=\"type-colored\" style=\"color: {$color};\">{$this->renderInline($node['children'] ?? [])}</span>";
    }

    private function renderHighlight(array $node): string
    {
        $bg = $node['color'] ?? 'default';
        $className = $this->colorToClass($bg, 'bg');
        if ($className) {
            return "<mark class=\"{$className}\">{$this->renderInline($node['children'] ?? [])}</mark>";
        }
        $style = ($bg !== 'default') ? " style=\"background: {$bg};\"" : '';
        return "<mark class=\"type-highlight\"{$style}>{$this->renderInline($node['children'] ?? [])}</mark>";
    }

    private function renderEmbedInline(array $node): string
    {
        $src = htmlspecialchars($node['src'] ?? '');
        return "<a href=\"{$src}\" class=\"type-embed inline-embed\">📄 {$src}</a>";
    }

    private function convertMarkdownCheckbox(string $html): string
    {
        return preg_replace_callback('/^(\s*)\[([ xX])\]\s+(.*)/', function($m) {
            $indent = $m[1];
            $checked = ($m[2] === 'x' || $m[2] === 'X') ? 'checked' : '';
            $rest = $m[3];
            return $indent . '<input type="checkbox" disabled ' . $checked . '> ' . $rest;
        }, $html);
    }

    private function colorToClass(string $hex, string $prefix): ?string
    {
        $hexLower = strtolower($hex);
        if (isset($this->colorMap[$hexLower])) {
            $colorName = $this->colorMap[$hexLower];
            return "{$prefix}-{$colorName}";
        }
        return null;
    }
}

// CLI
if (PHP_SAPI === 'cli') {
    $input = $argc > 1 ? file_get_contents($argv[1]) : stream_get_contents(STDIN);
    if (!$input) exit(1);
    $data = json_decode($input, true);
    if (json_last_error()) exit(1);
    $renderer = new Euclasio();
    echo $renderer->render($data);
}