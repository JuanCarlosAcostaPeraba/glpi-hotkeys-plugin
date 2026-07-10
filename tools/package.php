<?php
declare(strict_types=1);

/**
 * GLPI Hotkeys Plugin - Release Packaging Tool
 * This script stages files and builds the release archives:
 * - glpi-hotkeys-plugin-1.0.0.zip
 * - glpi-hotkeys-plugin-1.0.0.tar.gz
 * containing the root folder 'hotkeys/'.
 */

$version = '1.0.0';
$plugin_key = 'hotkeys';
$root_dir = dirname(__DIR__);
$staging_dir = $root_dir . '/build_staging';
$target_dir = $staging_dir . '/' . $plugin_key;

echo "Starting release packaging for Hotkeys v{$version}...\n";

// 1. Run assets build
echo "Building assets...\n";
exec('npm run build', $build_output, $build_status);
if ($build_status !== 0) {
    echo "Asset build failed:\n" . implode("\n", $build_output) . "\n";
    exit(1);
}

// 2. Clean previous build staging
if (is_dir($staging_dir)) {
    echo "Cleaning old staging directory...\n";
    if (PHP_OS_FAMILY === 'Windows') {
        exec("rmdir /s /q \"{$staging_dir}\"");
    } else {
        exec("rm -rf \"{$staging_dir}\"");
    }
}

// Create staging directories
mkdir($staging_dir, 0777, true);
mkdir($target_dir, 0777, true);

// 3. Define files and directories to copy
$files_to_copy = [
    'setup.php',
    'hook.php',
    'hotkeys.xml',
    'hotkeys.png',
    'LICENSE',
    'README.md',
    'CHANGELOG.md'
];

$dirs_to_copy = [
    'src',
    'front',
    'templates',
    'public',
    'locales'
];

// Helper: copy directory recursively
function copy_dir(string $src, string $dst): void {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file !== '.') && ($file !== '..')) {
            if (is_dir($src . '/' . $file)) {
                copy_dir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// Copy files
echo "Staging files...\n";
foreach ($files_to_copy as $file) {
    $src_path = $root_dir . '/' . $file;
    if (file_exists($src_path)) {
        copy($src_path, $target_dir . '/' . $file);
    } else {
        echo "Warning: Required file missing: {$file}\n";
    }
}

// Copy directories
foreach ($dirs_to_copy as $dir) {
    $src_path = $root_dir . '/' . $dir;
    if (is_dir($src_path)) {
        copy_dir($src_path, $target_dir . '/' . $dir);
    }
}

// 4. Create ZIP archive
$zip_file = $root_dir . "/glpi-hotkeys-plugin-{$version}.zip";
if (file_exists($zip_file)) {
    unlink($zip_file);
}

echo "Creating ZIP archive...\n";
$zip_created = false;

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staging_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($staging_dir) + 1);
                $zip_path = str_replace('\\', '/', $relative_path);
                $zip->addFile($file_path, $zip_path);
            }
        }
        $zip->close();
        $zip_created = true;
        echo "ZIP archive created via ZipArchive.\n";
    }
}

if (!$zip_created) {
    // Fall back to system utilities
    if (PHP_OS_FAMILY === 'Windows') {
        echo "ZipArchive not available. Falling back to PowerShell Compress-Archive...\n";
        // Run PowerShell Compress-Archive
        $cmd = "powershell -Command \"Compress-Archive -Path '{$staging_dir}/*' -DestinationPath '{$zip_file}' -Force\"";
        exec($cmd, $out, $status);
        if ($status === 0 && file_exists($zip_file)) {
            $zip_created = true;
            echo "ZIP archive created via PowerShell.\n";
        }
    } else {
        echo "ZipArchive not available. Falling back to zip command...\n";
        $cmd = "cd \"{$staging_dir}\" && zip -r \"{$zip_file}\" {$plugin_key}";
        exec($cmd, $out, $status);
        if ($status === 0 && file_exists($zip_file)) {
            $zip_created = true;
            echo "ZIP archive created via system zip.\n";
        }
    }
}

if (!$zip_created) {
    echo "Error: Failed to create ZIP archive.\n";
    exit(1);
}

// 5. Create TAR.GZ archive
$tgz_file = $root_dir . "/glpi-hotkeys-plugin-{$version}.tar.gz";
if (file_exists($tgz_file)) {
    unlink($tgz_file);
}

echo "Creating TAR.GZ archive...\n";
$tar_created = false;

// Try PharData first
if (class_exists('PharData') && !ini_get('phar.readonly')) {
    $tar_file = $root_dir . "/glpi-hotkeys-plugin-{$version}.tar";
    if (file_exists($tar_file)) {
        unlink($tar_file);
    }
    try {
        $tar = new PharData($tar_file);
        $tar->buildFromDirectory($staging_dir);
        $tar->compress(Phar::GZ);
        if (file_exists($tar_file)) {
            unlink($tar_file);
        }
        if (file_exists($tgz_file)) {
            $tar_created = true;
            echo "TAR.GZ archive created via PharData.\n";
        }
    } catch (Exception $e) {
        if (file_exists($tar_file)) {
            unlink($tar_file);
        }
    }
}

if (!$tar_created) {
    // Fall back to system utilities
    if (PHP_OS_FAMILY === 'Windows') {
        echo "PharData not available or read-only. Falling back to PowerShell Tar...\n";
        // tar is available natively in Windows 10/11 (bsdtar)
        // bsdtar expects forward slashes or escaped paths. Let's change directory to build_staging and run.
        $cmd = "powershell -Command \"cd '{$staging_dir}'; tar -czf '{$tgz_file}' {$plugin_key}\"";
        exec($cmd, $out, $status);
        if ($status === 0 && file_exists($tgz_file)) {
            $tar_created = true;
            echo "TAR.GZ archive created via Windows tar.\n";
        }
    } else {
        echo "PharData not available. Falling back to tar command...\n";
        $cmd = "cd \"{$staging_dir}\" && tar -czf \"{$tgz_file}\" {$plugin_key}";
        exec($cmd, $out, $status);
        if ($status === 0 && file_exists($tgz_file)) {
            $tar_created = true;
            echo "TAR.GZ archive created via system tar.\n";
        }
    }
}

if (!$tar_created) {
    echo "Error: Failed to create TAR.GZ archive.\n";
    exit(1);
}

// 6. Cleanup build staging
echo "Cleaning up staging directory...\n";
if (PHP_OS_FAMILY === 'Windows') {
    exec("rmdir /s /q \"{$staging_dir}\"");
} else {
    exec("rm -rf \"{$staging_dir}\"");
}

echo "Packaging complete successfully!\n";
exit(0);
