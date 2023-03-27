<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MoneyBox;
use App\Models\Product;
use App\Models\Repository;
use App\Models\RepositoryUser;
use App\Models\Sale;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleInvoiceController extends Controller
{
    /**
     *get sales invoices with:client name
     * @return JsonResponse
     */
    public function getAllSalesInvoices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $SaleInvoices = Repository::with(['sales_invoices' => function ($q) {
            return $q->with(['client' => function ($q) {
                return $q->select('id', 'name');
            }]);
        }])->find($request->repository_id)->sales_invoices;

        return $this->success($SaleInvoices);
    }

    /**
     * get sale invoice with:sales,product
     * @param Request $request
     * @return JsonResponse
     */
    public function getSaleInvoice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:sales_invoices,id',]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $SaleInvoice = SaleInvoice::with(['client' => function ($q) {
            return $q->select('id', 'name');
        }])->with(['sales' => function ($q) {
            return $q->select('product_id', 'sale_invoice_id', 'amount', 'total_sale_price')
                ->with(['product' => function ($q) {
                    return $q->select('id', 'name', 'sale_price');
                }]);
        }])->where('id', $request->id)->first();
        return $this->success($SaleInvoice);
    }

    /**
     * create sale invoice with:sales,register
     * @param Request $request
     * @return JsonResponse
     */
    public function addSaleInvoice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'client_id' => 'required|numeric|exists:clients,id',
                'sales' => 'required',
                'total_price' => 'required|numeric',
                'paid' => 'required|numeric',
                'date' => 'required',
                'remained' => 'required',
                'number' => 'required|numeric',
            ]);

            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $register = MoneyBox::create([
                'type_money' => 'sales',
                'total_price' => $request->paid,
                'date' => $request->date,
                'is_finished' => $request->remained > 0 ? 0 : 1,
                'repository_id' => Client::with('repository')->find($request->client_id )->repository->id,

            ]);
            $Sale_invoice = SaleInvoice::create([
                'register_id' => $register->id,
                'client_id' => $request->client_id,
                'total_price' => $request->total_price,
                'paid' => $request->paid,
                'remained' => $request->remained,
                'date' => $request->date,
                'number' => $request->number,
            ]);
            $sales = json_decode($request->sales);
            foreach ($sales as $sale) {
                $product = Product::where('id', $sale->product_id)->first();
                if ($product->amount < $sale->amount)
                    return $this->error('you have not amount enough of product ' . $product->name);
                $sale = Sale::create([
                    'product_id' => $sale->product_id,
                    'sale_invoice_id' => $Sale_invoice->id,
                    'amount' => $sale->amount,
                    'total_purchase_price' => $sale->total_purchase_price,
                    'total_sale_price' => $sale->total_sale_price,
                    'date' => $request->date,
                ]);
                $sale->product->update([
                    'amount' => $sale->product->amount - $sale->amount
                ]);
            }
            $sale->save();
            $sale->product->save();
            $register = SaleInvoiceRegister::create([
                'sale_invoice_id' => $Sale_invoice->id,
                'user_id' => $request->user()->id,
                'type_operation' => 'add',
            ]);
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e);
        }
    }

    /**
     * update sale invoice with:sales,register
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSaleInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:sales_invoices,id',
                'client_id' => 'required|numeric|exists:clients,id',
                'sales' => 'required',
                'total_price' => 'required|numeric',
                'paid' => 'required|numeric',
                'remained' => 'required|numeric',
                'date' => 'required',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $sale_invoice = SaleInvoice::where('id', $request->id)->first();
            $sale_invoice->update([
                'client_id' => $request->client_id,
                'total_price' => $request->total_price,
                'paid' => $request->paid,
                'remained' => $request->remained,
                'date' => $request->date,
            ]);

            $sales = json_decode($request->sales);
            foreach ($sale_invoice->sales as $sale) {
                Sale::where('id', $sale->id)->delete();
            }
            foreach ($sales as $sale) {
                Sale::create([
                    'product_id' => $sale->product_id,
                    'sale_invoice_id' => $sale_invoice->id,
                    'amount' => $sale->amount,
                    'total_purchase_price' => $sale->total_purchase_price,
                    'total_sale_price' => $sale->total_sale_price,
                    'date' => $request->date,
                ]);
            }
            $sale_invoice->register->update([
                'repository_id' => $request->user()->repositories->first()->id,
                'total_price' => $request->paid,
                'date' => $request->date,
                'is_finished' => $sale_invoice->remained > 0 ? 0 : 1,
            ]);
            $sale_invoice->register->save();

            $register = SaleInvoiceRegister::create([
                'sale_invoice_id' => $sale_invoice->id,
                'user_id' => $request->user()->id,
                'type_operation' => 'add',
            ]);
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * delete sale invoice with:sales,register
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSaleInvoice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:sales_invoices,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $saleInvoice = SaleInvoice::
        with('sales')
            ->with('register')
            ->where('id', $request->id)
            ->first();
        foreach ($saleInvoice->sales as $sale) {
            Sale::where('id', $sale->id)->forceDelete();
        }
        $saleInvoice->register->forceDelete();
        $saleInvoice->forceDelete();
        $saleInvoice->forceDelete();
        return $this->success();
    }

    /**
     * get sales invoices between tow date with:sales,product
     * @param Request $request
     * @return JsonResponse
     */
    public function getSalesInvoiceBetweenTowDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $SaleInvoice = SaleInvoice::
        with(['sales' => function ($q) {
            return $q->select('id', 'product_id', 'sale_invoice_id', 'amount', 'total_sale_price')
                ->with(['product' => function ($q) {
                    return $q->select('id', 'name');
                }]);
        }])
            ->with(['client' => function ($q) {
                return $q->select('id', 'name');
            }])
            ->whereBetween('date', [$request->start_date, $request->end_date])->get();
        return $this->success($SaleInvoice);
    }

    /**
     * add sale invoice to archive with: register,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function addToArchivesSalesInvoice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:sales_invoices,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $sale_invoice = SaleInvoice::find($request->id);
            if (!$sale_invoice)
                return $this->error('the invoice not found');

            $register = SaleInvoiceRegister::create([
                'sale_invoice_id' => $sale_invoice->id,
                'user_id' => $request->user()->id,
                'type_operation' => 'add_to_archive',
            ]);
            foreach ($sale_invoice->sales as $sale)
                $sale->delete();
            $sale_invoice->register->delete();
            $sale_invoice->delete();

            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * get sale invoice in archive with: register,sales
     * @return JsonResponse
     */
    public function getArchivesSaleInvoices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $sale_invoices = Repository::with(['sales_invoices' => function ($q) {
            return $q->with(['client' => function ($q) {
                return $q->select('id', 'name');
            }])->onlyTrashed();
        }])->find($request->repository_id)->sales_invoices;

        if (!$sale_invoices)
            return $this->error();
        return $this->success($sale_invoices);
    }

    /**
     * get sale invoice in archive with: client,sales
     * @return JsonResponse
     */
    public function getArchiveSaleInvoice(Request $request): JsonResponse
    {

        $sale_invoice = SaleInvoice::with(['sales' => function ($q) {
            return $q->onlyTrashed()->select('product_id', 'sale_invoice_id', 'amount', 'total_sale_price')
                ->with(['product' => function ($q) {
                    return $q->select('id', 'name', 'sale_price');
                }]);
        }])->with(['client' => function ($q) {
            return $q->select('id', 'name');
        }])->onlyTrashed()->where('id', $request->id)->first();
        if (!$sale_invoice)
            return $this->error();
        return $this->success($sale_invoice);
    }

    /**
     * remove sale invoice from archive with: register,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function removeToArchivesSaleInvoice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:sales_invoices,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $saleInvoice = SaleInvoice::onlyTrashed()->where('id', $request->id)->with(['register' => function ($q) {
                return $q->onlyTrashed();
            }])->with(['sales' => function ($q) {
                return $q->onlyTrashed();
            }])->first();
            if (!$saleInvoice)
                return $this->error('the invoice not found in archive');
            $saleInvoice->register->update(['deleted_at' => null]);
            foreach ($saleInvoice->sales as $sale) {
                $sale->update(['deleted_at' => null]);
                $sale->save();
            }
            $saleInvoice->update(['deleted_at' => null]);
            $saleInvoice->save();
            $saleInvoice->register->save();

            $register = SaleInvoiceRegister::create([
                'sale_invoice_id' => $saleInvoice->id,
                'user_id' => $request->user()->id,
                'type_operation' => 'remove_to_archive',
            ]);
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getSaleInvoiceRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:sales_invoices,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not see this register');
        $rigister = SaleInvoiceRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('sale_invoice_id', $request->id)->get();
        return $this->success($rigister);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSaleInvoiceRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:sale_invoice_registers,sale_invoice_id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not delete this register');
        SaleInvoiceRegister::where('id', $request->id)->delete();
        return $this->success();
    }

    /**
     * meet debt for purchase invoice
     * @param Request $request
     * @return JsonResponse
     */
    public function meetDebt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:sales_invoices,id',
            'payment' => 'required|numeric'
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $payment = $request->payment;
        $sale_invoice = SaleInvoice::where('id', $request->id)->first();
        if ($payment > $sale_invoice->remained) {
            return $this->error('The Payment Value Is Bigger Than Debt,the remained of your payment  is ' . $payment);
        }
        $sale_invoice->update([
            'paid' => $sale_invoice->paid + $payment,
            'remained' => $sale_invoice->remained - $payment,
        ]);
        $sale_invoice->save();
        SaleInvoiceRegister::create([
            'sale_invoice_id' => $sale_invoice->id,
            'user_id' => $request->user()->id,
            'type_operation' => 'meet_debt',
        ]);
        return $this->success();
    }

}
