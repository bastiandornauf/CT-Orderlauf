<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Inbox\InboxClassifier;
use App\Services\Inbox\MimeParser;
use App\Services\Inbox\ZipWriter;
use PHPUnit\Framework\TestCase;

final class InboxMailTest extends TestCase
{
    public function testForwardedMailWithPdfAndEncodedHeaders(): void
    {
        $pdf = "%PDF-1.4 Testinhalt " . str_repeat('x', 200);
        $raw = implode("\r\n", [
            'From: =?UTF-8?Q?Bj=C3=B6rn_D?= <Gastronomie@Example.de>',
            'Subject: =?UTF-8?B?V0c6IFJlY2hudW5nIFDDvHR6?=',
            'Message-ID: <abc@example.de>',
            'Date: Mon, 2 Nov 2026 09:15:00 +0100',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="b1"',
            '',
            '--b1',
            'Content-Type: multipart/alternative; boundary="b2"',
            '',
            '--b2',
            'Content-Type: text/plain; charset=ISO-8859-1',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            'Gr=FC=DFe, siehe Anhang',
            '--b2',
            'Content-Type: text/html; charset=UTF-8',
            '',
            '<p>HTML</p>',
            '--b2--',
            '--b1',
            'Content-Type: application/pdf',
            "Content-Disposition: attachment; filename*=UTF-8''Rechnung%20P%C3%BCtz.pdf",
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($pdf), 76, "\r\n"),
            '--b1--',
            '',
        ]);

        $m = MimeParser::parse($raw);

        self::assertSame('gastronomie@example.de', $m['from_email']);
        self::assertSame('Björn D', $m['from_name']);
        self::assertSame('WG: Rechnung Pütz', $m['subject']);
        self::assertSame('<abc@example.de>', $m['message_id']);
        self::assertSame('Grüße, siehe Anhang', $m['text']);
        self::assertCount(1, $m['attachments']);
        self::assertSame('Rechnung Pütz.pdf', $m['attachments'][0]['name']);
        self::assertSame('application/pdf', $m['attachments'][0]['mime']);
        self::assertSame($pdf, $m['attachments'][0]['content']);
    }

    public function testHtmlOnlyAndAttachedMessageAndPhotoWithoutName(): void
    {
        $photo = str_repeat("\xFF", 20000);
        $inner = implode("\r\n", [
            'From: lieferant@example.com',
            'Subject: Lieferschein 55',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'Pos 1 Tomaten',
        ]);
        $raw = implode("\r\n", [
            'From: chef@example.de',
            'Subject: Fwd: Lieferschein',
            'Content-Type: multipart/mixed; boundary=XX',
            '',
            '--XX',
            'Content-Type: text/html; charset=UTF-8',
            '',
            '<div>Hallo<br>Foto anbei &amp; Mail</div><style>p{}</style>',
            '--XX',
            'Content-Type: message/rfc822',
            '',
            $inner,
            '--XX',
            'Content-Type: image/jpeg',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode($photo),
            '--XX--',
        ]);

        $m = MimeParser::parse($raw);

        self::assertStringContainsString('Pos 1 Tomaten', $m['text']);
        self::assertStringContainsString('Weitergeleitet: Lieferschein 55', $m['text']);
        self::assertCount(1, $m['attachments']);
        self::assertSame('anhang-1.jpg', $m['attachments'][0]['name']);
        self::assertSame($photo, $m['attachments'][0]['content']);
    }

    public function testHtmlFallbackWhenNoPlainText(): void
    {
        $raw = "From: x@example.de\r\nSubject: Test\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>Hallo&nbsp;Welt</p><p>Zeile 2</p>";
        self::assertSame("Hallo Welt\nZeile 2", MimeParser::parse($raw)['text']);
    }

    public function testClassifier(): void
    {
        $c = new InboxClassifier(
            ['ich@gmail.com', 'Gastronomie@Haus.de'],
            [1 => 'bestellung@puetz.de; info@puetz.de', 2 => 'tg@gmail.com', 3 => 'order@hanos.de', 4 => 'gastronomie@haus.de']
        );

        self::assertSame('beleg', $c->classify('ich@gmail.com', 'WG: Rechnung')['kind']);
        self::assertSame('beleg', $c->classify('gastronomie@haus.de', 'Lieferschein Foto')['kind']);
        self::assertSame('nachtrag', $c->classify('ich@gmail.com', 'AW: Bestellung 4.11.')['kind']);
        self::assertSame('nachtrag', $c->classify('ich@gmail.com', 'Re: Bestellung')['kind']);
        self::assertSame('nachtrag', $c->classify('ich@gmail.com', 'RE[2]: Bestellung')['kind']);
        self::assertSame(['kind' => 'lieferant', 'supplier_id' => 1], $c->classify('INFO@puetz.de', 'AW: Bestellung'));
        self::assertSame(['kind' => 'lieferant', 'supplier_id' => 1], $c->classify('verkauf@puetz.de', 'Liefertermin'));
        self::assertSame(['kind' => 'lieferant', 'supplier_id' => 2], $c->classify('tg@gmail.com', 'x'));
        // Freemail-Domain des Lieferanten 2 darf nicht jede Gmail-Adresse zuordnen
        self::assertSame('sonstiges', $c->classify('fremd@gmail.com', 'Newsletter')['kind']);
        self::assertSame('sonstiges', $c->classify('', '')['kind']);
        // eigene Adresse als Platzhalter beim Lieferanten: Kollegen derselben Domain sind kein Lieferant
        self::assertSame('sonstiges', $c->classify('kollegin@haus.de', 'Frage')['kind']);
    }

    public function testZipIsReadable(): void
    {
        $zip = new ZipWriter();
        $zip->add('1_2026-11-03_Rechnung/1_Rechnung Pütz.pdf', '%PDF-1.4');
        $zip->add('belege.json', '{"a":1}');
        $file = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($file, $zip->bytes());

        if (class_exists(\ZipArchive::class)) {
            $za = new \ZipArchive();
            self::assertTrue($za->open($file) === true);
            self::assertSame('%PDF-1.4', $za->getFromName('1_2026-11-03_Rechnung/1_Rechnung Pütz.pdf'));
            self::assertSame('{"a":1}', $za->getFromName('belege.json'));
            $za->close();
        } else {
            exec('command -v unzip', $which, $found);
            if ($found !== 0) {
                unlink($file);
                self::markTestSkipped('Weder ZipArchive noch unzip vorhanden.');
            }
            exec('unzip -tq ' . escapeshellarg($file) . ' 2>&1', $out, $code);
            self::assertSame(0, $code, implode("\n", $out));
        }
        unlink($file);
    }
}
