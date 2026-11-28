<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One editable section of the public site, as last saved.
 *
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $value
 * @property int|null $updated_by
 */
#[Fillable(['key', 'value', 'updated_by'])]
class SiteContent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
