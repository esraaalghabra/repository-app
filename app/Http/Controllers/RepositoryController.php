<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Models\RepositoryUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RepositoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum', ['except' => ['addRepository', 'joinRepository']]);
    }

    public function getRepositories()
    {
        $repositories = Repository::get();
        return $this->success($repositories);
    }

    public function addRepository(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:30',
            'code' => 'required|string|min:5|max:10|unique:repositories,code,',
            'address' => 'required|string',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $repository = Repository::create([
            'name' => $request->name,
            'code' => $request->code,
            'address' => $request->address,
        ]);

        RepositoryUser::create([
            'repository_id' => $repository->id,
            'user_id' => $request->user()->id,
            'is_admin' => 1,
        ]);
        return $this->success($repository, 'User has been register successfully to his repository');

    }

    public function updateRepository(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:repositories,id',
            'name' => 'required|string|min:3|max:30',
            'address' => 'required|string',
            'code' => 'required|string|min:5|max:10|unique:repositories,code,' . $request->id,
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $repository = Repository::find($request->id);
        $repository->update([
            'name' => $request->name,
            'address' => $request->address,
            'code' => $request->code,
        ]);
        return $this->success();
    }

    public function deleteRepository(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $repository = Repository::find($request->id);
        if (count($repository->categories) > 0 ||
            count($repository->registers) > 0 ||
            count($repository->suppliers) > 0 ||
            count($repository->clients) > 0 ||
            count($repository->expenses) > 0)
            return $this->error('You cannot delete the repository');
        $repository->delete();
        return $this->success();
    }
    public function joinRepository(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|exists:repositories,code',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $repository = Repository::where('code', $request->code)->first();
        $repository_user = RepositoryUser::where('repository_id', $repository->id)->where('user_id', $request->user()->id)->first();
        if (!$repository_user)
            $repository_user = RepositoryUser::create([
                'repository_id' => $repository->id,
                'user_id' => $request->user()->id,
                'is_admin' => 0,
            ]);
        return $this->success($repository);
    }
    public function getRepositoriesForUser()
    {
        $repositories = User::with('repositories')->find(auth()->user()->id)->repositories;
        return $this->success($repositories);
    }

    public function getUsersForRepository(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|string|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $repository = Repository::with(['users' => function ($q) {
            return $q->select('users.id', 'name', 'email', 'photo');
        }])->where('id', $request->repository_id)->select('id')->first();
        foreach ($repository->users as $user) {
            $is_admin = RepositoryUser::where('repository_id', $request->repository_id)->where('user_id', $user->id)->first();
            $user->is_admin = $is_admin->is_admin;
        }
        return $this->success($repository->users);
    }

}
