<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RosterSlot;
use App\Models\SchoolClass;
use App\Models\SchoolRoom;
use App\Models\SchoolSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class RosterGenerator extends Component
{
    use WithPagination;

    public string $branchId = '';

    public string $academicYearId = '';

    public array $lessons = [];

    public array $generated = [];

    public string $swapSlotA = '';

    public string $swapSlotB = '';

    public int $perPage = 25;

    public function mount(): void
    {
        if (auth()->user()->role !== 'admin_pusat') {
            $this->branchId = auth()->user()->branch_id;
        }

        $this->academicYearId = (string) (AcademicYear::query()->where('is_active', true)->value('id') ?? '');
        $this->lessons[] = [
            'subject' => '',
            'teacher_id' => '',
            'class_name' => '',
            'room' => '',
            'weekly_hours' => 1,
        ];
    }

    public function updatedBranchId(): void
    {
        $this->resetPage();
    }

    public function updatedAcademicYearId(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function addLesson(): void
    {
        $this->ensureRosterAdmin();
        $this->lessons[] = [
            'subject' => '',
            'teacher_id' => '',
            'class_name' => '',
            'room' => '',
            'weekly_hours' => 1,
        ];
    }

    public function removeLesson(int $index): void
    {
        $this->ensureRosterAdmin();
        unset($this->lessons[$index]);
        $this->lessons = array_values($this->lessons);
    }

    public function generate(): void
    {
        $this->ensureRosterAdmin();
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;

        $validated = $this->validate([
            'branchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'academicYearId' => ['required', 'uuid', 'exists:academic_years,id'],
            'lessons' => ['required', 'array', 'min:1', 'max:40'],
            'lessons.*.subject' => ['required', 'string', 'max:100', Rule::exists('school_subjects', 'name')->where('branch_id', $branchId)],
            'lessons.*.teacher_id' => ['required', 'uuid', 'exists:employees,id'],
            'lessons.*.class_name' => ['required', 'string', 'max:50', Rule::exists('school_classes', 'name')->where('branch_id', $branchId)],
            'lessons.*.room' => ['required', 'string', 'max:50', Rule::exists('school_rooms', 'name')->where('branch_id', $branchId)],
            'lessons.*.weekly_hours' => ['required', 'integer', 'min:1', 'max:40'],
        ]);

        abort_unless($branchId, 422, 'Pilih cabang sebelum membuat roster.');
        $existingRoster = RosterSlot::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $validated['academicYearId'])
            ->exists();

        if ($existingRoster) {
            $this->addError('lessons', 'Roster untuk tahun ajaran ini sudah ada. Gunakan fitur tukar jam di bawah agar roster yang sudah disesuaikan tidak tertimpa.');

            return;
        }

        $teacherIds = collect($validated['lessons'])->pluck('teacher_id')->unique();
        $validTeacherCount = Employee::query()
            ->where('branch_id', $branchId)
            ->where('type', 'GURU')
            ->whereIn('id', $teacherIds)
            ->count();

        if ($validTeacherCount !== $teacherIds->count()) {
            $this->addError('lessons', 'Semua guru harus berasal dari cabang terpilih dan bertipe guru.');

            return;
        }

        $existing = RosterSlot::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $validated['academicYearId'])
            ->get(['employee_id', 'class_name', 'room', 'weekday', 'period']);
        $occupiedTeachers = [];
        $occupiedClasses = [];
        $occupiedRooms = [];

        foreach ($existing as $slot) {
            $key = "{$slot->weekday}:{$slot->period}";
            $occupiedTeachers[$key][$slot->employee_id] = true;
            $occupiedClasses[$key][$slot->class_name] = true;
            $occupiedRooms[$key][$slot->room] = true;
        }

        $requirements = collect($validated['lessons'])
            ->sortByDesc('weekly_hours')
            ->values();
        $plan = [];

        foreach ($requirements as $lesson) {
            for ($hour = 0; $hour < (int) $lesson['weekly_hours']; $hour++) {
                $placed = false;
                for ($weekday = 1; $weekday <= 5 && ! $placed; $weekday++) {
                    for ($period = 1; $period <= 8; $period++) {
                        $key = "{$weekday}:{$period}";
                        if (isset($occupiedTeachers[$key][$lesson['teacher_id']])
                            || isset($occupiedClasses[$key][$lesson['class_name']])
                            || isset($occupiedRooms[$key][$lesson['room']])) {
                            continue;
                        }

                        $slot = [
                            'branch_id' => $branchId,
                            'academic_year_id' => $validated['academicYearId'],
                            'employee_id' => $lesson['teacher_id'],
                            'subject' => trim($lesson['subject']),
                            'class_name' => trim($lesson['class_name']),
                            'room' => trim($lesson['room']),
                            'weekday' => $weekday,
                            'period' => $period,
                        ];
                        $plan[] = $slot;
                        $occupiedTeachers[$key][$lesson['teacher_id']] = true;
                        $occupiedClasses[$key][$lesson['class_name']] = true;
                        $occupiedRooms[$key][$lesson['room']] = true;
                        $placed = true;
                        break;
                    }
                }

                if (! $placed) {
                    $this->addError('lessons', "Tidak ada slot yang cukup untuk {$lesson['subject']} - kelas {$lesson['class_name']}. Jadwal yang ada tetap dipertahankan.");

                    return;
                }
            }
        }

        $created = DB::transaction(function () use ($plan, $branchId, $validated): bool {
            AcademicYear::query()
                ->whereKey($validated['academicYearId'])
                ->lockForUpdate()
                ->firstOrFail();

            if (RosterSlot::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $validated['academicYearId'])
                ->exists()) {
                return false;
            }

            foreach ($plan as $slot) {
                RosterSlot::query()->create($slot);
            }

            return true;
        });

        if (! $created) {
            $this->addError('lessons', 'Roster untuk tahun ajaran ini baru saja dibuat. Muat ulang jadwal sebelum melakukan perubahan.');

            return;
        }

        $this->generated = $plan;
        session()->flash('success', count($plan).' jam pelajaran berhasil dijadwalkan tanpa bentrok.');
    }

    public function swapTimes(): void
    {
        $this->ensureRosterAdmin();
        $this->resetValidation();
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $validated = $this->validate([
            'branchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid', Rule::exists('branches', 'id')],
            'academicYearId' => ['required', 'uuid', Rule::exists('academic_years', 'id')],
            'swapSlotA' => ['required', 'uuid', 'different:swapSlotB'],
            'swapSlotB' => ['required', 'uuid'],
        ], [
            'swapSlotA.different' => 'Pilih dua jadwal pelajaran yang berbeda.',
        ]);

        abort_unless($branchId, 422, 'Pilih cabang sebelum mengubah roster.');

        $swapped = DB::transaction(function () use ($branchId, $validated): bool {
            AcademicYear::query()
                ->whereKey($validated['academicYearId'])
                ->lockForUpdate()
                ->firstOrFail();

            $slots = RosterSlot::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $validated['academicYearId'])
                ->whereIn('id', [$validated['swapSlotA'], $validated['swapSlotB']])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($slots->count() !== 2) {
                $this->addError('swapSlotA', 'Kedua jadwal harus berasal dari roster cabang dan tahun ajaran yang dipilih.');

                return false;
            }

            $first = $slots->firstWhere('id', $validated['swapSlotA']);
            $second = $slots->firstWhere('id', $validated['swapSlotB']);
            $firstTarget = ['weekday' => $second->weekday, 'period' => $second->period];
            $secondTarget = ['weekday' => $first->weekday, 'period' => $first->period];
            $excludedIds = [$first->id, $second->id];

            foreach ([[$first, $firstTarget], [$second, $secondTarget]] as [$slot, $target]) {
                $conflict = RosterSlot::query()
                    ->where('branch_id', $branchId)
                    ->where('academic_year_id', $validated['academicYearId'])
                    ->where('weekday', $target['weekday'])
                    ->where('period', $target['period'])
                    ->whereNotIn('id', $excludedIds)
                    ->where(function ($query) use ($slot): void {
                        $query->where('employee_id', $slot->employee_id)
                            ->orWhere('class_name', $slot->class_name)
                            ->orWhere('room', $slot->room);
                    })
                    ->exists();

                if ($conflict) {
                    $this->addError('swapSlotA', 'Pertukaran ditolak karena akan menimbulkan bentrok guru, kelas, atau ruangan.');

                    return false;
                }
            }

            $first->update(['weekday' => 0, 'period' => 0]);
            $second->update($secondTarget);
            $first->update($firstTarget);

            $this->dispatch('roster-swapped');

            return true;
        });

        if ($swapped) {
            session()->flash('success', 'Jam kedua pelajaran berhasil ditukar. Roster tahun ajaran ini tetap tersimpan.');
        }
    }

    private function ensureRosterAdmin(): void
    {
        abort_unless(auth()->user()->role === 'admin_cabang', 403);
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $branches = $user->role === 'admin_pusat' ? Branch::query()->whereIn('level', ['TK', 'SD', 'SMP', 'SMA'])->orderBy('name')->get() : collect();
        $employees = $branchId
            ? Employee::query()->where('branch_id', $branchId)->where('type', 'GURU')->orderBy('name')->get(['id', 'name', 'nip'])
            : collect();
        $classes = $branchId ? SchoolClass::query()->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name']) : collect();
        $rooms = $branchId ? SchoolRoom::query()->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name']) : collect();
        $subjects = $branchId ? SchoolSubject::query()->where('branch_id', $branchId)->orderBy('name')->get(['id', 'name']) : collect();
        $academicYears = AcademicYear::query()->orderByDesc('starts_on')->get();
        $rosterQuery = $branchId && $this->academicYearId
            ? RosterSlot::query()
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $this->academicYearId)
            : null;
        $rosterCount = $rosterQuery?->count() ?? 0;
        $rosterSlots = $rosterQuery
            ? (clone $rosterQuery)
                ->with('employee:id,name,nip')
                ->orderBy('weekday')
                ->orderBy('period')
                ->orderBy('class_name')
                ->paginate($this->perPage)
            : RosterSlot::query()->whereRaw('1 = 0')->paginate($this->perPage);
        $swapSlots = $rosterQuery
            ? (clone $rosterQuery)
                ->with('employee:id,name,nip')
                ->orderBy('weekday')
                ->orderBy('period')
                ->orderBy('class_name')
                ->get()
            : collect();
        $isRosterAdmin = $user->role === 'admin_cabang';

        return view('livewire.roster-generator', compact('branches', 'employees', 'classes', 'rooms', 'subjects', 'academicYears', 'rosterSlots', 'swapSlots', 'rosterCount', 'isRosterAdmin'))
            ->layout('layouts.app', ['title' => 'Generator Roster']);
    }
}
