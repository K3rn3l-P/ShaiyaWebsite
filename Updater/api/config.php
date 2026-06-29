<?php
// Updater API configuration.
// Segreti di gioco letti da GMSecurity.ini — unica fonte di verità.
// Dev: modifica GMSecurity.ini per ruotare TokenSecret, ClientSecret, SahKeyHex.
// Credenziali DB: variabili d'ambiente Windows (SetEnv in Apache o sistema).

$readEnv = static function ($name, $default = '') {
    $value = getenv($name);
    if ($value === false || $value === null || $value === '') return $default;
    return $value;
};

// Legge [SETTINGS] da GMSecurity.ini.
// Fallback a variabile d'ambiente se la chiave non esiste nell'INI.
$_gmIni = 'C:\\ShaiyaServer\\PSM_Client\\Bin\\Data\\GMSecurity.ini';
$_gmSec    = is_file($_gmIni) ? (@parse_ini_file($_gmIni, true, INI_SCANNER_RAW) ?: []) : [];
$_gm       = isset($_gmSec['SETTINGS']) ? $_gmSec['SETTINGS'] : [];
$_hwid_sec = isset($_gmSec['HWID'])     ? $_gmSec['HWID']     : [];
$readGm = static function ($key, $envFallback = '') use ($_gm, $readEnv) {
    $v = isset($_gm[$key]) ? trim((string)$_gm[$key]) : '';
    return $v !== '' ? $v : $readEnv($envFallback, '');
};

return [
    'db' => [
        'server'                   => '127.0.0.1',
        'database'                 => 'PS_UserData',
        'user'                     => $readEnv('DB_USER', ''),
        'password'                 => $readEnv('DB_PASSWORD', ''),
        'trust_server_certificate' => true,
        'encrypt'                  => false,
        'login_timeout'            => 5,
        'query_timeout'            => 15,
    ],
    'auth' => [
        'game_password_mode'          => 'plain',
        'allow_saved_login_by_device' => true,
        'allowed_statuses'            => [0, 16, 32, 48, 80],
    ],
    'security' => [
        'max_userid_length'  => 18,
        'max_password_length' => 32,
    ],
    // Probe del login server (ps_login) per api/status.php.
    // Default 127.0.0.1:30800 (apache sullo stesso host di ps_login). cache_ttl limita la frequenza
    // della probe TCP (guard anti DoS-amplification).
    'status' => [
        'login_host' => '127.0.0.1',
        'login_port' => 30800,
        'timeout'    => 1.0,
        'cache_ttl'  => 10,
    ],
    // Tutti i segreti di gioco da GMSecurity.ini [SETTINGS]:
    'token_secret'  => $readGm('TokenSecret',  'TOKEN_SECRET'),   // GM saved-account HMAC
    'client_secret' => $readGm('ClientSecret', 'CLIENT_SECRET'),  // 0xA101 client auth
    'sah_key_hex'   => $readGm('SahKeyHex',    'SAH_KEY_HEX'),    // AES-256 data.sah (client live)
    'patch_key_hex' => $readGm('PatchKeyHex',  'PATCH_KEY_HEX'),  // AES-256 patch/update files
    // Chiave HMAC-SHA256 per firma device.id.sig — da GMSecurity.ini [HWID] HwidSigKey.
    // MAI nel client. Usata da sign_hwid_for_response() in common.php.
    'hwid_sig_key'  => isset($_hwid_sec['HwidSigKey']) ? trim((string)$_hwid_sec['HwidSigKey']) : '',
];
