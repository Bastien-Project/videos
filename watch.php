<?php

// ── Configuration ──────────────────────────────────────────────────────────────
$DOSSIER = "D:/useless/";
$FFMPEG  = "ffmpeg";
// ──────────────────────────────────────────────────────────────────────────────

// Fonction de sécurité : vérifie que le chemin reste bien dans $DOSSIER
function safePath($DOSSIER, $input)
{
    // Supprimer les tentatives de traversée
    $rel  = str_replace(['../', './', '..\\', '.\\', '..'], '', $input);
    $rel  = str_replace('\\', '/', $rel);
    $path = rtrim($DOSSIER, '/\\') . '/' . ltrim($rel, '/\\');
    // Vérification directe sans realpath (qui échoue sur les accents sous Windows)
    if (!file_exists($path)) return null;
    return $path;
}
if (isset($_GET["stream"])) {

    $file = safePath($DOSSIER, $_GET["stream"] ?? "");
    if (!$file) {
        http_response_code(403);
        exit;
    }

    $ext     = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $preview = isset($_GET["preview"]);

    // MP4 natif : streaming direct avec support Range
    if ($ext === "mp4" && !$preview) {

        $size  = filesize($file);
        $start = 0;
        $end   = $size - 1;

        header("Content-Type: video/mp4");
        header("Accept-Ranges: bytes");

        if (isset($_SERVER["HTTP_RANGE"])) {
            preg_match('/bytes=(\d+)-(\d*)/', $_SERVER["HTTP_RANGE"], $m);
            $start = intval($m[1]);
            if (!empty($m[2])) $end = intval($m[2]);
            header("HTTP/1.1 206 Partial Content");
        }

        $length = $end - $start + 1;
        header("Content-Length: $length");
        header("Content-Range: bytes $start-$end/$size");

        $fp = fopen($file, "rb");
        fseek($fp, $start);
        while (!feof($fp) && ftell($fp) <= $end) {
            echo fread($fp, 65536);
            flush();
        }
        fclose($fp);
        exit;
    }

    // AVI / MKV (ou preview MP4) : transcodage FFmpeg → MP4 fragmenté
    header("Content-Type: video/mp4");
    header("Cache-Control: no-cache");

    $timeLimit = $preview ? "-t 15" : "";

    $cmd = sprintf(
        '%s -i %s %s -f mp4 -movflags frag_keyframe+empty_moov+faststart ' .
            '-vcodec libx264 -preset ultrafast -crf 23 ' .
            '-acodec aac -b:a 128k pipe:1 2>%s',
        $FFMPEG,
        escapeshellarg($file),
        $timeLimit,
        PHP_OS_FAMILY === "Windows" ? "NUL" : "/dev/null"
    );

    $handle = popen($cmd, "r");
    if (!$handle) {
        http_response_code(500);
        exit;
    }

    while (!feof($handle)) {
        echo fread($handle, 65536);
        flush();
    }

    pclose($handle);
    exit;
}

// ── Page lecteur ──────────────────────────────────────────────────────────────
$videoRel = $_GET["video"] ?? "";
$file     = safePath($DOSSIER, $videoRel) ?? "";
$video    = $file ? basename($file) : "";
$titre    = htmlspecialchars(pathinfo($video, PATHINFO_FILENAME));
$ext      = strtolower(pathinfo($video, PATHINFO_EXTENSION));
$src      = "watch.php?stream=" . urlencode($videoRel);

// Formats non lisibles dans le navigateur → ouvrir l'explorateur sur le dossier
if ($ext !== "mp4") {

    $error = "";

    if (!$file || !file_exists($file)) {
        $attempted = rtrim($DOSSIER, '/\\') . '/' . ltrim($videoRel, '/\\');
        $error = "Fichier introuvable : " . htmlspecialchars($attempted);
    } else {
        $bat = sys_get_temp_dir() . '/open_folder.bat';
        file_put_contents($bat, '@explorer /select,"' . str_replace('/', '\\', $file) . '"');
        pclose(popen('cmd /c "' . $bat . '"', 'r'));
    }

?>
    <!DOCTYPE html>
    <html lang="fr">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $titre ?></title>
        <link rel="stylesheet" href="./style/watch.css">
    </head>

    <body>

        <div class="icon">📂</div>
        <h1><?= $titre ?></h1>

        <?php if ($error): ?>
            <div class="status error">⚠ <?= $error ?></div>
        <?php else: ?>
            <div class="status ok">✓ Dossier ouvert dans l'explorateur</div>
        <?php endif; ?>

        <a href="index.php" class="back">← Retour à la médiathèque</a>

    </body>

    </html>
<?php
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $titre ?></title>
    <link rel="stylesheet" href="./style/watch-bis.css">
</head>

<body>

    <a href="index.php" class="back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M5 12l7-7M5 12l7 7" />
        </svg>
        Médiathèque
    </a>

    <h1><?= $titre ?></h1>

    <div class="player-wrap">
        <video controls autoplay>
            <source src="<?= $src ?>" type="video/mp4">
            Votre navigateur ne supporte pas la lecture vidéo.
        </video>
    </div>

</body>

</html>