<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case AdmissionOfficer = 'admission_officer';
    case Teacher = 'teacher';
    case Student = 'student';

    /**
     * Human readable name of the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::AdmissionOfficer => 'Admission Officer',
            self::Teacher => 'Teacher',
            self::Student => 'Student',
        };
    }

    /**
     * Roles that sign in with their mobile number (everyone else uses a student ID).
     *
     * @return list<self>
     */
    public static function mobileLoginRoles(): array
    {
        return [self::Admin, self::AdmissionOfficer, self::Teacher];
    }
}
