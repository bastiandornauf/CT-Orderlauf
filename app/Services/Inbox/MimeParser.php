<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Kleiner MIME-Parser für den Posteingang: Kopfzeilen, lesbarer Text und Anhänge einer Mail.
 * Bewusst ohne Erweiterungen (ext-imap/mailparse sind auf dem Webspace nicht sicher vorhanden).
 * Weitergeleitete Mails als Anhang (message/rfc822) werden mit ausgepackt.
 */
final class MimeParser
{
    private const MAX_DEPTH = 8;

    /**
     * @return array{from_email:string, from_name:string, subject:string, message_id:string, date:string,
     *               text:string, attachments:list<array{name:string, mime:string, content:string}>}
     */
    public static function parse(string $raw): array
    {
        [$headers, $body] = self::split($raw);
        [$fromName, $fromEmail] = self::parseAddress(self::header($headers, 'from'));

        $plain = [];
        $html = [];
        $attachments = [];
        self::walk($headers, $body, $plain, $html, $attachments, 0);

        $text = $plain !== [] ? implode("\n\n", $plain) : implode("\n\n", array_map([self::class, 'htmlToText'], $html));

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject' => self::decodeHeader(self::header($headers, 'subject')),
            'message_id' => trim(self::header($headers, 'message-id')),
            'date' => trim(self::header($headers, 'date')),
            'text' => self::tidyText($text),
            'attachments' => $attachments,
        ];
    }

    /** @return array{0:string, 1:string} [Name, E-Mail klein] */
    public static function parseAddress(string $value): array
    {
        $value = self::decodeHeader($value);
        if (preg_match('/^(.*)<\s*([^<>\s]+@[^<>\s]+)\s*>/s', $value, $m)) {
            return [trim(trim($m[1]), "\" \t"), mb_strtolower(trim($m[2]))];
        }
        if (preg_match('/[^\s<>"]+@[^\s<>"]+/', $value, $m)) {
            return ['', mb_strtolower(trim($m[0], ' ,;'))];
        }

        return [trim($value), ''];
    }

    public static function decodeHeader(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !str_contains($value, '=?')) {
            return self::toUtf8($value, '');
        }
        // Leerraum zwischen zwei kodierten Wörtern gehört nicht zum Text (RFC 2047)
        $value = (string) preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value);

        return (string) preg_replace_callback(
            '/=\?([^?]+)\?([bqBQ])\?([^?]*)\?=/',
            static function (array $m): string {
                $charset = preg_replace('/\*.*$/', '', $m[1]);   // RFC 2231: Sprache abschneiden
                $data = strtoupper($m[2]) === 'B'
                    ? (string) base64_decode($m[3])
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));

                return self::toUtf8($data, (string) $charset);
            },
            $value
        );
    }

    /** @return array{0:array<string, list<string>>, 1:string} */
    private static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        $head = $pos === false ? $raw : substr($raw, 0, $pos);
        $body = $pos === false ? '' : substr($raw, $pos + 2);
        $head = (string) preg_replace("/\n[ \t]+/", ' ', $head);   // gefaltete Zeilen zusammenführen

        $headers = [];
        foreach (explode("\n", $head) as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $colon)))][] = trim(substr($line, $colon + 1));
        }

        return [$headers, $body];
    }

    /** @param array<string, list<string>> $headers */
    private static function header(array $headers, string $name): string
    {
        return $headers[$name][0] ?? '';
    }

    /**
     * Wert + Parameter einer Kopfzeile, z. B. Content-Type oder Content-Disposition.
     *
     * @return array{0:string, 1:array<string, string>}
     */
    private static function params(string $value): array
    {
        $parts = self::splitParams($value);
        $main = strtolower(trim((string) array_shift($parts)));
        $params = [];
        $continued = [];
        foreach ($parts as $p) {
            $eq = strpos($p, '=');
            if ($eq === false) {
                continue;
            }
            $key = strtolower(trim(substr($p, 0, $eq)));
            $val = trim(substr($p, $eq + 1));
            if (strlen($val) >= 2 && $val[0] === '"' && str_ends_with($val, '"')) {
                $val = stripcslashes(substr($val, 1, -1));
            }
            // RFC 2231: filename*0*=…, filename*1*=…  bzw. filename*=UTF-8''…
            if (preg_match('/^([^*]+)\*(\d+)?(\*)?$/', $key, $m)) {
                $continued[$m[1]][(int) ($m[2] ?? 0)] = [$val, isset($m[3]) || !isset($m[2])];
                continue;
            }
            $params[$key] = $val;
        }
        foreach ($continued as $key => $chunks) {
            ksort($chunks);
            $charset = '';
            $out = '';
            foreach ($chunks as $i => [$val, $encoded]) {
                if ($encoded) {
                    if ($i === 0 && preg_match("/^([^']*)'[^']*'(.*)$/s", $val, $m)) {
                        $charset = $m[1];
                        $val = $m[2];
                    }
                    $val = rawurldecode($val);
                }
                $out .= $val;
            }
            $params[$key] = self::toUtf8($out, $charset);
        }

        return [$main, $params];
    }

    /** @return list<string> an „;“ trennen, aber nicht innerhalb von Anführungszeichen */
    private static function splitParams(string $value): array
    {
        $parts = [];
        $buf = '';
        $quoted = false;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            if ($ch === '\\' && $quoted && $i + 1 < $len) {
                $buf .= $ch . $value[++$i];
                continue;
            }
            if ($ch === '"') {
                $quoted = !$quoted;
            }
            if ($ch === ';' && !$quoted) {
                $parts[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        $parts[] = $buf;

        return $parts;
    }

    /**
     * @param array<string, list<string>> $headers
     * @param list<string> $plain
     * @param list<string> $html
     * @param list<array{name:string, mime:string, content:string}> $attachments
     */
    private static function walk(array $headers, string $body, array &$plain, array &$html, array &$attachments, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        [$type, $ctParams] = self::params(self::header($headers, 'content-type') ?: 'text/plain; charset=us-ascii');
        [$disposition, $cdParams] = self::params(self::header($headers, 'content-disposition'));
        $encoding = strtolower(trim(self::header($headers, 'content-transfer-encoding')));

        if (str_starts_with($type, 'multipart/') && ($ctParams['boundary'] ?? '') !== '') {
            foreach (self::multipartParts($body, $ctParams['boundary']) as $part) {
                [$h, $b] = self::split($part);
                self::walk($h, $b, $plain, $html, $attachments, $depth + 1);
            }

            return;
        }

        $content = self::decodeBody($body, $encoding);
        $name = self::decodeHeader($cdParams['filename'] ?? $ctParams['name'] ?? '');

        if ($type === 'message/rfc822') {
            [$h, $b] = self::split($content);
            $inner = [];
            $innerHtml = [];
            self::walk($h, $b, $inner, $innerHtml, $attachments, $depth + 1);
            $label = 'Weitergeleitet: ' . self::decodeHeader(self::header($h, 'subject'));
            $innerText = $inner !== [] ? implode("\n\n", $inner) : implode("\n\n", array_map([self::class, 'htmlToText'], $innerHtml));
            if (trim($innerText) !== '') {
                $plain[] = "----- {$label} -----\n" . $innerText;
            }

            return;
        }

        $isText = $type === 'text/plain' || $type === 'text/html';
        if ($isText && $disposition !== 'attachment' && $name === '') {
            $text = self::toUtf8($content, $ctParams['charset'] ?? '');
            if ($type === 'text/plain') {
                $plain[] = $text;
            } else {
                $html[] = $text;
            }

            return;
        }

        if ($content === '' || $type === 'application/pkcs7-signature' || $type === 'application/x-pkcs7-signature') {
            return;
        }
        // Signatur-Logos u. ä. ohne Namen und sehr klein überspringen
        if ($name === '' && str_starts_with($type, 'image/') && strlen($content) < 15000) {
            return;
        }
        if ($name === '') {
            $name = 'anhang-' . (count($attachments) + 1) . self::extensionFor($type);
        }
        $attachments[] = ['name' => $name, 'mime' => $type, 'content' => $content];
    }

    /** @return list<string> */
    private static function multipartParts(string $body, string $boundary): array
    {
        $parts = [];
        $delim = '--' . $boundary;
        $chunks = explode("\n" . $delim, "\n" . $body);
        array_shift($chunks);   // Präambel
        foreach ($chunks as $chunk) {
            if (str_starts_with($chunk, '--')) {
                break;   // Schlussgrenze
            }
            $nl = strpos($chunk, "\n");
            $parts[] = $nl === false ? '' : substr($chunk, $nl + 1);
        }

        return $parts;
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode((string) preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode(str_replace("\n", "\r\n", $body)),
            default => $body,
        };
    }

    private static function toUtf8(string $text, string $charset): string
    {
        $charset = strtoupper(trim($charset, " \"'"));
        if ($charset === '' || $charset === 'US-ASCII' || $charset === 'UTF-8' || $charset === 'UTF8') {
            return mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        if (in_array($charset, ['ISO-8859-1', 'LATIN1', 'ISO-8859-15'], true)) {
            $charset = 'Windows-1252';   // Obermenge, verbreitet falsch deklariert
        }
        try {
            $out = mb_convert_encoding($text, 'UTF-8', $charset);
        } catch (\ValueError) {
            $out = false;
        }

        return is_string($out) ? $out : (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    public static function htmlToText(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = (string) preg_replace('#</(p|div|tr|li|h\d|table|blockquote)>#i', "\n", $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function tidyText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => '.pdf',
            'image/jpeg', 'image/jpg' => '.jpg',
            'image/png' => '.png',
            'image/heic' => '.heic',
            'image/gif' => '.gif',
            'text/plain' => '.txt',
            'text/html' => '.html',
            default => '.bin',
        };
    }
}
