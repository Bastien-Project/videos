<?php

// ── Configuration ──────────────────────────────────────────────────────────────
$DOSSIER  = "D:/film/";          // Dossier vidéos (avec slash final)
$FFMPEG   = "ffmpeg";            // Chemin vers ffmpeg (ex: "C:/ffmpeg/bin/ffmpeg")
$CACHE    = __DIR__ . "/thumbs/"; // Dossier cache miniatures
$SEEK     = 30;                  // Secondes pour la capture de miniature
$WIDTH    = 320;                 // Largeur miniature en px
// ──────────────────────────────────────────────────────────────────────────────

if (!is_dir($CACHE)) {
    mkdir($CACHE, 0755, true);
}

$video = basename($_GET["v"] ?? "");
if (!$video) {
    http_response_code(400);
    exit;
}

$file  = $DOSSIER . $video;
if (!file_exists($file)) {
    http_response_code(404);
    exit;
}

$thumb = $CACHE . md5($video) . ".jpg";

// Génère la miniature si elle n'existe pas encore
if (!file_exists($thumb)) {
    $cmd = sprintf(
        '%s -ss %d -i %s -vframes 1 -vf "scale=%d:-1" -q:v 3 %s 2>%s',
        $FFMPEG,
        $SEEK,
        escapeshellarg($file),
        $WIDTH,
        escapeshellarg($thumb),
        PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null"
    );
    exec($cmd);

    // Si la vidéo fait moins de $SEEK secondes, retenter à 2s
    if (!file_exists($thumb)) {
        $cmd = sprintf(
            '%s -ss 2 -i %s -vframes 1 -vf "scale=%d:-1" -q:v 3 %s 2>%s',
            $FFMPEG,
            escapeshellarg($file),
            $WIDTH,
            escapeshellarg($thumb),
            PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null"
        );
        exec($cmd);
    }
}

if (file_exists($thumb)) {
    header("Content-Type: image/jpeg");
    header("Cache-Control: public, max-age=86400");
    readfile($thumb);
} else {
    // Miniature de secours : SVG générique
    header("Content-Type: image/svg+xml");
    $ext = strtoupper(pathinfo($video, PATHINFO_EXTENSION));
    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180">
  <rect width="320" height="180" fill="#1a1a2e"/>
  <polygon points="120,60 220,90 120,120" fill="#e94560"/>
  <text x="160" y="155" text-anchor="middle" font-family="monospace" font-size="13" fill="#888">$ext</text>
</svg>
SVG;
}
