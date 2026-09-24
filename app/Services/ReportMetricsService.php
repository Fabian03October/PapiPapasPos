<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;

class ReportMetricsService
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $until,
    ) {}

    public static function fromFilters(array $filters): self
    {
        $range = $filters['range'] ?? 'today';

        [$from, $until] = match ($range) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'this_month' => [now()->startOfMonth(), now()->endOfDay()],
            'custom' => [
                ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfDay(),
                ! empty($filters['until']) ? Carbon::parse($filters['until'])->endOfDay() : now()->endOfDay(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        return new self($from, $until);
    }

    protected function salesQuery()
    {
        return Sale::where('status', 'pagada')->whereBetween('created_at', [$this->from, $this->until]);
    }

    public function totals(): array
    {
        $sales = $this->salesQuery()->get();
        $count = $sales->count();

        return [
            'gross' => (float) $sales->sum('subtotal'),
            'discount' => (float) $sales->sum('discount'),
            'net' => (float) $sales->sum('total'),
            'count' => $count,
            'average_ticket' => $count > 0 ? $sales->sum('total') / $count : 0,
            'cash' => (float) $sales->where('payment_method', 'efectivo')->sum('total'),
            'card' => (float) $sales->where('payment_method', 'tarjeta')->sum('total'),
        ];
    }

    public function salesTrend(): array
    {
        $rows = $this->salesQuery()
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return [
            'labels' => $rows->pluck('day')->map(fn ($d) => Carbon::parse($d)->format('d/M'))->all(),
            'data' => $rows->pluck('total')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    public function peakHours(): array
    {
        $rows = $this->salesQuery()
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        $labels = [];
        $data = [];

        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $data[] = (int) ($rows[$h]->total ?? 0);
        }

        return compact('labels', 'data');
    }

    public function topProducts(int $limit = 8): array
    {
        $rows = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'pagada')
            ->whereBetween('sales.created_at', [$this->from, $this->until])
            ->selectRaw('products.name as name, SUM(sale_items.line_total) as total')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('name')->all(),
            'data' => $rows->pluck('total')->map(fn ($v) => (float) $v)->all(),
        ];
    }

    public function deadStock(int $days = 14, int $limit = 10)
    {
        $activeProductIds = SaleItem::join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.created_at', '>=', now()->subDays($days))
            ->pluck('sale_items.product_id')
            ->unique();

        return Product::where('is_active', true)
            ->whereNotIn('id', $activeProductIds)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'base_price']);
    }

    public function lowStockIngredients()
    {
        return Ingredient::where('is_active', true)
            ->whereColumn('stock_qty', '<=', 'min_stock')
            ->orderBy('stock_qty')
            ->get();
    }

    public function shrinkageValue(): float
    {
        return (float) InventoryMovement::where('type', 'merma')
            ->whereBetween('created_at', [$this->from, $this->until])
            ->selectRaw('COALESCE(SUM(ABS(qty) * unit_cost), 0) as value')
            ->value('value');
    }

    public function inventoryValue(): float
    {
        return (float) Ingredient::where('is_active', true)
            ->selectRaw('COALESCE(SUM(stock_qty * cost_per_unit), 0) as value')
            ->value('value');
    }

    public function customerRetention(): array
    {
        $newCustomers = Customer::whereBetween('created_at', [$this->from, $this->until])->count();

        $recurringCustomerIds = Sale::where('status', 'pagada')
            ->whereBetween('created_at', [$this->from, $this->until])
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id');

        $recurring = Customer::whereIn('id', $recurringCustomerIds)
            ->where('created_at', '<', $this->from)
            ->count();

        return ['new' => $newCustomers, 'recurring' => $recurring];
    }

    public function frequentCustomersQuery(int $limit = 10)
    {
        $favoriteProduct = SaleItem::query()
            ->selectRaw('products.name')
            ->join('sales as fp_sales', 'fp_sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereColumn('fp_sales.customer_id', 'customers.id')
            ->where('fp_sales.status', 'pagada')
            ->whereBetween('fp_sales.created_at', [$this->from, $this->until])
            ->groupBy('products.id', 'products.name')
            ->orderByRaw('SUM(sale_items.qty) DESC')
            ->limit(1);

        return Sale::query()
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.status', 'pagada')
            ->whereBetween('sales.created_at', [$this->from, $this->until])
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('visits')
            ->limit($limit)
            ->select(['customers.id as id', 'customers.name as name'])
            ->selectRaw('COUNT(*) as visits, SUM(sales.total) as total')
            ->selectSub($favoriteProduct, 'favorite_product');
    }

    public function frequentCustomers(int $limit = 10)
    {
        return $this->frequentCustomersQuery($limit)->get();
    }

    public function salesByEmployee()
    {
        return Sale::join('users', 'users.id', '=', 'sales.user_id')
            ->where('sales.status', 'pagada')
            ->whereBetween('sales.created_at', [$this->from, $this->until])
            ->selectRaw('users.name as employee, COUNT(*) as tickets, SUM(sales.total) as total')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();
    }
}