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

$routes->add('no_access/index/(:segment)', 'No_access::index/$1');
$routes->add('no_access/index/(:segment)/(:segment)', 'No_access::index/$1/$2');

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
$routes->get('printjobs/new', 'Printjobs::new');
$routes->get('printjobs/view/(:num)', 'Printjobs::view/$1');
$routes->get('printjobs/edit/(:num)', 'Printjobs::edit/$1');
$routes->post('printjobs/save', 'Printjobs::save');
$routes->post('printjobs/update_status/(:num)', 'Printjobs::updateStatus/$1');
$routes->post('printjobs/assign/(:num)', 'Printjobs::assign/$1');
$routes->post('printjobs/add_material_usage/(:num)', 'Printjobs::addMaterialUsage/$1');
$routes->post('printjobs/delete/(:num)', 'Printjobs::delete/$1');
$routes->get('printjobs/sync', 'Printjobs::sync');

// Materials Inventory
$routes->get('printjobs/materials', 'Printjobs::materials');
$routes->post('printjobs/save_material', 'Printjobs::saveMaterial');
$routes->post('printjobs/delete_material/(:num)', 'Printjobs::deleteMaterial/$1');

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

// Payment gateways
$routes->post('webhook/pesepay', 'Webhook::pesepayCallback');
$routes->post('webhook/ecocash', 'Webhook::ecocashCallback');
$routes->post('webhook/paynow', 'Webhook::paynowCallback');
$routes->post('sales/initiate_ecocash', 'Sales::postInitiateEcocash');
$routes->get('sales/check_ecocash/(:any)/(:any)', 'Sales::getCheckEcocashStatus/$1/$2');

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
