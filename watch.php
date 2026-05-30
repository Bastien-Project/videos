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

$TAGS_FILE = __DIR__ . "/tags.json";
$tagsData  = [];
if (file_exists($TAGS_FILE)) {
    $tagsData = json_decode(file_get_contents($TAGS_FILE), true) ?? [];
}

$currentTags = $tagsData[$videoRel] ?? [];
$allTags     = [];
foreach ($tagsData as $videoTags) {
    if (!is_array($videoTags)) continue;
    foreach ($videoTags as $tag) {
        if (!in_array($tag, $allTags, true)) {
            $allTags[] = $tag;
        }
    }
}
sort($allTags);

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

    <section class="tags-panel" aria-labelledby="tags-title">
        <div class="tags-header">
            <h2 id="tags-title">Tags</h2>
            <span class="tags-count" id="tags-count"></span>
        </div>

        <div class="tags-list" id="tags-list"></div>

        <div class="tags-controls">
            <input id="tag-input" type="text" list="tag-suggestions" placeholder="Ajouter un tag… (Entrée pour valider)">
            <button type="button" class="tag-btn" id="add-tag-btn">Ajouter</button>
            <button type="button" class="tag-btn secondary" id="save-tags-btn">Enregistrer</button>
        </div>

        <datalist id="tag-suggestions">
            <?php foreach ($allTags as $tag): ?>
                <option value="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <div class="tags-status" id="tags-status"></div>
    </section>

    <script>
        (function() {
            const videoFile = <?= json_encode($videoRel) ?>;
            let currentTags = <?= json_encode(array_values($currentTags)) ?>;
            let knownTags = <?= json_encode(array_values($allTags)) ?>;

            const tagsList = document.getElementById("tags-list");
            const tagsCount = document.getElementById("tags-count");
            const tagInput = document.getElementById("tag-input");
            const tagSuggestions = document.getElementById("tag-suggestions");
            const tagsStatus = document.getElementById("tags-status");
            const addTagBtn = document.getElementById("add-tag-btn");
            const saveTagsBtn = document.getElementById("save-tags-btn");

            function normalizeTag(value) {
                return value.trim().toLowerCase().replace(/,/g, "");
            }

            function setStatus(message, type) {
                tagsStatus.textContent = message;
                tagsStatus.dataset.type = type || "";
            }

            function renderTags() {
                tagsList.innerHTML = "";
                tagsCount.textContent = currentTags.length === 0
                    ? "Aucun tag"
                    : currentTags.length + " tag" + (currentTags.length > 1 ? "s" : "");

                if (currentTags.length === 0) {
                    const empty = document.createElement("span");
                    empty.className = "tags-empty";
                    empty.textContent = "Aucun tag";
                    tagsList.appendChild(empty);
                    return;
                }

                currentTags.forEach(tag => {
                    const item = document.createElement("span");
                    item.className = "tag-chip";

                    const label = document.createElement("span");
                    label.className = "tag-chip-label";
                    label.textContent = tag;

                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "tag-chip-remove";
                    button.setAttribute("aria-label", "Supprimer le tag " + tag);
                    button.textContent = "×";
                    button.addEventListener("click", () => {
                        currentTags = currentTags.filter(currentTag => currentTag !== tag);
                        renderTags();
                        saveTags(true);
                    });

                    item.appendChild(label);
                    item.appendChild(button);
                    tagsList.appendChild(item);
                });
            }

            function renderSuggestions() {
                tagSuggestions.innerHTML = "";
                knownTags.forEach(tag => {
                    const option = document.createElement("option");
                    option.value = tag;
                    tagSuggestions.appendChild(option);
                });
            }

            function addTagFromInput() {
                const tag = normalizeTag(tagInput.value);
                if (!tag || currentTags.includes(tag)) {
                    tagInput.value = "";
                    return;
                }

                currentTags.push(tag);
                if (!knownTags.includes(tag)) {
                    knownTags.push(tag);
                    knownTags.sort();
                    renderSuggestions();
                }

                tagInput.value = "";
                renderTags();
                saveTags(true);
            }

            function saveTags(silent) {
                setStatus(silent ? "Sauvegarde…" : "Enregistrement…", "pending");

                fetch("tags_save.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        file: videoFile,
                        tags: currentTags
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.ok) {
                            setStatus("Tags enregistrés", "ok");
                            return;
                        }
                        setStatus("Erreur lors de la sauvegarde", "error");
                    })
                    .catch(() => {
                        setStatus("Erreur lors de la sauvegarde", "error");
                    });
            }

            tagInput.addEventListener("keydown", event => {
                if (event.key === "Enter" || event.key === ",") {
                    event.preventDefault();
                    addTagFromInput();
                }
            });

            addTagBtn.addEventListener("click", addTagFromInput);
            saveTagsBtn.addEventListener("click", () => saveTags(false));

            renderSuggestions();
            renderTags();
        })();
    </script>

</body>

</html>