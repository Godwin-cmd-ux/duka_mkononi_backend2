<?php

namespace App\Services;

/**
 * The single source of truth for office-expense categories.
 *
 * GET /api/office-expenses/categories serves this list verbatim and the
 * capability #7 expense parser uses it as its model/keyword allow-list, so a
 * category can never exist in one place and be rejected by the other. The
 * order and spelling are the original contract - do not reorder or rename
 * without migrating stored rows.
 */
class ExpenseCategories
{
    public const CATEGORIES = [
        'Kodisho za Nguvu',
        'Maji',
        'Mawasiliano',
        'Usafiri',
        'Chakula cha Mchana',
        'Vifaa vya Ofisi',
        'Matengenezo',
        'Ushuru',
        'Ada za Benki',
        'Malighafi',
        'Ufungashaji',
        'Mengineyo',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::CATEGORIES;
    }

    public static function isValid(string $category): bool
    {
        return in_array($category, self::CATEGORIES, true);
    }
}
