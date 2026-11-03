<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Ordnet eine eingegangene Mail ein (erste passende Regel gilt):
 *  1. eigene Adresse + Betreff „Re:/AW:/Antw:“ → nachtrag (eigene Antwort an alle, nichts zu tun)
 *  2. eigene Adresse                          → beleg (weitergeleitete Rechnung / Lieferschein-Foto)
 *  3. Adresse/Domain eines Lieferanten        → lieferant
 *  4. sonst                                   → sonstiges
 */
final class InboxClassifier
{
    public const KINDS = ['lieferant', 'beleg', 'nachtrag', 'sonstiges'];

    /** Domains, bei denen ein Domain-Vergleich nichts über den Absender aussagt */
    private const FREEMAIL = [
        'gmail.com', 'googlemail.com', 'gmx.de', 'gmx.net', 'web.de', 't-online.de', 'outlook.com', 'outlook.de',
        'hotmail.com', 'hotmail.de', 'live.de', 'live.com', 'yahoo.com', 'yahoo.de', 'icloud.com', 'me.com',
        'aol.com', 'freenet.de', 'online.de', 'ionos.de', 'posteo.de', 'mailbox.org',
    ];

    /** @var array<string, true> */
    private array $own = [];
    /** @var array<string, int> */
    private array $supplierByEmail = [];
    /** @var array<string, int|null> null = Domain gehört mehreren Lieferanten */
    private array $supplierByDomain = [];

    /**
     * @param list<string> $ownAddresses
     * @param array<int, string> $supplierEmails Lieferanten-ID → E-Mail-Feld (ggf. mehrere, durch , oder ; getrennt)
     */
    public function __construct(array $ownAddresses, array $supplierEmails)
    {
        foreach ($ownAddresses as $a) {
            $a = mb_strtolower(trim($a));
            if ($a !== '') {
                $this->own[$a] = true;
            }
        }
        $ownDomains = [];
        foreach (array_keys($this->own) as $a) {
            $ownDomains[self::domain($a)] = true;
        }
        foreach ($supplierEmails as $id => $field) {
            foreach (self::splitAddresses((string) $field) as $email) {
                if (isset($this->own[$email])) {
                    continue;   // Platzhalter: eigene Adresse beim Lieferanten eingetragen
                }
                $this->supplierByEmail[$email] ??= (int) $id;
                $domain = self::domain($email);
                // Freemail und eigene Domain (Kollegen!) sagen nichts über den Lieferanten
                if ($domain === '' || in_array($domain, self::FREEMAIL, true) || isset($ownDomains[$domain])) {
                    continue;
                }
                if (array_key_exists($domain, $this->supplierByDomain) && $this->supplierByDomain[$domain] !== (int) $id) {
                    $this->supplierByDomain[$domain] = null;
                } else {
                    $this->supplierByDomain[$domain] = (int) $id;
                }
            }
        }
    }

    /** @return array{kind:string, supplier_id:int|null} */
    public function classify(string $fromEmail, string $subject): array
    {
        $from = mb_strtolower(trim($fromEmail));
        if (isset($this->own[$from])) {
            return ['kind' => self::isReply($subject) ? 'nachtrag' : 'beleg', 'supplier_id' => null];
        }
        if (isset($this->supplierByEmail[$from])) {
            return ['kind' => 'lieferant', 'supplier_id' => $this->supplierByEmail[$from]];
        }
        $byDomain = $this->supplierByDomain[self::domain($from)] ?? null;
        if ($byDomain !== null) {
            return ['kind' => 'lieferant', 'supplier_id' => $byDomain];
        }

        return ['kind' => 'sonstiges', 'supplier_id' => null];
    }

    public static function isReply(string $subject): bool
    {
        return (bool) preg_match('/^\s*(re|aw|antw|antwort)\s*(\[\d+\])?\s*:/iu', $subject);
    }

    /** @return list<string> */
    public static function splitAddresses(string $field): array
    {
        $out = [];
        foreach (preg_split('/[,;\s]+/', mb_strtolower($field)) ?: [] as $a) {
            $a = trim($a, " <>\"'");
            if ($a !== '' && str_contains($a, '@')) {
                $out[] = $a;
            }
        }

        return array_values(array_unique($out));
    }

    private static function domain(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : substr($email, $at + 1);
    }
}
