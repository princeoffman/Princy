<?php
require_once __DIR__ . '/api_response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_request_method('GET');

/**
 * Downloads a remote image into uploads/.
 *
 * @throws RuntimeException when the directory, download or write fails, so the
 *         caller can report why it failed instead of only that it failed.
 */
function downloadImage(string $url, string $filename): string
{
    $uploadsDir = __DIR__ . '/uploads';

    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        throw new RuntimeException("Could not create uploads directory: $uploadsDir");
    }

    $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 1]]);
    $imageContent = @file_get_contents($url, false, $context);
    if ($imageContent === false) {
        $error = error_get_last();
        throw new RuntimeException("Could not download $url: " . ($error['message'] ?? 'unknown error'));
    }

    if ($imageContent === '') {
        throw new RuntimeException("Downloaded an empty body from $url");
    }

    $filePath = $uploadsDir . '/' . $filename;
    if (file_put_contents($filePath, $imageContent) === false) {
        throw new RuntimeException("Could not write $filePath");
    }

    return 'uploads/' . $filename;
}

if (!isset($_GET['download'])) {
    json_fail('Invalid request: missing download parameter', 400);
}

$downloads = [
    'kuda_avatar' => [
        'url' => "https://www.google.com/url?sa=i&url=https%3A%2F%2Fwww.shutterstock.com%2Fsearch%2Fzimbabwe-man&psig=AOvVaw28CKtAX6cBGo7KivMklL1c&ust=1761287612608000&source=images&cd=vfe&opi=89978449&ved=0CBUQjRxqFwoTCIif4P3ZuZADFQAAAAAdAAAAABAE",
        'filename' => 'kuda_mapinga_' . time() . '.jpg',
    ],
    'tanatswa_avatar' => [
        'url' => "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQpbHv3EFPD6tuWd-454ivCgvkPmjrotTwSlA&s",
        'filename' => 'tanatswa_sibanda_' . time() . '.jpg',
    ],
];

$paths = [];
$errors = [];
foreach ($downloads as $key => $download) {
    try {
        $paths[$key] = downloadImage($download['url'], $download['filename']);
    } catch (RuntimeException $e) {
        // Report which download failed and why, rather than a single generic message.
        $errors[$key] = $e->getMessage();
        error_log("download_image.php: $key failed: " . $e->getMessage());
    }
}

if ($errors) {
    json_response([
        'success' => false,
        'message' => 'Failed to download images: ' . implode('; ', array_keys($errors)),
        'errors' => $errors,
        'downloaded' => $paths
    ], 502);
    exit;
}

json_success(['message' => 'Images downloaded successfully'] + $paths);
