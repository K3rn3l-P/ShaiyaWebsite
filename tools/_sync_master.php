<?php
// One-shot sync script — apply all existing patches to data_master.sah
// DELETE this file after use.

function patchGetPatchKeyHex(): ?string
{
    $iniPath = 'C:\\ShaiyaServer\\PSM_Client\\Bin\\Data\\GMSecurity.ini';
    if (!is_file($iniPath)) return null;
    $sections = @parse_ini_file($iniPath, true, INI_SCANNER_RAW);
    if (!is_array($sections)) return null;
    $key = isset($sections['SETTINGS']['PatchKeyHex']) ? trim((string)$sections['SETTINGS']['PatchKeyHex']) : '';
    return (strlen($key) === 64 && ctype_xdigit($key)) ? $key : null;
}

function patchEsahDecrypt(string $esahData, string $keyHex): ?string
{
    if (strlen($esahData) < 64 || substr($esahData, 0, 4) !== 'ESAH') return null;
    $key   = hex2bin($keyHex);
    $nonce = substr($esahData, 16, 12);
    $tag   = substr($esahData, 28, 16);
    $plain = openssl_decrypt(substr($esahData, 64), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    return ($plain !== false) ? $plain : null;
}

function parseSahFolderNode(string $sah, int &$offset): array
{
    $nameLen = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
    $name    = rtrim(substr($sah, $offset, $nameLen), "\x00"); $offset += $nameLen;
    $node    = ['name' => $name, 'files' => [], 'folders' => []];
    $fc = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
    for ($i = 0; $i < $fc; $i++) {
        $fnLen = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
        $fn    = rtrim(substr($sah, $offset, $fnLen), "\x00"); $offset += $fnLen;
        $start = unpack('P', substr($sah, $offset, 8))[1]; $offset += 8;
        $len   = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
        $ver   = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
        $node['files'][strtolower($fn)] = ['name' => $fn, 'start' => $start, 'length' => $len, 'version' => $ver];
    }
    $dc = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
    for ($i = 0; $i < $dc; $i++) {
        $sub = parseSahFolderNode($sah, $offset);
        $node['folders'][strtolower($sub['name'])] = $sub;
    }
    return $node;
}

function parseSahBinary(string $sah): ?array
{
    if (strlen($sah) < 60 || substr($sah, 0, 3) !== 'SAH') return null;
    $offset = 56;
    $root   = ['name' => '', 'files' => [], 'folders' => []];
    $fc = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
    for ($i = 0; $i < $fc; $i++) {
        $fnLen = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
        $fn    = rtrim(substr($sah, $offset, $fnLen), "\x00"); $offset += $fnLen;
        $start = unpack('P', substr($sah, $offset, 8))[1]; $offset += 8;
        $len   = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
        $ver   = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
        $root['files'][strtolower($fn)] = ['name' => $fn, 'start' => $start, 'length' => $len, 'version' => $ver];
    }
    $dc = unpack('V', substr($sah, $offset, 4))[1]; $offset += 4;
    for ($i = 0; $i < $dc; $i++) {
        $sub = parseSahFolderNode($sah, $offset);
        $root['folders'][strtolower($sub['name'])] = $sub;
    }
    return $root;
}

function sahMergeFolderNodes(array &$master, array $patch, int &$safSize): void
{
    foreach ($patch['files'] as $key => $pf) {
        if (isset($master['files'][$key])) {
            $mf = &$master['files'][$key];
            if ($mf['length'] < $pf['length']) { $mf['start'] = $safSize; $safSize += $pf['length']; }
            $mf['length'] = $pf['length'];
            unset($mf);
        } else {
            $master['files'][$key] = ['name' => $pf['name'], 'start' => $safSize, 'length' => $pf['length'], 'version' => 0];
            $safSize += $pf['length'];
        }
    }
    foreach ($patch['folders'] as $key => $pf) {
        if (!isset($master['folders'][$key])) {
            $master['folders'][$key] = ['name' => $pf['name'], 'files' => [], 'folders' => []];
        }
        sahMergeFolderNodes($master['folders'][$key], $pf, $safSize);
    }
}

function serializeSahFileEntry(array $f): string
{
    $name = $f['name'] . "\x00";
    return pack('V', strlen($name)) . $name . pack('P', $f['start']) . pack('V', $f['length']) . pack('V', $f['version']);
}

function serializeSahFolderEntry(array $folder, int &$total): string
{
    $name = $folder['name'] . "\x00";
    $buf  = pack('V', strlen($name)) . $name;
    $files = array_values($folder['files']);
    $total += count($files);
    $buf .= pack('V', count($files));
    foreach ($files as $f) { $buf .= serializeSahFileEntry($f); }
    $subs = array_values($folder['folders']);
    $buf .= pack('V', count($subs));
    foreach ($subs as $s) { $buf .= serializeSahFolderEntry($s, $total); }
    return $buf;
}

function serializeSah(array $root): string
{
    $total   = 0;
    $files   = array_values($root['files']);
    $total  += count($files);
    $content = pack('V', count($files));
    foreach ($files as $f) { $content .= serializeSahFileEntry($f); }
    $subs    = array_values($root['folders']);
    $content .= pack('V', count($subs));
    foreach ($subs as $s) { $content .= serializeSahFolderEntry($s, $total); }
    return 'SAH' . "\x00" . pack('V', 0) . pack('V', $total)
         . str_repeat("\x00", 40) . pack('V', 1) . "\x00"
         . $content . str_repeat("\x00", 8);
}

// ─── main ─────────────────────────────────────────────────────────────────────

$masterPath       = 'C:\\xampp\\htdocs\\Updater\\data_master.sah';
$masterSafSizePath = 'C:\\xampp\\htdocs\\Updater\\data_master.saf_size';
$patchesDir       = 'C:\\xampp\\htdocs\\Updater\\Patches';
$iniPath          = 'C:\\ShaiyaServer\\PSM_Client\\Bin\\Data\\GMSecurity.ini';

echo "=== patch_sync_master ===\n";

// Load master
$masterBin = file_get_contents($masterPath);
if ($masterBin === false) { echo "ERROR: cannot read data_master.sah\n"; exit(1); }
$safSize = (int)trim((string)file_get_contents($masterSafSizePath));
echo "Master SAH loaded: " . strlen($masterBin) . " bytes, safSize=$safSize\n";

$master = parseSahBinary($masterBin);
if ($master === null) { echo "ERROR: cannot parse master SAH (invalid format)\n"; exit(1); }

// Get all patches sorted
$patches = glob($patchesDir . '\\*.patch');
sort($patches);
echo count($patches) . " patches found.\n";

foreach ($patches as $pf) {
    $pname = basename($pf);
    echo "Applying: $pname ... ";

    $zip = new ZipArchive();
    if ($zip->open($pf) !== true) { echo "ERROR: cannot open ZIP\n"; exit(1); }
    $patchSahData = $zip->getFromName('update.sah');
    $zip->close();
    if ($patchSahData === false) { echo "ERROR: update.sah not in patch\n"; exit(1); }

    $patchSahPlain = $patchSahData;
    if (strlen($patchSahData) >= 4 && substr($patchSahData, 0, 4) === 'ESAH') {
        $pk = patchGetPatchKeyHex();
        if ($pk === null) { echo "ERROR: no PatchKeyHex\n"; exit(1); }
        $patchSahPlain = patchEsahDecrypt($patchSahData, $pk);
        if ($patchSahPlain === null) { echo "ERROR: ESAH decrypt failed\n"; exit(1); }
        echo "(ESAH decrypted) ";
    } else {
        echo "(plain SAH) ";
    }

    $patch = parseSahBinary($patchSahPlain);
    if ($patch === null) { echo "ERROR: cannot parse patch SAH\n"; exit(1); }

    $filesBefore = strlen(serialize($master['files']));
    sahMergeFolderNodes($master, $patch, $safSize);
    echo "OK\n";
}

// Serialize new master
$newBin = serializeSah($master);
echo "New master SAH: " . strlen($newBin) . " bytes, safSize=$safSize\n";

// Write
file_put_contents($masterPath, $newBin);
file_put_contents($masterSafSizePath, (string)$safSize);

// Calculate token
$token = bin2hex(substr(hash('sha256', $newBin, true), 0, 8));
echo "SahToken: $token\n";

// Update GMSecurity.ini
$content = file_get_contents($iniPath);
if (preg_match('/^SahTokenHex\s*=/im', $content)) {
    $content = preg_replace('/^SahTokenHex\s*=.*/im', 'SahTokenHex=' . $token, $content);
} else {
    $content .= "\r\nSahTokenHex=" . $token . "\r\n";
}
file_put_contents($iniPath, $content);
echo "GMSecurity.ini updated.\n";

// Restart ps_login
echo "Stopping ps_login...\n";
exec('sc.exe stop ps_login', $out, $ret);
sleep(3);
echo "Starting ps_login...\n";
exec('sc.exe start ps_login', $out, $ret);
echo "Done. SahTokenHex=$token\n";
