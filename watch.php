<?php
require "data.php";

$id = $_GET['id'] ?? null;
$video = null;

foreach ($videos as $v) {
  if ($v['id'] === $id) {
    $video = $v;
    break;
  }
}

if (!$video) {
  die("Vidéo introuvable");
}
?>

<!DOCTYPE html>
<html>
<head>
<style>
video {
  width: 100%;
  max-width: 900px;
}
</style>
</head>

<body>

<h1><?= htmlspecialchars($video['title']) ?></h1>

<video controls>
  <source src="<?= $video['file'] ?>" type="video/mp4">
</video>

<p><?= htmlspecialchars($video['desc']) ?></p>

<a href="index.php">← Retour</a>

</body>
</html>