<?php require "data.php"; ?>

<!DOCTYPE html>
<html>
<head>
<style>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
}
.video {
  cursor: pointer;
}
.video img {
  width: 100%;
  border-radius: 10px;
}
.title {
  font-weight: bold;
}
</style>
</head>

<body>

<h1>Mes vidéos</h1>

<div class="grid">
<?php foreach ($videos as $v): ?>
  <a class="video" href="watch.php?id=<?= $v['id'] ?>">
    <img src="<?= $v['thumb'] ?>" alt="">
    <div class="title"><?= htmlspecialchars($v['title']) ?></div>
    <small><?= htmlspecialchars($v['desc']) ?></small>
  </a>
<?php endforeach; ?>
</div>

</body>
</html>