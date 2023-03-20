<?php

namespace App\Http\Controllers;

use App\Models\MoneyBox;
use App\Models\MoneyBoxRegister;
use App\Models\PurchaseInvoice;
use App\Models\Repository;
use App\Models\RepositoryUser;
use App\Models\SaleInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Type\Integer;

class MoneyBoxController extends Controller
{
    /**
     * get registers sales|purchases|expenses|cash
     * @param Request $request
     * @return JsonResponse
     */
    public function getInvoicesRegisters(Request $request): JsonResponse
    {
        if ($request->type_money == 'cash') {
            $MoneyCache = MoneyBox::where('type_money', 'add_cash')->orWhere('type_money', 'withdrawal_cash')->get();
            return $this->success($MoneyCache);
        } elseif ($request->type_money == 'sales') {
            $moneySales = MoneyBox::with(['saleInvoice' => function ($q) {
                return $q->select('id', 'number', 'register_id');
            }])->where('type_money', 'sales')->get();
            return $this->success($moneySales);
        } elseif ($request->type_money == 'purchases') {
            $moneyPurchases = MoneyBox::with(['purchaseInvoice' => function ($q) {
                return $q->select('id', 'number', 'register_id');
            }])->where('type_money', 'purchases')->get();
            return $this->success($moneyPurchases);
        } elseif ($request->type_money == 'expenses') {
            $moneyExpenses = MoneyBox::where('type_money', 'expenses')->get();
            return $this->success($moneyExpenses);
        } else
            return $this->error('you type_money not found');
    }

    /**
     * create register:add_cash|withdrawal_cash
     * @param Request $request
     * @return JsonResponse
     */
    public function addOrRemoveCashMoney(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(),
            [
                'total_price' => 'required|numeric',
                'date' => 'required|string',
                'repository_id' => 'required|exists:repositories,id',
            ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        if ($request->total_price > 0) {
            $register =  MoneyBox::create([
                'type_money' => 'add_cash',
                'total_price' => $request->total_price,
                'date' => $request->date,
                'repository_id' => $request->repository_id,
            ]);
        } elseif ($request->total_price < 0) {
            if ($this->getTotalBox($request->repository_id) < $request->total_price)
                return $this->error('your money not enough');
            $register =  MoneyBox::create([
                'type_money' => 'withdrawal_cash',
                'total_price' => -$request->total_price,
                'date' => $request->date,
                'repository_id' => $request->repository_id,
            ]);
        }else{
            return $this->error('you must to added amount of money');

        }
        MoneyBoxRegister::create([
            'money_box_id' => $register->id,
            'user_id' => $request->user()->id,
            'type_operation' => $register->type_money,
        ]);
        return $this->success();


    }

    /**
     * delete register
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteRegister(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(),
            [
                'id' => 'required|numeric|exists:money_box,id',
            ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        MoneyBox::find($request->id)->forceDelete();
        return $this->success();
    }

    /**
     * update register
     * @param Request $request
     * @return JsonResponse
     */
    public function updateRegister(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(),
            [
                'id' => 'required|numeric|exists:money_box,id',
                'total_price' => 'required|numeric',
                'date' => 'required|string',
            ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $register = MoneyBox::find($request->id);
        $register->update([
            'type_money' => $request->type_money,
            'total_price' => $request->total_price,
            'date' => $request->date,
        ]);
        MoneyBoxRegister::create([
            'client_id' => $register->id,
            'user_id' => $request->user()->id,
            'type_operation' => $request->type_money,
        ]);
        return $this->success();
    }

    /**
     * get invoice for register
     * @param Request $request
     * @return JsonResponse
     */
    public function getRegisterInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:money_box,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $register = MoneyBox::where('id', $request->id)->first();
        $invoice = null;
        if ($register->type_money == 'purchases')
            $invoice = PurchaseInvoice::where('register_id', $register->id)->first();
        elseif ($register->type_money == 'sales')
            $invoice = SaleInvoice::where('register_id', $register->id)->first();
        if (!$invoice)
            return $this->error();
        return $this->success($invoice);
    }

    /**
     * get total profits between tow date
     * @return JsonResponse
     */
    public function getProfits(Request $request)
    {
        $profits = 0;
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $totalAdd = 0;
        $totalRemove = 0;
        $all = Repository::with(['categories' => function ($q) use ($request) {
            return $q->with('sales');
        }])->with('registers')->find($request->repository_id);
        $money = $all->registers;
        $categories = $all->categories;
        foreach ($categories as $category) {
            foreach ($category->sales as $sale)
                $profits += $sale->total_sale_price - $sale->total_purchase_price;
        }
        foreach ($money as $one) {
            if ($one->type_money == 'add_cash' || $one->type_money == 'sales')
                $totalAdd += $one->total_price;
            elseif ($one->type_money == 'withdrawal_cash' || $one->type_money == 'expenses' || $one->type_money == 'purchases')
                $totalRemove += $one->total_price;
        }
        $details['total'] = $totalAdd - $totalRemove;
        $details['profits'] = $profits;
        $date = $details;
        return $this->success($date);
    }
    public function getRegisterRegisters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:money_box,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin!=1)
            return $this->error('ypu can not see this register');
        $rigister = MoneyBoxRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('money_box_id', $request->id)->get();
        return $this->success($rigister);
    }
    public function deleteRegisterRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:money_box_registers,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin!=1)
            return $this->error('ypu can not delete this register');
        MoneyBoxRegister::where('id', $request->id)->delete();
        return $this->success();
    }

}
