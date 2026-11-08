<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use App\Traits\HasUuidV7;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    use HasAuditLog, HasUuidV7;

    public const TYPES = [
        'PAS_PHOTO' => 'Pas foto',
        'FAMILY_CARD' => 'Kartu keluarga',
        'PREVIOUS_DIPLOMA' => 'Ijazah terakhir',
        'OTHER' => 'Dokumen lainnya',
    ];

    protected $fillable = [
        'student_id',
        'uploaded_by',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
