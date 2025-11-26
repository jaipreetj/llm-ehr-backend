<?php

function chunkText($text, $maxWords = 300) {
    $words = explode(" ", $text);
    $chunks = [];

    for ($i = 0; $i < count($words); $i += $maxWords) {
        $chunk = array_slice($words, $i, $maxWords);
        $chunks[] = implode(" ", $chunk);
    }

    return $chunks;
}

?>
