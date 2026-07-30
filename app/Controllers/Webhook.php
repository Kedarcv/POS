<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Externalpayment;
use App\Models\Printjob;
use App\Models\Sale;
use App\Models\Webhooklog;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;
use Config\OSPOS;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Webhook Router — BeamLink ERP Webhook Hub.
 *
 * This controller acts as the central incoming webhook handler.
 * It receives webhooks from:
 *   - WhatsApp Cloud API        /webhook/whatsapp
 *   - Pesepay payments          /webhook/pesepay
 *   - EcoCash payments          /webhook/ecocash
 *   - Paynow payments           /webhook/paynow
 *   - BeamLink ERP itself       /webhook/beamlink
 *   - Fusertech clone (Vercel)  /webhook/fusertech
 *
 * Each endpoint normalises the payload, logs it, triggers the
 * appropriate action, and forwards data to other connected systems.
 */
class Webhook extends BaseController
{
    protected Webhooklog $webhookLog;
    protected Externalpayment $externalPayment;
    protected Printjob $printJob;
    protected Sale $sale;
    protected OSPOS $ospos;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger): void
    {
        parent::initController($request, $response, $logger);
        $this->webhookLog      = model(Webhooklog::class);
        $this->externalPayment = model(Externalpayment::class);
        $this->printJob        = model(Printjob::class);
        $this->sale            = model(Sale::class);
        $this->ospos           = config('OSPOS');
    }

    /**
     * ═══════════════════════════════════════════════
     * 1. WhatsApp Cloud API Webhook
     * ═══════════════════════════════════════════════
     */

    /**
     * GET /webhook/whatsapp
     * Meta verification handshake.
     */
    public function whatsappVerify()
    {
        $mode      = $this->request->getGet('hub_mode') ?? '';
        $token     = $this->request->getGet('hub_verify_token') ?? '';
        $challenge = $this->request->getGet('hub_challenge') ?? '';

        $expectedToken = getenv('WHATSAPP_VERIFY_TOKEN') ?: 'ospos-webhook-2026';

        if ($mode === 'subscribe' && $token === $expectedToken && $challenge !== '') {
            return $this->response->setStatusCode(200)->setBody($challenge);
        }

        return $this->response->setStatusCode(403)->setBody('Verification failed');
    }

    /**
     * POST /webhook/whatsapp
     * Receive incoming WhatsApp messages.
     */
    public function whatsappCallback()
    {
        try {
            $body = $this->request->getJSON(true);

            if (($body['object'] ?? '') !== 'whatsapp_business_account') {
                return $this->response->setJSON(['status' => 'ignored']);
            }

            // Log the webhook
            $this->webhookLog->logInbound('whatsapp', 'message', json_encode($body, JSON_THROW_ON_ERROR));

            // Process entries
            $entries = $body['entry'] ?? [];

            foreach ($entries as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? '') !== 'messages') {
                        continue;
                    }

                    $messages = $change['value']['messages'] ?? [];

                    foreach ($messages as $msg) {
                        $this->processWhatsAppMessage($msg, $change['value']['contacts'] ?? []);
                    }
                }
            }

            return $this->response->setJSON(['status' => 'ok']);
        } catch (Throwable $e) {
            log_message('error', '[WhatsApp Webhook] ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error'])->setStatusCode(200); // Always 200 to Meta
        }
    }

    /**
     * Route a WhatsApp message to the appropriate handler.
     */
    private function processWhatsAppMessage(array $msg, array $contacts): void
    {
        $phone      = $msg['from'] ?? '';
        $senderName = $contacts[0]['profile']['name'] ?? '';
        $messageId  = $msg['id'] ?? '';

        // Deduplication — Meta sends duplicate webhooks
        if ($this->isDuplicateMessage($messageId)) {
            return;
        }
        $this->markMessageProcessed($messageId);

        $conversation = $this->getOrCreateConversation($phone);

        switch ($msg['type'] ?? '') {
            case 'text':
                $text = $msg['text']['body'] ?? '';
                $this->handleWhatsAppTextCommand($phone, $text, $conversation);
                break;

            case 'image':
                $mediaId = $msg['image']['id'] ?? '';
                $caption = $msg['image']['caption'] ?? '';
                $this->handleWhatsAppMedia($phone, $mediaId, 'image', $caption, $conversation);
                break;

            case 'interactive':
                $replyId = $msg['interactive']['button_reply']['id']
                    ?? $msg['interactive']['list_reply']['id']
                    ?? '';
                $this->handleWhatsAppInteractive($phone, $replyId, $conversation);
                break;

            case 'button':
                $payload = $msg['button']['payload'] ?? '';
                $this->handleWhatsAppInteractive($phone, $payload, $conversation);
                break;

            default:
                $this->sendWhatsAppMenu($phone);
                break;
        }
    }

    /**
     * Parse text commands: menu, shop, status, print, etc.
     */
    private function handleWhatsAppTextCommand(string $phone, string $text, array $conversation): void
    {
        $cmd = strtolower(trim($text));

        if (in_array($cmd, ['menu', 'hi', 'hello', 'help', 'start', '0'], true)) {
            $this->sendWhatsAppMenu($phone);
            $this->updateConversation($phone, 'menu');

            return;
        }

        if (in_array($cmd, ['shop', 'buy', 'catalog', 'products', '1'], true)) {
            $this->sendWhatsAppShopIntro($phone);
            $this->updateConversation($phone, 'shop');

            return;
        }

        if (in_array($cmd, ['print', 'printing', 'reprint', '2'], true)) {
            $this->sendWhatsAppPrintMenu($phone);
            $this->updateConversation($phone, 'print_job_request');

            return;
        }

        if (in_array($cmd, ['status', 'check', 'track', '3'], true)) {
            $this->sendWhatsAppStatusCheck($phone);
            $this->updateConversation($phone, 'status_check');

            return;
        }

        if (in_array($cmd, ['receipt', 'invoice', '4'], true) || str_starts_with($cmd, 'receipt')) {
            $this->sendWhatsAppReceiptMenu($phone);
            $this->updateConversation($phone, 'receipt');

            return;
        }

        if (str_starts_with($cmd, 'pay ')) {
            $this->handleWhatsAppPaymentRequest($phone, substr($cmd, 4));

            return;
        }

        if (str_starts_with($cmd, 'print ')) {
            $this->handleWhatsAppPrintRequest($phone, substr($cmd, 6));
            $this->updateConversation($phone, 'print_job_details');

            return;
        }

        // Fallback — echo help
        $this->sendWhatsAppUnknown($phone);
    }

    /**
     * Handle media (images) from WhatsApp — used for print job file proofs.
     */
    private function handleWhatsAppMedia(string $phone, string $mediaId, string $type, ?string $caption, array $conversation): void
    {
        if ($conversation['state'] ?? '' === 'print_job_details') {
            // Customer is sending a design file for a print job
            $this->sendWhatsAppText(
                $phone,
                'Thank you! Your design file has been received. ' .
                'Print job queued as: ' . $this->printJob->generateJobNumber() . "\n" .
                'We will confirm cost and delivery time shortly.',
            );
            $this->updateConversation($phone, 'menu');

            return;
        }

        $this->sendWhatsAppText(
            $phone,
            'Thanks for the image. What would you like to do next?',
            [[
                'type'  => 'reply',
                'reply' => ['id' => 'cmd_print', 'title' => 'Start Print Job'],
            ], [
                'type'  => 'reply',
                'reply' => ['id' => 'cmd_menu', 'title' => 'Main Menu'],
            ]],
        );
    }

    /**
     * Handle interactive button/list replies.
     */
    private function handleWhatsAppInteractive(string $phone, string $replyId, array $conversation): void
    {
        switch ($replyId) {
            case 'cmd_shop':
                $this->sendWhatsAppShopIntro($phone);
                $this->updateConversation($phone, 'shop');
                break;

            case 'cmd_print':
                $this->sendWhatsAppPrintMenu($phone);
                $this->updateConversation($phone, 'print_job_request');
                break;

            case 'cmd_status':
                $this->sendWhatsAppStatusCheck($phone);
                $this->updateConversation($phone, 'status_check');
                break;

            case 'cmd_receipt':
                $this->sendWhatsAppReceiptMenu($phone);
                $this->updateConversation($phone, 'receipt');
                break;

            case 'cmd_menu':
            default:
                $this->sendWhatsAppMenu($phone);
                $this->updateConversation($phone, 'menu');
                break;
        }
    }

    /**
     * ═══════════════════════════════════════════════
     * WhatsApp Message Builders (adapted from BeamLink)
     * ═══════════════════════════════════════════════
     */
    private function sendWhatsAppMenu(string $phone): void
    {
        $text = "🖨️ *BeamLink Print & Tech*\n\n"
            . "Hello! How can we help you today?\n\n"
            . "1️⃣ Shop products\n"
            . "2️⃣ Request printing\n"
            . "3️⃣ Check status\n"
            . "4️⃣ Get receipt/invoice\n\n"
            . 'Reply with the number or tap below 👇';

        $this->sendWhatsAppInteractiveButtons($phone, $text, [
            ['id' => 'cmd_shop', 'title' => '🛒 Shop'],
            ['id' => 'cmd_print', 'title' => '🖨️ Print'],
            ['id' => 'cmd_status', 'title' => '📦 Status'],
            ['id' => 'cmd_receipt', 'title' => '🧾 Receipt'],
        ]);
    }

    private function sendWhatsAppShopIntro(string $phone): void
    {
        $text = "🛒 *Shop with us*\n\n"
            . "Browse our catalog online:\n"
            . "https://connect.beamlink.co.zw\n\n"
            . "Or visit the store!\n\n"
            . 'Reply *menu* to go back.';

        $this->sendWhatsAppText($phone, $text);
    }

    private function sendWhatsAppPrintMenu(string $phone): void
    {
        $text = "🖨️ *Request a Print Job*\n\n"
            . "Send us details like:\n"
            . "`print Business Cards - A6, 500pcs, Full colour`\n\n"
            . "Or upload your design file here.\n\n"
            . "We print:\n"
            . "• Banners & Posters\n"
            . "• Business Cards\n"
            . "• Flyers & Brochures\n"
            . "• T-Shirts\n"
            . "• Stickers\n"
            . "• Booklets\n"
            . '• Any custom size';

        $this->sendWhatsAppText($phone, $text);
    }

    private function sendWhatsAppStatusCheck(string $phone): void
    {
        $text = "📦 *Track Your Order*\n\n"
            . "Reply with your order / job number.\n"
            . "Example: `status PRINT-2026-001`\n\n"
            . 'Or your receipt number from POS.';

        $this->sendWhatsAppText($phone, $text);
    }

    private function sendWhatsAppReceiptMenu(string $phone): void
    {
        $text = "🧾 *Get Receipt / Invoice*\n\n"
            . "Reply with your sale receipt number.\n"
            . "Example: `receipt 2026-0012345`\n\n"
            . 'We will send you a copy via WhatsApp.';

        $this->sendWhatsAppText($phone, $text);
    }

    private function sendWhatsAppUnknown(string $phone): void
    {
        $this->sendWhatsAppText(
            $phone,
            "Sorry, I didn't understand that.\n\n"
            . 'Reply *menu* for options.',
        );
    }

    /**
     * Handle a WhatsApp print request text.
     */
    private function handleWhatsAppPrintRequest(string $phone, string $details): void
    {
        $jobNumber = $this->printJob->generateJobNumber();
        $this->printJob->insert([
            'job_number'     => $jobNumber,
            'status'         => 'draft',
            'priority'       => 'normal',
            'source'         => 'whatsapp',
            'customer_notes' => $details,
        ]);

        $this->sendWhatsAppText(
            $phone,
            "✅ *Print Job Queued*\n\n"
            . "Job No: {$jobNumber}\n"
            . "Details: {$details}\n\n"
            . 'We will review your request and send a quote shortly. '
            . 'Reply with an image of your design file if you have one.',
        );
    }

    /**
     * Handle payment request via WhatsApp text.
     */
    private function handleWhatsAppPaymentRequest(string $phone, string $args): void
    {
        // Placeholder — in production this would initiate Pesepay mobile push
        $this->sendWhatsAppText(
            $phone,
            "💳 *Payment*\n\n"
            . "To complete payment, please visit:\n"
            . "https://connect.beamlink.co.zw/checkout\n\n"
            . "Or reply with your reference number if you've already paid.",
        );
    }

    /**
     * ═══════════════════════════════════════════════
     * WhatsApp Cloud API Send Helpers
     * ═══════════════════════════════════════════════
     */
    private function sendWhatsAppText(string $phone, string $text): void
    {
        $this->sendToWhatsAppApi($phone, [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $text],
        ]);
    }

    private function sendWhatsAppInteractiveButtons(string $phone, string $bodyText, array $buttons): void
    {
        $this->sendToWhatsAppApi($phone, [
            'type'        => 'interactive',
            'interactive' => [
                'type'   => 'button',
                'body'   => ['text' => $bodyText],
                'action' => [
                    'buttons' => array_slice($buttons, 0, 3),
                ],
            ],
        ]);
    }

    /**
     * Meta Graph API call.
     */
    private function sendToWhatsAppApi(string $phone, array $body): void
    {
        $token   = getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
        $phoneId = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';

        if ($token === '' || $phoneId === '') {
            log_message('error', '[WhatsApp] Not configured — message skipped');

            return;
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => "https://graph.facebook.com/v21.0/{$phoneId}/messages",
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode(['messaging_product' => 'whatsapp', 'to' => $phone, ...$body]),
                CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) {
            log_message('error', '[WhatsApp] Send failed: ' . $e->getMessage());
        }
    }

    /**
     * ═══════════════════════════════════════════════
     * Conversation State (WhatsApp)
     * ═══════════════════════════════════════════════
     */
    private function isDuplicateMessage(string $messageId): bool
    {
        $db = Database::connect();

        return $db->table('whatsapp_processed_messages')
            ->where('message_id', $messageId)
            ->countAllResults() > 0;
    }

    private function markMessageProcessed(string $messageId): void
    {
        $db = Database::connect();

        try {
            $db->table('whatsapp_processed_messages')
                ->insert(['message_id' => $messageId, 'processed_at' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
        }
    }

    private function getOrCreateConversation(string $phone): array
    {
        $db  = Database::connect();
        $row = $db->table('whatsapp_conversations')
            ->where('phone', $phone)
            ->get()->getRowArray();

        if ($row) {
            return $row;
        }

        $db->table('whatsapp_conversations')->insert([
            'phone' => $phone,
            'state' => 'menu',
        ]);

        return ['phone' => $phone, 'state' => 'menu', 'context' => null];
    }

    private function updateConversation(string $phone, string $state, ?array $context = null): void
    {
        $db   = Database::connect();
        $data = ['state' => $state, 'updated_at' => date('Y-m-d H:i:s')];
        if ($context !== null) {
            $data['context'] = json_encode($context);
        }
        $db->table('whatsapp_conversations')->where('phone', $phone)->update($data);
    }

    /**
     * ═══════════════════════════════════════════════
     * 2. Payment Webhook Handlers
     * ═══════════════════════════════════════════════
     */

    /**
     * POST /webhook/pesepay
     * Pesepay callback (update from payment gateway).
     */
    public function pesepayCallback()
    {
        $raw = $this->request->getBody();
        $this->webhookLog->logInbound('pesepay', 'payment', $raw);

        try {
            $data      = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $reference = $data['referenceNumber'] ?? '';

            $payment = $this->externalPayment->getByReference($reference);
            if ($payment === null) {
                log_message('error', '[Pesepay] Unknown reference: ' . $reference);

                return $this->response->setJSON(['status' => 'ok']);
            }

            if (($data['status'] ?? '') === 'SUCCESS') {
                $this->externalPayment->markCompleted(
                    $payment->id,
                    json_encode($data, JSON_THROW_ON_ERROR),
                );
            } else {
                $this->externalPayment->update($payment->id, [
                    'status'          => 'failed',
                    'gateway_payload' => json_encode($data),
                ]);
            }

            // Forward to BeamLink ERP
            $this->forwardToBeamLink($data, 'pesepay');

            return $this->response->setJSON(['status' => 'ok']);
        } catch (Throwable $e) {
            log_message('error', '[Pesepay Webhook] ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error'])->setStatusCode(200);
        }
    }

    /**
     * POST /webhook/ecocash
     * EcoCash callback.
     */
    public function ecocashCallback()
    {
        $raw = $this->request->getBody();
        $this->webhookLog->logInbound('ecocash', 'payment', $raw);

        // Same pattern as Pesepay
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $this->forwardToBeamLink($data, 'ecocash');

            return $this->response->setJSON(['status' => 'ok']);
        } catch (Throwable $e) {
            log_message('error', '[EcoCash Webhook] ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error'])->setStatusCode(200);
        }
    }

    /**
     * POST /webhook/paynow
     * Paynow callback.
     */
    public function paynowCallback()
    {
        $raw = $this->request->getBody();
        $this->webhookLog->logInbound('paynow', 'payment', $raw);

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $this->forwardToBeamLink($data, 'paynow');

            return $this->response->setJSON(['status' => 'ok']);
        } catch (Throwable $e) {
            log_message('error', '[Paynow Webhook] ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error'])->setStatusCode(200);
        }
    }

    /**
     * ═══════════════════════════════════════════════
     * 3. BeamLink ERP Webhook (bidirectional sync)
     * ═══════════════════════════════════════════════
     */

    /**
     * POST /webhook/beamlink
     * Receive events from BeamLink ERP (print job updates, stock changes, etc).
     */
    public function beamlinkCallback()
    {
        $raw = $this->request->getBody();
        $this->webhookLog->logInbound('beamlink-erp', 'sync', $raw);

        try {
            $data  = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $event = $data['event'] ?? '';

            switch ($event) {
                case 'print_job_created':
                    $this->handleBeamLinkPrintJobCreated($data);
                    break;

                case 'print_job_update':
                    $this->handleBeamLinkPrintJobUpdate($data);
                    break;

                case 'sale_sync':
                    // Sale created on BeamLink — reflect in OSPOS if needed
                    break;

                case 'stock_adjustment':
                    // BeamLink changed stock — sync to OSPOS
                    break;

                default:
                    log_message('info', '[BeamLink] Unknown event: ' . $event);
            }

            return $this->response->setJSON(['status' => 'ok']);
        } catch (Throwable $e) {
            log_message('error', '[BeamLink Webhook] ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error'])->setStatusCode(200);
        }
    }

    /**
     * POST /webhook/fusertech
     * Receive events from Fusertech clone on Vercel.
     */
    public function fusertechCallback()
    {
        $raw = $this->request->getBody();
        $this->webhookLog->logInbound('fusertech', 'sync', $raw);

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            // Fusertech events can trigger local print jobs or stock updates
            if (($data['type'] ?? '') === 'print_order') {
                $this->printJob->insert([
                    'job_number'     => $this->printJob->generateJobNumber(),
                    'job_type'       => $data['job_type'] ?? 'other',
                    'description'    => $data['description'] ?? '',
                    'dimensions'     => $data['dimensions'] ?? '',
                    'quantity'       => (int) ($data['quantity'] ?? 1),
                    'total'          => (float) ($data['total'] ?? 0),
                    'customer_notes' => $data['customer_notes'] ?? '',
                    'source'         => 'fusertech',
                    'external_ref'   => $data['order_id'] ?? '',
                    'status'         => 'draft',
                ]);
            }

            return $this->response->setJSON(['status' => 'ok']);
        } catch (Throwable $e) {
            log_message('error', '[Fusertech Webhook] ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error'])->setStatusCode(200);
        }
    }

    /**
     * ═══════════════════════════════════════════════
     * Helpers: Forward / Sync / Dispatch
     * ═══════════════════════════════════════════════
     */

    /**
     * Forward a payment/print event to BeamLink ERP.
     */
    private function forwardToBeamLink(array $payload, string $source): void
    {
        $endpoint = getenv('BEAMLINK_WEBHOOK_URL') ?: '';
        if ($endpoint === '') {
            return; // Not configured
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL        => $endpoint,
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'source'    => 'ospos',
                    'timestamp' => date('c'),
                    'data'      => $payload,
                ]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            $this->webhookLog->logOutbound('beamlink-erp', $source, json_encode($payload));
        } catch (Throwable $e) {
            log_message('error', '[Forward BeamLink] ' . $e->getMessage());
        }
    }

    /**
     * Handle a new print job created on BeamLink ERP (sync down).
     */
    private function handleBeamLinkPrintJobCreated(array $data): void
    {
        $jobData = $data['job'] ?? $data;

        // Check if job already exists by external_ref
        $externalRef = $jobData['external_ref'] ?? ($jobData['ticket_ref'] ?? '');
        if ($externalRef) {
            $existing = $this->printJob->where('external_ref', $externalRef)->first();
            if ($existing) {
                return;
            }
        }

        // Find or create customer by phone
        $customerName  = $jobData['customer_name'] ?? '';
        $customerPhone = $jobData['customer_phone'] ?? '';
        $customerId    = null;

        if ($customerPhone) {
            $db = db_connect();
            $builder = $db->table('people');
            $builder->where('phone_number', $customerPhone);
            $person = $builder->get()->getRow();
            if ($person) {
                $custBuilder = $db->table('customers');
                $custBuilder->where('person_id', $person->person_id);
                $cust = $custBuilder->get()->getRow();
                if ($cust) {
                    $customerId = $cust->person_id;
                }
            }

            if (! $customerId && $customerPhone) {
                // Create minimal person + customer record
                $personData = [
                    'first_name'   => $customerName ?: 'WhatsApp Client',
                    'last_name'    => '',
                    'phone_number' => $customerPhone,
                    'email'        => '',
                    'address_1'    => '',
                    'address_2'    => '',
                    'city'         => '',
                    'state'        => '',
                    'zip'          => '',
                    'country'      => '',
                    'comments'     => 'Auto-created from WhatsApp',
                ];
                $builder = $db->table('people');
                $builder->insert($personData);
                $personId = $db->insertID();

                if ($personId) {
                    $db->table('customers')->insert([
                        'person_id'      => $personId,
                        'company_name'   => '',
                        'account_number' => '',
                        'taxable'        => 1,
                        'discount'       => 0,
                        'points'         => 0,
                        'deleted'        => 0,
                    ]);
                    $customerId = $personId;
                }
            }
        }

        $jobNumber = $jobData['job_number'] ?? $this->printJob->generateJobNumber();

        $newJobId = $this->printJob->insert([
            'job_number'      => $jobNumber,
            'customer_id'     => $customerId,
            'client_phone'    => $customerPhone ?: null,
            'ticket_ref'      => $jobData['ticket_ref'] ?? '',
            'job_type'        => $jobData['job_type'] ?? 'other',
            'quantity'        => (int) ($jobData['quantity'] ?? 1),
            'width_mm'        => $jobData['width_mm'] ?? null,
            'height_mm'       => $jobData['height_mm'] ?? null,
            'material'        => $jobData['material'] ?? null,
            'machine'         => $jobData['machine'] ?? null,
            'design_file_url' => $jobData['design_file_url'] ?? null,
            'notes'           => $jobData['notes'] ?? ($jobData['description'] ?? ''),
            'whatsapp_images' => ! empty($jobData['whatsapp_images']) ? json_encode($jobData['whatsapp_images']) : null,
            'source'          => $jobData['source'] ?? 'erp',
            'external_ref'    => $externalRef,
            'status'          => 'pending',
        ]);

        log_message('info', '[BeamLink] New print job created: ' . $jobNumber . ' (ref: ' . $externalRef . ')');

        // Create notification for all employees
        $notification = model(\App\Models\Notification::class);
        $customerName = $jobData['customer_name'] ?? '';
        $notifTitle   = 'New Print Job: ' . $jobNumber;
        $notifMsg     = ucfirst($jobData['job_type'] ?? 'job') . ' — Qty: ' . ($jobData['quantity'] ?? 1);
        if ($customerName) {
            $notifMsg .= ' — ' . $customerName;
        }
        $notification->broadcastToAll($notifTitle, $notifMsg, 'job_new', '/printjobs/view/' . $newJobId);
    }

    /**
     * Handle a BeamLink print job update (sync down).
     */
    private function handleBeamLinkPrintJobUpdate(array $data): void
    {
        $ref = $data['external_ref'] ?? '';
        if (! $ref) {
            return;
        }

        $job = $this->printJob->where('external_ref', $ref)->first();
        if ($job) {
            $this->printJob->update($job->id, [
                'status'         => $data['status'] ?? $job->status,
                'total'          => $data['total'] ?? $job->total,
                'internal_notes' => $data['notes'] ?? $job->internal_notes,
                'assigned_to'    => $data['assigned_to'] ?? $job->assigned_to,
                'completed_at'   => $data['completed_at'] ?? null,
            ]);
        }
    }
}
