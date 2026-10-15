<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\BranchIncentive;
use App\Models\BranchPosition;
use App\Models\Employee;
use App\Models\EmployeePositionAssignment;
use App\Models\SchoolClass;
use App\Models\SchoolRoom;
use App\Models\SchoolSubject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class SampleDataSeeder extends Seeder
{
    private array $attendanceCounts = [];

    private const BRANCHES = [
        ['name' => 'TK Harapan Bangsa', 'level' => 'TK', 'address' => 'Jl. Melati No. 10'],
        ['name' => 'SD Harapan Bangsa', 'level' => 'SD', 'address' => 'Jl. Merdeka No. 25'],
        ['name' => 'SD Cendekia', 'level' => 'SD', 'address' => 'Jl. Pendidikan No. 8'],
        ['name' => 'SMP Harapan Bangsa', 'level' => 'SMP', 'address' => 'Jl. Pahlawan No. 12'],
        ['name' => 'SMA Harapan Bangsa', 'level' => 'SMA', 'address' => 'Jl. Pelajar No. 35'],
    ];

    private const FIRST_NAMES = [
        'Aditya', 'Aisyah', 'Ananda', 'Ayu', 'Bagas', 'Citra', 'Daffa', 'Dewi',
        'Dimas', 'Eka', 'Farhan', 'Fitri', 'Gita', 'Hana', 'Indra', 'Intan',
        'Jihan', 'Kevin', 'Laras', 'Maya', 'Nadia', 'Naufal', 'Putri', 'Rafi',
        'Rani', 'Rizky', 'Salsa', 'Tegar', 'Vina', 'Yusuf',
    ];

    private const LAST_NAMES = [
        'Pratama', 'Saputra', 'Permata', 'Wijaya', 'Lestari', 'Kusuma', 'Maulana',
        'Puspita', 'Firmansyah', 'Ramadhan', 'Utami', 'Nugraha', 'Anggraini',
        'Hidayat', 'Setiawan', 'Maharani', 'Putra', 'Wulandari', 'Hakim', 'Sari',
    ];

    private const SUBJECTS = [
        'TK' => ['Literasi', 'Numerasi', 'Seni', 'Motorik', 'Karakter'],
        'SD' => ['Bahasa Indonesia', 'Matematika', 'IPAS', 'Bahasa Inggris', 'Pendidikan Agama'],
        'SMP' => ['Bahasa Indonesia', 'Matematika', 'IPA', 'Bahasa Inggris', 'IPS'],
        'SMA' => ['Bahasa Indonesia', 'Matematika', 'Fisika', 'Bahasa Inggris', 'Kimia'],
    ];

    private const CLASSES = [
        'TK' => ['TK A', 'TK B', 'TK C', 'TK D'],
        'SD' => ['1A', '2A', '3A', '4A'],
        'SMP' => ['7A', '8A', '9A', '9B'],
        'SMA' => ['10 IPA', '11 IPA', '12 IPA', '12 IPS'],
    ];

    public function run(): void
    {
        if (app()->environment('production') && ! filter_var(env('SEED_SAMPLE_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Sample data seeder dilewati pada production. Set SEED_SAMPLE_DATA=true hanya jika memang diperlukan.');

            return;
        }

        DB::transaction(function (): void {
            $branches = $this->seedBranches();
            $academicYears = $this->seedAcademicYears();
            $employeesByBranch = $this->seedEmployees($branches);
            $studentsByBranch = $this->seedStudents($branches);
            $this->seedSchoolStructure($branches);
            $incentivesByBranch = $this->seedIncentives($branches);
            $this->seedPositionAssignments($branches, $employeesByBranch, $academicYears['active']);

            $this->seedUsers($branches, $employeesByBranch);
            $this->seedAttendance($branches, $employeesByBranch);
            $this->seedSalaries($branches, $employeesByBranch, $academicYears['active'], $incentivesByBranch);
            $this->seedBillings($branches, $studentsByBranch, $academicYears);
            $this->seedRosters($branches, $employeesByBranch, $academicYears['active']);
        });

        $this->command?->info('Data contoh siap: 5 cabang, 150 pegawai, 600 siswa, jabatan dan penugasan pegawai, tagihan SPP dua tahun ajaran, payroll, absensi, dan roster.');
        $this->command?->line('Akun contoh (khusus local/testing): admin.demo@yayasan.sch.id, admin.cabang1@demo.yayasan.sch.id, keuangan.cabang1@demo.yayasan.sch.id, guru.cabang1@demo.yayasan.sch.id, staff.cabang1@demo.yayasan.sch.id');
        $this->command?->line('Password akun contoh: '.(env('SEED_SAMPLE_PASSWORD') ?: 'Sekolah123!'));
    }

    /**
     * @return array<int, Branch>
     */
    private function seedBranches(): array
    {
        return collect(self::BRANCHES)
            ->map(fn (array $attributes): Branch => Branch::query()->firstOrCreate(
                ['name' => $attributes['name']],
                ['level' => $attributes['level'], 'address' => $attributes['address']]
            ))
            ->all();
    }

    /**
     * @return array{active: AcademicYear, previous: AcademicYear}
     */
    private function seedAcademicYears(): array
    {
        $currentYear = (int) now()->format('Y');
        $startYear = (int) now()->format('n') >= 7 ? $currentYear : $currentYear - 1;

        $active = AcademicYear::query()->updateOrCreate(
            ['name' => $startYear.'/'.($startYear + 1)],
            [
                'starts_on' => $startYear.'-07-01',
                'ends_on' => ($startYear + 1).'-06-30',
                'is_active' => true,
            ]
        );
        $previous = AcademicYear::query()->updateOrCreate(
            ['name' => ($startYear - 1).'/'.$startYear],
            [
                'starts_on' => ($startYear - 1).'-07-01',
                'ends_on' => $startYear.'-06-30',
                'is_active' => false,
            ]
        );

        AcademicYear::query()->where('id', '!=', $active->id)->update(['is_active' => false]);

        return ['active' => $active, 'previous' => $previous];
    }

    /**
     * @param  array<int, Branch>  $branches
     * @return array<string, array<int, Employee>>
     */
    private function seedEmployees(array $branches): array
    {
        $employeesByBranch = [];

        foreach ($branches as $branchIndex => $branch) {
            for ($index = 1; $index <= 30; $index++) {
                $type = $index <= 24 ? 'GURU' : 'STAFF';
                $name = $this->personName($branchIndex * 30 + $index);
                $employee = Employee::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'nip' => sprintf('DEMO-%02d-%03d', $branchIndex + 1, $index)],
                    [
                        'name' => $name,
                        'type' => $type,
                        'base_salary' => $type === 'GURU'
                            ? 4500000 + (($index % 8) * 250000)
                            : 3200000 + (($index % 5) * 200000),
                        'status' => $index % 6 === 0 ? 'HONORER' : 'TETAP',
                    ]
                );
                $employeesByBranch[$branch->id][] = $employee;
            }
        }

        return $employeesByBranch;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @return array<string, array<int, Student>>
     */
    private function seedStudents(array $branches): array
    {
        $studentsByBranch = [];

        foreach ($branches as $branchIndex => $branch) {
            $classes = self::CLASSES[$branch->level];

            for ($index = 1; $index <= 120; $index++) {
                $student = Student::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'nisn' => sprintf('D%02d%06d', $branchIndex + 1, $index)],
                    [
                        'name' => $this->personName(2000 + ($branchIndex * 120) + $index),
                        'current_class' => $classes[intdiv($index - 1, 30)],
                    ]
                );
                $studentsByBranch[$branch->id][] = $student;
            }
        }

        return $studentsByBranch;
    }

    /**
     * @param  array<int, Branch>  $branches
     */
    private function seedSchoolStructure(array $branches): void
    {
        foreach ($branches as $branch) {
            foreach (self::CLASSES[$branch->level] as $name) {
                SchoolClass::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $name],
                    []
                );
            }

            foreach (range(101, 108) as $roomNumber) {
                SchoolRoom::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => sprintf('R-%02d', $roomNumber)],
                    []
                );
            }

            foreach (self::SUBJECTS[$branch->level] as $name) {
                SchoolSubject::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $name],
                    []
                );
            }
        }
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, Employee>>  $employeesByBranch
     */
    private function seedPositionAssignments(array $branches, array $employeesByBranch, AcademicYear $activeYear): void
    {
        $compensationByLevel = [
            'TK' => 0.75,
            'SD' => 0.9,
            'SMP' => 1.0,
            'SMA' => 1.1,
        ];
        $positionDefinitions = [
            ['name' => 'Kepala Sekolah', 'requires_class' => false, 'salary' => 500000, 'allowance' => 1000000, 'incentive' => 500000],
            ['name' => 'Wakil Kepala Sekolah', 'requires_class' => false, 'salary' => 300000, 'allowance' => 600000, 'incentive' => 300000],
            ['name' => 'Wali Kelas', 'requires_class' => true, 'salary' => 0, 'allowance' => 250000, 'incentive' => 300000],
            ['name' => 'Koordinator Kurikulum', 'requires_class' => false, 'salary' => 250000, 'allowance' => 400000, 'incentive' => 250000],
            ['name' => 'Kepala Tata Usaha', 'requires_class' => false, 'salary' => 350000, 'allowance' => 500000, 'incentive' => 200000],
            ['name' => 'Bendahara Sekolah', 'requires_class' => false, 'salary' => 200000, 'allowance' => 350000, 'incentive' => 200000],
            ['name' => 'Operator Sekolah', 'requires_class' => false, 'salary' => 100000, 'allowance' => 250000, 'incentive' => 150000],
        ];

        foreach ($branches as $branch) {
            $employees = $employeesByBranch[$branch->id];
            $scale = $compensationByLevel[$branch->level] ?? 1.0;

            foreach ($positionDefinitions as $definition) {
                $position = BranchPosition::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'academic_year_id' => $activeYear->id,
                        'name' => $definition['name'],
                    ],
                    [
                        'requires_class' => $definition['requires_class'],
                        'monthly_salary_addition' => round($definition['salary'] * $scale, 2),
                        'monthly_allowance' => round($definition['allowance'] * $scale, 2),
                        'monthly_incentive' => round($definition['incentive'] * $scale, 2),
                        'is_active' => true,
                    ]
                );

                if ($definition['requires_class']) {
                    foreach (self::CLASSES[$branch->level] as $classIndex => $className) {
                        EmployeePositionAssignment::query()->updateOrCreate(
                            [
                                'branch_id' => $branch->id,
                                'academic_year_id' => $activeYear->id,
                                'employee_id' => $employees[2 + $classIndex]->id,
                                'branch_position_id' => $position->id,
                            ],
                            ['class_name' => $className]
                        );
                    }

                    continue;
                }

                $employeeIndex = match ($definition['name']) {
                    'Kepala Sekolah' => 0,
                    'Wakil Kepala Sekolah' => 1,
                    'Koordinator Kurikulum' => 6,
                    'Kepala Tata Usaha' => 24,
                    'Bendahara Sekolah' => 25,
                    'Operator Sekolah' => 26,
                };

                EmployeePositionAssignment::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'academic_year_id' => $activeYear->id,
                        'employee_id' => $employees[$employeeIndex]->id,
                        'branch_position_id' => $position->id,
                    ],
                    ['class_name' => null]
                );
            }
        }
    }

    /**
     * @param  array<int, Branch>  $branches
     * @return array<string, array<string, BranchIncentive>>
     */
    private function seedIncentives(array $branches): array
    {
        $definitions = [
            ['name' => 'Insentif Kinerja Tahunan', 'calculation_type' => 'ANNUAL', 'default_amount' => 500000],
            ['name' => 'Insentif Hari Raya', 'calculation_type' => 'ANNUAL', 'default_amount' => 750000],
            ['name' => 'Insentif Wali Kelas', 'calculation_type' => 'MANUAL', 'default_amount' => 300000],
            ['name' => 'Insentif Kegiatan Sekolah', 'calculation_type' => 'MANUAL', 'default_amount' => 150000],
        ];
        $incentivesByBranch = [];

        foreach ($branches as $branch) {
            foreach ($definitions as $definition) {
                $incentivesByBranch[$branch->id][$definition['name']] = BranchIncentive::query()->updateOrCreate(
                    ['branch_id' => $branch->id, 'name' => $definition['name']],
                    [
                        'calculation_type' => $definition['calculation_type'],
                        'default_amount' => $definition['default_amount'],
                        'is_active' => true,
                    ]
                );
            }
        }

        return $incentivesByBranch;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, Employee>>  $employeesByBranch
     */
    private function seedUsers(array $branches, array $employeesByBranch): void
    {
        $password = env('SEED_SAMPLE_PASSWORD') ?: 'Sekolah123!';
        User::query()->updateOrCreate(
            ['email' => 'admin.demo@yayasan.sch.id'],
            ['name' => 'Admin Pusat Demo', 'branch_id' => null, 'role' => 'admin_pusat', 'password' => Hash::make($password)]
        );

        foreach ($branches as $branchIndex => $branch) {
            $prefix = sprintf('cabang%d', $branchIndex + 1);
            $accounts = [
                ['role' => 'admin_cabang', 'email' => "admin.{$prefix}@demo.yayasan.sch.id", 'name' => "Admin {$branch->name}"],
                ['role' => 'keuangan', 'email' => "keuangan.{$prefix}@demo.yayasan.sch.id", 'name' => "Keuangan {$branch->name}"],
                ['role' => 'guru', 'email' => "guru.{$prefix}@demo.yayasan.sch.id", 'name' => $employeesByBranch[$branch->id][0]->name],
                ['role' => 'staff', 'email' => "staff.{$prefix}@demo.yayasan.sch.id", 'name' => $employeesByBranch[$branch->id][24]->name],
            ];

            foreach ($accounts as $account) {
                User::query()->updateOrCreate(
                    ['email' => $account['email']],
                    [
                        'name' => $account['name'],
                        'branch_id' => $branch->id,
                        'role' => $account['role'],
                        'password' => Hash::make($password),
                    ]
                );
            }
        }
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, Employee>>  $employeesByBranch
     */
    private function seedAttendance(array $branches, array $employeesByBranch): void
    {
        $rows = [];
        $attendanceCounts = [];
        $startDate = now()->subDays(59)->startOfDay();

        for ($date = $startDate->copy(); $date->lte(now()); $date->addDay()) {
            if (! $date->isWeekday()) {
                continue;
            }

            foreach ($branches as $branchIndex => $branch) {
                foreach ($employeesByBranch[$branch->id] as $employeeIndex => $employee) {
                    $seed = $date->dayOfYear + $employeeIndex + ($branchIndex * 7);
                    $status = match (true) {
                        $seed % 47 === 0 => 'ALFA',
                        $seed % 31 === 0 => 'IZIN',
                        $seed % 19 === 0 => 'SAKIT',
                        default => 'HADIR',
                    };
                    $rows[] = [
                        'id' => Uuid::uuid7()->toString(),
                        'employee_id' => $employee->id,
                        'branch_id' => $branch->id,
                        'date' => $date->toDateString(),
                        'status' => $status,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'synced_at' => null,
                    ];

                    if (in_array($status, ['ALFA', 'IZIN'], true)) {
                        $period = $date->format('Y-m');
                        $attendanceCounts[$employee->id][$period] = ($attendanceCounts[$employee->id][$period] ?? 0) + 1;
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attendances')->upsert(
                $chunk,
                ['employee_id', 'date'],
                ['branch_id', 'status', 'updated_at']
            );
        }

        $this->attendanceCounts = $attendanceCounts;
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, Employee>>  $employeesByBranch
     * @param  array<string, BranchIncentive>  $incentives
     */
    private function seedSalaries(array $branches, array $employeesByBranch, AcademicYear $activeYear, array $incentivesByBranch): void
    {
        $rows = [];
        $attendanceCounts = $this->attendanceCounts;

        foreach (range(5, 0) as $monthsAgo) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);
            $period = $month->format('Y-m');

            foreach ($branches as $branch) {
                $incentives = $incentivesByBranch[$branch->id];
                foreach ($employeesByBranch[$branch->id] as $employeeIndex => $employee) {
                    $annualAmount = $employee->type === 'GURU' ? 500000 : 300000;
                    $holidayAmount = $employeeIndex % 3 === 0 ? 750000 : 0;
                    $classAmount = $employee->type === 'GURU' && $employeeIndex % 4 === 0 ? 300000 : 0;
                    $activityAmount = $employeeIndex % 5 === 0 ? 150000 : 0;
                    $details = [
                        [
                            'branch_incentive_id' => $incentives['Insentif Kinerja Tahunan']->id,
                            'name' => 'Insentif Kinerja Tahunan',
                            'amount' => $annualAmount,
                            'type' => 'ANNUAL',
                        ],
                        [
                            'branch_incentive_id' => $incentives['Insentif Hari Raya']->id,
                            'name' => 'Insentif Hari Raya',
                            'amount' => $holidayAmount,
                            'type' => 'ANNUAL',
                        ],
                        [
                            'branch_incentive_id' => $incentives['Insentif Wali Kelas']->id,
                            'name' => 'Insentif Wali Kelas',
                            'amount' => $classAmount,
                            'type' => 'MANUAL',
                        ],
                        [
                            'branch_incentive_id' => $incentives['Insentif Kegiatan Sekolah']->id,
                            'name' => 'Insentif Kegiatan Sekolah',
                            'amount' => $activityAmount,
                            'type' => 'MANUAL',
                        ],
                    ];
                    $totalIncentives = $annualAmount + $holidayAmount + $classAmount + $activityAmount;
                    $allowances = $employee->type === 'GURU' ? 400000 : 250000;
                    $deductions = round(($employee->base_salary / $month->daysInMonth) * ($attendanceCounts[$employee->id][$period] ?? 0), 2);
                    $rows[] = [
                        'id' => Uuid::uuid7()->toString(),
                        'employee_id' => $employee->id,
                        'branch_id' => $employee->branch_id,
                        'period' => $period,
                        'incentives' => $totalIncentives,
                        'incentive_details' => json_encode($details, JSON_THROW_ON_ERROR),
                        'allowances' => $allowances,
                        'deductions' => $deductions,
                        'net_salary' => max(0, $employee->base_salary + $totalIncentives + $allowances - $deductions),
                        'status' => $monthsAgo === 0 && $employeeIndex % 4 !== 0 ? 'PENDING' : 'PAID',
                        'created_at' => now(),
                        'updated_at' => now(),
                        'synced_at' => null,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('salaries')->upsert(
                $chunk,
                ['employee_id', 'period'],
                ['branch_id', 'incentives', 'incentive_details', 'allowances', 'deductions', 'net_salary', 'status', 'updated_at']
            );
        }

        $assignmentRows = [];
        foreach ($branches as $branch) {
            foreach ($employeesByBranch[$branch->id] as $employeeIndex => $employee) {
                foreach (['Insentif Kinerja Tahunan' => ($employee->type === 'GURU' ? 500000 : 300000), 'Insentif Hari Raya' => ($employeeIndex % 3 === 0 ? 750000 : 0)] as $name => $amount) {
                    $assignmentRows[] = [
                        'id' => Uuid::uuid7()->toString(),
                        'branch_id' => $branch->id,
                        'academic_year_id' => $activeYear->id,
                        'employee_id' => $employee->id,
                        'branch_incentive_id' => $incentivesByBranch[$branch->id][$name]->id,
                        'amount' => $amount,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'synced_at' => null,
                    ];
                }
            }
        }

        foreach (array_chunk($assignmentRows, 500) as $chunk) {
            DB::table('annual_incentive_assignments')->upsert(
                $chunk,
                ['academic_year_id', 'employee_id', 'branch_incentive_id'],
                ['branch_id', 'amount', 'updated_at']
            );
        }
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, Student>>  $studentsByBranch
     * @param  array{active: AcademicYear, previous: AcademicYear}  $academicYears
     */
    private function seedBillings(array $branches, array $studentsByBranch, array $academicYears): void
    {
        $rows = [];

        foreach ($branches as $branchIndex => $branch) {
            foreach ([$academicYears['previous'], $academicYears['active']] as $yearIndex => $academicYear) {
                $amount = match ($branch->level) {
                    'TK' => 300000,
                    'SD' => 350000,
                    'SMP' => 450000,
                    'SMA' => 550000,
                };

                foreach ($studentsByBranch[$branch->id] as $studentIndex => $student) {
                    for ($month = 1; $month <= 12; $month++) {
                        $rows[] = [
                            'id' => Uuid::uuid7()->toString(),
                            'student_id' => $student->id,
                            'branch_id' => $branch->id,
                            'academic_year_id' => $academicYear->id,
                            'month' => $month,
                            'amount' => $amount + (($studentIndex % 4) * 25000),
                            'status' => (($studentIndex + $month + $branchIndex + $yearIndex) % 10) < 7 ? 'PAID' : 'UNPAID',
                            'created_at' => now(),
                            'updated_at' => now(),
                            'synced_at' => null,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('spp_billings')->upsert(
                $chunk,
                ['student_id', 'academic_year_id', 'month'],
                ['branch_id', 'amount', 'status', 'updated_at']
            );
        }
    }

    /**
     * @param  array<int, Branch>  $branches
     * @param  array<string, array<int, Employee>>  $employeesByBranch
     */
    private function seedRosters(array $branches, array $employeesByBranch, AcademicYear $activeYear): void
    {
        $rows = [];

        foreach ($branches as $branch) {
            $teachers = array_slice($employeesByBranch[$branch->id], 0, 24);
            $classes = self::CLASSES[$branch->level];
            $subjects = self::SUBJECTS[$branch->level];

            foreach (range(1, 5) as $weekday) {
                foreach ($classes as $classIndex => $className) {
                    foreach ($subjects as $subjectIndex => $subject) {
                        $teacherIndex = ($classIndex * count($subjects)) + $subjectIndex;
                        $rows[] = [
                            'id' => Uuid::uuid7()->toString(),
                            'branch_id' => $branch->id,
                            'academic_year_id' => $activeYear->id,
                            'employee_id' => $teachers[$teacherIndex]->id,
                            'subject' => $subject,
                            'class_name' => $className,
                            'room' => sprintf('R-%02d', 100 + $classIndex + 1),
                            'weekday' => $weekday,
                            'period' => $subjectIndex + 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                            'synced_at' => null,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('roster_slots')->upsert(
                $chunk,
                ['branch_id', 'academic_year_id', 'weekday', 'period', 'class_name'],
                ['employee_id', 'subject', 'room', 'updated_at']
            );
        }
    }

    private function personName(int $seed): string
    {
        $firstName = self::FIRST_NAMES[$seed % count(self::FIRST_NAMES)];
        $lastName = self::LAST_NAMES[intdiv($seed, count(self::FIRST_NAMES)) % count(self::LAST_NAMES)];

        return "{$firstName} {$lastName}";
    }
}
