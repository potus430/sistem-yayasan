<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SampleStudentProfileSeeder extends Seeder
{
    private const BIRTH_PLACES = ['Jakarta', 'Bandung', 'Yogyakarta', 'Surabaya'];

    public function run(): void
    {
        if (app()->environment('production') && ! filter_var(env('SEED_SAMPLE_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Sample student profiles skipped on production.');

            return;
        }

        DB::transaction(function (): void {
            Student::query()
                ->with('branch:id,level')
                ->where('nisn', 'like', 'D%')
                ->orderBy('id')
                ->chunk(100, function ($students): void {
                    foreach ($students as $student) {
                        if (! preg_match('/^D[0-9]{8}$/D', $student->nisn)) {
                            continue;
                        }

                        $sequence = (int) substr($student->nisn, -6);
                        $birthYear = match ($student->branch?->level) {
                            'TK' => 2019,
                            'SD' => 2015,
                            'SMP' => 2012,
                            'SMA' => 2009,
                            default => 2015,
                        };
                        $attributes = [
                            'gender' => $sequence % 2 === 0 ? 'L' : 'P',
                            'birth_place' => self::BIRTH_PLACES[$sequence % count(self::BIRTH_PLACES)],
                            'birth_date' => sprintf(
                                '%04d-%02d-%02d',
                                $birthYear,
                                ($sequence % 12) + 1,
                                ($sequence % 28) + 1
                            ),
                            'guardian_name' => 'Wali dari '.$student->name,
                            'guardian_phone' => '08'.str_pad((string) substr($student->nisn, 1), 10, '0', STR_PAD_LEFT),
                            'address' => sprintf('Jl. Pendidikan No. %d, Kota Contoh', ($sequence % 150) + 1),
                            'previous_school' => match ($student->branch?->level) {
                                'SD' => 'TK Contoh',
                                'SMP' => 'SD Contoh',
                                'SMA' => 'SMP Contoh',
                                default => null,
                            },
                        ];

                        foreach ($attributes as $field => $value) {
                            if (($student->{$field} === null || $student->{$field} === '') && $value !== null) {
                                $student->{$field} = $value;
                            }
                        }

                        if ($student->isDirty()) {
                            $student->save();
                        }
                    }
                });
        });
    }
}
