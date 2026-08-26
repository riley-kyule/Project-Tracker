<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A site connected for WordPress staff-access management — deliberately its
 * own concept, independent of the BigQuery-driven `websites` registry (see
 * the 2026_08_26 migration that split these apart). Just enough identity
 * (name + domain) to hang a credential and a synced user roster off of.
 */
class WordPressSite extends Model
{
    use HasFactory;

    // Laravel's class->table snake_case inference splits "WordPress" into
    // "word_press" (capital P reads as a new word boundary), which wouldn't
    // match the more natural "wordpress_sites" table name — spelled out
    // explicitly instead of renaming the table to match the inferred split.
    protected $table = 'wordpress_sites';

    protected $fillable = [
        'name',
        'domain',
    ];

    public function credential(): HasOne
    {
        // Explicit FK: Eloquent's default guess from this class name would be
        // "word_press_site_id" (same "WordPress" -> "Word"+"Press" split as
        // the table name above), not the actual "wordpress_site_id" column.
        return $this->hasOne(WordPressCredential::class, 'wordpress_site_id');
    }

    public function wordpressUsers(): HasMany
    {
        return $this->hasMany(WordPressUser::class, 'wordpress_site_id');
    }
}
