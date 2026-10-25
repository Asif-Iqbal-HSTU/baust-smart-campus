<?php

namespace Database\Seeders;

use App\Models\EducationBoard;
use App\Models\Hall;
use App\Models\Quota;
use Illuminate\Database\Seeder;

/**
 * Quotas, halls and education boards. Safe to run repeatedly.
 */
class LookupSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const QUOTAS = [
        'GC' => 'General',
        'AF' => 'Children of Armed Forces',
        'FF' => 'Children of Freedom Fighters',
        'JJ' => 'July Joddha',
        'UA' => 'Underdeveloped Area',
        'SB' => 'Siblings',
    ];

    /**
     * Hall code (digits 7–8 of the student ID) => name.
     *
     * @var array<string, string>
     */
    public const HALLS = [
        '01' => 'Bir Protik Taramon Bibi Hall and its Extension Annex-1',
        '02' => 'Abbas Uddin Ahmed Hall',
        '04' => 'Shahed Zikrul Haque Hall',
    ];

    /**
     * @var array<string, string>
     */
    public const EDUCATION_BOARDS = [
        'dhaka' => 'Dhaka',
        'rajshahi' => 'Rajshahi',
        'dinajpur' => 'Dinajpur',
        'jessore' => 'Jessore',
        'chittagong' => 'Chittagong',
        'comilla' => 'Comilla',
        'barisal' => 'Barisal',
        'sylhet' => 'Sylhet',
        'mymensingh' => 'Mymensingh',
        'madrasah' => 'Madrasah',
        'technical' => 'Technical',
    ];

    public function run(): void
    {
        foreach (self::QUOTAS as $code => $name) {
            Quota::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (self::HALLS as $code => $name) {
            Hall::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (self::EDUCATION_BOARDS as $code => $name) {
            EducationBoard::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
