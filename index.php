<?php

// ── Configuration ──────────────────────────────────────────────────────────────
$DOSSIER     = "D:/film/";
$EXTENSIONS  = ["mp4", "mkv", "avi"];
// ──────────────────────────────────────────────────────────────────────────────

$videos = [];
foreach ($EXTENSIONS as $ext) {
    foreach (glob($DOSSIER . "*.$ext") as $v) {
        $videos[] = basename($v);
    }
}
sort($videos, SORT_NATURAL | SORT_FLAG_CASE);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Médiathèque</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;700;800&display=swap');

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --red: #e94560;
            --bg: #0a0a0f;
            --card: #111118;
            --border: rgba(255, 255, 255, .07);
            --text: #e0e0e0;
            --muted: #666;
        }

        html {
            scrollbar-color: var(--red) var(--bg);
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Syne', sans-serif;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        /* ── Header ── */
        header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(10, 10, 15, .85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 18px 32px;
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
        }

        .logo {
            font-family: 'Space Mono', monospace;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: -.03em;
            color: var(--text);
            white-space: nowrap;
        }

        .logo span {
            color: var(--red);
        }

        .count {
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--muted);
            letter-spacing: .08em;
        }

        .search-wrap {
            flex: 1;
            min-width: 200px;
            max-width: 480px;
            position: relative;
            margin-left: auto;
        }

        .search-wrap svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--muted);
            pointer-events: none;
        }

        #search {
            width: 100%;
            padding: 10px 16px 10px 40px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Space Mono', monospace;
            font-size: 13px;
            outline: none;
            transition: border-color .2s, background .2s;
        }

        #search:focus {
            border-color: rgba(233, 69, 96, .5);
            background: rgba(233, 69, 96, .04);
        }

        #search::placeholder {
            color: var(--muted);
        }

        /* ── Grille ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            padding: 32px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ── Carte ── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: transform .2s, border-color .2s, box-shadow .2s;
            position: relative;
        }

        .card:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(233, 69, 96, .4);
            box-shadow: 0 12px 40px rgba(0, 0, 0, .6), 0 0 0 1px rgba(233, 69, 96, .15);
        }

        /* ── Zone miniature / aperçu ── */
        .thumb-zone {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #0d0d14;
            overflow: hidden;
        }

        .thumb-zone canvas,
        .thumb-zone video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* La vidéo est cachée par défaut */
        .thumb-zone .preview-video {
            opacity: 0;
            transition: opacity .25s;
        }

        .card:hover .preview-video.loaded {
            opacity: 1;
        }

        /* Badge format */
        .badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 4px;
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            padding: 2px 7px;
            color: #ccc;
            z-index: 2;
        }

        /* Icône Play au centre */
        .play-icon {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 3;
            opacity: 0;
            transition: opacity .2s;
            background: radial-gradient(circle, rgba(233, 69, 96, .35) 0%, transparent 70%);
        }

        .play-icon svg {
            width: 48px;
            height: 48px;
            filter: drop-shadow(0 2px 12px rgba(0, 0, 0, .8));
        }

        .card:hover .play-icon {
            opacity: 1;
        }

        /* Titre */
        .title {
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.4;
            color: #ccc;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Aucun résultat */
        .empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 0;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
            font-size: 14px;
        }

        .empty span {
            color: var(--red);
            font-size: 2rem;
            display: block;
            margin-bottom: 16px;
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">🎬 <span>Médi</span>athèque</div>
        <div class="count" id="counter"><?= count($videos) ?> film<?= count($videos) > 1 ? 's' : '' ?></div>
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
            </svg>
            <input id="search" type="search" placeholder="Rechercher…" autocomplete="off">
        </div>
    </header>

    <div class="grid" id="grid">

        <?php if (empty($videos)): ?>
            <div class="empty">
                <span>📂</span>
                Aucune vidéo trouvée dans <code><?= htmlspecialchars($DOSSIER) ?></code>
            </div>
        <?php endif; ?>

        <?php foreach ($videos as $v):
            $name   = htmlspecialchars(pathinfo($v, PATHINFO_FILENAME));
            $ext    = strtoupper(pathinfo($v, PATHINFO_EXTENSION));
            $enc    = urlencode($v);
        ?>
            <a class="card" href="watch.php?video=<?= $enc ?>" data-title="<?= strtolower($name) ?>">

                <div class="thumb-zone">

                    <!-- Miniature générée côté navigateur via canvas -->
                    <canvas class="thumb-canvas"></canvas>

                    <!-- Aperçu vidéo (chargé au premier survol) -->
                    <video
                        class="preview-video"
                        muted
                        loop
                        playsinline
                        preload="none"
                        data-src="watch.php?stream=<?= $enc ?>&preview=1"></video>

                    <!-- Badge format -->
                    <span class="badge"><?= $ext ?></span>

                    <!-- Icône play -->
                    <div class="play-icon">
                        <svg viewBox="0 0 24 24" fill="white">
                            <circle cx="12" cy="12" r="12" fill="rgba(233,69,96,.85)" />
                            <polygon points="10,8 18,12 10,16" fill="white" />
                        </svg>
                    </div>

                </div>

                <div class="title" title="<?= $name ?>"><?= $name ?></div>

            </a>
        <?php endforeach; ?>

    </div>

    <script>
        (function() {

            // ── Recherche ──────────────────────────────────────────────────────────────
            const search = document.getElementById("search");
            const counter = document.getElementById("counter");
            const cards = () => document.querySelectorAll(".card[data-title]");

            search.addEventListener("input", function() {
                const q = this.value.toLowerCase().trim();
                let visible = 0;
                cards().forEach(card => {
                    const match = !q || card.dataset.title.includes(q);
                    card.style.display = match ? "" : "none";
                    if (match) visible++;
                });
                counter.textContent = visible + " film" + (visible > 1 ? "s" : "");
            });

            // ── Miniatures via canvas ──────────────────────────────────────────────────
            // On charge les vidéos une par une (pas en parallèle) pour ne pas surcharger
            const SEEK = 10; // secondes où capturer la frame
            const CONCUR = 3; // nombre de miniatures générées en parallèle

            function generateThumb(card) {
                return new Promise(resolve => {
                    const canvas = card.querySelector(".thumb-canvas");
                    const preload = document.createElement("video");
                    const src = card.querySelector(".preview-video").dataset.src
                        .replace("&preview=1", ""); // stream complet pour seek

                    preload.muted = true;
                    preload.preload = "metadata";

                    preload.addEventListener("loadedmetadata", () => {
                        // Seek vers min(SEEK, 10% de la durée) pour les courts métrages
                        preload.currentTime = Math.min(SEEK, preload.duration * 0.1);
                    });

                    preload.addEventListener("seeked", () => {
                        try {
                            canvas.width = 320;
                            canvas.height = 180;
                            const ctx = canvas.getContext("2d");
                            ctx.drawImage(preload, 0, 0, 320, 180);
                            canvas.classList.add("ready");
                        } catch (e) {}
                        preload.src = ""; // libère la mémoire
                        resolve();
                    }, {
                        once: true
                    });

                    // Timeout de sécurité si la vidéo ne répond pas
                    setTimeout(() => {
                        preload.src = "";
                        resolve();
                    }, 8000);

                    preload.src = src;
                });
            }

            // Traitement en file avec concurrence limitée
            async function runQueue(items, concur) {
                let i = 0;
                async function worker() {
                    while (i < items.length) {
                        const card = items[i++];
                        await generateThumb(card);
                    }
                }
                await Promise.all(Array.from({
                    length: concur
                }, worker));
            }

            // Lancer après le chargement de la page
            window.addEventListener("load", () => {
                runQueue([...document.querySelectorAll(".card")], CONCUR);
            });

            // ── Aperçu au survol ───────────────────────────────────────────────────────
            cards().forEach(card => {
                const vid = card.querySelector(".preview-video");
                if (!vid) return;

                let loaded = false;

                card.addEventListener("mouseenter", () => {
                    if (!loaded) {
                        vid.src = vid.dataset.src;
                        vid.addEventListener("canplay", () => {
                            vid.classList.add("loaded");
                            loaded = true;
                        }, {
                            once: true
                        });
                    }
                    vid.play().catch(() => {});
                });

                card.addEventListener("mouseleave", () => {
                    vid.pause();
                    vid.currentTime = 0;
                });
            });

        })();
    </script>

</body>

</html>