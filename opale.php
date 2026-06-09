<?php
/**
 * opale.php - Parser indipendente per note Obsidian Markdown → JSON
 * Uso: php opale.php [file.md]   oppure pipe: cat nota.md | php opale.php
 */

class Opale {
    private $lines;
    private $pos;
    private $len;

    public function parse($markdown) {
        $this->lines = explode("\n", $markdown);
        $this->pos = 0;
        $this->len = count($this->lines);
        $blocks = [];

        // Frontmatter
        if ($this->pos < $this->len && trim($this->lines[$this->pos]) === '---') {
            $blocks[] = $this->parseFrontmatter();
        }

        // Blocchi principali
        while ($this->pos < $this->len) {
            $line = $this->lines[$this->pos];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $this->pos++;
                continue;
            }

            // Heading
            if (preg_match('/^(#{1,6})\s+(.*)/', $trimmed, $m)) {
                $blocks[] = [
                    'type' => 'heading',
                    'level' => strlen($m[1]),
                    'children' => $this->parseInlineContent($m[2])
                ];
                $this->pos++;
                continue;
            }

            // Horizontal rule
            if (preg_match('/^(\-{3,}|\*{3,}|_{3,})\s*$/', $trimmed)) {
                $blocks[] = ['type' => 'horizontalRule'];
                $this->pos++;
                continue;
            }

            // Table
            if (preg_match('/^\|.*\|/', $trimmed)) {
                $blocks[] = $this->parseTable();
                continue;
            }

            // Blockquote / Callout
            if ($trimmed[0] === '>') {
                $blocks[] = $this->parseBlockquote();
                continue;
            }

            // Code fence
            if (strpos($trimmed, '```') === 0) {
                $blocks[] = $this->parseCodeBlock();
                continue;
            }

            // Block embed / image (standalone)
            if (preg_match('/^!\[\[(.+?)\]\]$/', $trimmed, $m)) {
                $embed = $this->parseEmbedToken('![[', ']]', $trimmed);
                $blocks[] = $embed;
                $this->pos++;
                continue;
            }

            // Block math
            if (preg_match('/^\$\$(.+?)\$\$\s*$/', $trimmed, $m)) {
                $blocks[] = [
                    'type' => 'mathBlock',
                    'value' => $m[1]
                ];
                $this->pos++;
                continue;
            }
            if (preg_match('/^\$\$\s*$/', $trimmed)) {
                $blocks[] = $this->parseBlockMath();
                continue;
            }

            // Liste
            if (preg_match('/^(\s*)(- |\* |\d+\. |(-\s*\[[ x]\])\s)/', $line, $m)) {
                $blocks[] = $this->parseList(strlen($m[1]));
                continue;
            }

            // Paragrafo
            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function parseFrontmatter() {
        $this->pos++; // skip first ---
        $content = '';
        while ($this->pos < $this->len) {
            $line = rtrim($this->lines[$this->pos]);
            if (trim($line) === '---') {
                $this->pos++;
                break;
            }
            $content .= $line . "\n";
            $this->pos++;
        }
        return [
            'type' => 'frontmatter',
            'content' => trim($content)
        ];
    }

    private function parseParagraph() {
        $paraLines = [];
        while ($this->pos < $this->len) {
            $line = $this->lines[$this->pos];
            if (trim($line) === '' || $this->isBlockStart($line)) {
                break;
            }
            $paraLines[] = $line;
            $this->pos++;
        }

        // Costruisci array di token con softBreak tra le righe
        $tokens = [];
        foreach ($paraLines as $idx => $line) {
            if ($idx > 0) {
                $tokens[] = ['type' => 'softBreak'];
            }
            // Gestione tag HTML a blocco (allineamento)
            if (preg_match('/^<p\s+align="(.*?)"\s*>(.*)<\/p>\s*$/is', $line, $m)) {
                return [
                    'type' => 'div',
                    'attrs' => ['align' => $m[1]],
                    'children' => [
                        [
                            'type' => 'paragraph',
                            'children' => $this->parseInlineContent($m[2])
                        ]
                    ]
                ];
            }
            if (preg_match('/^<center>(.*)<\/center>\s*$/is', $line, $m)) {
                return [
                    'type' => 'div',
                    'attrs' => ['align' => 'center'],
                    'children' => [
                        [
                            'type' => 'paragraph',
                            'children' => $this->parseInlineContent($m[1])
                        ]
                    ]
                ];
            }

            $inlineTokens = $this->parseInlineContent($line);
            $tokens = array_merge($tokens, $inlineTokens);
        }

        return [
            'type' => 'paragraph',
            'children' => $tokens
        ];
    }

    private function isBlockStart($line) {
        $trimmed = trim($line);
        if ($trimmed === '') return true;
        // Tutti i blocchi che non sono paragrafo
        return preg_match('/^(#{1,6}\s|>\s|```|\$\$|!\[\[|\||\-(\s\[[ x]\]|\s)|\*\s|\d+\.\s)/', $trimmed)
            || preg_match('/^(\-{3,}|\*{3,}|_{3,})\s*$/', $trimmed)
            || preg_match('/^\|.*\|/', $trimmed);
    }

    private function parseInlineContent($text) {
        if (empty($text)) return [];
        return $this->tokenizeInline($text);
    }

    private function tokenizeInline($text) {
        $tokens = [];
        $len = strlen($text);
        $pos = 0;

        while ($pos < $len) {
            // Cerca la prima occorrenza tra tutti i pattern
            $patterns = [
                // Bold+Italic (prima di bold/italic per matchare *** e ___)
                'boldItalic_asterisk' => '/\*\*\*(.+?)\*\*\*/',
                'boldItalic_underscore' => '/_{3}(.+?)_{3}/',
                // Bold
                'bold' => '/\*\*(.+?)\*\*/',
                'bold_underscore' => '/(?<!\w)_{2}(.+?)_{2}(?!\w)/',
                // Italic
                'italic' => '/(?<!\w)\*(.+?)\*(?!\w)/',
                'italic_underscore' => '/(?<!\w)_(.+?)_(?!\w)/',
                'strikethrough' => '/~~(.+?)~~/',
                'highlight' => '/==(.+?)==/',
                'underline' => '/<u>(.+?)<\/u>/',
                'subscript' => '/<sub>(.+?)<\/sub>/',
                'superscript' => '/<sup>(.+?)<\/sup>/',
                'code' => '/`(.+?)`/',
                'math' => '/\$(.+?)\$/',
                'wikilink' => '/\[\[(.+?)\]\]/',
                'link' => '/\[([^\]]+)\]\(([^)]+)(?:\s+"([^"]*)")?\)/',
                'embed' => '/!\[\[(.+?)\]\]/',
                'font' => '/<font\s+color="([^"]*)"\s*>(.+?)<\/font>/is',
                'mark' => '/<mark\s+style="background:([^"]*)"\s*>(.+?)<\/mark>/is',
            ];

            $matches = [];
            $minPos = $len;
            $matchedType = null;
            $matchedData = null;

            foreach ($patterns as $type => $regex) {
                if (preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE, $pos)) {
                    $matchPos = $m[0][1];
                    if ($matchPos < $minPos) {
                        $minPos = $matchPos;
                        $matchedType = $type;
                        $matchedData = $m;
                    }
                }
            }

            if ($matchedType === null) {
                // Nessun pattern trovato, accoda il resto come testo
                $remaining = substr($text, $pos);
                if ($remaining !== '') {
                    $tokens[] = ['type' => 'text', 'value' => $remaining];
                }
                break;
            }

            // Testo prima del match
            if ($minPos > $pos) {
                $tokens[] = ['type' => 'text', 'value' => substr($text, $pos, $minPos - $pos)];
            }

            // Processa il token trovato
            $fullMatch = $matchedData[0][0];
            $matchEnd = $minPos + strlen($fullMatch);
            $pos = $matchEnd;

            switch ($matchedType) {
                case 'boldItalic_asterisk':
                case 'boldItalic_underscore':
                    $tokens[] = [
                        'type' => 'strong',
                        'children' => [
                            [
                                'type' => 'emphasis',
                                'children' => $this->tokenizeInline($matchedData[1][0])
                            ]
                        ]
                    ];
                    break;
                case 'bold':
                case 'bold_underscore':
                    $tokens[] = [
                        'type' => 'strong',
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'italic':
                case 'italic_underscore':
                    $tokens[] = [
                        'type' => 'emphasis',
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'strikethrough':
                    $tokens[] = [
                        'type' => 'strikethrough',
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'highlight':
                    $color = 'default';
                    $tokens[] = [
                        'type' => 'highlight',
                        'color' => $color,
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'underline':
                    $tokens[] = [
                        'type' => 'underline',
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'subscript':
                    $tokens[] = [
                        'type' => 'subscript',
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'superscript':
                    $tokens[] = [
                        'type' => 'superscript',
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'code':
                    $tokens[] = ['type' => 'inlineCode', 'value' => $matchedData[1][0]];
                    break;
                case 'math':
                    $tokens[] = ['type' => 'inlineMath', 'value' => $matchedData[1][0]];
                    break;
                case 'wikilink':
                    $parts = explode('|', $matchedData[1][0], 2);
                    $target = $parts[0];
                    $display = $parts[1] ?? $target;
                    $tokens[] = [
                        'type' => 'wikilink',
                        'target' => $target,
                        'children' => [['type' => 'text', 'value' => $display]]
                    ];
                    break;
                case 'link':
                    $url = $matchedData[2][0];
                    $title = $matchedData[3][0] ?? '';
                    $tokens[] = [
                        'type' => 'link',
                        'url' => $url,
                        'title' => $title,
                        'children' => $this->tokenizeInline($matchedData[1][0])
                    ];
                    break;
                case 'embed':
                    $embed = $this->parseEmbedToken('![[', ']]', $fullMatch);
                    $tokens[] = $embed;
                    break;
                case 'font':
                    $tokens[] = [
                        'type' => 'colored',
                        'color' => $matchedData[1][0],
                        'children' => $this->tokenizeInline($matchedData[2][0])
                    ];
                    break;
                case 'mark':
                    $tokens[] = [
                        'type' => 'highlight',
                        'color' => $matchedData[1][0],
                        'children' => $this->tokenizeInline($matchedData[2][0])
                    ];
                    break;
            }
        }

        return $tokens;
    }

    private function parseEmbedToken($open, $close, $raw) {
        $inner = substr($raw, strlen($open), -strlen($close));
        $parts = explode('|', $inner);
        $target = $parts[0];
        $rest = $parts[1] ?? '';
        // Se il resto è numerico: larghezza
        $width = null;
        if (is_numeric(trim($rest))) {
            $width = (int) trim($rest);
        } elseif (preg_match('/^(\d+)$/', $rest, $m)) {
            $width = (int) $m[1];
        }

        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'bmp', 'webp'];
        if (in_array($ext, $imageExts)) {
            return [
                'type' => 'image',
                'src' => $target,
                'alt' => '',
                'width' => $width
            ];
        } else {
            return [
                'type' => 'embed',
                'src' => $target,
                'kind' => 'attachment',
                'attributes' => []  // si possono aggiungere attributi
            ];
        }
    }

    private function parseBlockquote() {
        $lines = [];
        $firstLine = $this->lines[$this->pos];
        // Determina se è un callout
        $callout = false;
        $kind = '';
        $foldable = false;
        $title = '';
        if (preg_match('/^>\s*\[!([^\]]+)\]\s*([+-]?)\s*(.*)/', $firstLine, $m)) {
            $callout = true;
            $kind = strtolower($m[1]);
            $foldable = ($m[2] === '+');
            $title = trim($m[3]);
            $lines[] = $title; // Il titolo è parte del contenuto? Nel modello il titolo è separato.
            // In realtà il contenuto del callout è tutto ciò che segue il titolo sulla prima riga e le righe successive
            // Per semplicità, trattiamo il testo dopo il tag come contenuto.
        } else {
            // blockquote normale
            $lines[] = ltrim($firstLine, '> ');
        }
        $this->pos++;

        // Accumula righe successive del blockquote
        while ($this->pos < $this->len) {
            $line = $this->lines[$this->pos];
            if (!preg_match('/^>\s*(.*)/', $line, $lm)) {
                break;
            }
            $lines[] = $lm[1];
            $this->pos++;
        }

        $contentText = implode("\n", $lines);
        $children = $this->parseInlineContent($contentText);

        if ($callout) {
            return [
                'type' => 'callout',
                'kind' => $kind,
                'foldable' => $foldable,
                'title' => $title,
                'children' => $children
            ];
        } else {
            return [
                'type' => 'blockquote',
                'children' => $children
            ];
        }
    }

    private function parseTable() {
        $lines = [];
        while ($this->pos < $this->len) {
            $line = rtrim($this->lines[$this->pos]);
            if (strpos($line, '|') === false) break;
            $lines[] = $line;
            $this->pos++;
        }
        if (count($lines) < 2) {
            // tabella non valida, restituisci paragrafo direttamente
            $tokens = [];
            foreach ($lines as $idx => $line) {
                if ($idx > 0) {
                    $tokens[] = ['type' => 'softBreak'];
                }
                $tokens = array_merge($tokens, $this->parseInlineContent(ltrim($line, '| ')));
            }
            return [
                'type' => 'paragraph',
                'children' => $tokens
            ];
        }

        // Prima riga = headers
        $headers = $this->splitTableRow($lines[0]);
        // Seconda riga = separatore (ignorata)
        $rows = [];
        for ($i = 2; $i < count($lines); $i++) {
            $rows[] = $this->splitTableRow($lines[$i]);
        }
        return [
            'type' => 'table',
            'headers' => $headers,
            'rows' => $rows
        ];
    }

    private function splitTableRow($line) {
        $cells = explode('|', trim($line, '| '));
        return array_map('trim', $cells);
    }

    private function parseCodeBlock() {
        $line = $this->lines[$this->pos];
        $language = '';
        if (preg_match('/^```(\w+)?\s*$/', trim($line), $m)) {
            $language = $m[1] ?? '';
        }
        $this->pos++;
        $code = '';
        while ($this->pos < $this->len) {
            $current = rtrim($this->lines[$this->pos]);
            if (trim($current) === '```') {
                $this->pos++;
                break;
            }
            $code .= $this->lines[$this->pos] . "\n";
            $this->pos++;
        }
        return [
            'type' => 'codeBlock',
            'language' => $language,
            'code' => rtrim($code, "\n")
        ];
    }

    private function parseBlockMath() {
        $this->pos++; // skip $$
        $math = '';
        while ($this->pos < $this->len) {
            $line = rtrim($this->lines[$this->pos]);
            if (trim($line) === '$$') {
                $this->pos++;
                break;
            }
            $math .= $line . "\n";
            $this->pos++;
        }
        return [
            'type' => 'mathBlock',
            'value' => rtrim($math, "\n")
        ];
    }

    private function old_parseList($baseIndent = 0) {
        $items = [];
        while ($this->pos < $this->len) {
            $line = $this->lines[$this->pos];
            $trimmed = trim($line);
            if ($trimmed === '') break;

            // Calcola indentazione corrente
            if (!preg_match('/^(\s*)/', $line, $sm)) break;
            $indent = strlen($sm[1]);
            if ($indent < $baseIndent) break;   // fine lista

            // Se indentato più del previsto, potrebbe essere sottolista
            if ($indent > $baseIndent) {
                // Recupera la lista annidata come continuazione dell'ultimo item
                if (empty($items)) break; // errore
                $lastIdx = count($items) - 1;
                // Trova la lista annidata
                $sublist = $this->parseList($indent);
                $items[$lastIdx]['children'][] = $sublist;
                continue;
            }

            // È un nuovo item di lista
            if (preg_match('/^(-|\*|\d+\.)\s+(.*)/', $trimmed, $lm)) {
                $marker = $lm[1];
                $contentText = $lm[2];
                $this->pos++;
                $ordered = is_numeric($marker);
                $item = [
                    'type' => 'listItem',
                    'children' => $this->parseInlineContent($contentText)
                ];
                $items[] = $item;
            } elseif (preg_match('/^(-\s*\[([ x])\]\s*)(.*)/', $trimmed, $tm)) {
                // Task list
                $checked = $tm[2] === 'x';
                $contentText = $tm[3];
                $this->pos++;
                $item = [
                    'type' => 'taskItem',
                    'checked' => $checked,
                    'children' => $this->parseInlineContent($contentText)
                ];
                $items[] = $item;
            } else {
                // Non è un item, fine lista
                break;
            }

            // Gestione continuazione: linee indentate che non sono marker
            $continuationIndent = $indent + 2; // per semplicità, 2 spazi aggiuntivi
            while ($this->pos < $this->len) {
                $nextLine = $this->lines[$this->pos];
                if (trim($nextLine) === '') break;
                if (!preg_match('/^(\s*)/', $nextLine, $nsm)) break;
                $nextIndent = strlen($nsm[1]);
                if ($nextIndent <= $indent) break; // non è più continuazione
                // Se la linea inizia con un marker allo stesso livello? No, è indentata
                // Aggiungi come continuazione dell'ultimo item
                $lastIdx = count($items) - 1;
                $text = ltrim($nextLine);
                $newTokens = $this->parseInlineContent($text);
                if (!empty($newTokens)) {
                    $items[$lastIdx]['children'][] = ['type' => 'softBreak'];
                    $items[$lastIdx]['children'] = array_merge($items[$lastIdx]['children'], $newTokens);
                }
                $this->pos++;
            }
        }

        // Determina il tipo lista
        if (!empty($items)) {
            $firstItem = $items[0];
            if ($firstItem['type'] === 'taskItem') {
                return [
                    'type' => 'taskList',
                    'items' => $items
                ];
            }
            // Distingui numerata vs non numerata dal primo item? Forse mescolate? Assumiamo uniforme
            $ordered = is_numeric($firstItem['check'] ?? false); // Non abbiamo marcato, usiamo euristica
            // Dobbiamo sapere se il marker del primo era un numero; meglio passare il flag
            // Semplifichiamo: riutilizziamo l'informazione del marker catturato prima.
            // Ripeschiamo il marker dal primo item? Dobbiamo ricordarcelo.
            // Poiché non lo abbiamo salvato, assumiamo che se il primo pattern match è stato \d+ allora è ordered.
            // Poco elegante, ma funziona per il nostro esempio.
            // In verità, il parsing precedente ci ha già detto il marker. Salvo un flag.
            // Per ora, forzo controllo esaminando la prima riga nel buffer? 
            // Avrei bisogno di ripensare. Per ora assumo che la lista sia ordered se il primo item non inizia con '-'.
            // Non perfetto, ma per lo scopo va bene.
        }

        // Fallback: restituisci lista semplice
        return [
            'type' => 'list',
            'ordered' => false, // da correggere
            'items' => $items
        ];
    }

		private function parseList($baseIndent = 0) {
    $items = [];
    while ($this->pos < $this->len) {
        $line = $this->lines[$this->pos];
        $trimmed = trim($line);
        if ($trimmed === '') {
            $this->pos++;
            continue;
        }

        // Calcola indentazione (spazi + tab)
        if (!preg_match('/^(\s*)/', $line, $sm)) break;
        $indent = strlen($sm[1]);
        
        // Se l'indentazione è minore del livello base, la lista è finita
        if ($indent < $baseIndent) break;

        // Se indentazione maggiore, potrebbe essere sottolista o continuazione
        if ($indent > $baseIndent) {
            // Verifica se la riga inizia con un marker di lista (task o normale)
            $isMarker = (bool) preg_match('/^(\s*)(-\s*\[[ x]\]\s|[-*+]\s|\d+\.\s)/', $line);
            if ($isMarker) {
                // Sottolista: parsa ricorsivamente a partire da questo indent
                $sublist = $this->parseList($indent);
                if (!empty($items)) {
                    $lastIdx = count($items) - 1;
                    $items[$lastIdx]['children'][] = $sublist;
                }
                continue; // la posizione è già avanzata dentro parseList
            } else {
                // Continuazione del testo dell'ultimo item
                if (empty($items)) break;
                $lastIdx = count($items) - 1;
                $text = ltrim($line);
                $newTokens = $this->parseInlineContent($text);
                if (!empty($newTokens)) {
                    $items[$lastIdx]['children'][] = ['type' => 'softBreak'];
                    $items[$lastIdx]['children'] = array_merge($items[$lastIdx]['children'], $newTokens);
                }
                $this->pos++;
                continue;
            }
        }

        // Indent uguale al base: nuovo item
        // Prima controlla se è un task (checkbox)
        if (preg_match('/^(\s*)(-\s*\[([ x])\]\s*)(.*)/', $line, $tm)) {
            $checked = ($tm[3] === 'x');
            $contentText = $tm[4];
            $this->pos++;
            $items[] = [
                'type' => 'taskItem',
                'checked' => $checked,
                'children' => $this->parseInlineContent($contentText)
            ];
        } 
        // Poi list item normale (pallino, trattino, numero)
        elseif (preg_match('/^(\s*)([-*+]|\d+\.)\s+(.*)/', $line, $lm)) {
            $marker = $lm[2];
            $contentText = $lm[3];
            $this->pos++;
            $ordered = is_numeric($marker);
            $items[] = [
                'type' => 'listItem',
                'ordered' => $ordered, // salva se è numerata
                'children' => $this->parseInlineContent($contentText)
            ];
        } 
        else {
            break; // non è un item, fine lista
        }
    }

    // Dopo aver raccolto gli item, determina il tipo di lista (taskList o list)
    if (!empty($items)) {
        $allTasks = true;
        foreach ($items as $item) {
            if ($item['type'] !== 'taskItem') {
                $allTasks = false;
                break;
            }
        }
        if ($allTasks) {
            return ['type' => 'taskList', 'items' => $items];
        } else {
            // Per la lista normale, determiniamo se è ordinata (tutti i marker numerici?)
            // Assumiamo ordinata se il primo item ha 'ordered' true
            $ordered = isset($items[0]['ordered']) ? $items[0]['ordered'] : false;
            return ['type' => 'list', 'ordered' => $ordered, 'items' => $items];
        }
    }
    return ['type' => 'list', 'ordered' => false, 'items' => []];
}
}

// --- Esecuzione CLI ---
if (PHP_SAPI === 'cli') {
    $input = '';
    if ($argc > 1) {
        // Da file
        $filename = $argv[1];
        if (!file_exists($filename)) {
            fwrite(STDERR, "File non trovato: $filename\n");
            exit(1);
        }
        $input = file_get_contents($filename);
    } else {
        // Da stdin
        $input = stream_get_contents(STDIN);
    }

    $parser = new Opale();
    $result = $parser->parse($input);

    // Opzionale: includi metadata se presenti (frontmatter)
    $metadata = [];
    if (!empty($result) && $result[0]['type'] === 'frontmatter') {
        // tenta di estrarre YAML semplice
        $front = $result[0]['content'];
        // Spostamento: rimuovi frontmatter dall'output principale
        array_shift($result);
        // Parsing YAML banale: righe "chiave: valore"
        foreach (explode("\n", $front) as $line) {
            if (preg_match('/^(\w[\w-]*)\s*:\s*(.*)/', $line, $m)) {
                $metadata[$m[1]] = $m[2];
            }
        }
    }

    $final = [
        'metadata' => $metadata,
        'content' => $result
    ];

    echo json_encode($final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}