<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryRegister;
use App\Models\Product;
use App\Models\RepositoryUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * get categories with:products_amount,sales_amount,purchases_amount
     * @return
     */
    public function getAllCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $data = [];
        $categories = Category::where('repository_id', $request->repository_id)->get();
        for ($i = 0; $i < count($categories); $i++) {
            $sum_of_products = 0;
            $sum_of_sales = 0;
            $sum_of_purchases = 0;
            $categories[$i]->photo = asset('assets/images/categories/' . $categories[$i]->photo);
            $products = Product::with('sales')->with('purchases')->where('category_id', $categories[$i]->id)->get();
            foreach ($products as $product) {
                $sum_of_products += $product->amount;
                foreach ($product->sales as $sale) {
                    $sum_of_sales += $sale->amount;
                }
                foreach ($product->purchases as $purchase) {
                    $sum_of_purchases += $purchase->amount;
                }
            }
            $data[$i] = $categories[$i];
            $data[$i]->products_amount = $sum_of_products;
            $data[$i]->sales_amount = $sum_of_sales;
            $data[$i]->purchases_amount = $sum_of_purchases;
        }
        return $this->success($data);
    }

    /**
     * get category with:
     * products,sales,purchases
     * products_amount,sales_amount,purchases_amount
     * @param Request $request
     * @return JsonResponse
     */
    public function getCategory(Request $request)
    {
        $sum_of_products = 0;
        $sum_of_sales = 0;
        $sum_of_purchases = 0;
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:categories,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        try {
            $category = Category::with(['products' => function ($q) {
                return $q->select('id', 'category_id', 'name', 'amount', 'purchase_price', 'sale_price');
            }])->with(['sales' => function ($q) {
                return $q->with(['product' => function ($q) {
                    return $q->select('products.id', 'name', 'sale_price');
                }])->select('sales.id', 'product_id', 'sales.amount', 'total_sale_price');
            }])->with(['purchases' => function ($q) {
                return $q->with(['product' => function ($q) {
                    return $q->select('products.id', 'name', 'purchase_price');
                }])->select('purchases.id', 'product_id', 'purchases.amount', 'total_purchase_price');
            }])->where('id', $request->id)->first();
            foreach ($category->products as $product) {
                $sum_of_products += $product->amount;
            }
            foreach ($category->sales as $sale) {
                $sum_of_sales += $sale->amount;
            }
            foreach ($category->purchases as $purchase) {
                $sum_of_purchases += $purchase->amount;
            }
            $details['products'] = $category->products;
            $details['sales'] = $category->sales;
            $details['purchases'] = $category->purchases;
            $data = Category::where('id', $request->id)->first();
            $data['photo'] = asset('assets/images/categories/' . $category->photo);
            $data['products_amount'] = $sum_of_products;
            $data['sales_amount'] = $sum_of_sales;
            $data['purchases_amount'] = $sum_of_purchases;
            $data['details'] = $details;

            return $this->success($data);
        } catch (\Exception $e) {
            DB::rollback();
            return $this->error($e);
        }
    }

    /**
     * create category
     * @param Request $request
     * @return JsonResponse
     */
    public function addCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:30',
            'photo' => 'mimes:jpg,jpeg,png,jfif',
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $category = Category::create([
            'name' => $request->name,
            'photo' => 'default_category.png',
            'repository_id' => $request->repository_id,
        ]);
        if ($request->has('photo')) {
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('categories', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $category->update([
                'photo' => $path[1]
            ]);
        }
        $register = CategoryRegister::create([
            'category_id' => $category->id,
            'user_id' => $request->user()->id,
            'name' => $category->name,
            'type_operation' => 'add',
        ]);
        return $this->success();
    }

    /**
     * update category
     * @param Request $request
     * @return JsonResponse
     */
    public function updateCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:categories,id',
            'name' => 'required|string|min:3|max:30|unique:categories,name,' . $request->id,
            'photo' => 'mimes:jpg,jpeg,png,jfif',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $category = Category::where('id', $request->id)->first();
        $category->update([
            'name' => $request->name,
        ]);
        if ($request->has('photo')) {
            if ($category->photo != 'default_category.png') {
                $image = public_path('assets\images\categories\\' . $category->photo);
                unlink($image);
            }
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('categories', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $category->update([
                'photo' => $path[1]
            ]);
        }
        CategoryRegister::create([
            'category_id' => $category->id,
            'user_id' => $request->user()->id,
            'name' => $category->name,
            'type_operation' => 'edit',
        ]);
        return $this->success();
    }

    /**
     * delete category
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:categories,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $category = Category::where('id', $request->id)->first();
        if ($category->photo != 'default_category.png') {
            $image = public_path('assets\images\categories\\' . $category->photo);
            unlink($image);
        }
        if (count($category->products) > 0 ||
            count($category->sales) > 0 ||
            count($category->purchases) > 0)
            return $this->error('You cannot delete the repository');
        $category->delete();
        return $this->success();
    }


    public function getCategoryRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:categories,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not see this register');
        $rigister = CategoryRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('category_id', $request->id)->get();
        return $this->success($rigister);
    }

    public function deleteCategoryRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:category_registers,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not delete this register');
        CategoryRegister::where('id', $request->id)->delete();
        return $this->success();
    }
}
