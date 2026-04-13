<?php
/**
 * Brute-Force SMTP-Test: alle Kombinationen aus Port/Verschlüsselung/Username-Format
 */

$host     = 'smtp.ionos.de';
$email    = 'carolus.thermen@bdornauf.de';
$pass     = 'XLtP8VH9m9dfPrFvHrXRkPpKs2hdkN7f';
$tests = [
    ['port' => 587, 'ssl' => false, 'user' => $email, 'label' => '587/STARTTLS/app-passwort'],
    ['port' => 465, 'ssl' => true,  'user' => $email, 'label' => '465/SSL/app-passwort'],
];

foreach ($tests as $t) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "TEST: {$t['label']}\n";
    echo str_repeat('=', 60) . "\n";

    $prefix = $t['ssl'] ? 'ssl://' : '';
    $target = "{$prefix}{$host}:{$t['port']}";

    $conn = @stream_socket_client(
        $target, $errno, $errstr, 10,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
    );
    if (!$conn) {
        echo "  FEHLER: Verbindung zu {$target} fehlgeschlagen: {$errstr}\n";
        continue;
    }
    stream_set_timeout($conn, 10);

    $greeting = smtpRead($conn);
    echo "  S: {$greeting}\n";

    $ehlo = gethostname() ?: 'localhost';
    smtpWrite($conn, "EHLO {$ehlo}");
    $ehloR = smtpRead($conn);
    // nur AUTH-Zeile ausgeben
    foreach (explode("\n", $ehloR) as $l) {
        $l = trim($l);
        if (stripos($l, 'AUTH') !== false || stripos($l, '250 ') === 0) {
            echo "  S: {$l}\n";
        }
    }

    if (!$t['ssl']) {
        smtpWrite($conn, 'STARTTLS');
        $stR = smtpRead($conn);
        echo "  S: {$stR}\n";
        if (!str_starts_with($stR, '220')) {
            echo "  STARTTLS nicht moeglich, skip.\n";
            fclose($conn);
            continue;
        }
        $cryptoOk = @stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        if (!$cryptoOk) {
            echo "  TLS-Handshake fehlgeschlagen, skip.\n";
            fclose($conn);
            continue;
        }
        echo "  TLS aktiv.\n";
        smtpWrite($conn, "EHLO {$ehlo}");
        $ehlo2 = smtpRead($conn);
        foreach (explode("\n", $ehlo2) as $l) {
            $l = trim($l);
            if (stripos($l, 'AUTH') !== false) {
                echo "  S: {$l}\n";
            }
        }
    }

    // AUTH PLAIN
    $user = $t['user'];
    smtpWrite($conn, 'AUTH PLAIN ' . base64_encode("\0{$user}\0{$pass}"));
    $r = smtpRead($conn);
    $code = (int)substr($r, 0, 3);
    echo "  AUTH PLAIN ({$user}): {$r}\n";

    if ($code === 235) {
        echo "  >>> ERFOLG! <<<\n";
        smtpWrite($conn, 'QUIT');
        smtpRead($conn);
        fclose($conn);
        continue;
    }

    // RSET + AUTH LOGIN
    smtpWrite($conn, 'RSET');
    smtpRead($conn);

    smtpWrite($conn, 'AUTH LOGIN');
    smtpRead($conn);
    smtpWrite($conn, base64_encode($user));
    smtpRead($conn);
    smtpWrite($conn, base64_encode($pass));
    $r2 = smtpRead($conn);
    $code2 = (int)substr($r2, 0, 3);
    echo "  AUTH LOGIN ({$user}): {$r2}\n";

    if ($code2 === 235) {
        echo "  >>> ERFOLG! <<<\n";
    } else {
        echo "  FEHLGESCHLAGEN.\n";
    }

    smtpWrite($conn, 'QUIT');
    @smtpRead($conn);
    fclose($conn);
}

echo "\n" . str_repeat('=', 60) . "\nAlle Tests abgeschlossen.\n";

function smtpWrite($conn, string $cmd): void {
    fwrite($conn, "{$cmd}\r\n");
}
function smtpRead($conn): string {
    $data = '';
    while ($line = fgets($conn, 512)) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return trim($data);
}
