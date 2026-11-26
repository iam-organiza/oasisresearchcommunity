<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A state chapter: one of the 36 states or the FCT, in its region.
 *
 * @property int $id
 * @property int $region_id
 * @property string $name
 * @property string $slug
 */
class State extends Model
{
    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
