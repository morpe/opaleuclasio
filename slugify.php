<?php

trait Slugify
{
    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = $this->transliterate($text);
        $text = str_replace('.md', '', $text);
        $text = preg_replace('/[^a-z0-9\s\-_]/', '', $text);
        $text = preg_replace('/[\s_]+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');
        return $text;
    }

    private function transliterate(string $text): string
    {
        static $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'š' => 's', 'ž' => 'z', 'đ' => 'd',
        ];
        return strtr($text, $map);
    }
}
