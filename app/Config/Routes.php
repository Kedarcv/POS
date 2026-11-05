<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setDefaultController('Login');

$routes->get('/', 'Login::index');
$routes->get('login', 'Login::index');
$routes->post('login', 'Login::index');
$routes->post('migrate', 'Login::migrate');

$routes->get('dashboard', 'Dashboard::index');

// Secure_Controller redirects denied users to no_access/<module_id>/<submodule_id>
// (no literal "index" segment, and the method is getIndex — these must match exactly).
$routes->get('no_access/(:segment)', 'No_access::getIndex/$1');
$routes->get('no_access/(:segment)/(:segment)', 'No_access::getIndex/$1/$2');

$routes->add('reports/summary_(:any)/(:any)/(:any)', 'Reports::Summary_$1/$2/$3/$4');
$routes->add('reports/summary_expenses_categories', 'Reports::date_input_only');
$routes->add('reports/summary_payments', 'Reports::date_input_only');
$routes->add('reports/summary_discounts', 'Reports::summary_discounts_input');
$routes->add('reports/summary_(:any)', 'Reports::date_input');

$routes->add('reports/graphical_(:any)/(:any)/(:any)', 'Reports::Graphical_$1/$2/$3/$4');
$routes->add('reports/graphical_summary_expenses_categories', 'Reports::date_input_only');
$routes->add('reports/graphical_summary_discounts', 'Reports::summary_discounts_input');
$routes->add('reports/graphical_(:any)', 'Reports::date_input');

$routes->add('reports/inventory_(:any)/(:any)', 'Reports::Inventory_$1/$2');
$routes->add('reports/inventory_low', 'Reports::inventory_low');
$routes->add('reports/inventory_summary', 'Reports::inventory_summary_input');
$routes->add('reports/inventory_summary/(:any)/(:any)/(:any)', 'Reports::inventory_summary/$1/$2/$3');

$routes->add('reports/detailed_(:any)/(:any)/(:any)/(:any)', 'Reports::Detailed_$1/$2/$3/$4');
$routes->add('reports/detailed_sales', 'Reports::date_input_sales');
$routes->add('reports/detailed_receivings', 'Reports::date_input_recv');

$routes->add('reports/specific_(:any)/(:any)/(:any)/(:any)', 'Reports::Specific_$1/$2/$3/$4');
$routes->add('reports/specific_customers', 'Reports::specific_customer_input');
$routes->add('reports/specific_employees', 'Reports::specific_employee_input');
$routes->add('reports/specific_discounts', 'Reports::specific_discount_input');
$routes->add('reports/specific_suppliers', 'Reports::specific_supplier_input');

// ═══════════════════════════════════════════════
// PRINT SHOP MODULE — Full workflow
// ═══════════════════════════════════════════════

// Dashboard & Jobs
$routes->get('printjobs', 'Printjobs::index');
$routes->get('printjobs/myjobs', 'Printjobs::myJobs');
$routes->get('printjobs/new', 'Printjobs::new');
$routes->get('printjobs/view/(:num)', 'Printjobs::view/$1');
$routes->get('printjobs/edit/(:num)', 'Printjobs::edit/$1');
$routes->post('printjobs/save', 'Printjobs::save');
$routes->post('printjobs/update_status/(:num)', 'Printjobs::updateStatus/$1');
$routes->post('printjobs/assign/(:num)', 'Printjobs::assign/$1');
$routes->post('printjobs/add_material_usage/(:num)', 'Printjobs::addMaterialUsage/$1');
$routes->post('printjobs/delete/(:num)', 'Printjobs::delete/$1');
$routes->post('printjobs/upload_file/(:num)', 'Printjobs::postUploadFile/$1');
$routes->get('printjobs/download_file/(:num)', 'Printjobs::getDownloadFile/$1');
$routes->post('printjobs/delete_file/(:num)', 'Printjobs::postDeleteFile/$1');
$routes->post('printjobs/estimate', 'Printjobs::estimate');
$routes->get('printjobs/sync', 'Printjobs::sync');

// Process types (DTF / UV / Xerox / Eco-solvent) + per-channel ink rates
$routes->get('printjobs/processes', 'Printjobs::processes');
$routes->post('printjobs/save_process', 'Printjobs::saveProcess');
$routes->post('printjobs/delete_process/(:num)', 'Printjobs::deleteProcess/$1');

// Print shop economics defaults (overhead, spoilage, target margin)
$routes->get('printjobs/settings', 'Printjobs::settings');
$routes->post('printjobs/save_settings', 'Printjobs::saveSettings');

// Materials Inventory
$routes->get('printjobs/materials', 'Printjobs::materials');
$routes->post('printjobs/save_material', 'Printjobs::saveMaterial');
$routes->post('printjobs/delete_material/(:num)', 'Printjobs::deleteMaterial/$1');
$routes->post('printjobs/receive_material/(:num)', 'Printjobs::receiveMaterial/$1');

// Pricing
$routes->get('printjobs/pricing', 'Printjobs::pricing');
$routes->post('printjobs/save_pricing', 'Printjobs::savePricing');
$routes->post('printjobs/delete_pricing/(:num)', 'Printjobs::deletePricing/$1');

// ═══════════════════════════════════════════════
// PRINT MACHINES MANAGEMENT
// ═══════════════════════════════════════════════
$routes->get('machines', 'Machines::index');
$routes->get('machines/new', 'Machines::new');
$routes->get('machines/edit/(:num)', 'Machines::edit/$1');
$routes->post('machines/save', 'Machines::save');
$routes->get('machines/delete/(:num)', 'Machines::delete/$1');
$routes->get('machines/json', 'Machines::json');

// ═══════════════════════════════════════════════
// ROLES (role-based access control)
// ═══════════════════════════════════════════════
$routes->get('roles', 'Roles::getIndex');
$routes->get('roles/new', 'Roles::getView');
$routes->get('roles/edit/(:num)', 'Roles::getView/$1');
$routes->post('roles/save', 'Roles::postSave');
$routes->post('roles/save/(:num)', 'Roles::postSave/$1');
$routes->post('roles/delete/(:num)', 'Roles::postDelete/$1');
$routes->get('roles/permissions/(:num)', 'Roles::getPermissions/$1');

// ═══════════════════════════════════════════════
// EMPLOYEE LEAVE TRACKING
// ═══════════════════════════════════════════════
$routes->get('leave', 'Leave::getIndex');
$routes->post('leave/save', 'Leave::postSave');
$routes->post('leave/save/(:num)', 'Leave::postSave/$1');
$routes->post('leave/apply', 'Leave::postApply');
$routes->post('leave/approve/(:num)', 'Leave::postApprove/$1');
$routes->post('leave/reject/(:num)', 'Leave::postReject/$1');
$routes->post('leave/delete/(:num)', 'Leave::postDelete/$1');

// ═══════════════════════════════════════════════
// ZIMRA FISCALISATION
// ═══════════════════════════════════════════════
$routes->get('zimra', 'Zimra::getIndex');
$routes->post('zimra/save_settings', 'Zimra::postSaveSettings');
$routes->post('zimra/save_tax_map', 'Zimra::postSaveTaxMap');
$routes->post('zimra/register_device', 'Zimra::postRegisterDevice');
$routes->post('zimra/open_day', 'Zimra::postOpenDay');
$routes->post('zimra/close_day', 'Zimra::postCloseDay');
$routes->post('zimra/retry_pending', 'Zimra::postRetryPending');
$routes->get('zimra/fetch_config', 'Zimra::getFetchConfig');

// ═══════════════════════════════════════════════
// LAYBY / INSTALLMENT MODULE
// ═══════════════════════════════════════════════
$routes->get('laybys', 'Laybys::index');
$routes->get('laybys/new', 'Laybys::new');
$routes->get('laybys/view/(:num)', 'Laybys::view/$1');
$routes->get('laybys/edit/(:num)', 'Laybys::edit/$1');
$routes->post('laybys/save', 'Laybys::save');
$routes->post('laybys/pay/(:num)', 'Laybys::pay/$1');
$routes->post('laybys/cancel/(:num)', 'Laybys::cancel/$1');
$routes->post('laybys/mark_signed/(:num)', 'Laybys::markSigned/$1');

// ═══════════════════════════════════════════════
// WEBHOOK HUB — connects OSPOS to external systems
// ═══════════════════════════════════════════════
// WhatsApp Cloud API
$routes->get('webhook/whatsapp', 'Webhook::whatsappVerify');
$routes->post('webhook/whatsapp', 'Webhook::whatsappCallback');

// Background print-job sync trigger (local-only, called by the Electron main process)
$routes->get('webhook/beamlink/pull-jobs', 'Webhook::pullPrintJobs');

// Payment gateways
$routes->post('webhook/pesepay', 'Webhook::pesepayCallback');
$routes->post('webhook/ecocash', 'Webhook::ecocashCallback');
$routes->post('webhook/paynow', 'Webhook::paynowCallback');
$routes->post('sales/initiate_ecocash', 'Sales::postInitiateEcocash');
$routes->get('sales/check_ecocash/(:any)/(:any)', 'Sales::getCheckEcocashStatus/$1/$2');

// ═══════════════════════════════════════════════
// SALES / POS REGISTER
// ═══════════════════════════════════════════════
// NOTE: autoRoute is on, but CI4 disables auto-routing for a whole
// controller once ANY explicit route references it (see the ecocash routes
// above) — so every action Sales needs must be listed here explicitly, or
// it 404s even though the method exists.
$routes->get('sales', 'Sales::getIndex');
$routes->get('sales/manage', 'Sales::getManage');
$routes->get('sales/row/(:num)', 'Sales::getRow/$1');
$routes->get('sales/search', 'Sales::getSearch');
$routes->get('sales/itemSearch', 'Sales::getItemSearch');
$routes->post('sales/selectCustomer', 'Sales::postSelectCustomer');
$routes->post('sales/changeMode', 'Sales::postChangeMode');
$routes->post('sales/setComment', 'Sales::postSetComment');
$routes->post('sales/setInvoiceNumber', 'Sales::postSetInvoiceNumber');
$routes->post('sales/setPaymentType', 'Sales::postSetPaymentType');
$routes->post('sales/setPrintAfterSale', 'Sales::postSetPrintAfterSale');
$routes->post('sales/setPriceWorkOrders', 'Sales::postSetPriceWorkOrders');
$routes->post('sales/setEmailReceipt', 'Sales::postSetEmailReceipt');
$routes->post('sales/addPayment', 'Sales::postAddPayment');
$routes->post('sales/deletePayment/(:any)', 'Sales::getDeletePayment/$1');
$routes->post('sales/add', 'Sales::postAdd');
$routes->post('sales/editItem/(:any)', 'Sales::postEditItem/$1');
$routes->post('sales/deleteItem/(:any)', 'Sales::getDeleteItem/$1');
$routes->post('sales/removeCustomer', 'Sales::getRemoveCustomer');
$routes->post('sales/complete', 'Sales::postComplete');
$routes->get('sales/sendPdf/(:num)', 'Sales::getSendPdf/$1');
$routes->get('sales/sendPdf/(:num)/(:any)', 'Sales::getSendPdf/$1/$2');
$routes->get('sales/sendReceipt/(:num)', 'Sales::getSendReceipt/$1');
$routes->get('sales/receipt/(:num)', 'Sales::getReceipt/$1');
$routes->get('sales/reprint_last', 'Sales::getReprintLast');
$routes->get('sales/invoice/(:num)', 'Sales::getInvoice/$1');
$routes->get('sales/edit/(:num)', 'Sales::getEdit/$1');
$routes->post('sales/save', 'Sales::postSave');
$routes->post('sales/save/(:num)', 'Sales::postSave/$1');
$routes->post('sales/delete', 'Sales::postDelete');
$routes->post('sales/cancel', 'Sales::postCancel');
$routes->get('sales/discardsuspendedsale', 'Sales::getDiscardSuspendedSale');
$routes->get('sales/discard_suspended_sale', 'Sales::getDiscardSuspendedSale');
$routes->post('sales/suspend', 'Sales::postSuspend');
$routes->get('sales/suspended', 'Sales::getSuspended');
$routes->post('sales/unsuspend', 'Sales::postUnsuspend');
$routes->get('sales/salesKeyboardHelp', 'Sales::getSalesKeyboardHelp');
$routes->post('sales/checkInvoiceNumber', 'Sales::postCheckInvoiceNumber');
$routes->post('sales/changeItemNumber', 'Sales::postChangeItemNumber');
$routes->post('sales/changeItemName', 'Sales::postChangeItemName');
$routes->post('sales/changeItemDescription', 'Sales::postChangeItemDescription');

// BeamLink ERP (bidirectional sync)
$routes->post('webhook/beamlink', 'Webhook::beamlinkCallback');

// Fusertech clone on Vercel
$routes->post('webhook/fusertech', 'Webhook::fusertechCallback');

// ═══════════════════════════════════════════════
// NOTIFICATIONS (push + SSE)
// ═══════════════════════════════════════════════
$routes->get('notifications', 'Notifications::index');
$routes->get('notifications/unread_count', 'Notifications::unread_count');
$routes->get('notifications/stream', 'Notifications::stream');
$routes->post('notifications/read/(:num)', 'Notifications::read/$1');
$routes->post('notifications/read_all', 'Notifications::read_all');

// ═══════════════════════════════════════════════
// PRINTER & TERMINAL CONFIGURATION
// ═══════════════════════════════════════════════
$routes->get('config/printer', 'Printer_config::index');
$routes->post('config/printer/save', 'Printer_config::save');
