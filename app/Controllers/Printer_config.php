<?php

namespace App\Controllers;

use App\Models\Appconfig;
use ReflectionException;

class Printer_config extends Secure_Controller
{
    private Appconfig $appconfig;

    public function __construct()
    {
        parent::__construct('config');

        $this->appconfig = model(Appconfig::class);
    }

    /**
     * Display the printer and terminal configuration form.
     */
    public function index(): string
    {
        $data                   = [];
        $data['printer_config'] = $this->_load_printer_config();

        return view('config/printer_config', $data);
    }

    /**
     * Save printer and terminal configuration to ospos_app_config.
     *
     * @throws ReflectionException
     */
    public function save()
    {
        $printerConnection    = $this->request->getPost('printer_connection');
        $printerDeviceName    = $this->request->getPost('printer_device_name');
        $printerBluetoothName = $this->request->getPost('printer_bluetooth_name');
        $printerUsbVendorId   = $this->request->getPost('printer_usb_vendor_id');
        $printerNetworkIp     = $this->request->getPost('printer_network_ip');
        $paperWidth           = $this->request->getPost('paper_width');
        $autoCut              = $this->request->getPost('auto_cut_receipt') !== null;
        $printCustomerCopy    = $this->request->getPost('print_customer_copy') !== null;

        $terminalEnabled     = $this->request->getPost('terminal_enabled') !== null;
        $terminalType        = $this->request->getPost('terminal_type');
        $autoApprovePayments = $this->request->getPost('auto_approve_payments') !== null;

        $showLogo        = $this->request->getPost('receipt_show_logo') !== null;
        $customFooter    = $this->request->getPost('receipt_custom_footer');
        $showItemCodes   = $this->request->getPost('receipt_show_item_codes') !== null;
        $showCashierName = $this->request->getPost('receipt_show_cashier_name') !== null;

        $batch_save_data = [
            'printer_connection'        => $printerConnection,
            'printer_device_name'       => $printerDeviceName,
            'printer_bluetooth_name'    => $printerBluetoothName,
            'printer_usb_vendor_id'     => $printerUsbVendorId,
            'printer_network_ip'        => $printerNetworkIp,
            'paper_width'               => $paperWidth,
            'auto_cut_receipt'          => $autoCut,
            'print_customer_copy'       => $printCustomerCopy,
            'terminal_enabled'          => $terminalEnabled,
            'terminal_type'             => $terminalType,
            'auto_approve_payments'     => $autoApprovePayments,
            'receipt_show_logo'         => $showLogo,
            'receipt_custom_footer'     => $customFooter,
            'receipt_show_item_codes'   => $showItemCodes,
            'receipt_show_cashier_name' => $showCashierName,
        ];

        $printerConfigJson = json_encode([
            'printer' => [
                'connection'          => $printerConnection,
                'device_name'         => $printerDeviceName,
                'bluetooth_name'      => $printerBluetoothName,
                'usb_vendor_id'       => $printerUsbVendorId,
                'network_ip'          => $printerNetworkIp,
                'paper_width'         => $paperWidth,
                'auto_cut'            => $autoCut,
                'print_customer_copy' => $printCustomerCopy,
            ],
            'terminal' => [
                'enabled'      => $terminalEnabled,
                'type'         => $terminalType,
                'auto_approve' => $autoApprovePayments,
            ],
            'receipt' => [
                'show_logo'         => $showLogo,
                'custom_footer'     => $customFooter,
                'show_item_codes'   => $showItemCodes,
                'show_cashier_name' => $showCashierName,
            ],
        ]);

        $batch_save_data['printer_config'] = $printerConfigJson;

        $success = $this->appconfig->batch_save($batch_save_data);

        return $this->response->setJSON([
            'success' => $success,
            'message' => lang('Config.saved_' . ($success ? '' : 'un') . 'successfully'),
        ]);
    }

    /**
     * Load the printer configuration from the database with defaults.
     */
    private function _load_printer_config(): array
    {
        $defaults = [
            'printer_connection'        => 'usb',
            'printer_device_name'       => '',
            'printer_bluetooth_name'    => '',
            'printer_usb_vendor_id'     => '',
            'printer_network_ip'        => '',
            'paper_width'               => '80mm',
            'auto_cut_receipt'          => 0,
            'print_customer_copy'       => 0,
            'terminal_enabled'          => 0,
            'terminal_type'             => 'manual',
            'auto_approve_payments'     => 0,
            'receipt_show_logo'         => 1,
            'receipt_custom_footer'     => '',
            'receipt_show_item_codes'   => 0,
            'receipt_show_cashier_name' => 1,
        ];

        $config = [];

        foreach ($defaults as $key => $default) {
            $value = $this->appconfig->get_value($key);
            if ($value === '' && $default === 0) {
                $config[$key] = false;
            } elseif ($value === '' && is_bool($default)) {
                $config[$key] = false;
            } else {
                $config[$key] = $value !== '' ? $value : $default;
            }
        }

        return $config;
    }
}
