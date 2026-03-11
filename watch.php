<?php

// ── Configuration ──────────────────────────────────────────────────────────────
$DOSSIER = "D:/film/";
$FFMPEG  = "ffmpeg";
// ──────────────────────────────────────────────────────────────────────────────

// ── Endpoint de streaming (?stream=fichier[&preview=1]) ───────────────────────
if (isset($_GET["stream"])) {

    $video = basename($_GET["stream"]);
    $file  = $DOSSIER . $video;

    if (!file_exists($file)) {
        http_response_code(404);
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
$video = basename($_GET["video"] ?? "");
$titre = htmlspecialchars(pathinfo($video, PATHINFO_FILENAME));
$ext   = strtolower(pathinfo($video, PATHINFO_EXTENSION));
$file  = $DOSSIER . $video;
$src   = "watch.php?stream=" . urlencode($video);

// Formats non lisibles dans le navigateur → ouvrir l'explorateur sur le dossier
if ($ext !== "mp4") {

    $error = "";

    if (!file_exists($file)) {
        $error = "Fichier introuvable : " . htmlspecialchars($file);
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
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700&display=swap');

            *,
            *::before,
            *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                background: #0a0a0f;
                color: #e0e0e0;
                font-family: 'Syne', sans-serif;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 28px;
                padding: 40px 20px;
                text-align: center;
            }

            .icon {
                font-size: 4rem;
            }

            h1 {
                font-size: clamp(1.1rem, 3vw, 1.8rem);
                font-weight: 700;
                max-width: 640px;
                line-height: 1.3;
            }

            .status {
                font-family: 'Space Mono', monospace;
                font-size: 13px;
                padding: 10px 22px;
                border-radius: 6px;
                letter-spacing: .04em;
            }

            .status.ok {
                background: rgba(60, 180, 80, .12);
                border: 1px solid rgba(60, 180, 80, .35);
                color: #5ddb7a;
            }

            .status.error {
                background: rgba(233, 69, 96, .12);
                border: 1px solid rgba(233, 69, 96, .35);
                color: #e94560;
            }

            .back {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: #888;
                text-decoration: none;
                font-family: 'Space Mono', monospace;
                font-size: 13px;
                transition: color .2s;
            }

            .back:hover {
                color: #e94560;
            }
        </style>
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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700&display=swap');

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #0a0a0f;
            color: #e0e0e0;
            font-family: 'Syne', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
        }

        .back {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #888;
            text-decoration: none;
            font-family: 'Space Mono', monospace;
            font-size: 13px;
            letter-spacing: .05em;
            margin-bottom: 32px;
            transition: color .2s;
        }

        .back:hover {
            color: #e94560;
        }

        .back svg {
            width: 16px;
            height: 16px;
        }

        h1 {
            font-size: clamp(1.2rem, 3vw, 2rem);
            font-weight: 700;
            letter-spacing: -.02em;
            text-align: center;
            margin-bottom: 28px;
            max-width: 900px;
            line-height: 1.2;
        }

        .player-wrap {
            width: 100%;
            max-width: 960px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 0 0 1px rgba(233, 69, 96, .25), 0 24px 80px rgba(0, 0, 0, .8);
            background: #000;
        }

        video {
            width: 100%;
            display: block;
            max-height: 80vh;
        }
    </style>
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