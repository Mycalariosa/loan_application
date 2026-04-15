<?php
// Simple file browser for uploads directory
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size >= 1024 && $i < 3; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

function scanDirectory($dir, $basePath = '') {
    $items = [];
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file[0] !== '.') {
                $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                $relativePath = $basePath ? $basePath . '/' . $file : $file;
                $items[] = [
                    'name' => $file,
                    'path' => $relativePath,
                    'is_dir' => is_dir($fullPath),
                    'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                    'modified' => filemtime($fullPath)
                ];
            }
        }
    }
    return $items;
}

$uploadsPath = UPLOAD_PATH;
$currentPath = $_GET['path'] ?? '';
$fullPath = $uploadsPath . ($currentPath ? '/' . $currentPath : '');

// Security: prevent directory traversal
$currentPath = str_replace('..', '', $currentPath);
$fullPath = $uploadsPath . ($currentPath ? '/' . $currentPath : '');

$items = scanDirectory($fullPath, $currentPath);
$breadcrumb = $currentPath ? explode('/', $currentPath) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uploads Browser - Alpha Loans</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Uploads Directory Browser</h1>
        
        <!-- Breadcrumb -->
        <nav class="mb-4">
            <ol class="flex items-center space-x-2">
                <li><a href="?" class="text-blue-600 hover:underline">uploads</a></li>
                <?php foreach ($breadcrumb as $i => $crumb): ?>
                    <li class="flex items-center">
                        <span class="mx-2">/</span>
                        <?php 
                        $crumbPath = implode('/', array_slice($breadcrumb, 0, $i + 1));
                        ?>
                        <a href="?path=<?= urlencode($crumbPath) ?>" class="text-blue-600 hover:underline"><?= htmlspecialchars($crumb) ?></a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <!-- File List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modified</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No files found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <?php if ($item['is_dir']): ?>
                                        <a href="?path=<?= urlencode($item['path']) ?>" class="flex items-center text-blue-600 hover:underline">
                                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                                            </svg>
                                            <?= htmlspecialchars($item['name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <?= htmlspecialchars($item['name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?= $item['is_dir'] ? 'Directory' : formatBytes($item['size']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?= date('Y-m-d H:i', $item['modified']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?= $item['is_dir'] ? 'Folder' : strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-sm text-gray-600">
            <p>Total items: <?= count($items) ?></p>
            <p class="mt-2">Current path: <code><?= htmlspecialchars($currentPath ?: 'uploads') ?></code></p>
        </div>
    </div>
</body>
</html>
