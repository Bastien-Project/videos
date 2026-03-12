<?php

// ── Configuration ──────────────────────────────────────────────────────────────
$DOSSIER    = "D:/useless/";
$EXTENSIONS = ["mp4", "mkv", "avi"];
$TAGS_FILE  = __DIR__ . "/tags.json";
// ──────────────────────────────────────────────────────────────────────────────

// ── Lecture des tags ───────────────────────────────────────────────────────────
$tags = [];
if (file_exists($TAGS_FILE)) {
    $tags = json_decode(file_get_contents($TAGS_FILE), true) ?? [];
}

// ── Scan récursif des vidéos ───────────────────────────────────────────────────
$videos = [];
foreach ($EXTENSIONS as $ext) {
    foreach (glob($DOSSIER . "**/*.$ext", GLOB_BRACE) ?: [] as $v) {
        $videos[] = substr($v, strlen($DOSSIER));
    }
    foreach (glob($DOSSIER . "*.$ext") ?: [] as $v) {
        $videos[] = basename($v);
    }
}
$videos = array_unique($videos);
sort($videos, SORT_NATURAL | SORT_FLAG_CASE);

// ── Liste de tous les sous-dossiers ───────────────────────────────────────────
$folders = [""];
foreach ($videos as $v) {
    $d = dirname($v);
    if ($d !== "." && !in_array($d, $folders)) $folders[] = $d;
}
sort($folders);

// ── Tous les tags existants (pour l'autocomplétion) ───────────────────────────
$allTags = [];
foreach ($tags as $t) {
    foreach ($t as $tag) {
        if (!in_array($tag, $allTags)) $allTags[] = $tag;
    }
}
sort($allTags);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Médiathèque</title>
    <link rel="stylesheet" href="style/index.css">
</head>

<body>

    <header>
        <div class="header-top">
            <div class="logo">🎬 <span>Médi</span>athèque</div>
            <div class="count" id="counter"><?= count($videos) ?> film<?= count($videos) > 1 ? 's' : '' ?></div>
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <input id="search" type="search" placeholder="Rechercher titre ou tag…" autocomplete="off">
            </div>
        </div>

        <div class="filters">
            <label>Dossier :</label>
            <select id="folder-filter">
                <option value="">Tous</option>
                <?php foreach ($folders as $f): ?>
                    <?php if ($f !== ""): ?>
                        <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <label style="margin-left:12px">Tags actifs :</label>
            <div class="active-tags" id="active-tags">
                <span style="font-family:'Space Mono',monospace;font-size:11px;color:var(--muted)" id="no-active-tag">aucun</span>
            </div>
        </div>

        <?php if (!empty($allTags)): ?>
            <div class="tag-list" id="tag-list">
                <?php foreach ($allTags as $tag): ?>
                    <span class="tag-pill" data-tag="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="grid" id="grid">

        <?php if (empty($videos)): ?>
            <div class="empty">
                <span>📂</span>
                Aucune vidéo trouvée dans <code><?= htmlspecialchars($DOSSIER) ?></code>
            </div>
        <?php endif; ?>

        <?php foreach ($videos as $v):
            $name      = htmlspecialchars(pathinfo($v, PATHINFO_FILENAME));
            $folder    = dirname($v) !== '.' ? htmlspecialchars(dirname($v)) : '';
            $ext       = strtoupper(pathinfo($v, PATHINFO_EXTENSION));
            $enc       = urlencode($v);
            $videoTags = $tags[$v] ?? [];
            $tagsJson  = htmlspecialchars(json_encode($videoTags), ENT_QUOTES);
            $search    = strtolower(pathinfo($v, PATHINFO_FILENAME) . ' ' . implode(' ', $videoTags));
        ?>
            <div class="card"
                data-file="<?= htmlspecialchars($v, ENT_QUOTES) ?>"
                data-title="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
                data-folder="<?= htmlspecialchars($folder, ENT_QUOTES) ?>"
                data-tags="<?= $tagsJson ?>">

                <a class="card-link" href="watch.php?video=<?= $enc ?>">
                    <div class="thumb-zone">
                        <canvas class="thumb-canvas"></canvas>
                        <video class="preview-video" muted loop playsinline preload="none"
                            data-src="watch.php?stream=<?= $enc ?>&preview=1"></video>
                        <span class="badge"><?= $ext ?></span>
                        <div class="play-icon">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="12" fill="rgba(233,69,96,.85)" />
                                <polygon points="10,8 18,12 10,16" fill="white" />
                            </svg>
                        </div>
                    </div>
                </a>

                <div class="card-body">
                    <?php if ($folder): ?><div class="subfolder">📁 <?= $folder ?></div><?php endif; ?>
                    <a href="watch.php?video=<?= $enc ?>" class="title" title="<?= $name ?>" style="text-decoration:none;color:inherit"><?= $name ?></a>
                    <div class="card-tags">
                        <?php foreach ($videoTags as $tag): ?>
                            <span class="tag" data-tag="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <button class="edit-tags-btn" data-file="<?= htmlspecialchars($v, ENT_QUOTES) ?>" data-name="<?= $name ?>">✏ tags</button>
                </div>

            </div>
        <?php endforeach; ?>

    </div>

    <!-- Modal édition tags -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal">
            <h2 id="modal-title">Tags<small id="modal-filename"></small></h2>

            <div class="modal-current-tags" id="modal-current-tags"></div>

            <div class="tag-input-wrap">
                <input type="text" id="tag-input" placeholder="Ajouter un tag… (Entrée pour valider)">
                <div class="suggestions" id="suggestions"></div>
            </div>
            <p class="modal-hint">Entrée ou virgule pour ajouter • cliquer × pour supprimer</p>

            <div class="modal-actions">
                <button class="btn btn-cancel" id="modal-cancel">Annuler</button>
                <button class="btn btn-save" id="modal-save">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
        (function() {

            const allTagsList = <?= json_encode($allTags) ?>;
            let activeTagFilters = [];
            let currentFile = null;
            let currentTags = [];

            // ─── Utilitaire : re-render les tags d'une carte dans le DOM ─────────────────
            function renderCardTags(card, tagList) {
                const container = card.querySelector(".card-tags");
                container.innerHTML = "";
                tagList.forEach(tag => {
                    const s = document.createElement("span");
                    s.className = "tag" + (activeTagFilters.includes(tag) ? " active-filter" : "");
                    s.dataset.tag = tag;
                    s.textContent = tag;
                    container.appendChild(s);
                });
                // Mettre à jour data-title et data-tags
                const titleBase = card.dataset.file ?
                    card.dataset.file.replace(/\.[^.]+$/, "").replace(/.*[/\\]/, "") : "";
                card.dataset.title = (titleBase + " " + tagList.join(" ")).toLowerCase();
                card.dataset.tags = JSON.stringify(tagList);
            }

            // ─── Filtre global ────────────────────────────────────────────────────────────
            function applyFilters() {
                const q = document.getElementById("search").value.toLowerCase().trim();
                const folder = document.getElementById("folder-filter").value;
                let visible = 0;

                document.querySelectorAll(".card[data-file]").forEach(card => {
                    const matchText = !q || card.dataset.title.includes(q);
                    const matchFolder = !folder || card.dataset.folder === folder;
                    const cardTags = JSON.parse(card.dataset.tags || "[]");
                    const matchTags = activeTagFilters.every(t => cardTags.includes(t));

                    const show = matchText && matchFolder && matchTags;
                    card.style.display = show ? "" : "none";
                    if (show) visible++;
                });

                document.getElementById("counter").textContent =
                    visible + " film" + (visible > 1 ? "s" : "");
            }

            document.getElementById("search").addEventListener("input", applyFilters);
            document.getElementById("folder-filter").addEventListener("change", applyFilters);

            // ─── Clic sur tag-pill (barre de tags) ───────────────────────────────────────
            document.querySelectorAll(".tag-pill").forEach(pill => {
                pill.addEventListener("click", () => {
                    const t = pill.dataset.tag;
                    if (activeTagFilters.includes(t)) {
                        activeTagFilters = activeTagFilters.filter(x => x !== t);
                        pill.classList.remove("active");
                    } else {
                        activeTagFilters.push(t);
                        pill.classList.add("active");
                    }
                    syncTagPills();
                    renderActiveTagsBar();
                    applyFilters();
                });
            });

            function syncTagPills() {
                document.querySelectorAll(".tag-pill").forEach(pill => {
                    pill.classList.toggle("active", activeTagFilters.includes(pill.dataset.tag));
                });
            }

            // ─── Clic sur un tag de carte (filtre) ───────────────────────────────────────
            document.getElementById("grid").addEventListener("click", e => {
                const tag = e.target.closest(".tag");
                if (!tag) return;
                e.preventDefault();
                e.stopPropagation();

                const t = tag.dataset.tag;
                if (activeTagFilters.includes(t)) {
                    activeTagFilters = activeTagFilters.filter(x => x !== t);
                } else {
                    activeTagFilters.push(t);
                }

                // Mettre à jour les badges active-filter sur toutes les cartes
                document.querySelectorAll(".tag").forEach(el => {
                    el.classList.toggle("active-filter", activeTagFilters.includes(el.dataset.tag));
                });
                syncTagPills();
                renderActiveTagsBar();
                applyFilters();
            });

            function renderActiveTagsBar() {
                const bar = document.getElementById("active-tags");
                bar.innerHTML = "";

                if (activeTagFilters.length === 0) {
                    bar.innerHTML = '<span id="no-active-tag" style="font-family:Space Mono,monospace;font-size:11px;color:var(--muted)">aucun</span>';
                    return;
                }

                activeTagFilters.forEach(t => {
                    const el = document.createElement("span");
                    el.className = "active-tag";
                    el.innerHTML = `${t} <span class="remove">×</span>`;
                    el.addEventListener("click", () => {
                        activeTagFilters = activeTagFilters.filter(x => x !== t);
                        document.querySelectorAll(".tag").forEach(el => {
                            el.classList.toggle("active-filter", activeTagFilters.includes(el.dataset.tag));
                        });
                        syncTagPills();
                        renderActiveTagsBar();
                        applyFilters();
                    });
                    bar.appendChild(el);
                });
            }

            // ─── Modal édition tags ───────────────────────────────────────────────────────
            const overlay = document.getElementById("modal-overlay");
            const tagInput = document.getElementById("tag-input");
            const suggBox = document.getElementById("suggestions");

            function openModal(file, name, existingTags) {
                currentFile = file;
                currentTags = [...existingTags];
                document.getElementById("modal-filename").textContent = name;
                renderModalTags();
                tagInput.value = "";
                hideSuggestions();
                overlay.classList.add("open");
                setTimeout(() => tagInput.focus(), 50);
            }

            function closeModal() {
                overlay.classList.remove("open");
                currentFile = null;
                currentTags = [];
            }

            function renderModalTags() {
                const c = document.getElementById("modal-current-tags");
                c.innerHTML = "";
                if (currentTags.length === 0) {
                    c.innerHTML = '<span style="font-family:Space Mono,monospace;font-size:11px;color:#555">Aucun tag</span>';
                    return;
                }
                currentTags.forEach((t, i) => {
                    const el = document.createElement("span");
                    el.className = "modal-tag";
                    el.innerHTML = `${t} <button data-i="${i}">×</button>`;
                    el.querySelector("button").addEventListener("click", () => {
                        currentTags.splice(i, 1);
                        renderModalTags();
                    });
                    c.appendChild(el);
                });
            }

            // Suggestions autocomplétion
            function showSuggestions(val) {
                const q = val.trim().toLowerCase();
                const matches = allTagsList.filter(t =>
                    t.toLowerCase().includes(q) && !currentTags.includes(t)
                ).slice(0, 6);

                if (matches.length === 0 || !q) {
                    hideSuggestions();
                    return;
                }

                suggBox.innerHTML = "";
                matches.forEach(t => {
                    const d = document.createElement("div");
                    d.className = "suggestion";
                    d.textContent = t;
                    d.addEventListener("mousedown", () => addTag(t));
                    suggBox.appendChild(d);
                });
                suggBox.classList.add("open");
            }

            function hideSuggestions() {
                suggBox.classList.remove("open");
                suggBox.innerHTML = "";
            }

            function addTag(val) {
                const t = val.trim().toLowerCase().replace(/,/g, "");
                if (t && !currentTags.includes(t)) {
                    currentTags.push(t);
                    // Ajouter à la liste globale si nouveau
                    if (!allTagsList.includes(t)) allTagsList.push(t);
                    renderModalTags();
                }
                tagInput.value = "";
                hideSuggestions();
            }

            tagInput.addEventListener("input", () => showSuggestions(tagInput.value));
            tagInput.addEventListener("blur", () => setTimeout(hideSuggestions, 150));

            tagInput.addEventListener("keydown", e => {
                if (e.key === "Enter" || e.key === ",") {
                    e.preventDefault();
                    addTag(tagInput.value);
                }
                if (e.key === "Escape") closeModal();
            });

            // Ouvrir modal
            document.getElementById("grid").addEventListener("click", e => {
                const btn = e.target.closest(".edit-tags-btn");
                if (!btn) return;
                e.preventDefault();
                const card = btn.closest(".card");
                const file = btn.dataset.file;
                const name = btn.dataset.name;
                const existing = JSON.parse(card.dataset.tags || "[]");
                openModal(file, name, existing);
            });

            document.getElementById("modal-cancel").addEventListener("click", closeModal);
            overlay.addEventListener("click", e => {
                if (e.target === overlay) closeModal();
            });

            // Sauvegarder
            document.getElementById("modal-save").addEventListener("click", () => {
                if (!currentFile) return;
                // Si l'utilisateur a tapé un tag sans appuyer Entrée, l'ajouter quand même
                if (tagInput.value.trim()) addTag(tagInput.value);

                fetch("tags_save.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            file: currentFile,
                            tags: currentTags
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok) {
                            // Mettre à jour la carte dans le DOM
                            const card = document.querySelector(`.card[data-file="${CSS.escape(currentFile)}"]`);
                            if (card) renderCardTags(card, currentTags);
                            closeModal();
                            applyFilters();
                        }
                    })
                    .catch(() => alert("Erreur lors de la sauvegarde"));
            });

            // ─── Miniatures canvas ────────────────────────────────────────────────────────
            const SEEK = 10;
            const CONCUR = 3;

            function generateThumb(card) {
                return new Promise(resolve => {
                    const canvas = card.querySelector(".thumb-canvas");
                    const preload = document.createElement("video");
                    const vidEl = card.querySelector(".preview-video");
                    if (!vidEl) return resolve();
                    const src = vidEl.dataset.src.replace("&preview=1", "");

                    preload.muted = true;
                    preload.preload = "metadata";

                    preload.addEventListener("loadedmetadata", () => {
                        preload.currentTime = Math.min(SEEK, preload.duration * 0.1);
                    });

                    preload.addEventListener("seeked", () => {
                        try {
                            canvas.width = 320;
                            canvas.height = 180;
                            canvas.getContext("2d").drawImage(preload, 0, 0, 320, 180);
                            canvas.classList.add("ready");
                        } catch (e) {}
                        preload.src = "";
                        resolve();
                    }, {
                        once: true
                    });

                    setTimeout(() => {
                        preload.src = "";
                        resolve();
                    }, 8000);
                    preload.src = src;
                });
            }

            async function runQueue(items, concur) {
                let i = 0;
                const worker = async () => {
                    while (i < items.length) await generateThumb(items[i++]);
                };
                await Promise.all(Array.from({
                    length: concur
                }, worker));
            }

            window.addEventListener("load", () => {
                const observer = new IntersectionObserver((entries, obs) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            generateThumb(entry.target);
                            obs.unobserve(entry.target);
                        }
                    });
                }, {
                    rootMargin: "200px"
                });

                document.querySelectorAll(".card").forEach(card => observer.observe(card));
            });

            // ─── Aperçu au survol ─────────────────────────────────────────────────────────
            document.querySelectorAll(".card[data-file]").forEach(card => {
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