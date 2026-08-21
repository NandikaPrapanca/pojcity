<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

/*
 * API v1 Routes
 */
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Public auth routes
    $routes->post('auth/login', 'AuthController::login');

    // Protected auth routes
    $routes->group('', ['filter' => 'auth'], function ($routes) {
        $routes->get('auth/me', 'AuthController::me');
        $routes->post('auth/logout', 'AuthController::logout');

        // ── Dashboard ─────────────────────────────────────────────────────────
        $routes->get('dashboard/summary', 'DashboardController::summary');

        // ── Companies ────────────────────────────────────────────────────────
        $routes->get('companies', 'CompanyController::index');
        $routes->post('companies', 'CompanyController::create');
        $routes->get('companies/(:num)', 'CompanyController::show/$1');
        $routes->put('companies/(:num)', 'CompanyController::update/$1');
        $routes->delete('companies/(:num)', 'CompanyController::delete/$1');

        // ── Customers ────────────────────────────────────────────────────────
        $routes->get('customers', 'CustomerController::index');
        $routes->post('customers', 'CustomerController::create');
        $routes->get('customers/(:num)', 'CustomerController::show/$1');
        $routes->put('customers/(:num)', 'CustomerController::update/$1');
        $routes->delete('customers/(:num)', 'CustomerController::delete/$1');

        // ── PICs (nested under customers) ────────────────────────────────────
        $routes->get('customers/(:num)/pics', 'PicController::index/$1');
        $routes->post('customers/(:num)/pics', 'PicController::create/$1');
        $routes->put('customers/(:num)/pics/(:num)', 'PicController::update/$1/$2');
        $routes->delete('customers/(:num)/pics/(:num)', 'PicController::delete/$1/$2');

        // ── Projects ─────────────────────────────────────────────────────────
        $routes->get('projects', 'ProjectController::index');
        $routes->post('projects', 'ProjectController::create');
        $routes->get('projects/(:num)', 'ProjectController::show/$1');
        $routes->put('projects/(:num)', 'ProjectController::update/$1');
        $routes->delete('projects/(:num)', 'ProjectController::delete/$1');
        $routes->get('projects/(:num)/clusters', 'ProjectController::clusters/$1');

        // ── Clusters ─────────────────────────────────────────────────────────
        $routes->get('clusters', 'ClusterController::index');
        $routes->post('clusters', 'ClusterController::create');
        $routes->get('clusters/(:num)', 'ClusterController::show/$1');
        $routes->put('clusters/(:num)', 'ClusterController::update/$1');
        $routes->delete('clusters/(:num)', 'ClusterController::delete/$1');
        $routes->get('clusters/(:num)/blocks', 'ClusterController::blocks/$1');

        // ── Blocks ───────────────────────────────────────────────────────────
        $routes->get('blocks', 'BlockController::index');
        $routes->post('blocks', 'BlockController::create');
        $routes->get('blocks/(:num)', 'BlockController::show/$1');
        $routes->put('blocks/(:num)', 'BlockController::update/$1');
        $routes->delete('blocks/(:num)', 'BlockController::delete/$1');
        $routes->get('blocks/(:num)/lots', 'BlockController::lots/$1');

        // ── Lots ─────────────────────────────────────────────────────────────
        $routes->get('lots', 'LotController::index');
        $routes->post('lots', 'LotController::create');
        $routes->get('lots/(:num)', 'LotController::show/$1');
        $routes->put('lots/(:num)', 'LotController::update/$1');
        $routes->delete('lots/(:num)', 'LotController::delete/$1');

        // ── IPL Rates ────────────────────────────────────────────────────────
        $routes->get('ipl-rates', 'IplRateController::index');
        $routes->post('ipl-rates', 'IplRateController::create');
        $routes->get('ipl-rates/(:num)', 'IplRateController::show/$1');
        $routes->put('ipl-rates/(:num)', 'IplRateController::update/$1');
        $routes->delete('ipl-rates/(:num)', 'IplRateController::delete/$1');

        // ── Water Rate Groups ────────────────────────────────────────────────
        $routes->get('water-rate-groups', 'WaterRateGroupController::index');
        $routes->post('water-rate-groups', 'WaterRateGroupController::create');
        $routes->get('water-rate-groups/(:num)', 'WaterRateGroupController::show/$1');
        $routes->put('water-rate-groups/(:num)', 'WaterRateGroupController::update/$1');
        $routes->delete('water-rate-groups/(:num)', 'WaterRateGroupController::delete/$1');
        $routes->get('water-rate-groups/(:num)/tiers', 'WaterRateGroupController::tiers/$1');
        $routes->post('water-rate-groups/(:num)/tiers', 'WaterRateTierController::createForGroup/$1');

        // ── Water Rate Tiers ─────────────────────────────────────────────────
        $routes->put('water-rate-tiers/(:num)', 'WaterRateTierController::update/$1');
        $routes->delete('water-rate-tiers/(:num)', 'WaterRateTierController::delete/$1');

        // ── Tax Configurations ───────────────────────────────────────────────
        $routes->get('tax-configurations', 'TaxConfigurationController::index');
        $routes->post('tax-configurations', 'TaxConfigurationController::create');
        $routes->get('tax-configurations/(:num)', 'TaxConfigurationController::show/$1');
        $routes->put('tax-configurations/(:num)', 'TaxConfigurationController::update/$1');
        $routes->put('tax-configurations/(:num)/activate', 'TaxConfigurationController::activate/$1');

        // ── Signatures ───────────────────────────────────────────────────────
        $routes->get('signatures', 'SignatureController::index');
        $routes->post('signatures', 'SignatureController::create');
        $routes->get('signatures/(:num)', 'SignatureController::show/$1');
        $routes->put('signatures/(:num)', 'SignatureController::update/$1');
        $routes->post('signatures/(:num)', 'SignatureController::update/$1');
        $routes->delete('signatures/(:num)', 'SignatureController::delete/$1');

        // -- Ownerships --
        $routes->get('ownerships', 'OwnershipController::index');
        $routes->post('ownerships', 'OwnershipController::create');
        $routes->get('ownerships/(:num)', 'OwnershipController::show/$1');
        $routes->put('ownerships/(:num)', 'OwnershipController::update/$1');
        $routes->delete('ownerships/(:num)', 'OwnershipController::delete/$1');

        // -- Meter Readings --
        $routes->get('meter-readings', 'MeterReadingController::index');
        $routes->post('meter-readings', 'MeterReadingController::create');
        $routes->get('meter-readings/(:num)', 'MeterReadingController::show/$1');
        $routes->put('meter-readings/(:num)', 'MeterReadingController::update/$1');
        $routes->post('meter-readings/(:num)', 'MeterReadingController::update/$1');
        $routes->delete('meter-readings/(:num)', 'MeterReadingController::delete/$1');
        $routes->get('ownerships/(:num)/meter-readings', 'MeterReadingController::forOwnership/$1');
        $routes->get('ownerships/(:num)/meter-readings/latest', 'MeterReadingController::latest/$1');

        // -- Billing Items (Phase 5A) --
        $routes->get('billing-items', 'BillingController::index');
        $routes->post('billing-items', 'BillingController::create');
        $routes->get('billing-items/(:num)', 'BillingController::show/$1');
        $routes->put('billing-items/(:num)', 'BillingController::update/$1');
        $routes->delete('billing-items/(:num)', 'BillingController::delete/$1');

        // -- Billing Engine (Phase 5B / 5C) --
        $routes->post('billing/generate-ipl',   'BillingController::generateIpl');
        $routes->post('billing/generate-water',  'BillingController::generateWater');

        // ── Invoices (Phase 6 / 7 / 9) ────────────────────────────────────────────
        $routes->get('invoices',                         'InvoiceController::index');
        $routes->get('invoices/(:num)',                  'InvoiceController::show/$1');
        $routes->get('invoices/(:num)/pdf',              'InvoiceController::downloadPdf/$1');
        $routes->get('invoices/(:num)/receipt',          'InvoiceController::downloadReceipt/$1');
        $routes->post('invoices/(:num)/send-whatsapp',  'InvoiceController::sendWhatsApp/$1');
        $routes->post('invoices/preview-tax',            'InvoiceController::previewTax');
        $routes->post('invoices/generate',               'InvoiceController::generate');

        // ── Payments (Phase 9) ────────────────────────────────────────────────
        $routes->post('payments',                        'PaymentController::create');
        $routes->get('payments/invoice/(:num)',          'PaymentController::getForInvoice/$1');

        // ── Reports & Exports (Phase 10) ──────────────────────────────────────
        $routes->get('reports/export-invoices',          'ReportController::exportInvoices');
    });

    // Public asset serving for uploaded meter photos
    $routes->get('meter-readings/photo/(:segment)', 'MeterReadingController::servePhoto/$1');
});

