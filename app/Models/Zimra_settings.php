<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

// Single-row table holding this till's ZIMRA fiscal device identity.
class Zimra_settings extends Model
{
    protected $table            = 'zimra_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'enabled', 'environment', 'tin', 'device_serial_no',
        'device_model_name', 'device_model_version', 'device_id',
        'activation_key', 'certificate_path', 'private_key_path',
        'default_currency', 'last_fiscal_day_no', 'last_receipt_global_no',
        'registered_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getSingleton(): ?object
    {
        $row = $this->first();
        if ($row !== null) {
            return $row;
        }

        $id = $this->insert(['enabled' => 0, 'environment' => 'test'], true);
        return $this->find($id);
    }

    public function updateSettings(array $data): bool
    {
        $row = $this->getSingleton();
        return $this->update($row->id, $data);
    }
}
