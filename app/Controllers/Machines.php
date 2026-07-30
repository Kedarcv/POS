<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Machine;

class Machines extends Secure_Controller
{
    protected Machine $machine;

    public function __construct()
    {
        parent::__construct('machines');
        $this->machine = model(Machine::class);
    }

    /**
     * GET /machines — List all machines with status
     */
    public function index()
    {
        $data['title']         = 'Print Machines';
        $data['machines']      = $this->machine->getWithJobCount();
        $data['status_counts'] = $this->machine->getStatusCounts();

        echo view('machines/index', $data);
    }

    /**
     * GET /machines/new — Create form
     */
    public function new()
    {
        $data['title']   = 'Add New Machine';
        $data['machine'] = null;

        echo view('machines/form', $data);
    }

    /**
     * GET /machines/edit/(:num) — Edit form
     */
    public function edit(int $id = 0)
    {
        $data['machine'] = $this->machine->find($id);
        if (! $data['machine']) {
            return redirect()->to('/machines');
        }

        $data['title'] = 'Edit Machine — ' . esc($data['machine']->name);

        echo view('machines/form', $data);
    }

    /**
     * POST /machines/save — Save new or update
     */
    public function save()
    {
        $id = (int) $this->request->getPost('id');

        $input = [
            'name'            => $this->request->getPost('name'),
            'machine_type'    => $this->request->getPost('machine_type') ?: 'other',
            'brand'           => $this->request->getPost('brand'),
            'model_number'    => $this->request->getPost('model_number'),
            'serial_number'   => $this->request->getPost('serial_number'),
            'status'          => $this->request->getPost('status') ?: 'active',
            'ip_address'      => $this->request->getPost('ip_address'),
            'connection_type' => $this->request->getPost('connection_type'),
            'location'        => $this->request->getPost('location'),
            'max_width_mm'    => (int) ($this->request->getPost('max_width_mm') ?: 0) ?: null,
            'max_height_mm'   => (int) ($this->request->getPost('max_height_mm') ?: 0) ?: null,
            'notes'           => $this->request->getPost('notes'),
        ];

        if ($id > 0) {
            $this->machine->update($id, $input);

            return redirect()->to('/machines')->with('message', 'Machine updated successfully');
        }

        $newId = $this->machine->insert($input);

        return redirect()->to('/machines')->with('message', 'Machine added successfully');
    }

    /**
     * GET /machines/delete/(:num) — Delete machine
     */
    public function delete(int $id = 0)
    {
        $machine = $this->machine->find($id);
        if ($machine) {
            $this->machine->delete($id);
        }

        return redirect()->to('/machines')->with('message', 'Machine deleted');
    }

    /**
     * GET /machines/json — JSON list for dropdowns
     */
    public function json()
    {
        $machines = $this->machine->getActive();

        $result = [];

        foreach ($machines as $m) {
            $result[] = [
                'id'            => $m->id,
                'name'          => $m->name,
                'type'          => $m->machine_type,
                'max_width_mm'  => $m->max_width_mm,
                'max_height_mm' => $m->max_height_mm,
            ];
        }

        return $this->response->setJSON($result);
    }
}
