<?php
$p = 'd:\\xamp\\htdocs\\ticketing_tvri\\admin\\dashboard.php';
$c = file_get_contents($p);
$old = '$url = ($t[\'status\'] == \'Assigned\') ? \'assign.php?id=\' . $t[\'id\'] : \'../detail.php?id=\' . $t[\'id\'];';
$new = '$url = (in_array($t[\'status\'], [\'Assigned\', \'Open\'])) ? \'assign.php?id=\' . $t[\'id\'] : \'../detail.php?id=\' . $t[\'id\'];';
$c = str_replace($old, $new, $c, $count);
file_put_contents($p, $c);
echo "REPLACED: $count\n";
?>
