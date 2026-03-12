<?php

$TAGS_FILE = __DIR__ . "/tags.json";

header("Content-Type: application/json");

$body = json_decode(file_get_contents("php://input"), true);

if (!isset($body["file"]) || !isset($body["tags"]) || !is_array($body["tags"])) {
    echo json_encode(["ok" => false, "error" => "Données invalides"]);
    exit;
}

$file = $body["file"];
$tags = array_values(array_unique(array_filter(array_map("trim", $body["tags"]))));

// Charger les tags existants
$data = [];
if (file_exists($TAGS_FILE)) {
    $data = json_decode(file_get_contents($TAGS_FILE), true) ?? [];
}

// Mettre à jour ou supprimer l'entrée
if (empty($tags)) {
    unset($data[$file]);
} else {
    $data[$file] = $tags;
}

// Sauvegarder
$saved = file_put_contents($TAGS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(["ok" => $saved !== false]);
