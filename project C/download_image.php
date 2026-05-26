<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

function downloadImage($url, $filename) {
    $uploadsDir = 'uploads/';

    // Create uploads directory if it doesn't exist
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Get image content
    $imageContent = file_get_contents($url);

    if ($imageContent === false) {
        return false;
    }

    // Save image to uploads folder
    $filePath = $uploadsDir . $filename;
    $result = file_put_contents($filePath, $imageContent);

    if ($result === false) {
        return false;
    }

    return $filePath;
}

// Example usage for the two users
if (isset($_GET['download'])) {
    $kudaUrl = "https://www.google.com/url?sa=i&url=https%3A%2F%2Fwww.shutterstock.com%2Fsearch%2Fzimbabwe-man&psig=AOvVaw28CKtAX6cBGo7KivMklL1c&ust=1761287612608000&source=images&cd=vfe&opi=89978449&ved=0CBUQjRxqFwoTCIif4P3ZuZADFQAAAAAdAAAAABAE";
    $tanatswaUrl = "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQpbHv3EFPD6tuWd-454ivCgvkPmjrotTwSlA&s";

    $kudaFilename = 'kuda_mapinga_' . time() . '.jpg';
    $tanatswaFilename = 'tanatswa_sibanda_' . time() . '.jpg';

    $kudaPath = downloadImage($kudaUrl, $kudaFilename);
    $tanatswaPath = downloadImage($tanatswaUrl, $tanatswaFilename);

    if ($kudaPath && $tanatswaPath) {
        echo json_encode([
            'success' => true,
            'message' => 'Images downloaded successfully',
            'kuda_avatar' => $kudaPath,
            'tanatswa_avatar' => $tanatswaPath
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to download images'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?>
