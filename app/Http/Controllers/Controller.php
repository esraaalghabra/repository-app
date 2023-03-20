<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MoneyBox;
use App\Models\Repository;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Models\Supplier;
use App\Models\SupplierRegister;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * response success
     * @param mixed|null $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public function success
    (mixed $data = null, string $message = "ok", int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'success' => true,
            'message' => $message
        ], $statusCode);

    }

    /**
     * response failure
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public function error
    (string $message = "error", int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'data' => null,
            'success' => false,
            'message' => $message
        ], $statusCode);
    }

    /**
     * monitoring for:
     * get the most_selling_products,
     * most_popular_clients,
     * most_popular_suppliers
     * @return
     */
    public function monitoring(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $content = Repository::with(['products' => function ($q) {
            return $q->whereHas('sales')->select('products.id', 'products.name', 'sale_price', 'purchase_price', 'amount');
        }])
            ->with(['clients' => function ($q) {
                return $q->whereHas('sales_invoices');
            }])
            ->with(['suppliers' => function ($q) {
                return $q->whereHas('purchases_invoices');
            }])->
        with(['sales_invoices' => function ($q) {
            return $q->with(['client' => function ($q) {
                return $q->select('id', 'name');
            }])->latest('date');
        }])->
        with(['purchases_invoices' => function ($q) {
            return $q->with(['supplier' => function ($q) {
                return $q->select('id', 'name');
            }])->latest('date');
        }])->
        with(['expenses' => function ($q) {
            return $q->latest('date');
        }])
            ->find($request->repository_id);
        $data['most_selling_products'] = $this->getMostSellingProducts(count($content->products) > 5 ? 5 : count($content->products), $content->products);
        $data['less_amount_products'] = $this->getLessAmountProducts($content->products);
        $data['most_popular_clients'] = $this->getPopularClients(count($content->clients) > 5 ? 5 : count($content->clients), $content->clients);
        $data['latest_sale_invoices'] = $this->getLatestSaleInvoices(count($content->sales_invoices) > 5 ? 5 : count($content->sales_invoices), $content->sales_invoices);
        $data['latest_purchase_invoices'] = $this->getLatestPurchaseInvoices(count($content->purchases_invoices) > 5 ? 5 : count($content->purchases_invoices), $content->purchases_invoices);
        $data['latest_expenses_invoices'] = $this->getLatestExpenses(count($content->expenses) > 5 ? 5 : count($content->expenses), $content->expenses);
        $data['most_popular_suppliers'] = $this->getPopularSuppliers(count($content->suppliers) > 5 ? 5 : count($content->suppliers), $content->suppliers);

        return $this->success($data);
    }

    /**
     * get popular clients
     * @param $n
     * @param $clients
     * @return array
     */
    public function getPopularClients($n, $clients): array
    {
        $sort = [];
        $clients_sort = [];
        for ($i = 0; $i < $n; $i++) {
            $sort[$i] = 0;
            $clients_sort[$i] = $clients[0];
        }

        for ($p = 0; $p < count($clients); $p++) {
            $count_invoice = count($clients[$p]->sales_invoices);
            $clients[$p]->count_invoices = $count_invoice;

            for ($i = 0; $i < count($sort); $i++) {
                if ($count_invoice > $sort[$i]) {
                    for ($j = count($sort) - 1; $j > $i; $j--) {
                        $sort[$j] = $sort[$j - 1];
                        $clients_sort[$j] = $clients_sort[$j - 1];
                    }
                    $sort[$i] = $count_invoice;
                    $clients_sort[$i] = Client::select('id', 'name', 'photo')->find($clients[$p]->id);
                    $clients_sort[$i]->photo = asset('assets/images/products/' . $clients[$p]->photo);
                    break;
                }
            }
        }
        return $clients_sort;
    }

    /**
     * get popular clients
     * @param $n
     * @param $clients
     * @return array
     */
    public function getLatestSaleInvoices($n, $sales_invoices)
    {
        $latest_sale_invoices = [];
        for ($i = 0; $i < $n; $i++) {
            $latest_sale_invoices[$i] = $sales_invoices[$i];
        }
        return $latest_sale_invoices;
    }

    /**
     * get popular clients
     * @param $n
     * @param $clients
     * @return array
     */
    public function getLatestPurchaseInvoices($n, $purchases_invoices)
    {
        $latest_purchase_invoices = [];
        for ($i = 0; $i < $n; $i++) {
            $latest_purchase_invoices[$i] = $purchases_invoices[$i];
        }
        return $latest_purchase_invoices;
    }

    /**
     * get popular clients
     * @param $n
     * @param $clients
     * @return array
     */
    public function getLatestExpenses($n, $expenses)
    {
        $latest_expenses = [];
        for ($i = 0; $i < $n; $i++) {
            $latest_expenses[$i] = $expenses[$i];
        }
        return $latest_expenses;
    }

    /**
     * get popular suppliers
     * @param $n
     * @param $suppliers
     * @return array
     */
    public function getPopularSuppliers($n, $suppliers): array
    {
        $sort = [];
        $suppliers_sort = [];
        for ($i = 0; $i < $n; $i++) {
            $sort[$i] = 0;
            $suppliers_sort[$i] = $suppliers[0];
        }

        for ($p = 0; $p < count($suppliers); $p++) {
            $count_invoice = count($suppliers[$p]->purchases_invoices);
            $suppliers[$p]->count_invoices = $count_invoice;

            for ($i = 0; $i < count($sort); $i++) {
                if ($count_invoice > $sort[$i]) {
                    for ($j = count($sort) - 1; $j > $i; $j--) {
                        $sort[$j] = $sort[$j - 1];
                        $suppliers_sort[$j] = $suppliers_sort[$j - 1];
                    }
                    $sort[$i] = $count_invoice;
                    $suppliers_sort[$i] = Supplier::select('id', 'name', 'photo')->find($suppliers[$p]->id);
                    $suppliers_sort[$i]->photo = asset('assets/images/products/' . $suppliers[$p]->photo);
                    break;
                }
            }
        }
        return $suppliers_sort;
    }

    /**
     * get most_selling_products
     * @param $n
     * @param $products
     * @return array
     */
    public function getMostSellingProducts($n, $products): array
    {
        $sort = [];
        $product_sort = [];
        for ($i = 0; $i < $n; $i++) {
            $sort[$i] = 0;
            $product_sort[$i] = $products[0];
        }
        for ($p = 0; $p < count($products); $p++) {
            $sales = Sale::where('product_id', $products[$p]->id)->get();
            $sum_of_sales = 0;
            foreach ($sales as $sale) {
                $sum_of_sales += $sale->amount;
            }
            for ($i = 0; $i < count($sort); $i++)
                if ($sum_of_sales > $sort[$i]) {
                    for ($j = count($sort) - 1; $j > $i; $j--) {
                        $sort[$j] = $sort[$j - 1];
                        $product_sort[$j] = $product_sort[$j - 1];
                    }
                    $sort[$i] = $sum_of_sales;
                    $product_sort[$i] = $products[$p];
                    $product_sort[$i]->cont_sales = $sum_of_sales;
                    break;
                }
        }
        return $product_sort;
    }

    /**
     * get less_amount_products
     * @param $n
     * @param $products
     * @return array
     */
    public function getLessAmountProducts($products): array
    {
        $least_products = [];
        for ($p = 0; $p < count($products); $p++) {
            if ($products[$p]->amount < 10)
                $least_products[$p] = $products[$p];
        }
        return $least_products;

    }

    /**
     * get total money in box
     * @return int
     */
    public function getTotalBox($repository_id)
    {
        $money = Repository::with('registers')->find($repository_id)->registers;
        $totalAdd = 0;
        $totalRemove = 0;
        foreach ($money as $one) {
            if ($one->type_money == 'add_cash' || $one->type_money == 'sales')
                $totalAdd += $one->total_price;
            elseif ($one->type_money == 'withdrawal_cash' || $one->type_money == 'expenses' || $one->type_money == 'purchases')
                $totalRemove += $one->total_price;
        }
        $total = $totalAdd - $totalRemove;
        return $total;
    }

}
