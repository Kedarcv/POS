<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Commands\ImportStock;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use RuntimeException;

class Admin_data extends Secure_Controller
{
    public function __construct()
    {
        parent::__construct('admin_data', null, 'home');

        if (! $this->employee->isAdmin((int) $this->employee->get_logged_in_employee_info()->person_id)) {
            throw new RuntimeException('Administrator access is required.');
        }
    }

    public function getIndex(): string
    {
        return view('admin_data/index');
    }

    public function postImport(): ResponseInterface
    {
        $file = $this->request->getFile('stock_file');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return redirect()->back()->with('error', 'Please select a valid Excel file.');
        }

        $extension = strtolower($file->getClientExtension());
        if (! in_array($extension, ['xls', 'xlsx'], true)) {
            return redirect()->back()->with('error', 'Only XLS and XLSX files are supported.');
        }

        $path = $file->getTempName();
        (new ImportStock())->run(['file' => $path]);

        return redirect()->back()->with('success', 'Excel stock import completed.');
    }

    public function postClearOperational(): ResponseInterface
    {
        if ($this->request->getPost('confirmation') !== 'CLEAR OPERATIONAL DATA') {
            return redirect()->back()->with('error', 'Type CLEAR OPERATIONAL DATA to confirm.');
        }

        $db = Database::connect();
        $tables = [
            'sales_payments', 'sales_items_taxes', 'sales_items', 'sales',
            'sales_suspended_payments', 'sales_suspended_items_taxes',
            'sales_suspended_items', 'sales_suspended',
            'receivings_items', 'receivings', 'expenses',
            'inventory', 'item_quantities', 'items_taxes', 'item_kit_items',
            'items', 'cash_up', 'layby_installments',
        ];

        $db->transStart();
        $db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            if ($db->tableExists($table)) {
                $db->table($table)->truncate();
            }
        }
        $db->query('SET FOREIGN_KEY_CHECKS=1');
        $db->transComplete();

        return redirect()->back()->with(
            $db->transStatus() ? 'success' : 'error',
            $db->transStatus() ? 'Operational and stock data cleared.' : 'Data clear failed.',
        );
    }
}
