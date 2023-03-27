<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRegister;
use App\Models\Repository;
use App\Models\RepositoryUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{

    /**
     * get products with category name
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllProducts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'repository_id' => 'required|exists:repositories,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        $products = Repository::with(['products' => function ($q) {
            return $q->with(['category' => function ($q) {
                return $q->select('id', 'name');
            }]);
        }])->find($request->repository_id)->products;

        foreach ($products as $product) {
            $product->photo = asset('assets/images/products/' . $product->photo);
        }
        return $this->success($products);
    }

    /**
     * get product with:sales,purchases
     * @param Request $request
     * @return JsonResponse
     */
    public function getProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:products,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $product = Product::where('id', $request->id)->first();
        $details['sales'] = $product->sales;
        $details['purchases'] = $product->purchases;
        $data = Product::with(['category' => function ($q) {
            return $q->select('id', 'name');
        }])->where('id', $request->id)->first();
        $data['photo'] = asset('assets/images/products/' . $product->photo);
        $data['details'] = $details;
        return $this->success($data);
    }

    /**
     * create product
     * @param Request $request
     * @return JsonResponse
     */
    public function addProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(),
            [
                'name' => 'required|string|min:3|max:30',
                'category_id' => 'required|numeric|exists:categories,id',
                'purchase_price' => 'required|numeric',
                'sale_price' => 'required|numeric',
                'measuring_unit' => 'required|string',
                'photo' => 'mimes:jpg,jpeg,png,jfif',
            ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());

        if ($request->sale_price <= $request->purchase_price)
            return $this->error('You cannot sell the product for less than you bought it');
        $product = Product::create([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'purchase_price' => $request->purchase_price,
            'sale_price' => $request->sale_price,
            'amount' => 0,
            'measuring_unit' => $request->measuring_unit,
            'photo' => 'default_product.png',
        ]);
        if ($request->has('photo')) {
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('products', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $product->update([
                'photo' => $path[1]
            ]);
        }

        $register = ProductRegister::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'name' => $product->name,
            'type_operation' => 'add',
        ]);
        return $this->success();
    }

    /**
     * update product
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:products,id',
            'name' => 'required|string|min:3|max:30',
            'category_id' => 'required|numeric|exists:categories,id',
            'purchase_price' => 'required|numeric',
            'sale_price' => 'required|numeric',
            'measuring_unit' => 'required|string',
            'photo' => 'mimes:jpg,jpeg,png,jfif',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $product = Product::where('id', $request->id)->first();
        if ($request->sale_price <= $request->purchase_price)
            return $this->error('You cannot sell the product for less than you bought it');

        $product->update([
            'name' => $request->name,
            'category_id' => $request->category_id,
            'purchase_price' => $request->purchase_price,
            'sale_price' => $request->sale_price,
            'measuring_unit' => $request->measuring_unit,
        ]);
        if ($request->has('photo')) {
            if ($product->photo != 'default_product.png') {
                $image = public_path('assets\images\products\\' . $product->photo);
                unlink($image);
            }
            $name = explode(' ', $request->name);
            $path = $request->file('photo')->storeAs('products', $name[0] . '.' . $request->file('photo')->extension(), 'images');
            $path = explode('/', $path);
            $product->update([
                'photo' => $path[1]
            ]);
        }

        ProductRegister::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'name' => $product->name,
            'type_operation' => 'edit',
        ]);
        return $this->success();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:products,id',
        ]);
        if ($validator->fails())
            return $this->error($validator->errors()->first());
        $product = Product::where('id', $request->id)->first();
        if (count($product->sales) > 0 || count($product->purchases) > 0)
            return $this->error('You cannot delete the product');
        if ($product->photo != 'default_product.png') {
            $image = public_path('assets\images\products\\' . $product->photo);
            unlink($image);
        }
        $product->delete();
        return $this->success();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function GetProductRegister(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|numeric|exists:products,id',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }
        $is_admin = RepositoryUser::where('user_id', $request->user()->id)->first();
        if ($is_admin->is_admin != 1)
            return $this->error('ypu can not see this register');
        $rigister = ProductRegister::with(['user' => function ($q) {
            return $q->select('id', 'name');
        }])->where('product_id', $request->id)->get();
        return $this->success($rigister);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteProductRegister(Request $request): JsonResponse
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
        ProductRegister::where('id', $request->id)->delete();
        return $this->success();
    }
}
