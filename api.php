<?php
// HabalGo API: JSON CRUD over the PostgreSQL schema "habalgo". All queries are prepared statements.
header('Content-Type: application/json; charset=utf-8');
$c = require __DIR__ . '/config.php';
function out($d, $code = 200) { http_response_code($code); echo json_encode($d); exit; }

try {
  $db = new PDO("pgsql:host={$c['host']};port={$c['port']};dbname={$c['dbname']}", $c['user'], $c['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $db->exec('SET search_path TO habalgo');
} catch (PDOException $e) { out(['error' => 'Database connection failed: ' . $e->getMessage()], 500); }

function q($sql, $p = []) { global $db; $s = $db->prepare($sql); $s->execute($p); return $s; }
function rows($sql, $p = []) { return q($sql, $p)->fetchAll(); }
function fail($msg) { global $db; if ($db->inTransaction()) $db->rollBack(); out(['error' => $msg], 400); }

function validPhone($phone) {
  $phone = preg_replace('/[\s().-]/', '', trim((string)$phone));
  $phone = preg_replace('/^00/', '+', $phone);
  if (preg_match('/^\+?63(9\d{9})$/', $phone, $matches)) {
    $phone = '0' . $matches[1];
  }
  if (preg_match('/^9\d{9}$/', $phone)) {
    $phone = '0' . $phone;
  }
  if (!preg_match('/^(?:09\d{9}|\d{7,15}|\+[1-9]\d{7,14})$/', $phone)) {
    fail('Enter a valid phone number, including its country code when applicable.');
  }
  return $phone;
}

function validEmail($email) {
  $email = trim((string)$email);
  if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Enter a valid email address containing an @ symbol and a valid domain.');
  }
  $domain = substr(strrchr($email, '@'), 1);
  if (!preg_match('/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', $domain)) {
    fail('Enter an email address with a valid domain (e.g., example.com).');
  }
  return $email;
}

function loc($a, $city) {
  $a = trim($a); $city = trim($city);
  $r = q('SELECT location_id FROM location WHERE lower(address)=lower(?) AND lower(city)=lower(?) LIMIT 1', [$a, $city])->fetchColumn();
  return $r ?: q('INSERT INTO location(address,city) VALUES(?,?) RETURNING location_id', [$a, $city])->fetchColumn();
}

function findOrCreateCustomer($name, $phone, $email) {
  $name = trim($name);
  $r = q('SELECT customer_id FROM customer WHERE lower(name)=lower(?) LIMIT 1', [$name])->fetchColumn();
  if ($r) {
    if (trim((string)$phone) !== '') validPhone($phone);
    if (trim((string)$email) !== '') validEmail($email);
    return $r;
  }
  if (trim((string)$phone) === '' || trim((string)$email) === '') {
    fail('Phone and email are required when creating a new customer.');
  }
  return q('INSERT INTO customer(name,phone,email) VALUES(?,?,?) RETURNING customer_id', [$name, validPhone($phone), validEmail($email)])->fetchColumn();
}

function assign($vid, $did) {
  q('UPDATE driver_vehicle SET assigned_to=now() WHERE vehicle_id=? AND assigned_to IS NULL AND driver_id IS DISTINCT FROM ?::int', [$vid, $did]);
  if ($did) q('INSERT INTO driver_vehicle(driver_id,vehicle_id,assigned_from) VALUES(?,?,now())
    ON CONFLICT (driver_id,vehicle_id) DO UPDATE SET assigned_from=now(), assigned_to=NULL WHERE driver_vehicle.assigned_to IS NOT NULL', [$did, $vid]);
}

function save($tbl, $pk, $cols, $in, $id) {
  $set = []; $p = [];
  foreach ($cols as $c) if (array_key_exists($c, $in)) { $set[] = $c; $p[] = ($in[$c] === '' ? null : $in[$c]); }
  if ($id) { $p[] = $id; q("UPDATE $tbl SET " . implode(',', array_map(fn($c) => "$c=?", $set)) . " WHERE $pk=?", $p); return $id; }
  return q("INSERT INTO $tbl (" . implode(',', $set) . ') VALUES (' . implode(',', array_fill(0, count($set), '?')) . ") RETURNING $pk", $p)->fetchColumn();
}

$E = [
  'customers' => ['customer', 'customer_id', ['name', 'phone', 'email']],
  'vehicles'  => ['vehicle', 'vehicle_id', ['plate_no', 'vehicle_type', 'model']],
  'bookings'  => ['booking', 'booking_id', ['customer_id', 'driver_id', 'booking_status']],
  'payments'  => ['payment', 'payment_id', ['booking_id', 'amount', 'method', 'status']],
  'ratings'   => ['rating_feedback', 'rating_id', ['booking_id', 'rating', 'feedback']],
];

$BK = "SELECT b.booking_id AS id,b.customer_id,c.name AS customer,lp.address AS pickup,lp.city AS pickup_city,ld.address AS dropoff,ld.city AS dropoff_city,b.driver_id,d.name AS driver,b.booking_status,to_char(b.booking_date,'Mon DD') AS date
  ,c.name AS customer_name,c.phone AS customer_phone,c.email AS customer_email
  FROM booking b JOIN customer c ON c.customer_id=b.customer_id JOIN location lp ON lp.location_id=b.pickup_location_id
  JOIN location ld ON ld.location_id=b.dropoff_location_id LEFT JOIN driver d ON d.driver_id=b.driver_id ORDER BY b.booking_id DESC";

$L = [
  'customers' => 'SELECT c.customer_id AS id, c.name, c.phone, c.email, COUNT(b.booking_id)::int AS bookings
                  FROM customer c
                  JOIN booking b ON b.customer_id = c.customer_id
                  GROUP BY c.customer_id, c.name, c.phone, c.email
                  HAVING COUNT(b.booking_id) > 0
                  ORDER BY c.customer_id',
  'vehicles'  => 'SELECT v.vehicle_id AS id,v.plate_no,v.vehicle_type,v.model,d.driver_id,d.name AS driver FROM vehicle v
    LEFT JOIN driver_vehicle dv ON dv.vehicle_id=v.vehicle_id AND dv.assigned_to IS NULL LEFT JOIN driver d ON d.driver_id=dv.driver_id ORDER BY v.vehicle_id',
  'bookings'  => $BK,
  'payments'  => 'SELECT p.payment_id AS id,p.booking_id,c.name AS customer,p.amount::float AS amount,p.method,p.status FROM payment p
    JOIN booking b ON b.booking_id=p.booking_id JOIN customer c ON c.customer_id=b.customer_id ORDER BY p.payment_id DESC',
  'ratings'   => 'SELECT r.rating_id AS id,r.booking_id,r.rating,r.feedback,c.name AS customer,lp.address AS pickup,ld.address AS dropoff,d.name AS driver
    FROM rating_feedback r JOIN booking b ON b.booking_id=r.booking_id JOIN customer c ON c.customer_id=r.customer_id
    JOIN location lp ON lp.location_id=b.pickup_location_id JOIN location ld ON ld.location_id=b.dropoff_location_id LEFT JOIN driver d ON d.driver_id=b.driver_id ORDER BY r.rating_id DESC',
];

$PH_CITIES = ['Manila', 'Quezon City', 'Caloocan', 'Davao City', 'Cebu City', 'Zamboanga City', 'Antipolo', 'Taguig', 'Pasig', 'Cagayan de Oro', 'Dasmarinas', 'Imus', 'Bacoor', 'General Mariano Alvarez', 'Tagaytay'];

$t = $_GET['t'] ?? ''; $id = (int)($_GET['id'] ?? 0); $m = $_SERVER['REQUEST_METHOD'];
$in = json_decode(file_get_contents('php://input'), true) ?: [];

try {
  if ($t === 'dashboard') {
    out(['stats' => rows("SELECT (SELECT count(*) FROM customer WHERE customer_id IN (SELECT DISTINCT customer_id FROM booking))::int AS customers,
        (SELECT count(*) FROM booking WHERE booking_status IN ('Pending','Confirmed','In Progress'))::int AS active,
        (SELECT COALESCE(sum(amount),0) FROM payment WHERE status='Paid')::float AS revenue,
        (SELECT round(avg(rating),1) FROM rating_feedback)::float AS avg_rating")[0],
      'byStatus' => rows('SELECT booking_status AS s,count(*)::int AS n FROM booking GROUP BY 1 ORDER BY 2 DESC'),
      'recent' => array_slice(rows($BK), 0, 5)]);
  }
  if ($t === 'options') {
    $lab = fn($r) => [$r['id'], "#{$r['id']} {$r['customer']}: {$r['pickup']} → {$r['dropoff']}"];
    $bk = rows($BK);
    out([
      'cities' => array_map(fn($city) => [$city, $city], $PH_CITIES),
      'drivers' => array_map(fn($r) => [$r['driver_id'], $r['name']], rows('SELECT driver_id,name FROM driver ORDER BY name')),
      'bookings' => array_map($lab, $bk),
      'completed' => array_map($lab, array_values(array_filter($bk, fn($r) => $r['booking_status'] === 'Completed')))
    ]);
  }
  if (!isset($L[$t])) out(['error' => 'Unknown resource.'], 404);
  if ($m === 'GET') out(rows($L[$t]));

  [$tbl, $pk, $cols] = $E[$t];
  $db->beginTransaction();

  if ($m === 'DELETE') {
    if ($t === 'vehicles') q('DELETE FROM driver_vehicle WHERE vehicle_id=?', [$id]);
    q("DELETE FROM $tbl WHERE $pk=?", [$id]);
    $db->commit(); out(['ok' => true]);
  }
  if ($t === 'ratings') {
    $cols[] = 'customer_id';
    $b = q('SELECT customer_id,booking_status FROM booking WHERE booking_id=?', [$in['booking_id'] ?? 0])->fetch();
    if (!$b || $b['booking_status'] !== 'Completed') fail('Only completed bookings can be rated.');
    $in['customer_id'] = $b['customer_id'];
  }
  if ($t === 'customers') {
    if (array_key_exists('phone', $in)) $in['phone'] = validPhone($in['phone']);
    if (array_key_exists('email', $in)) $in['email'] = validEmail($in['email']);
  }
  if ($t === 'bookings') {
    if (trim((string)($in['customer_name'] ?? '')) === '') fail('Customer name is required.');
    $in['customer_id'] = findOrCreateCustomer($in['customer_name'], $in['customer_phone'] ?? '', $in['customer_email'] ?? '');
    if (array_key_exists('driver_id', $in) && ($in['driver_id'] === '' || $in['driver_id'] === null)) {
      $in['driver_id'] = q('SELECT driver_id FROM driver ORDER BY random() LIMIT 1')->fetchColumn();
      if (!$in['driver_id']) fail('No drivers are available. Add a driver before assigning a booking.');
    }
    $cols = array_merge($cols, ['pickup_location_id', 'dropoff_location_id']);
    foreach (['pickup' => 'pickup_location_id', 'dropoff' => 'dropoff_location_id'] as $k => $col) {
      if (!empty($in[$k])) {
        $cityKey = $k . '_city';
        $in[$col] = loc($in[$k], $in[$cityKey] ?? 'Manila');
      }
    }
    if (isset($in['pickup_location_id'], $in['dropoff_location_id']) && $in['pickup_location_id'] == $in['dropoff_location_id']) fail('Pickup and drop-off must be different locations.');
    if (empty($in['booking_status']) && $m === 'POST') $in['booking_status'] = 'Pending';
    if (!empty($in['driver_id']) && ($in['booking_status'] ?? '') === 'Pending') $in['booking_status'] = 'Confirmed';
  }

  $rid = save($tbl, $pk, $cols, $in, $m === 'PUT' ? $id : 0);
  if ($t === 'vehicles' && array_key_exists('driver_id', $in)) assign($rid, $in['driver_id'] ?: null);
  $db->commit(); out(['ok' => true, 'id' => $rid]);
} catch (PDOException $e) {
  if ($db->inTransaction()) $db->rollBack();
  $msg = ['23503' => 'This record is linked to other records, so the change is not allowed.', '23505' => 'That value already exists.',
    '23514' => 'One of the values is not allowed.', '23502' => 'Please fill in all required fields.'][$e->getCode()] ?? null;
  out(['error' => $msg ?: 'Database error.'], 400);
}