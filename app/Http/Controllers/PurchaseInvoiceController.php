<?php

namespace App\Http\Controllers;

use App\Models\MoneyBox;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceRegister;
use App\Models\Repository;
use App\Models\RepositoryUser;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceController extends Controller
{
    /**
     * get sales invoices with:supplier name
     * @return JsonResponse
     */
    public function getAllPurchasesInvoices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $PurchasesInvoices = Repository::with(['purchases_invoices' => function ($q) {
            return $q->with(['supplier' => function ($q) {
                return $q->select('id', 'name');
            }]);
        }])->find($request->repository_id)->purchases_invoices;

        return $this->success(json_decode($PurchasesInvoices));
    }

    /**
     * get purchase invoice with:purchases,product
     * @param Request $request
     * @return JsonResponse
     */
    public function getPurchaseInvoice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:purchases_invoices,id',]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $purchaseInvoice = PurchaseInvoice::with(['supplier' => function ($q) {
            return $q->select('id', 'name');
        }])->with(['purchases' => function ($q) {
            return $q->select('product_id', 'purchase_invoice_id', 'amount', 'total_purchase_price')
                ->with(['product' => function ($q) {
                    return $q->select('id', 'name', 'purchase_price');
                }]);
        }])->where('id', $request->id)->first();
        return $this->success($purchaseInvoice);
    }

    /**
     * create purchase invoice with:purchases,register
     * @param Request $request
     * @return JsonResponse
     */
    public function addPurchaseInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|numeric|exists:suppliers,id',
                'purchases' => 'required',
                'total_price' => 'required|numeric',
                'paid' => 'required|numeric',
                'date' => 'required',
                'remained' => 'required',
                'number' => 'required|numeric',
            ]);

            if ($validator->fails())
                return $this->error($validator->errors()->first());
            $repository_id = Supplier::with('repository')->find($request->supplier_id)->repository->id;
            if ($this->getTotalBox($repository_id) < $request->total_price)
                return $this->error('your money not enough');
            DB::beginTransaction();

            $register = MoneyBox::create([
                'type_money' => 'purchases',
                'is_finished' => $request->remained > 0 ? 0 : 1,
                'total_price' => $request->paid,
                'date' => $request->date,
                'repository_id' => $repository_id,
            ]);
            $purchase_invoice = PurchaseInvoice::create([
                'register_id' => $register->id,
                'supplier_id' => $request->supplier_id,
                'total_price' => $request->total_price,
                'paid' => $request->paid,
                'remained' => $request->remained,
                'date' => $request->date,
                'number' => $request->number,
            ]);
            $purchases = json_decode($request->purchases);
            foreach ($purchases as $purchase) {
                $purchase = Purchase::create([
                    'product_id' => $purchase->product_id,
                    'supplier_id' => $request->supplier_id,
                    'purchase_invoice_id' => $purchase_invoice->id,
                    'amount' => $purchase->amount,
                    'date' => $request->date,
                    'total_purchase_price' => $purchase->total_purchase_price,
                    'total_sale_price' => $purchase->total_sale_price,
                ]);
                $purchase->product->update([
                    'amount' => $purchase->product->amount + $purchase['amount']
                ]);
                $purchase->product->save();
            }
            PurchaseInvoiceRegister::create([
                'purchase_invoice_id' => $purchase_invoice->id,
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
     * update purchase invoice with:purchases,register
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePurchaseInvoice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:purchases_invoices,id',
                'supplier_id' => 'required|numeric|exists:suppliers,id',
                'purchases' => 'required',
                'total_price' => 'required|numeric',
                'paid' => 'required|numeric',
                'remained' => 'required|numeric',
                'date' => 'required',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());

            $purchase_invoice = PurchaseInvoice::where('id', $request->id)->with('purchases')->with('register')->first();
            $purchase_invoice->update([
                'supplier_id' => $request->supplier_id,
                'total_price' => $request->total_price,
                'paid' => $request->paid,
                'remained' => $request->remained,
                'date' => $request->date,
            ]);
            foreach ($purchase_invoice->purchases as $purchase) {
                Purchase::where('id', $purchase->id)->delete();
            }
            $purchases = json_decode($request->purchases);
            foreach ($purchases as $purchase) {
                Purchase::create([
                    'product_id' => $purchase->product_id,
                    'purchase_invoice_id' => $purchase_invoice->id,
                    'amount' => $purchase->amount,
                    'date' => $request->date,
                    'total_purchase_price' => $purchase->total_purchase_price,
                    'total_sale_price' => $purchase->total_sale_price,
                ]);
            }
            $purchase_invoice->register->update([
                'total_price' => $request->paid,
                'is_finished' => $purchase_invoice->remained > 0 ? 0 : 1,
                'date' => $request->date,
            ]);
            $purchase_invoice->register->save();
            $register = PurchaseInvoiceRegister::create([
                'purchase_invoice_id' => $purchase_invoice->id,
                'user_id' => $request->user()->id,
                'type_operation' => 'edit',
            ]);
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * delete purchase invoice with:purchases,register
     * @param Request $request
     * @return JsonResponse
     */
    public function deletePurchaseInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:purchases_invoices,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $purchaseInvoice = PurchaseInvoice::
        with('purchases')
            ->with('register')
            ->where('id', $request->id)
            ->first();

        foreach ($purchaseInvoice->purchases as $purchase) {
            Purchase::where('id', $purchase->id)->forceDelete();
        }
        $purchaseInvoice->register->forceDelete();
        $purchaseInvoice->forceDelete();
        return $this->success();
    }

    /**
     * get purchases invoices between tow date with:sales,product
     * @param Request $request
     * @return JsonResponse
     */
    public function getPurchasesInvoicesBetweenTowDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required',
            'end_date' => 'required',
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $PurchasesInvoices = Repository::with(['purchases_invoices' => function ($q) use ($request) {
            return $q->with(['supplier' => function ($q) {
                return $q->select('id', 'name');
            }])->whereBetween('date', [$request->start_date, $request->end_date]);
        }])->find($request->repository_id)->purchases_invoices;

        return $this->success($PurchasesInvoices);
    }

    /**
     * add purchase invoice to archive with: register,purchases
     * @param Request $request
     * @return JsonResponse
     */
    public function addToArchivesPurchaseInvoice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:purchases_invoices,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $purchase_invoice = PurchaseInvoice::find($request->id);
            if (!$purchase_invoice)
                return $this->error('the invoice not found');

            $register = PurchaseInvoiceRegister::create([
                'purchase_invoice_id' => $purchase_invoice->id,
                'user_id' => $request->user()->id,
                'type_operation' => 'add_to_archive',
            ]);
            foreach ($purchase_invoice->purchases as $purchase)
                $purchase->delete();
            $purchase_invoice->register->delete();
            $purchase_invoice->delete();
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * get purchases invoice in archive with: register,purchases
     * @return JsonResponse
     */
    public function getArchivesPurchasesInvoices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $PurchasesInvoices = Repository::with(['purchases_invoices' => function ($q) {
            return $q->with(['supplier' => function ($q) {
                return $q->select('id', 'name');
            }])->onlyTrashed();
        }])->find($request->repository_id)->purchases_invoices;

        if (!$PurchasesInvoices)
            return $this->error();
        return $this->success($PurchasesInvoices);
    }

    /**
     * get purchases invoice in archive with: register,purchases
     * @return JsonResponse
     */
    public function getArchivePurchasesInvoice(): JsonResponse
    {
        $purchases_invoices = PurchaseInvoice::with(['purchases' => function ($q) {
            return $q->onlyTrashed()->select('product_id', 'purchase_invoice_id', 'amount', 'total_purchase_price')
                ->with(['product' => function ($q) {
                    return $q->select('id', 'name', 'purchase_price');
                }]);
        }])->with(['supplier' => function ($q) {
            return $q->select('id', 'name');
        }])->onlyTrashed()->get();
        if (!$purchases_invoices)
            return $this->error();
        return $this->success($purchases_invoices);
    }

    /**
     * remove purchase invoice from archive with: register,purchases
     * @param Request $request
     * @return JsonResponse
     */
    public function removeToArchivesPurchaseInvoice(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:purchases_invoices,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $purchaseInvoice = PurchaseInvoice::with(['register' => function ($q) {
                return $q->onlyTrashed();
            }])
                ->with(['purchases' => function ($q) {
                    return $q->onlyTrashed();
                }])->onlyTrashed()->where('id', $request->id)->first();
            if (!$purchaseInvoice)
                return $this->error('the invoice not found in archive');
            $register = MoneyBox::onlyTrashed()->where('id', $purchaseInvoice->register_id)->first();
            $purchaseInvoice->register->update(['deleted_at' => null]);
            foreach ($purchaseInvoice->purchases as $purchase)
                $purchase->update(['deleted_at' => null]);
            $purchaseInvoice->update(['deleted_at' => null]);
            $purchaseInvoice->save();
            $register->save();

            $register = PurchaseInvoiceRegister::create([
                'purchase_invoice_id' => $purchaseInvoice->id,
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
     * get products with:name,sale_price,purchase_price,amount available
     * @return JsonResponse
     */
    public function getProductsForInvoice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $products = Repository::with(['products' => function ($q) {
            return $q->select('products.id', 'products.name', 'sale_price', 'purchase_price', 'amount');
        }])->find($request->repository_id)->products;
        return $this->success($products);
    }

    public function getPurchaseInvoiceRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:purchases_invoices,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not see this register');
        $rigister = PurchaseInvoiceRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('purchase_invoice_id', $request->id)->get();
        return $this->success($rigister);
    }

    public function deletePurchaseInvoiceRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:purchase_invoice_registers,purchase_invoice_id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not delete this register');
        PurchaseInvoiceRegister::where('id', $request->id)->delete();
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
            'id' => 'required|numeric|exists:purchases_invoices,id',
            'payment' => 'required|numeric'
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $payment = $request->payment;
        $purchase_invoice = PurchaseInvoice::where('id', $request->id)->first();
        if ($payment > $purchase_invoice->remained) {
            return $this->error('The Payment Value Is Bigger Than Debt,the remained of your payment  is ' . $payment);
        }
        $purchase_invoice->update([
            'paid' => $purchase_invoice->paid + $payment,
            'remained' => $purchase_invoice->remained - $payment,
            ]);
        $purchase_invoice->save();
        PurchaseInvoiceRegister::create([
            'purchase_invoice_id' => $purchase_invoice->id,
            'user_id' => $request->user()->id,
            'type_operation' => 'meet_debt',
        ]);
        return $this->success();
    }

}
