<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientRegister;
use App\Models\Repository;
use App\Models\RepositoryCategory;
use App\Models\RepositoryClient;
use App\Models\RepositoryUser;
use App\Models\SaleInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * get clients with:debts,invoices_total_price,invoices_count
     * @return JsonResponse
     */
    public function getAllClients(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $clients = Client::where('repository_id',$request->repository_id)->get();
        foreach ($clients as $client) {
            $client->photo = asset('assets/images/clients/' . $client->photo);
            $invoices = SaleInvoice::where('client_id', $client->id)->get();
            $details = $this->getTotalDebts($invoices);
            $client->debts = $details['debts'];
            $client->invoices_total = $details['invoices_total'];
            $client->invoices_count = count($invoices);
        }
        return $this->success($clients);
    }

    /**
     * get client with:sales_invoices,sales,debts
     * @param Request $request
     * @return JsonResponse
     */
    public function getClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:clients,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $client = Client::with(['sales' => function ($q) {
            return $q->with(['product' => function ($q) {
                return $q->select('products.id', 'name');
            }])->select('sales.id', 'product_id', 'sales.amount', 'total_sale_price');
        }])->with('sales_invoices')->where('id', $request->id)->first();

        $d = $this->getTotalDebts($client->sales_invoices);
        $details['sales_invoices'] = $client->sales_invoices;
        $details['sales'] = $client->sales;
        $data = Client::where('id', $request->id)->first();
        $data['photo'] = asset('assets/images/clients/' . $client->photo);
        $data['debts'] = $d['debts'];
        $data['invoices_total'] = $d['invoices_total'];
        $data['details'] = $details;
        return $this->success($data);
    }

    /**
     * create client
     * @param Request $request
     * @return JsonResponse
     */
    public function addClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:30',
            'phone_number' => 'required|string|min:10|max:30|unique:clients',
            'address' => 'required|string',
            'photo' => 'mimes:jpg,jpeg,png,jfif',
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $client = Client::create([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
            'photo' => 'default_client.png',
            'repository_id' => $request->repository_id,

        ]);

        if ($request->has('photo')) {
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('clients', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $client->update([
                'photo' => $path[1]
            ]);
        }

        ClientRegister::create([
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'name' => $client->name,
            'type_operation' => 'add',
        ]);
        return $this->success();
    }

    /**
     * update client
     * @param Request $request
     * @return JsonResponse
     */
    public function updateClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:clients,id',
            'name' => 'required|string|min:3|max:30',
            'phone_number' => 'required|string|min:10|max:30|unique:clients,phone_number,' . $request->id,
            'address' => 'required|string',
            'photo' => 'mimes:jpg,jpeg,png,jfif',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $client = Client::where('id', $request->id)->first();

        $client->update([
            'name' => $request->name,
            'phone_number' => $request->phone_number,
            'address' => $request->address,
        ]);
        if ($request->has('photo')) {
            if ($client->photo != 'default_client.png') {
                $image = public_path('assets\images\clients\\' . $client->photo);
                unlink($image);
            }
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('clients', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $client->update([
                'photo' => $path[1]
            ]);
        }
        $register = ClientRegister::create([
            'category_id' => $client->id,
            'user_id' => $request->user()->id,
            'name' => $client->name,
            'type_operation' => 'update',
        ]);
        return $this->success();
    }

    /**
     * delete client
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteClient(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:clients,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $client = Client::where('id', $request->id)->first();
        if (count($client->sales_invoices) > 0)
            return $this->error('You cannot delete the client');
        if ($client->photo != 'default_client.png') {
            $image = public_path('assets\images\clients\\' . $client->photo);
            unlink($image);
        }
        $client->delete();
        return $this->success();
    }

    /**
     * get_total_debts
     * @param $invoices
     * @return array
     */
    public function getTotalDebts($invoices): array
    {
        $sum_of_debt = 0;
        $total_of_invoice = 0;
        foreach ($invoices as $saleInvoice) {
            $sum_of_debt += $saleInvoice->remained;
            $total_of_invoice += $saleInvoice->total_price;
        }
        $data['debts'] = $sum_of_debt;
        $data['invoices_total'] = $total_of_invoice;
        return $data;
    }

    /**
     * meet debt for client
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
        $client = Client::with(['sales_invoices' => function ($q) {
            return $q->select('id', 'client_id', 'paid', 'remained')->latest('date');
        }])->find($request->id);
        $debts = $this->getTotalDebts($client->sales_invoices)['debts'];
        if ($payment > $debts) {
            return $this->error('The Payment Value Is Bigger Than Debt,the remained of your payment  is ' . $payment);
        }
        $sales_invoices = $client->sales_invoices;
        for ($i = 0; $i < count($sales_invoices); $i++) {
            $remained = $sales_invoices[$i]->remained;
            $paid = $sales_invoices[$i]->paid;
            if ($remained <= $payment) {
                $remained = 0;
                $paid += $sales_invoices[$i]->remained;
                $payment -= $sales_invoices[$i]->remained;
                $sales_invoices[$i]->update([
                    'paid' => $paid,
                    'remained' => $remained,
                ]);
                if ($payment == 0)
                    break;

            } elseif ($sales_invoices[$i]->remained >= $payment) {
                $sales_invoices[$i]->remained -= $payment;
                $sales_invoices[$i]->paid += $payment;
                $sales_invoices[$i]->update([
                    'paid' => $paid,
                    'remained' => $remained,
                ]);
                $payment = 0;
                break;
            }
        }
        ClientRegister::create([
            'client_id' => $client->id,
            'user_id' => $request->user()->id,
            'name' => $client->name,
            'type_operation' => 'meet_debt',
        ]);
        return $this->success();
    }

    /**
     * add clients to archive with: sales_invoices,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function addToArchivesClients(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:clients,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $client = Client::find($request->id);
            if (!$client)
                return $this->error('the client not found');
            foreach ($client->sales_invoices as $sale_invoice) {
                $sale_invoice->delete();
            }
            foreach ($client->sales as $sale)
                $sale->delete();
            $client->delete();
            $register = ClientRegister::create([
                'client_id' => $client->id,
                'user_id' => $request->user()->id,
                'name' => $client->name,
                'type_operation' => 'add_to_archive',
            ]);
            DB::commit();
            return $this->success();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }

    }

    /**
     * remove client from archive with: register,sales
     * @param Request $request
     * @return JsonResponse
     */
    public function removeFromArchivesClients(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|numeric|exists:clients,id',
            ]);
            if ($validator->fails())
                return $this->error($validator->errors()->first());
            DB::beginTransaction();
            $client = Client::with(['sales_invoices' => function ($q) {
                return $q->onlyTrashed()->with(['sales' => function ($q) {
                    return $q->onlyTrashed();
                }]);
            }])->onlyTrashed()->where('id', $request->id)->first();
            if (!$client)
                return $this->error('the client not found in archive');

            foreach ($client->sales_invoices as $sale_invoice) {
                foreach ($sale_invoice->sales as $sale) {
                    $sale->update(['deleted_at' => null]);
                    $sale->save();
                }
                $sale_invoice->update(['deleted_at' => null]);
                $sale_invoice->save();
            }
            $client->update(['deleted_at' => null]);
            $client->save();
            $register = ClientRegister::create([
                'client_id' => $client->id,
                'user_id' => $request->user()->id,
                'name' => $client->name,
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
     * get client in archive with: register,sales
     * @return JsonResponse
     */
    public function getArchivesClients(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $clients = Client::where('repository_id',$request->repository_id)->onlyTrashed()->get();
        if (!$clients)
            return $this->error();
        foreach ($clients as $client) {
            $client->photo = asset('assets/images/clients/' . $client->photo);
            $invoices = SaleInvoice::onlyTrashed()->where('client_id', $client->id)->get();
            $details = $this->getTotalDebts($invoices);
            $client->debts = $details['debts'];
            $client->invoices_total = $details['invoices_total'];
            $client->invoices_count = count($invoices);
        }
        return $this->success($clients);
    }

    /**
     * get Archive Client
     * @param Request $request
     * @return JsonResponse
     */
    public function getArchiveClient(Request $request): JsonResponse
    {
        $client = Client::with(['sales_invoices' => function ($q) {
            return $q->onlyTrashed();
        }])
            ->onlyTrashed()->where('id', $request->id)->first();
        $data = Client::onlyTrashed()->where('id', $request->id)->first();
        $d = $this->getTotalDebts($client->sales_invoices);
        $data['debts'] = $d['debts'];
        $data['invoices_total'] = $d['invoices_total'];
        $details['sales_invoices'] = $client->sales_invoices;
        $data['details'] = $details;
        $data['photo'] = asset('assets/images/clients/' . $client->photo);
        return $this->success($data);
    }


    public function getClientRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:clients,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin!=1)
            return $this->error('ypu can not see this register');
        $rigister = ClientRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('client_id', $request->id)->get();
        return $this->success($rigister);
    }
    public function deleteClientRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:client_registers,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin!=1)
            return $this->error('ypu can not delete this register');
        ClientRegister::where('id', $request->id)->delete();
        return $this->success();
    }

}
