<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['code', 'name'])]
class EducationBoard extends Model
{
    /**
     * @return HasMany<StudentEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(StudentEducation::class);
    }
}
