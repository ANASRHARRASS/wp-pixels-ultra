<?php
/**
 * Simple PHP CLI tool to scan the plugin folder for .json files and validate them.
 *
 * Usage:
 *   php tools/scan-json.php
 *
 * Output:
 *   - Console summary
 *   - creates/overwrites "scan-report.json" in plugin root with detailed results
 */

$pluginRoot = realpath(__DIR__ . '/..');
if ($pluginRoot === false) {
    fwrite(STDERR, "Cannot resolve plugin root\n");
    exit(1);
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot));
$jsonFiles = [];
foreach ($rii as $file) {
    if ($file->isFile() && preg_match('/\.json$/i', $file->getFilename())) {
        $jsonFiles[] = $file->getPathname();
    }
}

$results = [
    'scanned_at' => date('c'),
    'plugin_root' => $pluginRoot,
    'total_files' => count($jsonFiles),
    'valid' => [],
    'invalid' => [],
];

foreach ($jsonFiles as $path) {
    $rel = str_replace($pluginRoot . DIRECTORY_SEPARATOR, '', $path);
    $content = @file_get_contents($path);
    if ($content === false) {
        $results['invalid'][] = [
            'file' => $rel,
            'error' => 'Cannot read file',
        ];
        continue;
    }

    if (trim($content) === '') {
        $results['invalid'][] = [
            'file' => $rel,
            'error' => 'Empty file',
        ];
        continue;
    }

    json_decode($content, true);
    $err = json_last_error();
    if ($err === JSON_ERROR_NONE) {
        $results['valid'][] = $rel;
    } else {
        $results['invalid'][] = [
            'file' => $rel,
            'error_code' => $err,
            'error_message' => json_last_error_msg(),
        ];
    }
}

echo "JSON Scan Report\n";
echo "================\n";
echo "Plugin root: {$results['plugin_root']}\n";
echo "Scanned at  : {$results['scanned_at']}\n";
echo "Total files : {$results['total_files']}\n";
echo "Valid files : " . count($results['valid']) . "\n";
echo "Invalid files: " . count($results['invalid']) . "\n\n";

if (count($results['invalid']) > 0) {
    echo "Invalid files detail:\n";
    foreach ($results['invalid'] as $inv) {
        $msg = isset($inv['error_message']) ? $inv['error_message'] : (isset($inv['error']) ? $inv['error'] : 'Unknown');
        $code = isset($inv['error_code']) ? $inv['error_code'] : '';
        echo "- {$inv['file']}: {$msg}" . ($code !== '' ? " (code: {$code})" : "") . "\n";
    }
    echo "\n";
}

$reportPath = $pluginRoot . DIRECTORY_SEPARATOR . 'scan-report.json';
if (@file_put_contents($reportPath, json_encode($results, JSON_PRETTY_PRINT))) {
    echo "Report written to: {$reportPath}\n";
} else {
    fwrite(STDERR, "Failed to write report to {$reportPath}\n");
}

exit(count($results['invalid']) > 0 ? 2 : 0);
