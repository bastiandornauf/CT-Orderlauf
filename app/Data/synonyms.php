<?php

declare(strict_types=1);

/**
 * Hilfen für die Dublettensuche in der Datenpflege.
 *
 * synonyms:  Wörter/Schreibweisen, die beim Vergleich als gleich gelten (erstes Wort = Normalform).
 *            Sie helfen nur beim Finden – ob zwei Artikel gleich sind, entscheidet der Mensch.
 * opposites: Merkmale, die zwei sonst ähnliche Artikel unterscheiden (Varianten). Hat ein Artikel ein
 *            Merkmal einer Gruppe und der andere ein *anderes* derselben Gruppe, sinkt der Score stark.
 * ignore:    Füllwörter, Marken-Kürzel und Gebinde-Wörter ohne Aussagekraft für den Vergleich.
 *
 * Alles in Kleinbuchstaben, Umlaute ausgeschrieben (ä → ae usw.) wie nach der Normalisierung.
 */
return [
    'synonyms' => [
        ['pommes', 'frites', 'fritten', 'fries', 'steakhouse'],
        ['tk', 'tiefgekuehlt', 'tiefgefroren', 'gefroren'],
        ['entsteint', 'ost', 'ohnestein', 'ohnekern', 'okern'],
        ['geschaelt', 'ohneschale', 'oschale'],
        ['haehnchen', 'haehn', 'chicken', 'chick', 'chik', 'gefluegel', 'gefl', 'huhn'],
        ['karotten', 'moehren', 'karotte', 'moehre'],
        ['mayonnaise', 'mayo', 'mayonaise', 'majo'],
        ['ketchup', 'tomatenketchup'],
        ['sahne', 'rahm'],
        ['kraeuter', 'kraeut'],
        ['portionen', 'portion', 'port', 'pt'],
        ['zitrone', 'zitronen', 'citrone'],
        ['tomate', 'tomaten', 'tom'],
        ['oliven', 'olive'],
        ['kartoffel', 'kartoffeln', 'kart'],
        ['brokkoli', 'brokoli', 'broccoli'],
        ['gerieben', 'ger'],
        ['gehobelt', 'geh', 'gehob'],
        ['geschnitten', 'geschn'],
        ['schwarz', 'schw'],
        ['dunkel', 'dkl'],
        ['knoblauch', 'knobl'],
        ['champignons', 'champignon', 'champ'],
        ['paprika', 'paprikaschote'],
        ['bohnen', 'bohne'],
    ],
    'opposites' => [
        ['entsteint', 'mitstein'],
        ['tk', 'frisch'],
        ['ganz', 'geschnitten', 'gewuerfelt', 'scheiben', 'streifen', 'gehackt', 'gerieben', 'gehobelt', 'gemahlen'],
        ['glatt', 'kraus'],
        ['hell', 'dunkel'],
        ['rot', 'gelb', 'gruen', 'weiss', 'schwarz', 'bunt'],
        ['gesalzen', 'ungesalzen'],
        ['suess', 'scharf', 'mittelscharf', 'sauer'],
        ['grxs', 'grs', 'grm', 'grl', 'grxl', 'grxxl'],
        ['vanille', 'schoko', 'schokolade', 'erdbeer', 'zitrone', 'cassis', 'passionsfrucht', 'nuss', 'karamell'],
        ['natur', 'kraeuter'],
        ['rind', 'schwein', 'kalb', 'haehnchen', 'pute', 'lamm', 'lachs', 'thunfisch'],
        ['filet', 'ruecken', 'nacken', 'huefte', 'brust', 'keule', 'schnitzel'],
        ['essig', 'mayonnaise', 'oel'],
        ['vegan', 'vegetarisch'],
        ['bio', 'konventionell'],
    ],
    'ignore' => [
        'und', 'mit', 'in', 'im', 'von', 'fuer', 'aus', 'der', 'die', 'das', 'ca',
        'tgq', 'tge', 'tgn', 'tgp', 'tgb', 'tgu', 'sn', 'fr', 'opm', 'wib', 'ed', 'hil', 'oet', 'bb', 'cd', 'moev',
        'stueck', 'stk', 'st', 'kg', 'g', 'gr', 'l', 'ml', 'ltr', 'karton', 'ktn', 'beutel', 'btl', 'dose', 'dosen',
        'flasche', 'glas', 'eimer', 'packung', 'paket', 'schale', 'becher', 'kiste', 'sack', 'wanne', 'box',
    ],
];
