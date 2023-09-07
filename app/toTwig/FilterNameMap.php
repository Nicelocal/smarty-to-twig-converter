<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace toTwig;

/**
 * Class FilterNameMap
 *
 * @package toTwig
 */
class FilterNameMap
{
    const NAME_MAP = [
        'smartwordwrap' => 'smart_wordwrap',
        'date_format' => 'date_format',
        'count' => 'length',
        'strip_tags' => 'striptags',
        'slice' => 'slice_str'
    ];

    public static function getConvertedFilterName(string $filterName): string
    {
        return self::NAME_MAP[$filterName] ?? $filterName;
    }
}
