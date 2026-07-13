<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteAssignment extends Model
{
    public const TEAM_MARKETING = 'marketing';

    public const TEAM_CUSTOMER_SERVICE = 'customer_service';

    public const TEAMS = [self::TEAM_MARKETING, self::TEAM_CUSTOMER_SERVICE];

    protected $fillable = ['website_id', 'user_id', 'team'];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
