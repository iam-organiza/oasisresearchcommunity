<?php

namespace App\Models;

use App\Membership\ApplicationSteps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A guide, video or reading that helps applicants prepare for the exam: a
 * file kept on the private disk, or a link. For one research area, or
 * general. Applicants see it once their exam fee is paid.
 *
 * @property int $id
 * @property string $title
 * @property string|null $area
 * @property string|null $description
 * @property string|null $url
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property bool $published
 */
#[Fillable(['title', 'area', 'description', 'url', 'path', 'original_name', 'mime_type', 'size', 'published', 'created_by'])]
class PrepMaterial extends Model
{
    public const MAX_KILOBYTES = 20480;

    protected static function booted(): void
    {
        // The file goes with the record.
        static::deleted(function (PrepMaterial $material) {
            if ($material->path) {
                Storage::disk('local')->delete($material->path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }

    public function areaLabel(): string
    {
        return $this->area ? (ApplicationSteps::AREAS[$this->area]['label'] ?? $this->area) : 'All areas';
    }

    public function isFile(): bool
    {
        return $this->path !== null;
    }
}
