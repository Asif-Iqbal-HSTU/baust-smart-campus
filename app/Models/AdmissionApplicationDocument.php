<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scanned certificate, transcript or other paper attached to an application.
 * Files live on the private "local" disk.
 *
 * @property int $id
 * @property int $admission_application_id
 * @property string $name
 * @property string $path
 * @property string|null $original_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'path', 'original_name'])]
class AdmissionApplicationDocument extends Model
{
    /**
     * @return BelongsTo<AdmissionApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
