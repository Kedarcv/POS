<?php

namespace App\Controllers;

use App\Models\Tax_category;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * @property tax_category tax_category
 */
class Tax_categories extends Secure_Controller
{
    private Tax_category $tax_category;

    public function __construct()
    {
        parent::__construct('tax_categories');

        $this->tax_category = model(Tax_category::class);
        helper('tax_helper');
    }

    /**
     * @return string
     */
    public function getIndex(): ResponseInterface
    {
        // taxes/tax_categories.php is a tab of the combined Taxes management
        // page (taxes/manage.php) and only ever renders correctly with the
        // full data set Taxes::getIndex() assembles for all four tabs at
        // once — trying to render it standalone here was missing most of
        // that data. Send users to the real page, tab pre-selected.
        return redirect()->to(base_url('taxes') . '#tax_categories_tab');
    }

    /**
     * Returns tax_category table data rows. This will be called with AJAX.
     *
     * @return void
     */
    public function getSearch(): ResponseInterface
    {
        // Absent search param -> null, but Tax_category::search() requires a string.
        $search = $this->request->getGet('search') ?? '';
        $limit  = $this->request->getGet('limit', FILTER_SANITIZE_NUMBER_INT);
        $offset = $this->request->getGet('offset', FILTER_SANITIZE_NUMBER_INT);
        $sort   = $this->sanitizeSortColumn(get_tax_categories_table_headers(), $this->request->getGet('sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS), 'tax_category_id');
        $order  = $this->request->getGet('order', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $tax_categories = $this->tax_category->search($search, $limit, $offset, $sort, $order);
        $total_rows = $this->tax_category->get_found_rows($search);

        $data_rows = [];
        foreach ($tax_categories->getResult() as $tax_category) {
            $data_rows[] = get_tax_categories_data_row($tax_category);
        }

        return $this->response->setJSON(['total' => $total_rows, 'rows' => $data_rows]);
    }

    /**
     * @param $row_id
     * @return ResponseInterface
     */
    public function getRow($row_id): ResponseInterface
    {
        $data_row = get_tax_categories_data_row($this->tax_category->get_info($row_id));

        return $this->response->setJSON($data_row);
    }

    /**
     * @param int $tax_category_id
     * @return string
     */
    public function getView(int $tax_category_id = NEW_ENTRY): string
    {
        $data['tax_category_info'] = $this->tax_category->get_info($tax_category_id);

        return view("taxes/tax_category_form", $data);
    }


    /**
     * @param int $tax_category_id
     * @return ResponseInterface
     */
    public function postSave(int $tax_category_id = NEW_ENTRY): ResponseInterface
    {
        $tax_category_data = [
            'tax_category'       => $this->request->getPost('tax_category', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'tax_category_code'  => $this->request->getPost('tax_category_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'tax_group_sequence' => $this->request->getPost('tax_group_sequence', FILTER_SANITIZE_NUMBER_INT)
        ];

        if ($this->tax_category->save_value($tax_category_data, $tax_category_id)) {
            // New tax_category_id
            if ($tax_category_id == NEW_ENTRY) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => lang('Tax_categories.successful_adding'),
                    'id'      => $tax_category_data['tax_category_id']
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => lang('Tax_categories.successful_updating'),
                    'id'      => $tax_category_id
                ]);
            }
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => lang('Tax_categories.error_adding_updating') . ' ' . $tax_category_data['tax_category'],
                'id'      => NEW_ENTRY
            ]);
        }
    }

    /**
     * @return ResponseInterface
     */
    public function postDelete(): ResponseInterface
    {
        $tax_categories_to_delete = $this->request->getPost('ids', FILTER_SANITIZE_NUMBER_INT);

        if ($this->tax_category->delete_list($tax_categories_to_delete)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => lang('Tax_categories.successful_deleted') . ' ' . count($tax_categories_to_delete) . ' ' . lang('Tax_categories.one_or_multiple')
            ]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => lang('Tax_categories.cannot_be_deleted')]);
        }
    }
}
