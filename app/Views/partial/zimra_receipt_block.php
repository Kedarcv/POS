<?php
/**
 * Fiscal / Verification receipt block — styled after TM Pick n Pay Zimbabwe fiscal receipts.
 * Shows a centered QR code, fiscal verification code, and authenticity check details.
 *
 * @var array|null $zimra
 * @var string|null $sale_id
 * @var int|null $sale_id_num
 * @var string|null $transaction_time
 * @var float|null $total
 * @var string|null $employee
 */

use App\Libraries\Zimra_lib;

$zimra_lib = new Zimra_lib();
$isFiscalized = !empty($zimra['success']);

$saleNumeric = $sale_id_num ?? (isset($sale_id) ? preg_replace('/[^0-9]/', '', (string)$sale_id) : '');

if ($isFiscalized) {
    $qrUrl = $zimra['qr_url'] ?? '';
    $verificationCode = $zimra['verification_code'] ?? '';
    $blockTitle = 'FISCAL RECEIPT (ZIMRA FDMS)';
    $statusNotice = 'Fiscal receipt submitted &amp; verified';
} else {
    // Generate receipt verification code & verification link
    $seed = ($sale_id ?? 'SALE') . '|' . ($total ?? '0') . '|' . ($transaction_time ?? '');
    $verificationCode = Zimra_lib::buildReceiptVerificationCode($seed);
    $qrUrl = site_url('sales/receipt/' . $saleNumeric);
    $blockTitle = 'RECEIPT VERIFICATION';
    $statusNotice = 'Scan to verify receipt authenticity';
}

$qrDataUri = '';
if (!empty($qrUrl)) {
    $qrDataUri = $zimra_lib->renderQrDataUri($qrUrl);
}
?>

<div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
<div id="receipt_qr_block" style="text-align: center; padding: 2px 0;">
    <?php if (!empty($qrDataUri)): ?>
        <div style="margin: 3px auto 5px; display: inline-block; background: #fff; padding: 4px; border: 1px solid #000;">
            <img src="<?= $qrDataUri ?>" alt="" style="width: 115px; height: 115px; display: block; margin: 0 auto;" onerror="this.style.display='none'">
        </div>
    <?php endif; ?>

    <div style="font-weight: bold; font-size: 85%; letter-spacing: 0.5px; text-transform: uppercase;">
        <?= $blockTitle ?>
    </div>

    <div style="font-size: 80%; margin: 2px 0;">
        Verification Code:<br>
        <span style="font-family: 'Courier New', Courier, monospace; font-weight: bold; font-size: 110%; letter-spacing: 1px;">
            <?= esc($verificationCode) ?>
        </span>
    </div>

    <?php if ($isFiscalized && !empty($zimra['global_no'])): ?>
        <div style="font-size: 75%; color: #333;">
            Fiscal Global No: <?= esc($zimra['global_no']) ?>
        </div>
    <?php endif; ?>

    <div style="font-size: 75%; color: #555; margin-top: 2px;">
        <?= $statusNotice ?>
    </div>

    <div style="font-weight: bold; font-size: 85%; margin-top: 4px;">
        Thank you for shopping with us!
    </div>
</div>
