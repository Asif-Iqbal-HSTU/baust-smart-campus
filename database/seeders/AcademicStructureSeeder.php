<?php

namespace Database\Seeders;

use App\Enums\DegreeLevel;
use App\Enums\Semester;
use App\Models\Batch;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use Illuminate\Database\Seeder;

/**
 * Faculties, departments, programs and batches as they exist in the current
 * system. Safe to run repeatedly.
 */
class AcademicStructureSeeder extends Seeder
{
    /**
     * Faculty code (first two digits of the subject code) => name.
     *
     * @var array<string, string>
     */
    private const FACULTIES = [
        '05' => 'Faculty of Engineering',
        '04' => 'Faculty of Business Studies',
        '02' => 'Faculty of Arts and Social Sciences',
    ];

    /**
     * Department code => [faculty code, name].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const DEPARTMENTS = [
        'CSE' => ['05', 'Computer Science and Engineering'],
        'EEE' => ['05', 'Electrical and Electronic Engineering'],
        'ME' => ['05', 'Mechanical Engineering'],
        'IPE' => ['05', 'Industrial and Production Engineering'],
        'CE' => ['05', 'Civil Engineering'],
        'ICT' => ['05', 'Information and Communication Technology'],
        'DBA' => ['04', 'Business Administration'],
        'AIS' => ['04', 'Accounting and Information Systems'],
        'ENG' => ['02', 'English'],
    ];

    /**
     * Program code => [department code, name (as in the current system), subject code, level, duration in years].
     *
     * @var array<string, array{0: string, 1: string, 2: string|null, 3: DegreeLevel, 4: int|null}>
     */
    private const PROGRAMS = [
        'BSC-CSE' => ['CSE', 'B.Sc. Engg. in CSE', '05101', DegreeLevel::Undergraduate, 4],
        'BSC-EEE' => ['EEE', 'B.Sc. Engg. in EEE', '05131', DegreeLevel::Undergraduate, 4],
        'BSC-ME' => ['ME', 'B.Sc. Engg. in ME', '05221', DegreeLevel::Undergraduate, 4],
        'BSC-IPE' => ['IPE', 'B.Sc. Engg. in IPE', '05161', DegreeLevel::Undergraduate, 4],
        'BSC-CE' => ['CE', 'B.Sc. Engg. in CE', '05081', DegreeLevel::Undergraduate, 4],
        'BSC-ICT' => ['ICT', 'B.Sc. Engg. in ICT', '05181', DegreeLevel::Undergraduate, 4],
        'BBA' => ['DBA', 'BBA', '04081', DegreeLevel::Undergraduate, 4],
        'MBA-REG' => ['DBA', 'MBA (Regular)', '04083', DegreeLevel::Postgraduate, null],
        'MBA-BBA' => ['DBA', 'MBA (For BBA Graduates)', '04083', DegreeLevel::Postgraduate, null],
        'MBA-EXEC' => ['DBA', 'MBA (Executive)', '04083', DegreeLevel::Postgraduate, null],
        'BBA-AIS' => ['AIS', 'BBA in AIS', '04021', DegreeLevel::Undergraduate, 4],
        'BA-ENG' => ['ENG', 'BA (Honours) in English', '02161', DegreeLevel::Undergraduate, 4],
        'MA-ELT' => ['ENG', 'MA in English (ELT)', '02163', DegreeLevel::Postgraduate, null],
    ];

    /**
     * Admission year => batch numbers admitted that year (Winter first, then Summer).
     *
     * @var array<int, list<int>>
     */
    private const BATCHES = [
        2015 => [1, 2],
        2016 => [3, 4],
        2017 => [5],
        2018 => [6, 7],
        2019 => [8, 9],
        2020 => [10, 11],
        2021 => [12, 13],
        2022 => [14, 15],
        2023 => [16, 17],
        2024 => [18, 19],
        2025 => [20, 21],
        2026 => [22, 23],
    ];

    public function run(): void
    {
        foreach (self::FACULTIES as $code => $name) {
            Faculty::updateOrCreate(['code' => $code], ['name' => $name]);
        }
        $facultyIds = Faculty::pluck('id', 'code');

        foreach (self::DEPARTMENTS as $code => [$facultyCode, $name]) {
            Department::updateOrCreate(['code' => $code], [
                'faculty_id' => $facultyIds[$facultyCode],
                'name' => $name,
            ]);
        }
        $departmentIds = Department::pluck('id', 'code');

        foreach (self::PROGRAMS as $code => [$departmentCode, $name, $subjectCode, $level, $years]) {
            Program::updateOrCreate(['code' => $code], [
                'department_id' => $departmentIds[$departmentCode],
                'name' => $name,
                'subject_code' => $subjectCode,
                'degree_level' => $level,
                'duration_years' => $years,
            ]);
        }

        foreach (self::BATCHES as $year => $numbers) {
            foreach ($numbers as $number) {
                Batch::updateOrCreate(['number' => $number], [
                    'name' => Batch::ordinal($number),
                    'admission_year' => $year,
                    // Two intakes a year: Winter then Summer. 2017 had a single
                    // intake whose semester is not recorded.
                    'semester' => count($numbers) === 2
                        ? ($number === $numbers[0] ? Semester::Winter : Semester::Summer)
                        : null,
                ]);
            }
        }
    }
}
