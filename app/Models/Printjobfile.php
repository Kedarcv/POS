<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class Printjobfile extends Model
{
    public const IMAGE_TYPES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    public const DOC_TYPES   = ['pdf'];

    protected $table            = 'print_job_files';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = ['print_job_id', 'file_name', 'file_path', 'file_type', 'file_size'];
    protected $useTimestamps    = false; // created_at set explicitly below (no updated_at column on this table)

    public function getForJob(int $printJobId): array
    {
        return $this->where('print_job_id', $printJobId)->orderBy('created_at', 'ASC')->findAll();
    }

    public function isImage(object $file): bool
    {
        return in_array(strtolower($file->file_type ?? ''), self::IMAGE_TYPES, true);
    }
}
