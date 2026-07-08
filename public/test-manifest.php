<?php
$path = __DIR__ . '/build/manifest.json';
echo "<h3>Vite Manifest Diagnostic Test</h3>";
echo "Checking path: <strong>" . $path . "</strong><br>";

if (file_exists($path)) {
    echo "<span style='color: green;'>✔ File exists!</span><br>";
    echo "Size: " . filesize($path) . " bytes<br>";
    echo "Readable: " . (is_readable($path) ? 'Yes' : 'No') . "<br>";
} else {
    echo "<span style='color: red;'>✘ File does NOT exist!</span><br><br>";
    
    echo "<strong>Checking build/ directory contents:</strong><br>";
    if (is_dir(__DIR__ . '/build')) {
        $files = scandir(__DIR__ . '/build');
        echo "<pre>" . print_r($files, true) . "</pre>";
    } else {
        echo "build/ directory does not exist or is not readable!<br>";
    }
}
