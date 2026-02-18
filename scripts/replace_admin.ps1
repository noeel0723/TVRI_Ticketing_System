$path='d:\xamp\htdocs\ticketing_tvri\admin\dashboard.php'
$c = Get-Content -Raw -LiteralPath $path
$old = '$url = ($t[\'status\'] == \'Assigned\') ? \'assign.php?id=\' . $t[\'id\'] : \'../detail.php?id=\' . $t[\'id\'];'
$new = '$url = (in_array($t[\'status\'], [\'Assigned\', \'Open\'])) ? \'assign.php?id=\' . $t[\'id\'] : \'../detail.php?id=\' . $t[\'id\'];'
$escaped = [regex]::Escape($old)
$c2 = [regex]::Replace($c, $escaped, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) return $new })
if ($c2 -ne $c) { Set-Content -LiteralPath $path -Value $c2 -Encoding UTF8; Write-Output 'REPLACED' } else { Write-Output 'NO CHANGE' }
