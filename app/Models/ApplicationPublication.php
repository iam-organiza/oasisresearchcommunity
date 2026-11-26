<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $venue
 * @property int $year
 * @property string|null $link
 */
#[Fillable(['title', 'venue', 'year', 'link', 'position'])]
class ApplicationPublication extends Model {}
