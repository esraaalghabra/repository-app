<?php

namespace App\Http\Controllers;

use App\Models\MoneyBoxRegister;
use App\Models\PurchaseInvoice;
use App\Models\Repository;
use App\Models\RepositoryClient;
use App\Models\RepositorySupplier;
use App\Models\RepositoryUser;
use App\Models\Supplier;
use App\Models\SupplierRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{

    /**
     * get Suppliers with
     * my total debts with them,
     * total purchases price,
     * purchases invoices count
     * @return JsonResponse
     */
    public function getAllSuppliers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $suppliers = Supplier::where('repository_id', $request->repository_id)->get();
        foreach ($suppliers as $supplier) {
            $invoices = PurchaseInvoice::where('supplier_id', $supplier->id)->get();
            $details = $this->getTotalDebts($invoices);
            $supplier->debts = $details['debts'];
            $supplier->invoices_total = $details['invoices_total'];
            $supplier->invoices_count = count($invoices);
            $supplier->photo = asset('assets/images/suppliers/' . $supplier->photo);
        }
        return $this->success($suppliers);
    }

    /**
     * get supplier with:
     * purchases,purchases_invoices,$total purchases price,my debts
     * @param Request $request
     * @return JsonResponse
     */
    public function getSupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suppliers,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $total_purchase_price = 0;
        $all_debts = 0;
        $supplier = Supplier::with('purchases_invoices')->with(['purchases' => function ($q) {
            return $q->with(['product' => function ($q) {
                return $q->select('products.id', 'name');
            }])->select('purchases.id', 'product_id', 'purchases.amount', 'total_purchase_price');
        }])->where('id', $request->id)->first();
        $invoices = $supplier->purchases_invoices;
        foreach ($invoices as $purchaseInvoice) {
            $total_purchase_price += $purchaseInvoice->total_purchase_price;
            $all_debts += $purchaseInvoice->remained;
        }

        $details['purchases'] = $supplier->purchases;
        $details['purchases_invoices'] = $supplier->purchases_invoices;
        $data = Supplier::where('id', $request->id)->first();
        $data['photo'] = asset('assets/images/suppliers/' . $supplier->photo);
        $data['invoices_total'] = $total_purchase_price;
        $data['debts'] = $all_debts;
        $data['details'] = $details;
        return $this->success($data);
    }

    /**
     * create supplier
     * @param Request $request
     * @return JsonResponse
     */
    public function addSupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:30',
            'phone_number' => 'required|string|min:10|max:30|unique:suppliers,phone_number,' . $request->id,
            'address' => 'required|string',
            'photo' => 'mimes:jpg,jpeg,png,jfif',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $supplier = Supplier::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
            'photo' => 'default_supplier.png',
            'repository_id' => $request->repository_id,
        ]);
        if ($request->has('photo')) {
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('suppliers', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $supplier->update([
                'photo' => $path[1]
            ]);
        }
        $register = SupplierRegister::create([
            'supplier_id' => $supplier->id,
            'user_id' => $request->user()->id,
            'name' => $supplier->name,
            'type_operation' => 'add',
        ]);
        return $this->success();
    }

    /**
     * update supplier
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suppliers,id',
            'name' => 'required|string|min:3|max:30',
            'phone_number' => 'required|string|min:10|max:30|unique:suppliers,phone_number,' . $request->id,
            'address' => 'required|string',
            'photo' => 'mimes:jpg,jpeg,png,jfif',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $supplier = Supplier::where('id', $request->id)->first();

        $supplier->update([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
        ]);
        if ($request->has('photo')) {
            if ($supplier->photo != 'default_supplier.png') {
                $image = public_path('assets\images\suppliers\\' . $supplier->photo);
                unlink($image);
            }
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('suppliers', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $supplier->update([
                'photo' => $path[1]
            ]);
        }
        SupplierRegister::create([
            'supplier_id' => $supplier->id,
            'user_id' => $request->user()->id,
            'name' => $supplier->name,
            'type_operation' => 'edit',
        ]);
        return $this->success();
    }

    /**
     * delete supplier
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteSupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suppliers,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $supplier = Supplier::where('id', $request->id)->first();
        if (count($supplier->purchases_invoices) > 0)
            return $this->error('You cannot delete the supplier');
        if ($supplier->photo != 'default_supplier.png') {
            $image = public_path('assets\images\suppliers\\' . $supplier->photo);
            unlink($image);
        }
        $supplier->delete();
        return $this->success();
    }

    /**
     * get total debts
     * @param $invoices
     * @return array
     */
    public function getTotalDebts($invoices): array
    {
        $sum_of_debt = 0;
        $total_of_invoice = 0;
        foreach ($invoices as $purchaseInvoice) {
            $sum_of_debt += $purchaseInvoice->remained;
            $total_of_invoice += $purchaseInvoice->total_price;
        }
        $data['debts'] = $sum_of_debt;
        $data['invoices_total'] = $total_of_invoice;
        return $data;
    }

    /**
     * meet debt for supplier
     * @param Request $request
     * @return JsonResponse
     */
    public function meetDebt(Request $request): JsonResponse
    {
        $i = 0;
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:clients,id',
            'payment' => 'required|numeric'
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $payment = $request->payment;
        $supplier = Supplier::with(['purchases_invoices' => function ($q) {
            return $q->select('id', 'supplier_id', 'paid', 'remained')->latest('date');
        }])->find($request->id);
        $debts = $this->getTotalDebts($supplier->purchases_invoices)['debts'];
        if ($payment > $debts) {
            return $this->error('The Payment Value Is Bigger Than Debt,the remained of your payment  is ' . $payment);
        }
        $purchases_invoices = $supplier->purchases_invoices;
        for ($i = 0; $i < count($purchases_invoices); $i++) {
            $remained = $purchases_invoices[$i]->remained;
            $paid = $purchases_invoices[$i]->paid;
            if ($remained <= $payment) {
                $remained = 0;
                $paid += $purchases_invoices[$i]->remained;
                $payment -= $purchases_invoices[$i]->remained;
                $purchases_invoices[$i]->update([
                    'paid' => $paid,
                    'remained' => $remained,
                ]);
                if ($payment == 0)
                    break;

            } elseif ($purchases_invoices[$i]->remained >= $payment) {
                $purchases_invoices[$i]->remained -= $payment;
                $purchases_invoices[$i]->paid += $payment;
                $purchases_invoices[$i]->update([
                    'paid' => $paid,
                    'remained' => $remained,
                ]);
                $payment = 0;
                break;
            }
        }
        SupplierRegister::create([
            'supplier_id' => $supplier->id,
            'user_id' => $request->user()->id,
            'name' => $supplier->name,
            'type_operation' => 'meet_debt',
        ]);
        return $this->success();
    }

    /**
     * add supplier to archive with: sales_invoices,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function addToArchivesSuppliers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:suppliers,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $supplier = Supplier::find($request->id);
            if (!$supplier)
                return $this->error('the supplier not found');
            foreach ($supplier->purchases_invoices as $purchase_invoice) {
                foreach ($purchase_invoice->purchases as $purchase)
                    $purchase->delete();
                $purchase_invoice->delete();
            }
            $supplier->delete();
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * remove supplier from archive with: register,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFromArchivesSuppliers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:suppliers,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $supplier = Supplier::with(['purchases_invoices' => function ($q) {
                return $q->onlyTrashed()->with(['purchases' => function ($q) {
                    return $q->onlyTrashed();
                }]);
            }])->onlyTrashed()->where('id', $request->id)->first();
            if (!$supplier)
                return $this->error('the client not found in archive');

            foreach ($supplier->purchases_invoices as $purchase_invoice) {
                foreach ($purchase_invoice->purchases as $purchase) {
                    $purchase->update(['deleted_at' => null]);
                    $purchase->save();
                }
                $purchase_invoice->update(['deleted_at' => null]);
                $purchase_invoice->save();
            }
            $supplier->update(['deleted_at' => null]);
            $supplier->save();
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * get supplier in archive with: register,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function getArchivesSuppliers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $suppliers = Repository::with(['suppliers' => function ($q) {
            return $q
                ->onlyTrashed();
        }])->find($request->repository_id)->suppliers;
        if (!$suppliers)
            return $this->error();

        foreach ($suppliers as $supplier) {
            $invoices = PurchaseInvoice::where('supplier_id', $supplier->id)->onlyTrashed()->get();
            $details = $this->getTotalDebts($invoices);
            $supplier->debts = $details['debts'];
            $supplier->invoices_total = $details['invoices_total'];
            $supplier->invoices_count = count($invoices);
        }

        return $this->success($suppliers);
    }

    /**
     * get Archive Supplier
     * @param Request $request
     * @return JsonResponse
     */
    public function getArchiveSupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suppliers,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $supplier = Supplier::with(['purchases_invoices' => function ($q) {
            return $q->onlyTrashed();
        }])
            ->onlyTrashed()->where('id', $request->id)->first();
        if (!$supplier)
            return $this->error('supplier not found in archive');
        $data = Supplier::onlyTrashed()->where('id', $request->id)->first();
        $d = $this->getTotalDebts($supplier->purchases_invoices);
        $data['debts'] = $d['debts'];
        $data['invoices_total'] = $d['invoices_total'];
        $details['purchases_invoices'] = $supplier->purchases_invoices;
        $data['details'] = $details;
        $data['photo'] = asset('assets/images/clients/' . $supplier->photo);
        return $this->success($data);
    }


    public function getSupplierRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:suppliers,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not see this register');
        $rigister = SupplierRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('supplier_id', $request->id)->get();
        return $this->success($rigister);
    }

    public function deleteSupplierRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:supplier_registers,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not delete this register');
        SupplierRegister::where('id', $request->id)->delete();
        return $this->success();
    }

}
