<?php

namespace App\Livewire;

use App\Livewire\Concerns\HasDateRangeFilter;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogViewer extends Component
{
    use HasDateRangeFilter, WithPagination;

    public string $search = '';

    public string $event = '';

    public function mount(): void
    {
        $this->authorizeViewing();
        app(AuditLogger::class)->record(
            'viewed',
            AuditLog::class,
            'activity-logs',
            null,
            [],
            ['event_filter' => $this->event, 'search' => $this->search !== '']
        );
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->authorizeViewing();

        $logs = AuditLog::query()
            ->when(in_array($this->event, ['created', 'updated', 'deleted', 'viewed'], true), fn ($query) => $query->where('event', $this->event))
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('actor_name', 'like', $search)
                        ->orWhere('actor_email', 'like', $search)
                        ->orWhere('ip_address', 'like', $search)
                        ->orWhere('auditable_type', 'like', $search)
                        ->orWhere('auditable_id', 'like', $search);
                });
            })
            ->tap(fn ($query) => $this->applyDateFilter($query, 'created_at'))
            ->latest('created_at')
            ->paginate(25);

        return view('livewire.audit-log-viewer', compact('logs'))
            ->layout('layouts.app', ['title' => 'Log Aktivitas']);
    }

    private function authorizeViewing(): void
    {
        abort_unless(auth()->user()->role === 'admin_pusat', 403);
    }
}
