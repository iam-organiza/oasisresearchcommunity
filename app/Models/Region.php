<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A regional chapter: one of Nigeria's six geopolitical zones.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Region extends Model
{
    /**
     * @return HasMany<State, $this>
     */
    public function states(): HasMany
    {
        return $this->hasMany(State::class)->orderBy('name');
    }
}
