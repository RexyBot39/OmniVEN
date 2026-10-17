<?php

namespace App\Support\Seo;

use RuntimeException;
use XMLReader;

/**
 * Checks a Google Shopping product feed file (RSS/Atom XML, or a tab- or comma-separated sheet) for the
 * problems Merchant Center rejects or limits items for. Keeps counts and up to five example product
 * IDs per problem; nothing else from the feed.
 */
class ProductFeed
{
    /** key => [level, label]. "fail": Google disapproves the item; "warn": it shows less or worse. */
    public const CHECKS = [
        'id' => ['fail', 'No id'],
        'title' => ['fail', 'No title'],
        'link' => ['fail', 'No product link'],
        'image_link' => ['fail', 'No image'],
        'price' => ['fail', 'No price'],
        'price_format' => ['fail', 'Price not like “39.00 USD”'],
        'availability' => ['fail', 'No availability'],
        'availability_value' => ['fail', 'Availability isn’t in_stock, out_of_stock, preorder or backorder'],
        'duplicate_id' => ['fail', 'Same id used twice'],
        'description' => ['warn', 'No description'],
        'brand' => ['warn', 'No brand'],
        'identifier' => ['warn', 'No GTIN or MPN (and identifier_exists isn’t “no”)'],
        'condition' => ['warn', 'No condition'],
        'title_long' => ['warn', 'Title over 150 characters (cut off)'],
        'http_link' => ['warn', 'Link or image not on https'],
    ];

    private const AVAILABILITY = ['in_stock', 'out_of_stock', 'preorder', 'backorder', 'in stock', 'out of stock'];

    private const MAX_ITEMS = 200000;

    /** Check a downloaded feed file. */
    public static function check(string $path): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 2048);
        $isXml = str_starts_with(ltrim($head, "\xEF\xBB\xBF \t\r\n"), '<');
        $items = $isXml ? self::xmlItems($path) : self::sheetItems($path, $head);

        $count = 0;
        $issues = [];
        $seen = [];
        $avail = [];
        foreach ($items as $i) {
            $count++;
            $id = trim((string) ($i['id'] ?? ''));
            $label = $id !== '' ? mb_substr($id, 0, 40) : '#'.$count;
            $flag = function (string $key) use (&$issues, $label) {
                $issues[$key]['count'] = ($issues[$key]['count'] ?? 0) + 1;
                if (count($issues[$key]['examples'] ?? []) < 5) {
                    $issues[$key]['examples'][] = $label;
                }
            };
            foreach (['id', 'title', 'link', 'image_link', 'price', 'availability', 'description', 'brand', 'condition'] as $f) {
                if (trim((string) ($i[$f] ?? '')) === '') {
                    $flag($f);
                }
            }
            if ($id !== '') {
                if (isset($seen[$id])) {
                    $flag('duplicate_id');
                }
                $seen[$id] = true;
            }
            $price = trim((string) ($i['price'] ?? ''));
            if ($price !== '' && ! preg_match('/^\d+(?:\.\d{1,2})?\s+[A-Z]{3}$/', $price)) {
                $flag('price_format');
            }
            $a = mb_strtolower(trim((string) ($i['availability'] ?? '')));
            if ($a !== '') {
                in_array($a, self::AVAILABILITY, true) ? $avail[str_replace(' ', '_', $a)] = ($avail[str_replace(' ', '_', $a)] ?? 0) + 1 : $flag('availability_value');
            }
            if (trim((string) ($i['gtin'] ?? '')) === '' && trim((string) ($i['mpn'] ?? '')) === '' && ! in_array(mb_strtolower(trim((string) ($i['identifier_exists'] ?? ''))), ['no', 'false', 'n'], true)) {
                $flag('identifier');
            }
            if (mb_strlen((string) ($i['title'] ?? '')) > 150) {
                $flag('title_long');
            }
            if (preg_match('#^http://#i', (string) ($i['link'] ?? '')) || preg_match('#^http://#i', (string) ($i['image_link'] ?? ''))) {
                $flag('http_link');
            }
            if ($count >= self::MAX_ITEMS) {
                break;
            }
        }
        if ($count === 0) {
            throw new RuntimeException('The feed has no products in it. Check that the address is the product feed itself.');
        }
        unset($seen);

        $rows = [];
        foreach (self::CHECKS as $key => [$level, $text]) {
            if (isset($issues[$key])) {
                $rows[] = ['key' => $key, 'level' => $level, 'label' => $text, 'count' => $issues[$key]['count'], 'examples' => $issues[$key]['examples']];
            }
        }
        usort($rows, fn ($a, $b) => [$a['level'] === 'fail' ? 0 : 1, -$a['count']] <=> [$b['level'] === 'fail' ? 0 : 1, -$b['count']]);

        return ['items' => $count, 'issues' => $rows, 'availability' => $avail];
    }

    /** Items from RSS (<item>) or Atom (<entry>), each as field => text, with or without the g: prefix. */
    private static function xmlItems(string $path): \Generator
    {
        $r = new XMLReader;
        if (! @$r->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('The feed isn’t readable XML.');
        }
        try {
            while (@$r->read()) {
                if ($r->nodeType !== XMLReader::ELEMENT || ! in_array($r->localName, ['item', 'entry'], true)) {
                    continue;
                }
                $item = [];
                $depth = $r->depth;
                while (@$r->read() && ! ($r->nodeType === XMLReader::END_ELEMENT && $r->depth === $depth)) {
                    if ($r->nodeType === XMLReader::ELEMENT && $r->depth === $depth + 1) {
                        $name = $r->localName;
                        // Atom puts the product link in <link href="…">.
                        $value = $name === 'link' && $r->getAttribute('href') ? $r->getAttribute('href') : $r->readString();
                        $item[$name] ??= trim($value);
                    }
                }
                yield $item;
            }
        } finally {
            $r->close();
        }
    }

    /** Items from a tab- or comma-separated sheet with a header row. */
    private static function sheetItems(string $path, string $head): \Generator
    {
        $sep = substr_count(strtok($head, "\n") ?: '', "\t") > 0 ? "\t" : ',';
        $f = fopen($path, 'r');
        try {
            $cols = fgetcsv($f, null, $sep, '"', '');
            if (! $cols || count($cols) < 2) {
                throw new RuntimeException('The feed has no header row of field names.');
            }
            $cols = array_map(fn ($c) => preg_replace('/^(g:)/', '', mb_strtolower(trim((string) $c, "\xEF\xBB\xBF \t"))), $cols);
            while (($row = fgetcsv($f, null, $sep, '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                yield array_combine($cols, array_pad(array_slice($row, 0, count($cols)), count($cols), ''));
            }
        } finally {
            fclose($f);
        }
    }
}
