<?php

namespace App\Services;

use App\Models\TaxConfigurationModel;

class TaxService
{
    protected TaxConfigurationModel $model;

    public function __construct()
    {
        $this->model = new TaxConfigurationModel();
    }

    public function getAll(): array
    {
        return $this->model->orderBy('effective_date', 'DESC')->findAll();
    }

    public function getById(int $id): ?array
    {
        return $this->model->find($id);
    }

    public function getActive(): ?array
    {
        return $this->model->getActive();
    }

    public function create(array $data): array
    {
        $id = $this->model->insert($data, true);
        return $this->model->find($id);
    }

    public function update(int $id, array $data): ?array
    {
        $config = $this->model->find($id);
        if (!$config) return null;

        $this->model->update($id, $data);
        return $this->model->find($id);
    }

    /**
     * Activate a tax configuration.
     * Deactivates all others inside a transaction to ensure only one is active.
     */
    public function activate(int $id): array
    {
        $config = $this->model->find($id);
        if (!$config) {
            return ['success' => false, 'error' => 'Konfigurasi pajak tidak ditemukan.'];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $db->table('tax_configurations')->update(['is_active' => 0]);
        $db->table('tax_configurations')->where('id', $id)->update(['is_active' => 1]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['success' => false, 'error' => 'Gagal mengaktifkan konfigurasi pajak.'];
        }

        return ['success' => true, 'data' => $this->model->find($id)];
    }
}
