<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RosterSlot;
use App\Models\TeacherSubstitution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceManager extends Component
{
    use WithPagination;

    public string $branchId = '';

    public string $date = '';

    public string $employeeId = '';

    public string $status = 'HADIR';

    public int $perPage = 25;

    /** @var array<string, string> */
    public array $substituteAssignments = [];

    public function mount(): void
    {
        $this->date = now()->toDateString();
        if (auth()->user()->role !== 'admin_pusat') {
            $this->branchId = auth()->user()->branch_id;
        }
    }

    public function updatedBranchId(): void
    {
        $this->employeeId = '';
        $this->substituteAssignments = [];
        $this->resetPage();
    }

    public function updatedDate(): void
    {
        $this->substituteAssignments = [];
        $this->loadSavedAttendance();
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function updatedEmployeeId(): void
    {
        $this->substituteAssignments = [];
        $this->loadSavedAttendance();
    }

    public function updatedStatus(): void
    {
        if ($this->status === 'HADIR') {
            $this->substituteAssignments = [];
        }
    }

    public function saveAttendance(): void
    {
        abort_unless(auth()->user()->role === 'admin_cabang', 403);
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $validated = $this->validate([
            'branchId' => [$user->role === 'admin_pusat' ? 'required' : 'nullable', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'employeeId' => ['required', 'uuid'],
            'status' => ['required', 'in:HADIR,IZIN,ALFA,SAKIT'],
            'substituteAssignments' => ['array'],
            'substituteAssignments.*' => ['nullable', 'uuid'],
        ]);

        abort_unless($branchId, 422, 'Pilih cabang sebelum menyimpan absensi.');
        $teacher = Employee::query()
            ->where('branch_id', $branchId)
            ->where('type', 'GURU')
            ->findOrFail($validated['employeeId']);
        $date = Carbon::createFromFormat('Y-m-d', $validated['date']);
        $slots = $this->scheduledSlots($branchId, $teacher->id, $date);
        $assignments = $validated['status'] === 'HADIR' ? [] : ($validated['substituteAssignments'] ?? []);
        $selectedPerPeriod = [];

        foreach ($slots as $slot) {
            $eligible = $this->availableSubstitutes($branchId, $teacher->id, $date, $slot);
            $selectedId = $assignments[$slot->id] ?? null;
            $eligibleIds = $eligible->modelKeys();

            if ($selectedId !== null && ! in_array($selectedId, $eligibleIds, true)) {
                $this->addError("substituteAssignments.{$slot->id}", 'Guru pengganti tidak tersedia atau sudah mengajar pada jam tersebut.');

                return;
            }
            if ($selectedId && isset($selectedPerPeriod[$slot->period][$selectedId])) {
                $this->addError("substituteAssignments.{$slot->id}", 'Guru yang sama tidak dapat menggantikan dua kelas pada jam yang sama.');

                return;
            }
            if ($selectedId) {
                $selectedPerPeriod[$slot->period][$selectedId] = true;
            }

            if ($validated['status'] !== 'HADIR' && $eligibleIds !== [] && ! $selectedId) {
                $this->addError("substituteAssignments.{$slot->id}", 'Pilih guru pengganti untuk jam pelajaran ini.');

                return;
            }
        }

        foreach (array_keys($assignments) as $slotId) {
            if (! $slots->contains('id', $slotId)) {
                $this->addError('substituteAssignments', 'Ada jadwal yang tidak valid untuk guru ini.');

                return;
            }
        }

        DB::transaction(function () use ($branchId, $teacher, $validated, $date, $slots, $assignments): void {
            $selectedIds = array_values(array_unique(array_filter($assignments)));
            if ($selectedIds !== []) {
                Employee::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('id', $selectedIds)
                    ->lockForUpdate()
                    ->get(['id']);

                foreach ($slots as $slot) {
                    $selectedId = $assignments[$slot->id] ?? null;
                    if ($selectedId && ! $this->availableSubstitutes($branchId, $teacher->id, $date, $slot)->contains('id', $selectedId)) {
                        throw ValidationException::withMessages([
                            "substituteAssignments.{$slot->id}" => 'Ketersediaan guru pengganti berubah. Pilih guru lain dan coba kembali.',
                        ]);
                    }
                }
            }

            Attendance::query()->updateOrCreate(
                ['employee_id' => $teacher->id, 'date' => $validated['date']],
                ['branch_id' => $branchId, 'status' => $validated['status']]
            );

            TeacherSubstitution::query()
                ->where('branch_id', $branchId)
                ->where('absent_employee_id', $teacher->id)
                ->whereDate('date', $validated['date'])
                ->each(fn (TeacherSubstitution $substitution) => $substitution->update(['is_cancelled' => true]));

            if ($validated['status'] === 'HADIR') {
                return;
            }

            foreach ($slots as $slot) {
                $substituteId = $assignments[$slot->id] ?? null;
                if (! $substituteId) {
                    continue;
                }

                TeacherSubstitution::query()->updateOrCreate(
                    ['roster_slot_id' => $slot->id, 'date' => $date->toDateString()],
                    [
                        'branch_id' => $branchId,
                        'absent_employee_id' => $teacher->id,
                        'substitute_employee_id' => $substituteId,
                        'period' => $slot->period,
                        'is_cancelled' => false,
                    ]
                );
            }
        });

        $this->substituteAssignments = $validated['status'] === 'HADIR' ? [] : $assignments;
        session()->flash('success', $validated['status'] === 'HADIR'
            ? 'Absensi hadir tersimpan dan penugasan pengganti pada tanggal ini dibersihkan.'
            : 'Absensi dan penugasan guru pengganti berhasil disimpan.');
    }

    private function scheduledSlots(string $branchId, string $teacherId, Carbon $date)
    {
        return RosterSlot::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $teacherId)
            ->where('weekday', $date->dayOfWeekIso)
            ->orderBy('period')
            ->get();
    }

    private function availableSubstitutes(string $branchId, string $absentTeacherId, Carbon $date, RosterSlot $slot)
    {
        $busyFromRoster = RosterSlot::query()
            ->where('branch_id', $branchId)
            ->where('weekday', $date->dayOfWeekIso)
            ->where('period', $slot->period)
            ->select('employee_id');
        $busyAsSubstitute = TeacherSubstitution::query()
            ->where('branch_id', $branchId)
            ->whereDate('date', $date->toDateString())
            ->where('period', $slot->period)
            ->where('is_cancelled', false)
            ->where('roster_slot_id', '<>', $slot->id)
            ->select('substitute_employee_id');
        $absentToday = Attendance::query()
            ->where('branch_id', $branchId)
            ->whereDate('date', $date->toDateString())
            ->whereIn('status', ['IZIN', 'ALFA', 'SAKIT'])
            ->select('employee_id');

        return Employee::query()
            ->where('branch_id', $branchId)
            ->where('type', 'GURU')
            ->where('id', '<>', $absentTeacherId)
            ->whereNotIn('id', $busyFromRoster)
            ->whereNotIn('id', $busyAsSubstitute)
            ->whereNotIn('id', $absentToday)
            ->orderBy('name')
            ->get(['id', 'name', 'nip']);
    }

    private function loadSavedAttendance(): void
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        if (! $branchId || ! $this->employeeId || ! $this->date) {
            return;
        }

        $attendance = Attendance::query()
            ->where('branch_id', $branchId)
            ->where('employee_id', $this->employeeId)
            ->whereDate('date', $this->date)
            ->first();
        $this->status = $attendance?->status ?? 'HADIR';

        $this->substituteAssignments = TeacherSubstitution::query()
            ->where('branch_id', $branchId)
            ->where('absent_employee_id', $this->employeeId)
            ->whereDate('date', $this->date)
            ->where('is_cancelled', false)
            ->pluck('substitute_employee_id', 'roster_slot_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    public function render()
    {
        $user = auth()->user();
        $branchId = $user->role === 'admin_pusat' ? $this->branchId : $user->branch_id;
        $branches = $user->role === 'admin_pusat' ? Branch::query()->whereIn('level', ['TK', 'SD', 'SMP', 'SMA'])->orderBy('name')->get() : collect();
        $teachers = $branchId
            ? Employee::query()->where('branch_id', $branchId)->where('type', 'GURU')->orderBy('name')->get(['id', 'name', 'nip'])
            : collect();
        $slots = collect();
        $substitutesBySlot = [];

        if ($branchId && $this->employeeId && $this->date) {
            $date = Carbon::parse($this->date);
            $slots = $this->scheduledSlots($branchId, $this->employeeId, $date);
            foreach ($slots as $slot) {
                $substitutesBySlot[$slot->id] = $this->availableSubstitutes($branchId, $this->employeeId, $date, $slot);
            }
        }

        $recentAttendances = Attendance::query()
            ->with('employee:id,name,nip,branch_id', 'employee.branch:id,name')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('date', $this->date)
            ->orderBy('status')
            ->orderBy('employee_id')
            ->paginate($this->perPage);
        $substitutionsByEmployee = TeacherSubstitution::query()
            ->with(['substituteEmployee:id,name', 'rosterSlot:id,subject,class_name,period'])
            ->whereDate('date', $this->date)
            ->where('is_cancelled', false)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->groupBy('absent_employee_id');

        return view('livewire.attendance-manager', compact(
            'branches',
            'teachers',
            'slots',
            'substitutesBySlot',
            'recentAttendances',
            'substitutionsByEmployee'
        ))->layout('layouts.app', ['title' => 'Absensi dan Guru Pengganti']);
    }
}
