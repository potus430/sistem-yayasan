<?php

namespace App\Livewire;

use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

class StudentDocumentManager extends Component
{
    use WithFileUploads;

    public string $studentId = '';

    /** @var array<string, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null> */
    public array $uploads = [];

    public function mount(string $student): void
    {
        $this->authorizeBranchAdmin();
        $this->studentId = $student;
        $this->student();
    }

    private function student(): Student
    {
        return Student::query()
            ->where('branch_id', auth()->user()->branch_id)
            ->findOrFail($this->studentId);
    }

    public function saveDocuments(): void
    {
        $this->authorizeBranchAdmin();
        $student = $this->student();
        $rules = [];
        foreach (StudentDocument::TYPES as $type => $_label) {
            $rules["uploads.{$type}"] = match ($type) {
                'PAS_PHOTO' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'FAMILY_CARD', 'PREVIOUS_DIPLOMA' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
                default => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            };
        }
        $validated = $this->validate($rules);
        $selected = collect($validated['uploads'])->filter();
        if ($selected->isEmpty()) {
            $this->addError('uploads', 'Pilih setidaknya satu dokumen untuk diunggah.');

            return;
        }

        foreach ($selected as $type => $file) {
            $extension = $file->extension();
            $directory = "student-documents/{$student->branch_id}/{$student->id}";
            $path = Storage::disk('local')->putFileAs($directory, $file, Str::uuid().'.'.$extension);
            if ($path === false) {
                throw new RuntimeException("Dokumen {$type} gagal disimpan ke penyimpanan privat.");
            }

            try {
                DB::transaction(fn () => StudentDocument::query()->create([
                    'student_id' => $student->id,
                    'uploaded_by' => auth()->id(),
                    'type' => $type,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                ]));
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        }

        $this->reset('uploads');
        $this->resetValidation();
        session()->flash('success', 'Dokumen siswa berhasil diunggah dan disimpan secara privat.');
    }

    public function deleteDocument(string $documentId): void
    {
        $this->authorizeBranchAdmin();
        $student = $this->student();
        $document = StudentDocument::query()
            ->where('student_id', $student->id)
            ->findOrFail($documentId);
        $disk = Storage::disk($document->disk);
        if ($disk->exists($document->path) && ! $disk->delete($document->path)) {
            throw new RuntimeException('Dokumen gagal dihapus dari penyimpanan privat.');
        }
        $document->delete();
        session()->flash('success', 'Dokumen berhasil dihapus.');
    }

    private function authorizeBranchAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin_cabang' && auth()->user()?->branch_id, 403);
    }

    public function render()
    {
        $this->authorizeBranchAdmin();
        $student = $this->student();
        $documents = StudentDocument::query()
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        return view('livewire.student-document-manager', compact('documents', 'student'))
            ->layout('layouts.app', ['title' => 'Dokumen siswa']);
    }
}
