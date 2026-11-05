<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Zimra_lib;
use App\Libraries\Zimra_node_bridge;
use App\Models\Tax_category;
use App\Models\Zimra_fiscal_day;
use App\Models\Zimra_receipt;
use App\Models\Zimra_settings;
use App\Models\Zimra_tax_map;
use CodeIgniter\HTTP\ResponseInterface;

class Zimra extends Secure_Controller
{
    private Zimra_lib $zimra_lib;
    private Zimra_settings $zimra_settings;
    private Zimra_tax_map $zimra_tax_map;
    private Zimra_fiscal_day $zimra_fiscal_day;
    private Zimra_receipt $zimra_receipt;
    private Tax_category $tax_category;
    private Zimra_node_bridge $node_bridge;

    public function __construct()
    {
        parent::__construct('zimra');

        $this->zimra_lib = new Zimra_lib();
        $this->zimra_settings = model(Zimra_settings::class);
        $this->zimra_tax_map = model(Zimra_tax_map::class);
        $this->zimra_fiscal_day = model(Zimra_fiscal_day::class);
        $this->zimra_receipt = model(Zimra_receipt::class);
        $this->tax_category = model(Tax_category::class);
        $this->node_bridge = new Zimra_node_bridge();
    }

    public function getIndex(): string
    {
        $data['title'] = 'ZIMRA Fiscalisation';
        $data['settings'] = $this->zimra_settings->getSingleton();
        $data['tax_categories'] = $this->tax_category->get_all()->getResult();
        $data['tax_map'] = $this->zimra_tax_map->getAllMapped();
        $data['open_day'] = $this->zimra_fiscal_day->getOpenDay();
        $data['recent_days'] = $this->zimra_fiscal_day->getRecent(10);
        $data['recent_receipts'] = $this->zimra_receipt->getRecent(20);
        $data['pending_count'] = count($this->zimra_receipt->getPending());

        return view('zimra/settings', $data);
    }

    public function postSaveSettings(): ResponseInterface
    {
        $this->zimra_settings->updateSettings([
            'enabled'          => $this->request->getPost('enabled') ? 1 : 0,
            'environment'      => $this->request->getPost('environment') === 'production' ? 'production' : 'test',
            'default_currency' => $this->request->getPost('default_currency') ?: 'USD',
        ]);

        $this->session->setFlashdata('success', 'ZIMRA settings saved.');

        return redirect()->to('zimra');
    }

    public function postSaveTaxMap(): ResponseInterface
    {
        $categoryIds = $this->request->getPost('tax_category_id') ?? [];
        $zimraTaxIds = $this->request->getPost('zimra_tax_id') ?? [];
        $zimraPercents = $this->request->getPost('zimra_tax_percent') ?? [];
        $zimraNames = $this->request->getPost('zimra_tax_name') ?? [];

        foreach ($categoryIds as $i => $categoryId) {
            if ($zimraTaxIds[$i] === '' || $zimraTaxIds[$i] === null) {
                continue;
            }
            $this->zimra_tax_map->upsert(
                (int) $categoryId,
                (int) $zimraTaxIds[$i],
                $zimraPercents[$i] !== '' ? (float) $zimraPercents[$i] : null,
                $zimraNames[$i] ?: null,
            );
        }

        $this->session->setFlashdata('success', 'Tax mapping saved.');

        return redirect()->to('zimra');
    }

    public function postRegisterDevice(): ResponseInterface
    {
        $deviceId = (int) $this->request->getPost('device_id');
        $activationKey = (string) $this->request->getPost('activation_key');
        $tin = (string) $this->request->getPost('tin');
        $deviceSerialNo = (string) $this->request->getPost('device_serial_no');
        $deviceModelName = (string) ($this->request->getPost('device_model_name') ?: 'Server');
        $deviceModelVersion = (string) ($this->request->getPost('device_model_version') ?: '1.0');

        // Environment/currency must be saved before registering so the
        // client talks to the right FDMS host (test vs production).
        $this->zimra_settings->updateSettings([
            'environment' => $this->request->getPost('environment') === 'production' ? 'production' : 'test',
        ]);

        try {
            if ($this->node_bridge->isAvailable()) {
                $nodeResult = $this->node_bridge->call([
                    'operation'     => 'register',
                    'profile'       => $this->node_bridge->profilePath(),
                    'deviceId'      => $deviceId,
                    'activationKey' => $activationKey,
                    'serialNumber'  => $deviceSerialNo,
                    'modelName'     => $deviceModelName,
                    'modelVersion'  => $deviceModelVersion,
                    'environment'   => $this->request->getPost('environment') === 'production' ? 'production' : 'test',
                ]);
                $this->zimra_settings->updateSettings([
                    'device_id'        => $deviceId,
                    'activation_key'   => $activationKey,
                    'tin'              => $tin,
                    'device_serial_no' => $deviceSerialNo,
                    'device_model_name' => $deviceModelName,
                    'device_model_version' => $deviceModelVersion,
                    'certificate_path' => $nodeResult['certificateFile'],
                    'private_key_path' => $nodeResult['privateKeyFile'],
                    'registered_at'    => date('Y-m-d H:i:s'),
                ]);
                $result = ['success' => true, 'message' => 'Device registered successfully'];
            } else {
                $result = $this->zimra_lib->registerDevice($deviceId, $activationKey, $tin, $deviceSerialNo, $deviceModelName, $deviceModelVersion);
            }
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->session->setFlashdata($result['success'] ? 'success' : 'error', $result['message']);

        return redirect()->to('zimra');
    }

    public function postOpenDay(): ResponseInterface
    {
        try {
            $result = $this->node_bridge->isAvailable() && $this->hasNodeProfile()
                ? ['success' => true, 'message' => 'Fiscal day opened', 'data' => $this->node_bridge->call([
                    'operation' => 'open',
                    'profile' => $this->node_bridge->profilePath(),
                ])]
                : $this->zimra_lib->openDay();
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->session->setFlashdata($result['success'] ? 'success' : 'error', $result['message']);

        return redirect()->to('zimra');
    }

    public function postCloseDay(): ResponseInterface
    {
        try {
            $result = $this->node_bridge->isAvailable() && $this->hasNodeProfile()
                ? ['success' => true, 'message' => 'Fiscal day closed', 'data' => $this->node_bridge->call([
                    'operation' => 'close',
                    'profile' => $this->node_bridge->profilePath(),
                ])]
                : $this->zimra_lib->closeDay();
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->session->setFlashdata($result['success'] ? 'success' : 'error', $result['message']);

        return redirect()->to('zimra');
    }

    public function postRetryPending(): ResponseInterface
    {
        try {
            $result = $this->zimra_lib->retryPending();
            $message = "Retried {$result['attempted']}: {$result['succeeded']} succeeded, {$result['failed']} failed.";
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        $this->session->setFlashdata('success', $message);

        return redirect()->to('zimra');
    }

    public function getFetchConfig(): ResponseInterface
    {
        try {
            $result = $this->node_bridge->isAvailable() && $this->hasNodeProfile()
                ? $this->node_bridge->call([
                    'operation' => 'config',
                    'profile' => $this->node_bridge->profilePath(),
                ])
                : $this->zimra_lib->getConfig();
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        return $this->response->setJSON($result);
    }

    private function hasNodeProfile(): bool
    {
        return is_file($this->node_bridge->profilePath() . DIRECTORY_SEPARATOR . 'device.json');
    }
}
