<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EditorialIndexRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'rubric_slug',
    'include_in_sitemap',
    'include_in_internal_search',
    'noindex_default',
    'exclude_from_crawl',
    'is_active',
    'notes',
])]
class EditorialIndexRule extends Model
{
    /** @use HasFactory<EditorialIndexRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'include_in_sitemap' => 'boolean',
            'include_in_internal_search' => 'boolean',
            'noindex_default' => 'boolean',
            'exclude_from_crawl' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
