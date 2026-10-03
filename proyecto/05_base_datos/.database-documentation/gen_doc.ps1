# Genera proyecto/05_base_datos/.database-documentation/ (21 artefactos)
# Fuente live: instancia MySQL efimera 127.0.0.1:33061, BD farmacia_doc (V1.0.0-V1.4.0)
# Fuente model: proyecto/05_base_datos/13_sql/V1.0.0 + V1.1.0 + V1.4.0
$ErrorActionPreference = 'Stop'

$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$H = '127.0.0.1'
$P = 33061
$DB = 'farmacia_doc'
$proj = 'C:\Users\Luis Alberto\Documents\CMBV\MAESTRÍA\3 módulo\sistema - farmacia (14)\sistema - farmacia'
$sqlDir = Join-Path $proj 'proyecto\05_base_datos\13_sql'
$out = Join-Path $proj 'proyecto\05_base_datos\.database-documentation'
$enc = New-Object System.Text.UTF8Encoding($false)
$stamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

New-Item -ItemType Directory -Force -Path $out | Out-Null

function Q([string]$sql) {
  $r = & $script:mysql --no-defaults -h $script:H -P $script:P -u root -N -B -e $sql
  if ($LASTEXITCODE -ne 0) { throw "SQL error ${LASTEXITCODE}: $sql" }
  return @($r)
}
function W([string]$name, [string[]]$lines) {
  [System.IO.File]::WriteAllLines((Join-Path $script:out $name), $lines, $script:enc)
}
function RF([string]$f) {
  $t = [System.IO.File]::ReadAllText((Join-Path $sqlDir $f), [System.Text.Encoding]::UTF8)
  return $t.TrimStart([char]0xFEFF)
}
function Cell([string]$v) {
  if ($v -eq '\N' -or $null -eq $v) { return 'NULL' }
  return $v
}
function MdEsc([string]$v) {
  $x = Cell $v
  return ($x -replace '\|', '\|')
}

# ---------------------------------------------------------------- live: queries
$ver = Q "SELECT CONCAT(@@version_comment,' ', @@version)"
$tbl = Q "SELECT table_name, engine, table_rows, data_length, index_length, table_collation, table_comment FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE' ORDER BY table_name"
$col = Q "SELECT table_name, ordinal_position, column_name, column_type, is_nullable, column_default, extra, column_key, character_set_name, collation_name, column_comment, generation_expression FROM information_schema.columns WHERE table_schema='$DB' ORDER BY table_name, ordinal_position"
$tc  = Q "SELECT table_name, constraint_name, constraint_type FROM information_schema.table_constraints WHERE table_schema='$DB' ORDER BY table_name, constraint_name"
$kcu = Q "SELECT constraint_name, table_name, column_name, ordinal_position, referenced_table_name, referenced_column_name FROM information_schema.key_column_usage WHERE table_schema='$DB' ORDER BY constraint_name, ordinal_position"
$rc  = Q "SELECT constraint_name, delete_rule, update_rule FROM information_schema.referential_constraints WHERE constraint_schema='$DB'"
$chk = Q "SELECT tc.table_name, cc.constraint_name, cc.check_clause FROM information_schema.check_constraints cc JOIN information_schema.table_constraints tc ON tc.constraint_schema=cc.constraint_schema AND tc.constraint_name=cc.constraint_name WHERE cc.constraint_schema='$DB' ORDER BY tc.table_name, cc.constraint_name"
$idx = Q "SELECT table_name, index_name, seq_in_index, column_name, non_unique, index_type, index_comment FROM information_schema.statistics WHERE table_schema='$DB' ORDER BY table_name, index_name, seq_in_index"
$trg = Q "SELECT trigger_name, event_manipulation, event_object_table, action_timing, action_statement, definer FROM information_schema.triggers WHERE trigger_schema='$DB' ORDER BY trigger_name"

$tableNames = @($tbl | ForEach-Object { ($_ -split "`t")[0] })
$idxTotal = (Q "SELECT COUNT(*) FROM (SELECT table_name, index_name FROM information_schema.statistics WHERE table_schema='$DB' GROUP BY table_name, index_name) x")
$nTbl = $tableNames.Count
$nChk = $chk.Count
$nFk  = @($tc | Where-Object { ($_ -split "`t")[2] -eq 'FOREIGN KEY' }).Count
$nUq  = @($tc | Where-Object { ($_ -split "`t")[2] -eq 'UNIQUE' }).Count
$nPk  = @($tc | Where-Object { ($_ -split "`t")[2] -eq 'PRIMARY KEY' }).Count

# SHOW CREATE TABLE por tabla
$creates = @()
foreach ($t in $tableNames) {
  $q = 'SHOW CREATE TABLE ' + $DB + '.`' + $t + '`'
  $r = & $mysql --no-defaults -h $H -P $P -u root -N -B -e $q
  if ($LASTEXITCODE -ne 0) { throw "SHOW CREATE failed: $t" }
  $line = @($r)[0]
  $ddl = ($line -split "`t", 2)[1]
  $creates += ,@($t, $ddl)
}
# ---------------------------------------------------------------- A. introspection.tsv
$a = New-Object System.Collections.Generic.List[string]
$a.Add("table_name`tcolumn_name`tordinal_position`tcolumn_type`tis_nullable`tcolumn_key`textra")
foreach ($r in $col) { $f = $r -split "`t"; $a.Add("$($f[0])`t$($f[2])`t$($f[1])`t$($f[3])`t$($f[4])`t$(Cell $f[7])`t$(Cell $f[6])") }
W 'introspection.tsv' $a.ToArray()

# ---------------------------------------------------------------- B. live_columns_full.tsv
$b = New-Object System.Collections.Generic.List[string]
$b.Add("table_name`tcolumn_name`tordinal_position`tcolumn_type`tis_nullable`tcolumn_default`textra`tcolumn_key`tcharacter_set_name`tcollation_name`tcolumn_comment`tgeneration_expression")
foreach ($r in $col) {
  $f = $r -split "`t"
  $b.Add("$($f[0])`t$($f[2])`t$($f[1])`t$($f[3])`t$($f[4])`t$(Cell $f[5])`t$(Cell $f[6])`t$(Cell $f[7])`t$(Cell $f[8])`t$(Cell $f[9])`t$(Cell $f[10])`t$(Cell $f[11])")
}
W 'live_columns_full.tsv' $b.ToArray()

# ---------------------------------------------------------------- C. introspection.txt
$c = New-Object System.Collections.Generic.List[string]
$c.Add("Introspeccion live de la BD $DB (${H}:${P}) - $ver")
$c.Add("Generado: $stamp | Origen: information_schema (Tier T2, leido de servidor vivo)")
$c.Add("Tablas=$nTbl PK=$nPk FK=$nFk UNIQUE=$nUq CHECK=$nChk indices_grupos=$idxTotal triggers=$($trg.Count)")
$c.Add('')
$c.Add('== TABLAS (table | engine | filas_est | data_length | index_length | collation | comment) ==')
foreach ($r in $tbl) { $f = $r -split "`t"; $c.Add("$($f[0]) | $($f[1]) | $($f[2]) | $($f[3]) | $($f[4]) | $($f[5]) | $(Cell $f[6])") }
$c.Add('')
$c.Add('== COLUMNAS (table.column | tipo | nulo | defecto | extra | key | comment) ==')
foreach ($r in $col) { $f = $r -split "`t"; $c.Add("$($f[0]).$($f[2]) | $($f[3]) | $($f[4]) | $(Cell $f[5]) | $(Cell $f[6]) | $(Cell $f[7]) | $(Cell $f[10])") }
$c.Add('')
$c.Add('== PRIMARY KEY ==')
foreach ($r in $tc) { $f = $r -split "`t"; if ($f[2] -eq 'PRIMARY KEY') { $c.Add("$($f[0]) | $($f[1])") } }
$c.Add('')
$c.Add('== FOREIGN KEY (constraint | tabla_hija | columnas | tabla_padre | columnas | ON DELETE | ON UPDATE) ==')
$rcMap = @{}; foreach ($r in $rc) { $f = $r -split "`t"; $rcMap[$f[0]] = @($f[1], $f[2]) }
$kcuBy = @{}; foreach ($r in $kcu) { $f = $r -split "`t"; if (-not $kcuBy.ContainsKey($f[0])) { $kcuBy[$f[0]] = New-Object System.Collections.Generic.List[string] }; $kcuBy[$f[0]].Add($r) }
foreach ($r in $tc) {
  $f = $r -split "`t"; if ($f[2] -ne 'FOREIGN KEY') { continue }
  $cols = @(); $pcols = @(); $pt = ''
  foreach ($k in $kcuBy[$f[1]]) { $kf = $k -split "`t"; $cols += $kf[2]; $pcols += $kf[5]; $pt = $kf[4] }
  $dr = if ($rcMap.ContainsKey($f[1])) { $rcMap[$f[1]][0] } else { '?' }; $ur = if ($rcMap.ContainsKey($f[1])) { $rcMap[$f[1]][1] } else { '?' }
  $c.Add("$($f[1]) | $($f[0]) | $($cols -join ', ') | $pt | $($pcols -join ', ') | $dr | $ur")
}
$c.Add('')
$c.Add('== UNIQUE ==')
foreach ($r in $tc) { $f = $r -split "`t"; if ($f[2] -eq 'UNIQUE') { $u = @(); foreach ($k in $kcuBy[$f[1]]) { $u += (($k -split "`t")[2]) }; $c.Add("$($f[1]) | $($f[0]) | $($u -join ', ')") } }
$c.Add('')
$c.Add('== CHECK ==')
foreach ($r in $chk) { $f = $r -split "`t"; $c.Add("$($f[1]) | $($f[0]) | $($f[2])") }
$c.Add('')
$c.Add('== INDICES (tabla | indice | seq | columna | unico | tipo | comentario) ==')
foreach ($r in $idx) { $f = $r -split "`t"; $c.Add("$($f[0]) | $($f[1]) | $($f[2]) | $($f[3]) | $($f[4]) | $($f[5]) | $(Cell $f[6])") }
$c.Add('')
$c.Add('== TRIGGERS ==')
foreach ($r in $trg) { $f = $r -split "`t"; $c.Add("$($f[0]) | $($f[3]) $($f[1]) ON $($f[2]) | definer=$($f[5]) | $($f[4])") }
W 'introspection.txt' $c.ToArray()

# ---------------------------------------------------------------- D. live_creates.tsv
$d = New-Object System.Collections.Generic.List[string]
$d.Add("table_name`tshow_create_table")
  foreach ($cr in $creates) { $d.Add("$($cr[0])`t$($cr[1])") }
W 'live_creates.tsv' $d.ToArray()

# ---------------------------------------------------------------- E. live_fk_edges.txt
$e = New-Object System.Collections.Generic.List[string]
foreach ($r in $tc) {
  $f = $r -split "`t"; if ($f[2] -ne 'FOREIGN KEY') { continue }
  foreach ($k in $kcuBy[$f[1]]) { $kf = $k -split "`t"; if ($kf[4]) { $e.Add("$($kf[1]).$($kf[2]) -> $($kf[4]).$($kf[5])") } }
}
W 'live_fk_edges.txt' $e.ToArray()

# ---------------------------------------------------------------- F/G. triggers
$g = New-Object System.Collections.Generic.List[string]
$g.Add("trigger_name`tevent_manipulation`tevent_object_table`taction_timing`tdefiner`taction_statement")
foreach ($r in $trg) { $f = $r -split "`t"; $g.Add("$($f[0])`t$($f[1])`t$($f[2])`t$($f[3])`t$($f[5])`t$($f[4])") }
W 'live_triggers.tsv' $g.ToArray()
W 'live_trig_names.txt' @($trg | ForEach-Object { ($_ -split "`t")[0] })

# ---------------------------------------------------------------- H. live_chk_names.txt
W 'live_chk_names.txt' @($chk | ForEach-Object { ($_ -split "`t")[1] })

# ---------------------------------------------------------------- fk_table_rows.txt / uq_table_rows.txt
$fkRows = New-Object System.Collections.Generic.List[string]
$fkRows.Add("constraint_name`tchild_table`tchild_columns`tparent_table`tparent_columns`ton_delete`ton_update")
$uqRows = New-Object System.Collections.Generic.List[string]
$uqRows.Add("constraint_name`ttable_name`tcolumns")
foreach ($r in $tc) {
  $f = $r -split "`t"
  if ($f[2] -eq 'FOREIGN KEY') {
    $cols = @(); $pcols = @(); $pt = ''
    foreach ($k in $kcuBy[$f[1]]) { $kf = $k -split "`t"; $cols += $kf[2]; $pcols += $kf[5]; $pt = $kf[4] }
    $dr = if ($rcMap.ContainsKey($f[1])) { $rcMap[$f[1]][0] } else { '?' }
    $ur = if ($rcMap.ContainsKey($f[1])) { $rcMap[$f[1]][1] } else { '?' }
    $fkRows.Add("$($f[1])`t$($f[0])`t$($cols -join ', ')`t$pt`t$($pcols -join ', ')`t$dr`t$ur")
  } elseif ($f[2] -eq 'UNIQUE') {
    $u = @(); foreach ($k in $kcuBy[$f[1]]) { $u += (($k -split "`t")[2]) }
    $uqRows.Add("$($f[1])`t$($f[0])`t$($u -join ', ')")
  }
}
W 'fk_table_rows.txt' $fkRows.ToArray()
W 'uq_table_rows.txt' $uqRows.ToArray()

# ---------------------------------------------------------------- K. dictionary_tables.md
$m = New-Object System.Collections.Generic.List[string]
$m.Add('# Diccionario de datos (live) - ' + $DB)
$m.Add('')
  $m.Add("- Fuente: ``information_schema`` leido de servidor MySQL vivo (${H}:${P}), $stamp.")
$m.Add("- Tier: **T2** (verificado contra servidor vivo). Motor: InnoDB / utf8mb4.")
$m.Add("- Conteos: $nTbl tablas, $nPk PK, $nFk FK, $nUq UNIQUE, $nChk CHECK, $idxTotal grupos de indice, $($trg.Count) triggers.")
$m.Add('')
$m.Add('| Tabla | Columnas | Filas est. | Descripcion |')
$m.Add('|---|---:|---:|---|')
$colBy = @{}; foreach ($r in $col) { $f = $r -split "`t"; if (-not $colBy.ContainsKey($f[0])) { $colBy[$f[0]] = New-Object System.Collections.Generic.List[string] }; $colBy[$f[0]].Add($r) }
$idxBy = @{}; foreach ($r in $idx) { $f = $r -split "`t"; if (-not $idxBy.ContainsKey($f[0])) { $idxBy[$f[0]] = New-Object System.Collections.Generic.List[string] }; $idxBy[$f[0]].Add($r) }
$tcBy = @{}; foreach ($r in $tc) { $f = $r -split "`t"; if (-not $tcBy.ContainsKey($f[0])) { $tcBy[$f[0]] = New-Object System.Collections.Generic.List[string] }; $tcBy[$f[0]].Add($r) }
$chkBy = @{}; foreach ($r in $chk) { $f = $r -split "`t"; if (-not $chkBy.ContainsKey($f[0])) { $chkBy[$f[0]] = New-Object System.Collections.Generic.List[string] }; $chkBy[$f[0]].Add($r) }
$tblInfo = @{}; foreach ($r in $tbl) { $f = $r -split "`t"; $tblInfo[$f[0]] = $f }
foreach ($t in $tableNames) {
  $info = $tblInfo[$t]
  $m.Add("| ``$t`` | $($colBy[$t].Count) | $($info[2]) | $(MdEsc $info[6]) |")
}
foreach ($t in $tableNames) {
  $info = $tblInfo[$t]
  $m.Add('')
  $m.Add("## ``$t``")
  $m.Add('')
  $m.Add("- Motor: $($info[1]); collation: $($info[5]); filas estimadas: $($info[2]); data_length: $($info[3]); index_length: $($info[4]).")
  $fksIn = @(); $fksOut = @()
  if ($tcBy.ContainsKey($t)) {
    foreach ($r in $tcBy[$t]) {
      $f = $r -split "`t"
      if ($f[2] -eq 'FOREIGN KEY') {
        $cols = @(); $pcols = @(); $pt = ''
        foreach ($k in $kcuBy[$f[1]]) { $kf = $k -split "`t"; $cols += $kf[2]; $pcols += $kf[5]; $pt = $kf[4] }
        $dr = if ($rcMap.ContainsKey($f[1])) { $rcMap[$f[1]][0] } else { '?' }
        $fksOut += "- FK ``$($f[1])``: ``$($cols -join ', ')`` -> ``$pt``(``$($pcols -join ', ')``) ON DELETE $dr"
      }
    }
  }
  foreach ($r in $tc) {
    $f = $r -split "`t"; if ($f[2] -ne 'FOREIGN KEY') { continue }
    $has = $false
    foreach ($k in $kcuBy[$f[1]]) { $kf = $k -split "`t"; if ($kf[4] -eq $t) { $has = $true } }
    if ($has) { $fksIn += "- FK ``$($f[1])`` referenciada por ``$($f[0])``" }
  }
  $m.Add('')
  $m.Add('### Columnas')
  $m.Add('')
  $m.Add('| # | Columna | Tipo | Nulo | Defecto | Extra | Comentario |')
  $m.Add('|---:|---|---|---|---|---|---|')
  foreach ($r in $colBy[$t]) {
    $f = $r -split "`t"
    $m.Add("| $($f[1]) | ``$($f[2])`` | ``$($f[3])`` | $($f[4]) | $(MdEsc $f[5]) | $(MdEsc $f[6]) | $(MdEsc $f[10]) |")
  }
  $m.Add('')
  $m.Add('### Claves y restricciones')
  $m.Add('')
  if ($tcBy.ContainsKey($t)) {
    foreach ($r in $tcBy[$t]) {
      $f = $r -split "`t"
      if ($f[2] -eq 'PRIMARY KEY') {
        $u = @(); foreach ($k in $kcuBy[$f[1]]) { $u += (($k -split "`t")[2]) }
        $m.Add("- **PK** ``$($u -join ', ')``")
      } elseif ($f[2] -eq 'UNIQUE') {
        $u = @(); foreach ($k in $kcuBy[$f[1]]) { $u += (($k -split "`t")[2]) }
        $m.Add("- **UNIQUE** ``$($f[1])``: ``$($u -join ', ')``")
      }
    }
  }
  foreach ($x in $fksOut) { $m.Add($x) }
  if ($chkBy.ContainsKey($t)) { foreach ($r in $chkBy[$t]) { $f = $r -split "`t"; $m.Add("- **CHECK** ``$($f[1])``: ``$($f[2])``") } }
  $m.Add('')
  $m.Add('### Indices')
  $m.Add('')
  $m.Add('| Indice | # | Columna | Unico | Tipo | Comentario |')
  $m.Add('|---|---:|---|---|---|---|')
  if ($idxBy.ContainsKey($t)) {
    foreach ($r in $idxBy[$t]) { $f = $r -split "`t"; $m.Add("| ``$($f[1])`` | $($f[2]) | ``$($f[3])`` | $(if ($f[4] -eq '0') { 'SI' } else { 'NO' }) | $($f[5]) | $(MdEsc $f[6]) |") }
  }
  if ($fksIn.Count -gt 0) { $m.Add(''); $m.Add('### Referenciada por'); $m.Add(''); foreach ($x in $fksIn) { $m.Add($x) } }
}
W 'dictionary_tables.md' $m.ToArray()

# ---------------------------------------------------------------- L. indexes_by_table.md
$ix = New-Object System.Collections.Generic.List[string]
$ix.Add('# Indices por tabla (live) - ' + $DB)
$ix.Add('')
$ix.Add("- Fuente: ``information_schema.statistics`` (${H}:${P}), $stamp. Tier **T2**.")
$ix.Add("- $idxTotal grupos de indice (tabla + nombre de indice) en $nTbl tablas.")
$ix.Add('')
foreach ($t in $tableNames) {
  $ix.Add('')
  $ix.Add("## ``$t``")
  $ix.Add('')
  $ix.Add('| Indice | Columnas | Unico | Tipo | Comentario |')
  $ix.Add('|---|---|---|---|---|')
  if ($idxBy.ContainsKey($t)) {
    $byIdx = @{}
    foreach ($r in $idxBy[$t]) {
      $f = $r -split "`t"
      if (-not $byIdx.ContainsKey($f[1])) { $byIdx[$f[1]] = New-Object System.Collections.Generic.List[object] }
      $byIdx[$f[1]].Add($f)
    }
    foreach ($n in ($byIdx.Keys | Sort-Object)) {
      $rows = $byIdx[$n]
      $cols = ($rows | Sort-Object { [int]$_[2] } | ForEach-Object { $_[3] }) -join '`, `'
      $first = $rows[0]
      $u = if ($first[4] -eq '0') { 'SI' } else { 'NO' }
      $ix.Add("| ``$n`` | ``$cols`` | $u | $($first[5]) | $(MdEsc $first[6]) |")
    }
  }
}
W 'indexes_by_table.md' $ix.ToArray()

# ---------------------------------------------------------------- modelo (parseo de 13_sql)
$v100 = RF 'V1.0.0__esquema_base.sql'
$v110 = RF 'V1.1.0__indices.sql'
$v140 = RF 'V1.4.0__triggers_auditoria.sql'

function Get-Balanced([string]$s, [int]$start) {
  $depth = 0
  for ($i = $start; $i -lt $s.Length; $i++) {
    $c = $s[$i]
    if ($c -eq '(') { $depth++ }
    elseif ($c -eq ')') { $depth--; if ($depth -eq 0) { return $s.Substring($start, $i - $start + 1) } }
  }
  return $s.Substring($start)
}

$reTbl = [regex]'(?is)CREATE TABLE\s+`?(?<n>\w+)`?\s*\((?<body>.*?)\)\s*ENGINE='
$reFk  = [regex]'(?is)CONSTRAINT\s+`?(?<n>\w+)`?\s+FOREIGN KEY\s*\((?<c>[^)]*)\)\s*REFERENCES\s+`?(?<p>\w+)`?\s*\((?<pc>[^)]*)\)(?<r>(?:\s*ON DELETE\s+(?:CASCADE|RESTRICT|SET NULL|NO ACTION))?(?:\s*ON UPDATE\s+(?:CASCADE|RESTRICT|SET NULL|NO ACTION))?)'
$reUq  = [regex]'(?is)CONSTRAINT\s+`?(?<n>\w+)`?\s+UNIQUE\s*\((?<c>[^)]*)\)'
$reCk  = [regex]'(?is)CONSTRAINT\s+`?(?<n>\w+)`?\s+CHECK\s*\('
$rePk  = [regex]'(?is)\bPRIMARY KEY\s*\((?<c>[^)]*)\)'

$modelTables = New-Object System.Collections.Generic.List[string]
$modelFks = New-Object System.Collections.Generic.List[object]
$modelUqs = New-Object System.Collections.Generic.List[object]
$modelCks = New-Object System.Collections.Generic.List[object]
$modelPks = @{}

foreach ($mt in $reTbl.Matches($v100)) {
  $name = $mt.Groups['n'].Value
  $body = $mt.Groups['body'].Value
  $modelTables.Add($name)
  foreach ($mf in $reFk.Matches($body)) {
    $rule = ($mf.Groups['r'].Value -replace '\s+', ' ').Trim()
    if ($rule -eq '') { $rule = 'NO ACTION / NO ACTION' }
    $modelFks.Add([pscustomobject]@{ table = $name; name = $mf.Groups['n'].Value; cols = $mf.Groups['c'].Value; parent = $mf.Groups['p'].Value; pcols = $mf.Groups['pc'].Value; rule = $rule })
  }
  foreach ($mu in $reUq.Matches($body)) {
    $modelUqs.Add([pscustomobject]@{ table = $name; name = $mu.Groups['n'].Value; cols = $mu.Groups['c'].Value })
  }
  foreach ($mc in $reCk.Matches($body)) {
    $open = $mc.Index + $mc.Length - 1
    $expr = Get-Balanced $body $open
    $modelCks.Add([pscustomobject]@{ table = $name; name = $mc.Groups['n'].Value; expr = ($expr -replace '\s+', ' ').Trim() })
  }
  $mp = $rePk.Match($body)
  if ($mp.Success) { $modelPks[$name] = $mp.Groups['c'].Value } else {
    $ic = [regex]'(?im)^\s*`?(?<n>\w+)`?\s+[^,\r\n]*\bPRIMARY KEY\b'
    $im = $ic.Match($body); if ($im.Success) { $modelPks[$name] = $im.Groups['n'].Value }
  }
}

$mTbl = New-Object System.Collections.Generic.List[string]
$mTbl.Add('# Tablas del modelo fisico (parseado de 13_sql/V1.0.0__esquema_base.sql)')
$mTbl.Add("# Parseado: $stamp | Tier T3 (derivado de artefacto versionado)")
$mTbl.Add("# Conteo: $($modelTables.Count)")
$mTbl.Add('')
foreach ($t in $modelTables) { $mTbl.Add($t) }
W 'model_tables.txt' $mTbl.ToArray()

# FK anadidos por ALTER TABLE (fuera de CREATE TABLE)
$reAlt = [regex]"(?is)ALTER TABLE\s+`?(?<t>\w+)`?\s+ADD\s+CONSTRAINT"
foreach ($ma in $reAlt.Matches($v100)) {
  $seg = $v100.Substring($ma.Index, [Math]::Min(800, $v100.Length - $ma.Index))
  foreach ($mf in $reFk.Matches($seg)) {
    $rule = ($mf.Groups['r'].Value -replace '\s+', ' ').Trim()
    if ($rule -eq '') { $rule = 'NO ACTION / NO ACTION' }
    $modelFks.Add([pscustomobject]@{ table = $ma.Groups['t'].Value; name = $mf.Groups['n'].Value; cols = $mf.Groups['c'].Value; parent = $mf.Groups['p'].Value; pcols = $mf.Groups['pc'].Value; rule = $rule })
  }
}
$mFk = New-Object System.Collections.Generic.List[string]
$mFk.Add('# FOREIGN KEY del modelo (13_sql/V1.0.0__esquema_base.sql)')
$mFk.Add("# Parseado: $stamp | Tier T3 | Conteo: $($modelFks.Count)")
$mFk.Add('# formato: tabla | constraint | columnas | tabla_padre | columnas_padre | reglas')
$mFk.Add('')
foreach ($x in $modelFks) { $mFk.Add("$($x.table) | $($x.name) | $($x.cols) | $($x.parent) | $($x.pcols) | $($x.rule)") }
W 'model_fk.txt' $mFk.ToArray()

$mFe = New-Object System.Collections.Generic.List[string]
foreach ($x in $modelFks) {
  $c = @(($x.cols -split ',') | ForEach-Object { $_.Trim().Trim('`') })
  $pc = @(($x.pcols -split ',') | ForEach-Object { $_.Trim().Trim('`') })
  for ($i = 0; $i -lt $c.Count; $i++) { $mFe.Add("$($x.table).$($c[$i]) -> $($x.parent).$($pc[$i])") }
}
W 'model_fk_edges.txt' $mFe.ToArray()

$mUq = New-Object System.Collections.Generic.List[string]
$mUq.Add('# UNIQUE del modelo (13_sql/V1.0.0__esquema_base.sql)')
$mUq.Add("# Parseado: $stamp | Tier T3 | Conteo: $($modelUqs.Count)")
$mUq.Add('# formato: constraint | tabla | columnas')
$mUq.Add('')
foreach ($x in $modelUqs) { $mUq.Add("$($x.name) | $($x.table) | $($x.cols)") }
W 'model_uniq.txt' $mUq.ToArray()

$mCk = New-Object System.Collections.Generic.List[string]
$mCk.Add('# CHECK del modelo (13_sql/V1.0.0__esquema_base.sql)')
$mCk.Add("# Parseado: $stamp | Tier T3 | Conteo: $($modelCks.Count)")
$mCk.Add('# formato: constraint | tabla | expresion')
$mCk.Add('')
foreach ($x in $modelCks) { $mCk.Add("$($x.name) | $($x.table) | $($x.expr)") }
W 'model_chk.txt' $mCk.ToArray()
W 'model_chk_names.txt' @($modelCks | ForEach-Object { $_.name })

# ---------------------------------------------------------------- er_edges / mermaid
$erPairs = New-Object System.Collections.Generic.List[string]
$mm = New-Object System.Collections.Generic.List[string]
foreach ($x in $modelFks) {
  $erPairs.Add("$($x.table) --> $($x.parent)")
  $mm.Add("$($x.parent) ||--o{ $($x.table) : `"$($x.name)`"")
}
$erDedup = @($erPairs | Sort-Object -Unique)
$erOut = New-Object System.Collections.Generic.List[string]
$erOut.Add('# Aristas ER (nivel tabla) derivadas de las FK del modelo (13_sql/V1.0.0)')
$erOut.Add("# Parseado: $stamp | Tier T3 | $($erDedup.Count) pares unicos de $($modelFks.Count) FK")
$erOut.Add('# formato: tabla_hija --> tabla_padre')
$erOut.Add('')
foreach ($x in $erDedup) { $erOut.Add($x) }
W 'er_edges.txt' $erOut.ToArray()

$mmOut = New-Object System.Collections.Generic.List[string]
$mmOut.Add('# Relaciones para bloques "erDiagram" de Mermaid (una por FK, con nombre de constraint)')
$mmOut.Add("# Generado: $stamp | Tier T3 | $($mm.Count) relaciones")
$mmOut.Add('# uso: pegar dentro de  erDiagram { ... }')
$mmOut.Add('')
foreach ($x in $mm) { $mmOut.Add($x) }
W 'mermaid_rel.txt' $mmOut.ToArray()

# ---------------------------------------------------------------- modelo_fisico.sql
$mf = New-Object System.Collections.Generic.List[string]
$mf.Add('-- =====================================================================')
$mf.Add('-- Modelo fisico consolidado: esquema + indices + triggers')
$mf.Add('-- BD: farmacia_doc | Motor: MySQL 8.4 LTS / InnoDB | utf8mb4_0900_ai_ci')
$mf.Add('-- Fuente: 13_sql/V1.0.0__esquema_base.sql + V1.1.0__indices.sql + V1.4.0__triggers_auditoria.sql')
$mf.Add('-- Nota: no incluye V1.2.0 (grants: contiene credenciales -> RNF-034) ni V1.3.0 (datos semilla).')
$mf.Add('-- Generado: ' + $stamp)
$mf.Add('-- =====================================================================')
$mf.Add('')
$mf.Add('-- >>> 13_sql/V1.0.0__esquema_base.sql')
$mf.Add($v100)
$mf.Add('')
$mf.Add('-- >>> 13_sql/V1.1.0__indices.sql')
$mf.Add($v110)
$mf.Add('')
$mf.Add('-- >>> 13_sql/V1.4.0__triggers_auditoria.sql')
$mf.Add($v140)
W 'modelo_fisico.sql' $mf.ToArray()

# ---------------------------------------------------------------- paridad
$liveTblSet = @($tableNames | Sort-Object)
$modelTblSet = @($modelTables | Sort-Object)
$tblDiff = @(($liveTblSet | Where-Object { $modelTblSet -notcontains $_ }) + @($modelTblSet | Where-Object { $liveTblSet -notcontains $_ }) | Sort-Object -Unique)

$liveEdges = @($e | Sort-Object -Unique)
$modelEdges = @($mFe | Sort-Object -Unique)
$edgeDiff = @(($liveEdges | Where-Object { $modelEdges -notcontains $_ }) + @($modelEdges | Where-Object { $liveEdges -notcontains $_ }) | Sort-Object -Unique)

$liveChk = @($chk | ForEach-Object { ($_ -split "`t")[1] } | Sort-Object -Unique)
$modelChk = @($modelCks | ForEach-Object { $_.name } | Sort-Object -Unique)
$chkDiff = @(($liveChk | Where-Object { $modelChk -notcontains $_ }) + @($modelChk | Where-Object { $liveChk -notcontains $_ }) | Sort-Object -Unique)

$liveUq = @($tc | Where-Object { ($_ -split "`t")[2] -eq 'UNIQUE' } | ForEach-Object { ($_ -split "`t")[1] } | Sort-Object -Unique)
$modelUq = @($modelUqs | ForEach-Object { $_.name } | Sort-Object -Unique)
$uqDiff = @(($liveUq | Where-Object { $modelUq -notcontains $_ }) + @($modelUq | Where-Object { $liveUq -notcontains $_ }) | Sort-Object -Unique)

$liveFkNames = @($tc | Where-Object { ($_ -split "`t")[2] -eq 'FOREIGN KEY' } | ForEach-Object { ($_ -split "`t")[1] } | Sort-Object -Unique)
$modelFkNames = @($modelFks | ForEach-Object { $_.name } | Sort-Object -Unique)
$fkDiff = @(($liveFkNames | Where-Object { $modelFkNames -notcontains $_ }) + @($modelFkNames | Where-Object { $liveFkNames -notcontains $_ }) | Sort-Object -Unique)

$gateOk = ($nTbl -eq 42 -and $modelTables.Count -eq 42 -and $nFk -eq 84 -and $modelFks.Count -eq 84 -and $nChk -eq 37 -and $modelCks.Count -eq 37 -and $nUq -eq 27 -and $modelUqs.Count -eq 27)
$identOk = ($tblDiff.Count -eq 0 -and $edgeDiff.Count -eq 0 -and $chkDiff.Count -eq 0 -and $uqDiff.Count -eq 0 -and $fkDiff.Count -eq 0)
$status = if ($gateOk -and $identOk) { 'VERIFIED' } else { 'INFERRED (ver detalle)' }

$files = @('introspection.txt','introspection.tsv','live_columns_full.tsv','live_creates.tsv','live_chk_names.txt','live_fk_edges.txt','live_trig_names.txt','live_triggers.tsv','model_chk.txt','model_chk_names.txt','model_fk.txt','model_fk_edges.txt','model_tables.txt','model_uniq.txt','dictionary_tables.md','indexes_by_table.md','er_edges.txt','fk_table_rows.txt','mermaid_rel.txt','uq_table_rows.txt','modelo_fisico.sql')

$r = New-Object System.Collections.Generic.List[string]
$r.Add('# .database-documentation - reporte de paridad')
$r.Add('')
$r.Add('**Estado: ' + $status + '**')
$r.Add('')
$r.Add('## Metodo y tiering')
$r.Add('')
$r.Add('| Artefacto | Tier | Fuente |')
$r.Add('|---|---|---|')
$r.Add('| `introspection.*`, `live_*`, `fk_table_rows.txt`, `uq_table_rows.txt`, `dictionary_tables.md`, `indexes_by_table.md` | T2 (verificado) | `information_schema` + `SHOW CREATE TABLE` leidos de servidor MySQL vivo |')
$r.Add('| `model_*`, `er_edges.txt`, `mermaid_rel.txt`, `modelo_fisico.sql` | T3 (derivado) | `13_sql/V1.0.0`, `V1.1.0`, `V1.4.0` parseados |')
$r.Add('')
$r.Add('Instancia live: MySQL 8.4.3 efimera en `127.0.0.1:33061`, BD `' + $DB + '`, creada aplicando V1.0.0 -> V1.1.0 -> V1.2.0 (placeholders sustituidos por valores de prueba efimeros) -> V1.3.0 -> V1.4.0. Es la misma tecnologia y mismos scripts que el registro historico de pruebas del Paso 19; no es una base de produccion ni un despliegue (sigue pendiente el Paso 16).')
$r.Add('')
$r.Add('## Gate de conteos (42/84/37/27)')
$r.Add('')
$r.Add('| Objeto | Live (T2) | Modelo (T3) | Match |')
$r.Add('|---|---:|---:|---|')
$r.Add("| Tablas | $nTbl | $($modelTables.Count) | $(if ($nTbl -eq $modelTables.Count) { 'OK' } else { 'FALLO' }) |")
$r.Add("| FK | $nFk | $($modelFks.Count) | $(if ($nFk -eq $modelFks.Count) { 'OK' } else { 'FALLO' }) |")
$r.Add("| CHECK | $nChk | $($modelCks.Count) | $(if ($nChk -eq $modelCks.Count) { 'OK' } else { 'FALLO' }) |")
$r.Add("| UNIQUE | $nUq | $($modelUqs.Count) | $(if ($nUq -eq $modelUqs.Count) { 'OK' } else { 'FALLO' }) |")
$r.Add("| PK | $nPk | $($modelPks.Count) | $(if ($nPk -eq $modelPks.Count) { 'OK' } else { 'FALLO' }) |")
$r.Add("| Triggers | $($trg.Count) | 2 (V1.4.0) | $(if ($trg.Count -eq 2) { 'OK' } else { 'FALLO' }) |")
$r.Add("| Grupos de indice | $idxTotal | *(V1.1.0 + auto)* | informativo |")
$r.Add('')
$r.Add('## Diferencias de identidad (live vs modelo)')
$r.Add('')
$r.Add('| Conjunto | Diferencias | Detalle |')
$r.Add('|---|---:|---|')
$r.Add("| Tablas | $($tblDiff.Count) | $($tblDiff -join ', ') |")
$r.Add("| Nombres FK | $($fkDiff.Count) | $($fkDiff -join ', ') |")
$r.Add("| Aristas columna->columna | $($edgeDiff.Count) | $($edgeDiff -join '; ') |")
$r.Add("| Nombres CHECK | $($chkDiff.Count) | $($chkDiff -join ', ') |")
$r.Add("| Nombres UNIQUE | $($uqDiff.Count) | $($uqDiff -join ', ') |")
$r.Add('')
$r.Add('## Inventario de archivos (21)')
$r.Add('')
$r.Add('| # | Archivo | Bytes |')
$r.Add('|---:|---|---:|')
$i = 1
foreach ($f in $files) {
  $pf = Join-Path $out $f
  if (Test-Path $pf) { $r.Add("| $i | ``$f`` | $((Get-Item $pf).Length) |") } else { $r.Add("| $i | ``$f`` | FALTA |") ; $status = 'INFERRED (archivo faltante)' }
  $i++
}
$r.Add('')
$r.Add('El generador `gen_doc.ps1` vive en esta carpeta y NO cuenta como artefacto.')
$r.Add('')
$r.Add('## Reproduccion')
$r.Add('')
$r.Add('1. Instancia efimera: `mysqld --initialize-insecure --datadir=<tmp>`; `mysqld --port=33061 --bind-address=127.0.0.1`.')
$r.Add('2. `CREATE DATABASE farmacia_doc ...` y aplicar `13_sql/V1.0.0..V1.4.0` (V1.2.0 con placeholders sustituidos).')
$r.Add('3. Correr `gen_doc.ps1` (incluido en esta misma carpeta): reintrospeccion + parseo de `13_sql` + paridad.')
$r.Add('4. Apagar `mysqladmin shutdown` y borrar el datadir efimero.')
W 'README.md' $r.ToArray()

Write-Output "STATUS=$status  tablas=$nTbl/$($modelTables.Count) fk=$nFk/$($modelFks.Count) chk=$nChk/$($modelCks.Count) uq=$nUq/$($modelUqs.Count) pk=$nPk/$($modelPks.Count) idx=$idxTotal"
Write-Output "diffs tablas=$($tblDiff.Count) fknames=$($fkDiff.Count) edges=$($edgeDiff.Count) chk=$($chkDiff.Count) uq=$($uqDiff.Count)"
if ($tblDiff.Count -gt 0) { Write-Output ("TBL: " + ($tblDiff -join ',')) }
if ($fkDiff.Count -gt 0) { Write-Output ("FK : " + ($fkDiff -join ',')) }
if ($edgeDiff.Count -gt 0) { Write-Output ("EDG: " + ($edgeDiff -join ' ; ')) }
if ($chkDiff.Count -gt 0) { Write-Output ("CHK: " + ($chkDiff -join ',')) }
if ($uqDiff.Count -gt 0) { Write-Output ("UQ : " + ($uqDiff -join ',')) }
