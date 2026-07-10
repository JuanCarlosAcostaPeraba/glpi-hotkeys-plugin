<?php
declare(strict_types=1);

/**
 * GLPI Hotkeys Plugin - Translation Compiler Tool
 * This script compiles .po files into .mo binary files.
 */

function parse_po(string $content): array {
    $lines = explode("\n", $content);
    $translations = [];
    
    $current_msgid = null;
    $current_msgstr = null;
    $state = null; // 'msgid' or 'msgstr'
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        
        if (preg_match('/^msgid\s+"(.*)"$/', $line, $matches)) {
            if ($current_msgid !== null && $current_msgstr !== null) {
                $translations[$current_msgid] = $current_msgstr;
            }
            $current_msgid = stripcslashes($matches[1]);
            $current_msgstr = null;
            $state = 'msgid';
        } elseif (preg_match('/^msgstr\s+"(.*)"$/', $line, $matches)) {
            $current_msgstr = stripcslashes($matches[1]);
            $state = 'msgstr';
        } elseif (preg_match('/^"(.*)"$/', $line, $matches)) {
            $val = stripcslashes($matches[1]);
            if ($state === 'msgid') {
                $current_msgid .= $val;
            } elseif ($state === 'msgstr') {
                $current_msgstr .= $val;
            }
        }
    }
    
    if ($current_msgid !== null && $current_msgstr !== null) {
        $translations[$current_msgid] = $current_msgstr;
    }
    
    return $translations;
}

function compile_po_to_mo(string $po_path, string $mo_path): void {
    $content = file_get_contents($po_path);
    if ($content === false) {
        throw new Exception("Cannot read PO file: {$po_path}");
    }
    
    $translations = parse_po($content);
    
    // Sort original strings alphabetically (essential for gettext binary search)
    ksort($translations);
    
    $num_strings = count($translations);
    
    $orig_data = '';
    $trans_data = '';
    
    $orig_offset = 28 + ($num_strings * 8) * 2;
    
    $current_orig_offset = $orig_offset;
    $orig_descriptors = [];
    foreach ($translations as $orig => $trans) {
        $len = strlen($orig);
        $orig_descriptors[] = [$len, $current_orig_offset];
        $orig_data .= $orig . "\0";
        $current_orig_offset += $len + 1;
    }
    
    $current_trans_offset = $current_orig_offset;
    $trans_descriptors = [];
    foreach ($translations as $orig => $trans) {
        $len = strlen($trans);
        $trans_descriptors[] = [$len, $current_trans_offset];
        $trans_data .= $trans . "\0";
        $current_trans_offset += $len + 1;
    }
    
    // Write MO Header
    // Magic: 0x950412de
    $mo = pack('I7', 
        0x950412de, 
        0, 
        $num_strings, 
        28, 
        28 + $num_strings * 8, 
        0, 
        0
    );
    
    // Write original string descriptors
    foreach ($orig_descriptors as $desc) {
        $mo .= pack('II', $desc[0], $desc[1]);
    }
    
    // Write translation descriptors
    foreach ($trans_descriptors as $desc) {
        $mo .= pack('II', $desc[0], $desc[1]);
    }
    
    // Write actual string data
    $mo .= $orig_data . $trans_data;
    
    if (file_put_contents($mo_path, $mo) === false) {
        throw new Exception("Cannot write MO file: {$mo_path}");
    }
}

// Run compilation on all PO files in the locales directory
$locales_dir = dirname(__DIR__) . '/locales';
if (!is_dir($locales_dir)) {
    echo "Locales directory not found: {$locales_dir}\n";
    exit(1);
}

$files = scandir($locales_dir);
$po_files = [];
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'po') {
        $po_files[] = $locales_dir . '/' . $file;
    }
}

if (empty($po_files)) {
    echo "No .po files found in {$locales_dir}\n";
    exit(0);
}

foreach ($po_files as $po_file) {
    $mo_file = substr($po_file, 0, -3) . '.mo';
    try {
        compile_po_to_mo($po_file, $mo_file);
        echo "Successfully compiled: " . basename($po_file) . " -> " . basename($mo_file) . "\n";
    } catch (Exception $e) {
        echo "Error compiling " . basename($po_file) . ": " . $e->getMessage() . "\n";
        exit(1);
    }
}
