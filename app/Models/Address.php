<?php

namespace App\Models;

use App\Enums\AddressType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property AddressType $type
 * @property string|null $address_line
 * @property string|null $post_office
 * @property string|null $post_code
 * @property string|null $upazila
 * @property string|null $district
 * @property string|null $division
 * @property string $country
 * @property string|null $raw_text
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'type', 'address_line', 'post_office', 'post_code', 'upazila',
    'district', 'division', 'country', 'raw_text',
])]
class Address extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
