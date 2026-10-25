<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admission Test Threshold
    |--------------------------------------------------------------------------
    |
    | Applicants whose SSC + HSC (or equivalent) GPA adds up to less than this
    | must sit the admission test before they can be admitted.
    |
    */

    'test_gpa_threshold' => 8.0,

    /*
    |--------------------------------------------------------------------------
    | University Code
    |--------------------------------------------------------------------------
    |
    | First three digits of every student ID:
    | 080 | 26 | 1 | 02 | 05101 | 001 (university | year | semester | hall | subject | serial).
    |
    */

    'university_code' => '080',

];
