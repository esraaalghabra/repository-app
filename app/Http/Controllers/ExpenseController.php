<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseRegister;
use App\Models\MoneyBox;
use App\Models\RepositoryUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    /**
     * get expenses
     * @return JsonResponse
     */
    public function getAllExpenses(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $expenses = Expense::where('repository_id', $request->repository_id)->get();
        return $this->success($expenses);
    }

    /**
     * create expense
     * @param Request $request
     * @return JsonResponse
     */
    public function addExpense(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required',
                'total_price' => 'required|numeric',
                'paid' => 'required|numeric',
                'date' => 'required',
                'remained' => 'required',
                'repository_id' => 'required|exists:repositories,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            if ($this->getTotalBox($request->repository_id) < $request->total_price)
                return $this->error();
            DB::beginTransaction();
            $register = MoneyBox::create([
                'type_money' => 'expenses',
                'is_finished' => $request->remained > 0 ? 0 : 1,
                'total_price' => $request->paid,
                'date' => $request->date,
                'repository_id' => $request->repository_id,
            ]);
            $expense = Expense::create([
                'name' => $request->name,
                'details' => $request->details,
                'total_price' => $request->total_price,
                'paid' => $request->paid,
                'remained' => $request->remained,
                'date' => $request->date,
                'repository_id' => $request->repository_id,
                'register_id' => $register->id,
            ]);
            ExpenseRegister::create([
                'expense_id' => $expense->id,
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
     * update expense
     * @param Request $request
     * @return JsonResponse
     */
    public function updateExpense(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:expenses,id',
                'name' => 'required|string',
                'total_price' => 'required|numeric',
                'paid' => 'required|numeric',
                'date' => 'required',
                'remained' => 'required|numeric',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());

            $expense = Expense::with('register')->where('id', $request->id)->first();

            $expense->update([
                'name' => $request->name,
                'total_price' => $request->total_price,
                'paid' => $request->paid,
                'remained' => $request->remained,
                'date' => $request->date,
            ]);
            $expense->register->update([
                'total_price' => $request->paid,
                'date' => $request->date,
                'is_finished' => $request->remained > 0 ? 0 : 1,
            ]);
            $expense->register->save();

            ExpenseRegister::create([
                'expense_id' => $expense->id,
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
     * delete expense
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteExpense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:expenses,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $expense = Expense::with('register')->where('id', $request->id)->first();
        $expense->register->forceDelete();
        $expense->forceDelete();
        return $this->success();
    }

    /**
     * get expenses between tow date
     * @param Request $request
     * @return JsonResponse
     */
    public function getExpensesBetweenTowDate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $expenses = Expense::
        whereBetween('date', [$request->start_date, $request->end_date])
            ->where('repository_id', $request->repository_id)->get();
        return $this->success($expenses);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getExpenseRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:expense_registers,expense_id',

        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin!=1)
            return $this->error('ypu can not see this register');
        $rigister = ExpenseRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('expense_id', $request->id)->get();
        return $this->success($rigister);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteExpenseRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:expenses,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin!=1)
            return $this->error('ypu can not delete this register');
        ExpenseRegister::where('expense_id', $request->id)->delete();
        return $this->success();
    }

    /**
     * meet debt for Expense
     * @param Request $request
     * @return JsonResponse
     */
    public function meetDebt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:expenses,id',
            'payment' => 'required|numeric'
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $payment = $request->payment;
        $expense = Expense::where('id', $request->id)->first();
        if ($payment > $expense->remained) {
            return $this->error('The Payment Value Is Bigger Than Debt,the remained of your payment  is ' . $payment);
        }
        $expense->update([
            'paid' => $expense->paid + $payment,
            'remained' => $expense->remained - $payment,
        ]);
        $expense->save();
        ExpenseRegister::create([
            'expense_id' => $expense->id,
            'user_id' => $request->user()->id,
            'type_operation' => 'meet_debt',
        ]);
        return $this->success();
    }

}
