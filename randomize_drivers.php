<?php
session_start();

if (empty($_SESSION['randomizer_token'])) {
  $_SESSION['randomizer_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$count = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rawCount = $_POST['count'] ?? '';
  $count = filter_var($rawCount, FILTER_VALIDATE_INT);

  if (!hash_equals($_SESSION['randomizer_token'], $_POST['token'] ?? '')) {
    $error = 'The form expired. Reload the page and try again.';
  } elseif ($count === false || $count < 1 || $count > 100) {
    $error = 'Enter a number of drivers from 1 to 100.';
  } else {
    $firstNames = ['Juan', 'Carlos', 'Miguel', 'Antonio', 'Jose', 'Andres', 'Emilio', 'Apolinario', 'Francisco', 'Melchora', 'Maria', 'Ramon', 'Luz', 'Rizal', 'Teresa', 'Manuel', 'Elena', 'Roberto', 'Gloria', 'Eduardo'];
    $lastNames = ['Bautista', 'Gomez', 'Santos', 'Reyes', 'Aquino', 'Bonifacio', 'Jacinto', 'Mabini', 'Baltazar', 'Dela Cruz', 'Garcia', 'Mendoza', 'Ramos', 'Villanueva', 'Castillo', 'Flores', 'Torres', 'Rivera', 'Navarro', 'Cruz'];
    $names = [];
    foreach ($firstNames as $firstName) {
      foreach ($lastNames as $lastName) {
        $names[] = "$firstName $lastName";
      }
    }
    shuffle($names);

    try {
      $config = require __DIR__ . '/config.php';
      $db = new PDO(
        "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
      );

      $db->beginTransaction();
      $insert = $db->prepare(
        'INSERT INTO habalgo.driver (name, phone, license_no) VALUES (:name, :phone, :license) ON CONFLICT (license_no) DO NOTHING'
      );
      $inserted = 0;
      $attempts = 0;

      while ($inserted < $count && $attempts < $count * 10) {
        $attempts++;
        $insert->execute([
          ':name' => $names[($attempts - 1) % count($names)],
          ':phone' => '09' . random_int(100000000, 999999999),
          ':license' => 'N01-' . date('y') . '-' . random_int(100000, 999999),
        ]);
        $inserted += $insert->rowCount();
      }

      if ($inserted !== $count) {
        throw new RuntimeException('Could not generate enough unique license numbers.');
      }

      $db->commit();
      $message = "Added $inserted random drivers.";
      $_SESSION['randomizer_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
      if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
      }
      error_log('Driver randomizer failed: ' . $e->getMessage());
      $error = 'Could not add drivers. Check the database connection and schema, then try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Randomize HabalGo Drivers</title>
</head>
<body>
  <h1>Randomize drivers</h1>
  <p>Generate fictional driver records for the HabalGo database.</p>
  <?php if ($message !== ''): ?>
    <p role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= htmlspecialchars($_SESSION['randomizer_token'], ENT_QUOTES, 'UTF-8') ?>">
    <label for="count">Number of drivers (1–100)</label>
    <input id="count" name="count" type="number" min="1" max="100" value="<?= htmlspecialchars((string) $count, ENT_QUOTES, 'UTF-8') ?>" required>
    <button type="submit">Generate drivers</button>
  </form>
</body>
</html>
