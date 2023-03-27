<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Client;
use App\Models\Expense;
use App\Models\MoneyBox;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Repository;
use App\Models\SaleInvoice;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StocktakingController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function stocktakingCategory(Request $request)
    {
        $sales_amount = 0;
        $purchases_amount = 0;
        $sales_total_price = 0;
        $purchases_total_price = 0;
        $profit_sales = 0;
        $profit_remnant = 0;
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:categories,id',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $category = Category::
        with(['sales' => function ($q) use ($request) {
            return $q->with(['product' => function ($q) {
                return $q->select('id', 'sale_price', 'purchase_price');
            }])->whereBetween('date', [$request->start_date, $request->end_date]);
        }])->with(['purchases' => function ($q) use ($request) {
            return $q->with(['product' => function ($q) {
                return $q->select('id', 'sale_price', 'purchase_price');
            }])->whereBetween('date', [$request->start_date, $request->end_date]);
        }])
            ->find($request->id);
        foreach ($category->sales as $sale) {
            $sales_amount += $sale->amount;
            $profit_sales += $sale->total_sale_price - $sale->total_purchase_price;
            $sales_total_price += $sale->total_sale_price;
        }
        foreach ($category->purchases as $purchase) {
            $purchases_amount += $purchase->amount;
            $profit_remnant += $purchase->total_sale_price - $purchase->total_purchase_price;
            $purchases_total_price += $purchase->total_purchase_price;
        }
        $data['sales_amount'] = $sales_amount;
        $data['purchases_amount'] = $purchases_amount;
        $data['sales_total_price'] = $sales_total_price;
        $data['purchases_total_price'] = $purchases_total_price;
        $data['profits'] = $profit_sales;
        $data['expected_profits'] = $profit_remnant;
        return $this->success($data);
    }

    /**
     * stocktaking for product
     * @param Request $request
     * @return JsonResponse
     */
    public function stocktakingProduct(Request $request): JsonResponse
    {
        $sales_amount = 0;
        $purchases_amount = 0;
        $sales_total_price = 0;
        $purchases_total_price = 0;
        $profit_sales = 0;
        $profit_remnant = 0;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:products,id',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $product = Product::with(['sales' => function ($q) use ($request) {
            return $q->whereBetween('date', [$request->start_date, $request->end_date]);
        }])->with(['purchases' => function ($q) use ($request) {
            return $q->whereBetween('date', [$request->start_date, $request->end_date]);
        }])->find($request->id);
        $profit_product = $product->sale_price - $product->purchase_price;
        $sales = $product->sales;
        foreach ($sales as $sale) {
            $sales_amount += $sale->amount;
            $profit_sales += $sale->amount * $profit_product;
            $sales_total_price += $sale->total_sale_price;
        }
        $purchases = $product->purchases;
        foreach ($purchases as $purchase) {
            $purchases_amount += $purchase->amount;
            $purchases_total_price += $purchase->total_purchase_price;
        }
        $profit_remnant = $product->amount * $profit_product;

        $data['sales_amount'] = $sales_amount;
        $data['purchases_amount'] = $purchases_amount;
        $data['sales_total_price'] = $sales_total_price;
        $data['purchases_total_price'] = $purchases_total_price;
        $data['profits'] = $profit_sales;
        $data['expected_profits'] = $profit_remnant;
        return $this->success($data);
    }

    /**
     * stocktaking for client
     * @param Request $request
     * @return JsonResponse
     */
    public function stocktakingClient(Request $request): JsonResponse
    {
        $sum_of_sales = 0;
        $money_remained = 0;
        $money_paid = 0;
        $money_total = 0;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:clients,id',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $client = Client::with('sales_invoices')->with('sales')->find($request->id);
        $sales = $client->sales;
        $sales_invoices = $client->sales_invoices;
        foreach ($sales as $sale) {
            $sum_of_sales += $sale->amount;
        }
        foreach ($sales_invoices as $sale_invoice) {
            $money_remained += $sale_invoice->remained;
            $money_paid += $sale_invoice->paid;
            $money_total += $sale_invoice->total_price;
        }
        $data['invoices_count'] = count($client->sales_invoices);
        $data['sales_amount'] = $sum_of_sales;
        $data['debts'] = $money_remained;
        $data['paid'] = $money_paid;
        $data['invoices_total'] = $money_total;
        return $this->success($data);
    }

    /**
     * stocktaking for supplier
     * @param Request $request
     * @return JsonResponse
     */
    public function stocktakingSupplier(Request $request): JsonResponse
    {
        $sum_of_purchase = 0;
        $money_remained = 0;
        $money_paid = 0;
        $money_total = 0;

        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suppliers,id',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $supplier = Supplier::with('purchases_invoices')->with('purchases')->find($request->id);
        $purchases = $supplier->purchases;
        $purchases_invoices = $supplier->purchases_invoices;
        foreach ($purchases as $purchase) {
            $sum_of_purchase += $purchase->amount;
        }
        foreach ($purchases_invoices as $purchase_invoice) {
            $money_remained += $purchase_invoice->remained;
            $money_paid += $purchase_invoice->paid;
            $money_total += $purchase_invoice->total_price;
        }
        $data['invoices_count'] = count($supplier->purchases_invoices);
        $data['purchase_amount'] = $sum_of_purchase;
        $data['debts'] = $money_remained;
        $data['paid'] = $money_paid;
        $data['invoices_total'] = $money_total;
        return $this->success($data);
    }

    /**
     * stocktaking for all:
     * sales_amount,purchases_amount,
     * sales_total_price,purchases_total_price,
     * profits,expected_profits
     * add_cash,withdrawal_cash,expenses,
     * debts_clients,debts_suppliers,debts_expenses,
     * @param Request $request
     * @return JsonResponse
     */
    public function stocktakingAlls(Request $request): JsonResponse
    {
        $sum_of_sales = 0;
        $sum_of_purchases = 0;
        $money_sales = 0;
        $money_purchases = 0;
        $profit_sales = 0;
        $profit_remnant = 0;
        $add_cach = 0;
        $withdrawal_cash = 0;
        $expenses = 0;
        $debts_sales = 0;
        $debts_purchase = 0;
        $debts_expense = 0;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $categories = Category::with(['products' => function ($q) use ($request) {
            return $q->with(['sales' => function ($q) use ($request) {
                return $q->whereBetween('date', [$request->start_date, $request->end_date]);
            }])->with(['purchases' => function ($q) use ($request) {
                return $q->whereBetween('date', [$request->start_date, $request->end_date]);
            }]);
        }])->get();
        foreach ($categories as $category) {
            $products = $category->products;
            foreach ($products as $product) {
                $profit_product = 0;
                $sales = $product->sales;
                $profit_product = $product->sale_price - $product->purchase_price;
                foreach ($sales as $sale) {
                    $sum_of_sales += $sale->amount;
                    $profit_sales += $sale->amount * $profit_product;
                    $money_sales += $sale->total_sale_price;
                }
                $purchases = $product->purchases;
                foreach ($purchases as $purchase) {
                    $sum_of_purchases += $purchase->amount;
                    $money_purchases += $purchase->total_purchase_price;
                }
                $profit_remnant += $product->amount * $profit_product;
            }
        }
        $registers = MoneyBox::where('type_money', 'add_cash')->orWhere('type_money', 'withdrawal_cash')->orWhere('type_money', 'expenses')->whereBetween('date', [$request->start_date, $request->end_date])->get();
        foreach ($registers as $register) {
            if ($register->type_money == 'add_cash') $add_cach += $register->total_price;
            if ($register->type_money == 'withdrawal_cash') $withdrawal_cash += $register->total_price;
            if ($register->type_money == 'expenses') $expenses += $register->total_price;
        }
        $sale_invoices = SaleInvoice::get();
        foreach ($sale_invoices as $sale_invoice)
            $debts_sales += $sale_invoice->remained;
        $purchase_invoices = PurchaseInvoice::get();
        foreach ($purchase_invoices as $purchase_invoice)
            $debts_purchase += $purchase_invoice->remained;
        $expense_invoices = Expense::get();
        foreach ($expense_invoices as $expense_invoice)
            $debts_expense += $expense_invoice->remained;

        $data['sales_amount'] = $sum_of_sales;
        $data['purchases_amount'] = $sum_of_purchases;
        $data['sales_total_price'] = $money_sales;
        $data['purchases_total_price'] = $money_purchases;
        $data['profits'] = $profit_sales;
        $data['expected_profits'] = $profit_remnant;
        $data['add_cash'] = $add_cach;
        $data['withdrawal_cash'] = $withdrawal_cash;
        $data['expenses'] = $expenses;
        $data['debts_clients'] = $debts_sales;
        $data['debts_suppliers'] = $debts_purchase;
        $data['debts_expenses'] = $debts_expense;
        return $this->success($data);
    }

    /**
     * stocktaking for all:
     * sales_amount,purchases_amount,
     * sales_total_price,purchases_total_price,
     * profits,expected_profits
     * add_cash,withdrawal_cash,expenses,
     * debts_clients,debts_suppliers,debts_expenses,
     * @param Request $request
     * @return
     */
    public function stocktakingAll(Request $request)
    {
        $sum_of_sales = 0;
        $sum_of_purchases = 0;
        $money_sales = 0;
        $money_purchases = 0;
        $profit_sales = 0;
        $profit_remnant = 0;
        $add_cach = 0;
        $withdrawal_cash = 0;
        $expenses = 0;
        $debts_sales = 0;
        $debts_purchase = 0;
        $debts_expense = 0;

        $validator = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $all_content= Repository::with('categories')
            ->with('expenses')
            ->with('sales_invoices')
            ->with('purchases_invoices')->find($request->repository_id);

        foreach ($all_content->categories as $category) {
            $products = $category->products;
            $sales = $category->sales;
            foreach ($sales as $sale) {
                $sum_of_sales += $sale->amount;
                $profit_sales += $sale->total_sale_price - $sale->total_purchase_price;
                $money_sales += $sale->total_sale_price;
            }
            $purchases = $category->purchases;
            foreach ($purchases as $purchase) {
                $sum_of_purchases += $purchase->amount;
                $money_purchases += $purchase->total_purchase_price;
                $profit_remnant += $purchase->total_sale_price - $purchase->total_purchase_price;
            }
        }
        $registers = MoneyBox::where('type_money', 'add_cash')->orWhere('type_money', 'withdrawal_cash')->orWhere('type_money', 'expenses')->whereBetween('date', [$request->start_date, $request->end_date])->get();
        foreach ($registers as $register) {
            if ($register->type_money == 'add_cash') $add_cach += $register->total_price;
            if ($register->type_money == 'withdrawal_cash') $withdrawal_cash += $register->total_price;
            if ($register->type_money == 'expenses') $expenses += $register->total_price;
        }
        $sale_invoices = $all_content->sales_invoices;
        foreach ($sale_invoices as $sale_invoice)
            $debts_sales += $sale_invoice->remained;
        $purchase_invoices = $all_content->purchases_invoices;
        foreach ($purchase_invoices as $purchase_invoice)
            $debts_purchase += $purchase_invoice->remained;
        $expense_invoices = $all_content->expenses;
        foreach ($expense_invoices as $expense_invoice)
            $debts_expense += $expense_invoice->remained;

        $data['sales_amount'] = $sum_of_sales;
        $data['purchases_amount'] = $sum_of_purchases;
        $data['sales_total_price'] = $money_sales;
        $data['purchases_total_price'] = $money_purchases;
        $data['profits'] = $profit_sales;
        $data['expected_profits'] = $profit_remnant;
        $data['add_cash'] = $add_cach;
        $data['withdrawal_cash'] = $withdrawal_cash;
        $data['expenses'] = $expenses;
        $data['debts_clients'] = $debts_sales;
        $data['debts_suppliers'] = $debts_purchase;
        $data['debts_expenses'] = $debts_expense;
        return $this->success($data);
    }

    /**
     * get categories
     * @param Request $request
     * @return JsonResponse
     */
    public function getCategories(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $categories = Category::where('repository_id', $request->repository_id)->select('categories.id', 'name')->get();

        return $this->success($categories);
    }

    /**
     * get products names
     * @param Request $request
     * @return JsonResponse
     */
    public function getProducts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $products = Repository::with(['products' => function ($q) {
            return $q->select('products.id', 'products.name');
        }])->find($request->repository_id)->products;

        return $this->success($products);
    }

    /**
     * get clients names
     * @param Request $request
     * @return JsonResponse
     */
    public function getClients(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $clients = Client::where('repository_id', $request->repository_id)->select('id', 'name')->get();
        return $this->success($clients);
    }

    /**
     * get suppliers names
     * @param Request $request
     * @return JsonResponse
     */
    public function getSuppliers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $suppliers = Supplier::where('repository_id', $request->repository_id)->select('id', 'name')->get();
        return $this->success($suppliers);
    }
}
