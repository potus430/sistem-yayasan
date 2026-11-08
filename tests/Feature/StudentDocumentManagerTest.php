<?php

namespace Tests\Feature;

use App\Livewire\StudentDirectory;
use App\Livewire\StudentDocumentManager;
use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StudentDocumentManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_admin_can_upload_download_and_delete_private_student_documents(): void
    {
        Storage::fake('local');
        $branch = $this->createBranch('Document Branch');
        $admin = $this->createUser('admin_cabang', $branch);
        $student = $this->createStudent($branch);
        $file = UploadedFile::fake()->create('family-card.pdf', 128, 'application/pdf');

        Livewire::actingAs($admin)
            ->test(StudentDocumentManager::class, ['student' => $student->id])
            ->set('uploads.FAMILY_CARD', $file)
            ->call('saveDocuments')
            ->assertHasNoErrors()
            ->assertSee('Dokumen siswa berhasil diunggah');

        $document = StudentDocument::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame('FAMILY_CARD', $document->type);
        $this->assertSame('family-card.pdf', $document->original_name);
        $this->assertSame($admin->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($admin)
            ->get(route('student-documents.download', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        Livewire::actingAs($admin)
            ->test(StudentDocumentManager::class, ['student' => $student->id])
            ->call('deleteDocument', $document->id)
            ->assertHasNoErrors();
        Storage::disk('local')->assertMissing($document->path);
        $this->assertDatabaseMissing('student_documents', ['id' => $document->id]);
    }

    public function test_student_documents_are_private_to_their_own_branch_admin(): void
    {
        Storage::fake('local');
        $branch = $this->createBranch('Private Document Branch');
        $otherBranch = $this->createBranch('Other Document Branch');
        $admin = $this->createUser('admin_cabang', $branch);
        $otherAdmin = $this->createUser('admin_cabang', $otherBranch);
        $centralAdmin = $this->createUser('admin_pusat');
        $student = $this->createStudent($branch);
        $document = StudentDocument::query()->create([
            'student_id' => $student->id,
            'uploaded_by' => $admin->id,
            'type' => 'FAMILY_CARD',
            'disk' => 'local',
            'path' => "student-documents/{$branch->id}/{$student->id}/family.pdf",
            'original_name' => 'family.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
        Storage::disk('local')->put($document->path, '%PDF-test');

        $this->actingAs($otherAdmin)->get(route('student-documents', $student->id))->assertNotFound();
        $this->actingAs($otherAdmin)->get(route('student-documents.download', $document))->assertNotFound();
        $this->actingAs($centralAdmin)->get(route('student-documents', $student->id))->assertForbidden();
        $this->actingAs($centralAdmin)->get(route('student-documents.download', $document))->assertOk();
        $this->actingAs($admin)->get(route('student-documents.preview', $document))->assertNotFound();
    }

    public function test_student_directory_details_and_private_photo_follow_branch_access(): void
    {
        Storage::fake('local');
        $branch = $this->createBranch('Student Profile Branch');
        $otherBranch = $this->createBranch('Other Student Profile Branch');
        $branchAdmin = $this->createUser('admin_cabang', $branch);
        $otherAdmin = $this->createUser('admin_cabang', $otherBranch);
        $centralAdmin = $this->createUser('admin_pusat');
        $student = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => 'PROFILE-001',
            'name' => 'Complete Student Profile',
            'current_class' => '2A',
            'gender' => 'P',
            'birth_place' => 'Bandung',
            'birth_date' => '2015-04-12',
            'guardian_name' => 'Parent Example',
            'guardian_phone' => '081234567890',
            'address' => 'Jl. Contoh No. 5',
            'previous_school' => 'TK Contoh',
        ]);
        $maleStudent = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => 'PROFILE-002',
            'name' => 'Default Male Profile',
            'current_class' => '2A',
            'gender' => 'L',
        ]);
        $femaleStudent = Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => 'PROFILE-003',
            'name' => 'Default Female Profile',
            'current_class' => '2A',
            'gender' => 'PEREMPUAN',
        ]);
        $photo = StudentDocument::query()->create([
            'student_id' => $student->id,
            'uploaded_by' => $branchAdmin->id,
            'type' => 'PAS_PHOTO',
            'disk' => 'local',
            'path' => "student-documents/{$branch->id}/{$student->id}/photo.jpg",
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
        ]);
        Storage::disk('local')->put($photo->path, 'private-photo-bytes');
        $this->assertDatabaseHas('students', ['id' => $student->id, 'branch_id' => $branch->id]);
        $this->assertSame($branch->id, $branchAdmin->branch_id);

        $this->actingAs($branchAdmin);
        Livewire::test(StudentDirectory::class)
            ->call('showDetails', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertSee('Parent Example')
            ->assertSee('081234567890')
            ->assertSee('Jl. Contoh No. 5')
            ->assertSee('TK Contoh')
            ->assertSee(route('student-documents.preview', $photo));

        Livewire::actingAs($centralAdmin)
            ->test(StudentDirectory::class)
            ->call('showDetails', $student->id)
            ->assertSee('Complete Student Profile');

        Livewire::actingAs($branchAdmin)
            ->test(StudentDirectory::class)
            ->call('showDetails', $maleStudent->id)
            ->assertSee(asset('pasphoto/icon-laki.png'));
        Livewire::actingAs($branchAdmin)
            ->test(StudentDirectory::class)
            ->call('showDetails', $femaleStudent->id)
            ->assertSee(asset('pasphoto/icon-wanita.png'));

        Livewire::actingAs($otherAdmin)
            ->test(StudentDirectory::class)
            ->assertDontSee('Complete Student Profile');

        $photoResponse = $this->actingAs($branchAdmin)
            ->get(route('student-documents.preview', $photo))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('x-content-type-options', 'nosniff');
        $this->assertSame('private-photo-bytes', $photoResponse->streamedContent());
        $this->actingAs($centralAdmin)->get(route('student-documents.preview', $photo))->assertOk();
        $this->actingAs($otherAdmin)->get(route('student-documents.preview', $photo))->assertNotFound();
    }

    public function test_document_upload_rejects_unsupported_files(): void
    {
        Storage::fake('local');
        $branch = $this->createBranch('Invalid Document Branch');
        $admin = $this->createUser('admin_cabang', $branch);
        $student = $this->createStudent($branch);

        Livewire::actingAs($admin)
            ->test(StudentDocumentManager::class, ['student' => $student->id])
            ->set('uploads.FAMILY_CARD', UploadedFile::fake()->create('family-card.exe', 100, 'application/x-msdownload'))
            ->call('saveDocuments')
            ->assertHasErrors(['uploads.FAMILY_CARD']);

        $this->assertDatabaseCount('student_documents', 0);
    }

    private function createBranch(string $name): Branch
    {
        return Branch::query()->create([
            'name' => $name.' '.Str::uuid(),
            'level' => 'SD',
            'address' => 'Test address',
        ]);
    }

    private function createStudent(Branch $branch): Student
    {
        return Student::query()->create([
            'branch_id' => $branch->id,
            'nisn' => (string) random_int(1000000000, 9999999999),
            'name' => 'Document Student',
            'current_class' => '1A',
        ]);
    }

    private function createUser(string $role, ?Branch $branch = null): User
    {
        return User::query()->create([
            'name' => 'Test '.$role,
            'email' => Str::uuid().'@example.test',
            'password' => 'test-password',
            'branch_id' => $branch?->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
