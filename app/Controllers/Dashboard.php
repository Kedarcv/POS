<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\Database\Config;
use CodeIgniter\Database\ConnectionInterface;

class Dashboard extends Secure_Controller
{
    protected ConnectionInterface $db;

    public function __construct()
    {
        parent::__construct('home', null, 'home');
        $this->db = Config::connect();
    }

    public function index()
    {
        $today      = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $weekStart  = date('Y-m-d', strtotime('-6 days'));

        // Today's revenue (from payments)
        $todayRevenue = $this->db->table('sales_payments')
            ->selectSum('payment_amount', 'revenue')
            ->where('payment_time >=', $today)
            ->get()
            ->getRow();

        $todaySalesCount = $this->db->table('sales')
            ->selectCount('sale_id', 'count')
            ->where('sale_time >=', $today)
            ->get()
            ->getRow();

        // Monthly revenue
        $monthlyRevenue = $this->db->table('sales_payments')
            ->selectSum('payment_amount', 'revenue')
            ->where('payment_time >=', $monthStart)
            ->get()
            ->getRow();

        $monthlySalesCount = $this->db->table('sales')
            ->selectCount('sale_id', 'count')
            ->where('sale_time >=', $monthStart)
            ->get()
            ->getRow();

        // Active laybys
        $activeLaybys = $this->db->table('layby_plans')
            ->selectCount('id', 'count')
            ->selectSum('balance', 'outstanding')
            ->where('status', 'active')
            ->get()
            ->getRow();

        // Overdue laybys
        $overdueLaybys = $this->db->table('layby_plans')
            ->selectCount('id', 'count')
            ->where('status', 'overdue')
            ->get()
            ->getRow();

        // Print jobs in pipeline
        $printJobs = $this->db->table('print_jobs')
            ->selectCount('id', 'count')
            ->selectSum('total_cost', 'value')
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->get()
            ->getRow();

        // Low stock items (quantity <= 0)
        $lowStock = $this->db->table('item_quantities')
            ->selectCount('item_id', 'count')
            ->where('quantity <=', 0)
            ->get()
            ->getRow();

        // Top 5 selling items this month
        $topItems = $this->db->query('
            SELECT si.item_id, i.name,
                   SUM(si.quantity_purchased) as qty_sold,
                   SUM(si.quantity_purchased * si.item_unit_price) as revenue
            FROM ospos_sales_items si
            JOIN ospos_sales s ON s.sale_id = si.sale_id
            JOIN ospos_items i ON i.item_id = si.item_id
            WHERE s.sale_time >= ?
            GROUP BY si.item_id, i.name
            ORDER BY qty_sold DESC
            LIMIT 5
        ', [$monthStart])->getResult();

        // Last 7 days daily revenue
        $dailySales = $this->db->table('sales_payments')
            ->select('DATE(payment_time) as pay_date, SUM(payment_amount) as revenue')
            ->where('payment_time >=', $weekStart . ' 00:00:00')
            ->groupBy('DATE(payment_time)')
            ->orderBy('pay_date', 'ASC')
            ->get()
            ->getResult();

        // Build 7-day array with zero-filling
        $dailyChart = [];

        for ($i = 6; $i >= 0; $i--) {
            $date              = date('Y-m-d', strtotime("-{$i} days"));
            $dailyChart[$date] = ['date' => $date, 'revenue' => 0];
        }

        foreach ($dailySales as $row) {
            $d = $row->pay_date;
            if (isset($dailyChart[$d])) {
                $dailyChart[$d]['revenue'] = (float) $row->revenue;
            }
        }

        // Monthly expenses
        $monthlyExpenses = $this->db->table('expenses')
            ->selectSum('amount', 'total')
            ->where('date >=', $monthStart)
            ->get()
            ->getRow();

        $maxDailyRevenue = 0;

        foreach ($dailyChart as $day) {
            if ($day['revenue'] > $maxDailyRevenue) {
                $maxDailyRevenue = $day['revenue'];
            }
        }

        $data = [
            'today_count'       => (int) ($todaySalesCount->count ?? 0),
            'today_revenue'     => $todayRevenue->revenue ?? '0.00',
            'monthly_count'     => (int) ($monthlySalesCount->count ?? 0),
            'monthly_revenue'   => $monthlyRevenue->revenue ?? '0.00',
            'layby_count'       => (int) ($activeLaybys->count ?? 0),
            'layby_outstanding' => $activeLaybys->outstanding ?? '0.00',
            'overdue_count'     => (int) ($overdueLaybys->count ?? 0),
            'printjob_count'    => (int) ($printJobs->count ?? 0),
            'printjob_value'    => $printJobs->value ?? '0.00',
            'low_stock_count'   => (int) ($lowStock->count ?? 0),
            'top_items'         => $topItems,
            'daily_chart'       => array_values($dailyChart),
            'max_daily_revenue' => $maxDailyRevenue,
            'monthly_expenses'  => $monthlyExpenses->total ?? '0.00',
        ];

        return view('dashboard/index', $data);
    }
}
