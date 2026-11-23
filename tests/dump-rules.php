<?php
// Mencetak daftar field validasi tiap controller API sebagai referensi frontend.
foreach (glob(__DIR__ . '/../app/Http/Controllers/Api/*Controller.php') as $file) {
    $name = basename($file, '.php');
    $src = file_get_contents($file);
    preg_match_all('/(public function (\w+)\([\s\S]*?)\$request->validate\(\[(.*?)\]\s*\);/s', $src, $blocks);
    echo "== {$name}\n";
    foreach ($blocks[2] as $i => $method) {
        $fields = [];
        preg_match_all("/'([a-z0-9_.\[\]*]+)'\s*=>\s*'([^']+)'/i", $blocks[3][$i], $rules);
        foreach ($rules[1] as $j => $field) {
            $fields[] = "{$field} ({$rules[2][$j]})";
        }
        echo "  {$method}: " . implode(' | ', $fields) . "\n";
    }
}
