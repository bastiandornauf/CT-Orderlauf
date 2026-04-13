<?php
/**
 * IMAP-Credential-Check – sofort nach dem Aufrufen löschen!
 * https://orderlauf.bdornauf.de/smtp-webspace-test.php
 */

$user = 'carolus.thermen@bdornauf.de';
$pass = 'ionosIstDoof';

header('Content-Type: text/plain; charset=utf-8');

// IMAP SSL 993
$conn = @stream_socket_client(
    "ssl://imap.ionos.de:993", $errno, $errstr, 10,
    STREAM_CLIENT_CONNECT,
    stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
);
if (!$conn) {
    echo "IMAP-Verbindung fehlgeschlagen: {$errstr}\n";
} else {
    stream_set_timeout($conn, 10);
    $g = trim(fgets($conn, 512));
    echo "S: {$g}\n";
    fwrite($conn, "A1 LOGIN {$user} {$pass}\r\n");
    $r = trim(fgets($conn, 512));
    echo "S: {$r}\n";
    echo str_contains($r, 'OK') ? "\n>>> CREDENTIALS KORREKT <<<\n" : "\n>>> CREDENTIALS FALSCH – Passwort im IONOS-Panel neu setzen <<<\n";
    fwrite($conn, "A2 LOGOUT\r\n");
    fclose($conn);
}
