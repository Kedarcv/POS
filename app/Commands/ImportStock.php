<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import stock data from Excel files into OSPOS items table.
 *
 * Usage: php spark stock:import
 */
class ImportStock extends BaseCommand
{
    protected $group       = 'Stock';
    protected $name        = 'stock:import';
    protected $description = 'Import stock data from Excel files into OSPOS database';
    protected $usage       = 'stock:import [options]';
    protected $options     = [
        '--file' => 'Path to XLS file (default: stock-data/STOCK.XLS)',
        '--dry'  => 'Dry run — parse and show data without inserting',
    ];

    public function run(array $params): void
    {
        $filePath = $params['file'] ?? WRITEPATH . '../stock-data/STOCK.XLS';
        // A value-less flag like --dry is passed through with a null value,
        // so isset() (which treats null as "not set") always missed it.
        $dryRun   = array_key_exists('dry', $params);

        if (! file_exists($filePath)) {
            CLI::error("File not found: {$filePath}");

            return;
        }

        CLI::write("Reading: {$filePath}", 'cyan');

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet       = $spreadsheet->getActiveSheet();
            // formatData = FALSE: read the underlying cell values, not the text
            // the cell's number-format mask renders. A currency/grouping mask
            // ("$#,##0.00", "#,##0") otherwise reaches us as "1,234.56" and
            // (float) "1,234.56" === 1.0 — silently corrupting every price.
            $rows = $sheet->toArray(null, true, false, true);
        } catch (Exception $e) {
            CLI::error('Failed to read file: ' . $e->getMessage());

            return;
        }

        $db = Database::connect();

        // Ensure default stock location exists
        $loc = $db->table('stock_locations')->getWhere(['location_id' => 1])->getRow();
        if (! $loc) {
            $db->table('stock_locations')->insert(['location_name' => 'Main', 'deleted' => 0]);
        }

        $currentCategory = 'Uncategorized';
        $imported        = 0;
        $skipped         = 0;
        $categories      = [];
        $itemsData       = [];

        foreach ($rows as $rowNum => $row) {
            if ($rowNum <= 10) {
                continue;
            }

            $partNumber = trim((string) ($row['A'] ?? ''));
            $details    = trim((string) ($row['B'] ?? ''));
            $qtyRaw     = $row['E'] ?? 0;
            $costRaw    = $row['F'] ?? 0;

            if (empty($partNumber) && empty($details)) {
                continue;
            }

            // Detect category header
            $alphaPart = preg_replace('/[^A-Z]/', '', strtoupper($partNumber));
            if (empty($qtyRaw) && empty($costRaw) && ! empty($partNumber) && strlen($alphaPart) > 2 && ctype_upper($alphaPart)) {
                $currentCategory = ucwords(strtolower($partNumber));
                if (! in_array($currentCategory, $categories, true)) {
                    $categories[] = $currentCategory;
                }

                continue;
            }

            if (stripos($partNumber, 'Number of items') !== false || stripos($details, 'Number of items') !== false) {
                continue;
            }

            if (empty($details)) {
                continue;
            }

            $sku = $partNumber;
            if (preg_match('/^\d+\.0$/', $sku)) {
                $sku = (string) (int) $sku;
            }

            // Belt and braces: even reading raw values, tolerate a stray
            // currency symbol or thousands separator sneaking in as text.
            $qty  = (int) round($this->toNumber($qtyRaw));
            $cost = round($this->toNumber($costRaw), 2);

            $itemsData[] = [
                'name'          => $details,
                'sku'           => $sku,
                'category'      => $currentCategory,
                'quantity'      => $qty,
                'cost_price'    => $cost,
                'selling_price' => $cost > 0 ? round($cost * 1.3, 2) : 0,
            ];
        }

        CLI::write('Parsed: ' . count($itemsData) . ' items across ' . count($categories) . ' categories', 'green');

        if ($dryRun) {
            CLI::write("\n--- DRY RUN ---", 'yellow');
            CLI::write('Categories:', 'cyan');

            foreach ($categories as $cat) {
                CLI::write("  - {$cat}");
            }
            CLI::write("\nSample items:", 'cyan');

            foreach (array_slice($itemsData, 0, 10) as $item) {
                CLI::write("  {$item['sku']} | {$item['name']} | Qty: {$item['quantity']} | Cost: \${$item['cost_price']}");
            }
            CLI::write('... and ' . (count($itemsData) - 10) . ' more', 'yellow');

            return;
        }

        // Get next item_id
        $maxItem = $db->query('SELECT MAX(item_id) as mid FROM ospos_items')->getRow();
        $nextId  = (int) ($maxItem->mid ?? 0) + 1;

        $itemsTable     = $db->table('items');
        $qtyTable       = $db->table('item_quantities');
        $inventoryTable = $db->table('inventory');
        $batch          = [];
        $qtyBatch       = [];
        $invBatch       = [];
        $now            = date('Y-m-d H:i:s');

        foreach ($itemsData as $item) {
            $batch[] = [
                'item_id'               => $nextId,
                'name'                  => $item['name'],
                'description'           => '',
                'category'              => $item['category'],
                'supplier_id'           => null,
                'item_number'           => $item['sku'],
                'cost_price'            => $item['cost_price'],
                'unit_price'            => $item['selling_price'],
                'reorder_level'         => 5,
                'receiving_quantity'    => 1,
                'pic_filename'          => null,
                'allow_alt_description' => 0,
                'is_serialized'         => 0,
                'deleted'               => 0,
                'stock_type'            => 1,
                'item_type'             => 0,
                'tax_category_id'       => null,
                'qty_per_pack'          => 1,
                'pack_name'             => 'Each',
                'low_sell_item_id'      => 0,
                'hsn_code'              => '',
            ];

            $qtyBatch[] = [
                'item_id'     => $nextId,
                'location_id' => 1,
                'quantity'    => $item['quantity'],
            ];

            // The Items list inner-joins ospos_inventory to show each item's
            // transaction history, so an item with no inventory row at all
            // (as items created straight via SQL are, unlike the admin UI's
            // "Add Item" form) never appears in the list — even though it
            // exists and is otherwise fully usable everywhere else.
            $invBatch[] = [
                'trans_items'     => $nextId,
                'trans_user'      => 1,
                'trans_date'      => $now,
                'trans_comment'   => 'Initial stock import',
                'trans_location'  => 1,
                'trans_inventory' => $item['quantity'],
            ];

            $nextId++;
            $imported++;

            if (count($batch) >= 100) {
                $itemsTable->insertBatch($batch);
                $qtyTable->insertBatch($qtyBatch);
                $inventoryTable->insertBatch($invBatch);
                $batch    = [];
                $qtyBatch = [];
                $invBatch = [];
                CLI::write("  Imported {$imported} items...", 'white');
            }
        }

        if (! empty($batch)) {
            $itemsTable->insertBatch($batch);
            $qtyTable->insertBatch($qtyBatch);
            $inventoryTable->insertBatch($invBatch);
        }

        CLI::write("\n=== IMPORT COMPLETE ===", 'green');
        CLI::write("Items imported: {$imported}", 'green');
        CLI::write("Items skipped:  {$skipped}", 'yellow');
        CLI::write('Categories:     ' . count($categories), 'green');
    }

    /**
     * Coerce a spreadsheet cell to a float. Numeric cells arrive already typed;
     * a value that reaches us as text (currency symbol, thousands separators,
     * stray spaces) is stripped down to digits, a single decimal point and a
     * leading sign. Assumes '.' is the decimal separator (matches the source
     * files); a comma is treated as a thousands separator and removed.
     */
    private function toNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        // Keep only the first '.' and a leading '-'.
        $negative = str_starts_with($clean, '-');
        $clean    = str_replace('-', '', $clean);
        $firstDot = strpos($clean, '.');
        if ($firstDot !== false) {
            $clean = substr($clean, 0, $firstDot + 1) . str_replace('.', '', substr($clean, $firstDot + 1));
        }

        return ($negative ? -1 : 1) * (float) $clean;
    }
}
