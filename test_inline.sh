#!/usr/bin/env bash
set -euo pipefail
fail=0

test_case() {
    local desc="$1" input="$2" expected="$3"
    local actual
    actual=$(printf '%s' "$input" | php opale.php | php euclasio.php 2>&1)
    if [ "$actual" != "$expected" ]; then
        echo "FAIL: $desc"
        echo "  input:    $input"
        echo "  expected: $expected"
        echo "  actual:   $actual"
        fail=1
    else
        echo "PASS: $desc"
    fi
}

# Asterisk variants
test_case "*italic*"     "*italic*"     '<p class="type-paragraph"><em class="type-em"><!-- text IN -->italic<!-- text OUT --></em></p>'
test_case "**bold**"     "**bold**"     '<p class="type-paragraph"><strong class="type-strong"><!-- text IN -->bold<!-- text OUT --></strong></p>'
test_case "***bolditalic***" "***bolditalic***" '<p class="type-paragraph"><strong class="type-strong"><em class="type-em"><!-- text IN -->bolditalic<!-- text OUT --></em></strong></p>'

# Underscore variants
test_case "_italic_"     "_italic_"     '<p class="type-paragraph"><em class="type-em"><!-- text IN -->italic<!-- text OUT --></em></p>'
test_case "__bold__"     "__bold__"     '<p class="type-paragraph"><strong class="type-strong"><!-- text IN -->bold<!-- text OUT --></strong></p>'
test_case "___bolditalic___" "___bolditalic___" '<p class="type-paragraph"><strong class="type-strong"><em class="type-em"><!-- text IN -->bolditalic<!-- text OUT --></em></strong></p>'

# Edge cases
test_case "underscore in var name (no parse)" "some_var" '<p class="type-paragraph"><!-- text IN -->some_var<!-- text OUT --></p>'
test_case "mixed asterisk+underscore" "*_mixed_*" '<p class="type-paragraph"><em class="type-em"><em class="type-em"><!-- text IN -->mixed<!-- text OUT --></em></em></p>'
test_case "adjacent formatting" "**bold**_italic_" '<p class="type-paragraph"><strong class="type-strong"><!-- text IN -->bold<!-- text OUT --></strong><em class="type-em"><!-- text IN -->italic<!-- text OUT --></em></p>'

if [ "$fail" -eq 0 ]; then
    echo
    echo "All tests passed."
else
    echo
    echo "Some tests failed."
    exit 1
fi
