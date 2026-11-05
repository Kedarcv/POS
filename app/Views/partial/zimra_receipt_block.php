<?php
/**
 * Fiscal receipt block for ZIMRA (FDMS) — shown under the barcode on
 * receipts/invoices once a sale has been successfully fiscalized.
 *
 * @var array|null $zimra
 */
if (!empty($zimra['success'])):
    $zimra_lib = new \App\Libraries\Zimra_lib();
    ?>
    <div style="border-top: 1px dashed #000; margin: 8px 0;"></div>
    <div id="zimra_fiscal_block" style="text-align: center;">
        <img src="<?= $zimra_lib->renderQrDataUri($zimra['qr_url']) ?>" alt="ZIMRA QR Code" style="width: 120px; height: 120px;"><br>
        <span style="font-size: 85%;">Fiscal Verification Code</span><br>
        <span style="font-weight: bold; font-size: 90%;"><?= esc($zimra['verification_code']) ?></span>
    </div>
<?php elseif (isset($zimra) && empty($zimra['skipped'])): ?>
    <div style="text-align: center; font-size: 75%; color: #900;">
        Not yet fiscalized with ZIMRA — will retry automatically.
    </div>
<?php endif; ?>
