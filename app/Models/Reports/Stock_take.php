<?php

namespace App\Models\Reports;

use App\Models\Item;

class Stock_take extends Report
{
    /**
     * All fields available for selection on the stock take count sheet.
     *
     * @return array<string, array{label: string, default: bool, is_input?: bool}>
     */
    public function getAllAvailableFields(): array
    {
        return [
            'item_name'      => ['label' => lang('Reports.item_name'), 'default' => true],
            'item_number'    => ['label' => lang('Reports.item_number'), 'default' => true],
            'category'       => ['label' => lang('Reports.category'), 'default' => true],
            'location_name'  => ['label' => lang('Reports.stock_location'), 'default' => true],
            'quantity'       => ['label' => lang('Reports.stock_take_system_quantity'), 'default' => true],
            'physical_count' => ['label' => lang('Reports.stock_take_physical_count'), 'default' => true, 'is_input' => true],
            'variance'       => ['label' => lang('Reports.stock_take_variance'), 'default' => false, 'is_input' => true],
            'cost_price'     => ['label' => lang('Reports.cost_price'), 'default' => false],
            'unit_price'     => ['label' => lang('Reports.unit_price'), 'default' => false],
            'reorder_level'  => ['label' => lang('Reports.reorder_level'), 'default' => false],
            'qty_per_pack'   => ['label' => lang('Reports.stock_take_qty_per_pack'), 'default' => false],
            'description'    => ['label' => lang('Reports.description'), 'default' => false],
        ];
    }

    /**
     * Default list of field keys to select when none specified.
     *
     * @return string[]
     */
    public function getDefaultFields(): array
    {
        $fields = [];
        foreach ($this->getAllAvailableFields() as $key => $meta) {
            if (!empty($meta['default'])) {
                $fields[] = $key;
            }
        }
        return $fields;
    }

    /**
     * @return array[]
     */
    public function getDataColumns(): array
    {
        return $this->getDataColumnsForFields($this->getDefaultFields());
    }

    /**
     * Builds data columns tailored to the user's selected fields/schema.
     *
     * @param string[] $selectedFields
     * @return array[]
     */
    public function getDataColumnsForFields(array $selectedFields): array
    {
        $all = $this->getAllAvailableFields();
        $columns = [];

        foreach ($selectedFields as $field) {
            if (isset($all[$field])) {
                $columns[] = [$field => $all[$field]['label']];
            }
        }

        return $columns;
    }

    /**
     * Fetch inventory rows based on selected filters.
     *
     * @param array $inputs
     * @return array
     */
    public function getData(array $inputs): array
    {
        $item = model(Item::class);

        $builder = $this->db->table('items AS items');
        $builder->select(
            $item->get_item_name('name') . ',
            items.item_id,
            items.item_number,
            items.category,
            items.description,
            items.cost_price,
            items.unit_price,
            items.reorder_level,
            items.qty_per_pack,
            item_quantities.quantity,
            stock_locations.location_name,
            stock_locations.location_id'
        );
        $builder->join('item_quantities AS item_quantities', 'items.item_id = item_quantities.item_id');
        $builder->join('stock_locations AS stock_locations', 'item_quantities.location_id = stock_locations.location_id');
        $builder->where('items.deleted', 0);
        $builder->where('items.stock_type', 0);
        $builder->where('stock_locations.deleted', 0);

        if (!empty($inputs['location_id']) && $inputs['location_id'] !== 'all') {
            $builder->where('stock_locations.location_id', $inputs['location_id']);
        }

        if (!empty($inputs['category']) && $inputs['category'] !== 'all') {
            $builder->where('items.category', $inputs['category']);
        }

        if (!empty($inputs['item_count'])) {
            if ($inputs['item_count'] === 'zero_and_less') {
                $builder->where('item_quantities.quantity <=', 0);
            } elseif ($inputs['item_count'] === 'more_than_zero') {
                $builder->where('item_quantities.quantity >', 0);
            }
        }

        $sortBy = $inputs['sort_by'] ?? 'name';
        match ($sortBy) {
            'category'      => $builder->orderBy('items.category')->orderBy('items.name'),
            'item_number'   => $builder->orderBy('items.item_number')->orderBy('items.name'),
            'location_name' => $builder->orderBy('stock_locations.location_name')->orderBy('items.name'),
            default         => $builder->orderBy('items.name')->orderBy('items.qty_per_pack'),
        };

        return $builder->get()->getResultArray();
    }

    /**
     * @return array<string, string>
     */
    public function getItemCountDropdownArray(): array
    {
        return [
            'all'            => lang('Reports.all'),
            'more_than_zero' => lang('Reports.more_than_zero'),
            'zero_and_less'  => lang('Reports.zero_and_less'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getSortByDropdownArray(): array
    {
        return [
            'name'          => lang('Reports.item_name'),
            'category'      => lang('Reports.category'),
            'item_number'   => lang('Reports.item_number'),
            'location_name' => lang('Reports.stock_location'),
        ];
    }
}
