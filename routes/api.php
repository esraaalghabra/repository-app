<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MoneyBoxController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SaleInvoiceController;
use App\Http\Controllers\StocktakingController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::prefix('auth')->as('auth.')
    ->group(function (){
        Route::post('login',[UserController::class,'login'])->name('login');

        Route::post('register',[UserController::class,'register'])->name('register');
        Route::post('login_with_token',[UserController::class,'loginWithToken'])
            ->middleware('auth:sanctum')
            ->name('login_with_token');
        Route::get('logout',[UserController::class,'logout'])
            ->middleware('auth:sanctum')
            ->name('logout');
        Route::post('add_info',[UserController::class,'addInfo'])
            ->middleware('auth:sanctum')
            ->name('add_info');
        Route::post('add_admin',[UserController::class,'addAdmin'])
            ->middleware('auth:sanctum')
            ->name('add_admin');

    });
Route::middleware('auth:sanctum')->group(function () {
    Route::controller(CategoryController::class)->prefix('category')->group(function () {
        Route::post('get_all_categories', 'getAllCategories');
        Route::post('add_category', 'addCategory');
        Route::post('get_category', 'getCategory');
        Route::post('update_category', 'updateCategory');
        Route::post('delete_category', 'deleteCategory');
        Route::post('add_to_archives_category', 'addToArchivesCategory');
        Route::post('get_to_archives_category', 'getArchivesCategory');
        Route::post('remove_to_archives_category', 'removeToArchivesCategory');
        Route::post('get_category_register', 'GetCategoryRegister');
        Route::post('delete_category_register', 'deleteCategoryRegister');
    });

    Route::controller(RepositoryController::class)->prefix('repository')->group(function () {
        Route::post('add_repository', 'addRepository');
        Route::get('get_repository', 'getRepositories');
        Route::post('update_repository', 'updateRepository');
        Route::post('check_delete_repository', 'checkDeleteRepository');
        Route::post('delete_repository', 'deleteRepository');
        Route::post('join_repository', 'joinRepository');
        Route::get('get_repositories_for_user', 'getRepositoriesForUser');
        Route::post('get_user_for_repository', 'getUsersForRepository');
    });

    Route::controller(ProductController::class)->prefix('product')->group(function () {
        Route::post('get_all_products', 'getAllProducts');
        Route::post('add_product', 'addProduct');
        Route::post('get_product', 'getProduct');
        Route::post('update_product', 'updateProduct');
        Route::post('delete_product', 'deleteProduct');
        Route::post('add_to_archives_category', 'addToArchivesProduct');
        Route::post('get_to_archives_category', 'getArchivesProduct');
        Route::post('remove_to_archives_category', 'removeToArchivesProduct');
        Route::post('get_product_register', 'GetProductRegister');
        Route::post('delete_product_register', 'deleteProductRegister');
    });

    Route::controller(ClientController::class)->prefix('client')->group(function () {
        Route::post('get_all_clients', 'getAllClients');
        Route::post('add_client', 'addClient');
        Route::post('get_client', 'getClient');
        Route::post('update_client', 'updateClient');
        Route::post('delete_client', 'deleteClient');
        Route::post('meet_debt', 'meetDebt');
        Route::post('add_to_archives_clients', 'addToArchivesClients');
        Route::post('remove_to_archives_clients', 'removeFromArchivesClients');
        Route::post('get_archives_clients', 'getArchivesClients');
        Route::post('get_archive_client', 'getArchiveClient');
        Route::post('get_client_register', 'GetClientRegister');
        Route::post('delete_client_register', 'deleteClientRegister');

    });

    Route::controller(Controller::class)->prefix('monitoring')->group(function () {
        Route::post('get', 'monitoring');
    });

    Route::controller(SupplierController::class)->prefix('supplier')->group(function () {
        Route::post('get_all_suppliers', 'getAllSuppliers');
        Route::post('add_supplier', 'addSupplier');
        Route::post('get_supplier', 'getSupplier');
        Route::post('update_supplier', 'updateSupplier');
        Route::post('delete_supplier', 'deleteSupplier');
        Route::post('meet_debt', 'meetDebt');
        Route::post('add_to_archives_suppliers', 'addToArchivesSuppliers');
        Route::post('remove_to_archives_suppliers', 'removeFromArchivesSuppliers');
        Route::post('get_archives_suppliers', 'getArchivesSuppliers');
        Route::post('get_supplier_register', 'GetSupplierRegister');
        Route::post('delete_supplier_register', 'deleteSupplierRegister');
    });

    Route::controller(PurchaseInvoiceController::class)->prefix('purchase_invoice')->group(function () {
        Route::post('get_products_invoice', 'getProductsForInvoice');
        Route::post('get_purchases_invoices_between_tow_date', 'getPurchasesInvoicesBetweenTowDate');
        Route::post('get_purchase_invoice', 'getPurchaseInvoice');
        Route::post('remove_to_archives_purchase_invoice', 'removeToArchivesPurchaseInvoice');
        Route::post('get_all_purchases_invoices', 'getAllPurchasesInvoices');
        Route::post('get_purchases_invoices', 'get_purchases_invoice');
        Route::post('add_purchase_invoice', 'addPurchaseInvoice');
        Route::post('update_purchase_invoice', 'updatePurchaseInvoice');
        Route::post('delete_purchase_invoice', 'deletePurchaseInvoice');
        Route::post('add_to_archives_purchase_invoice', 'addToArchivesPurchaseInvoice');
        Route::post('get_archives_purchases_invoices', 'getArchivesPurchasesInvoices');
        Route::post('get_archive_purchases_invoice', 'getArchivePurchasesInvoice');
        Route::post('get_purchase_invoice_register', 'getPurchaseInvoiceRegister');
        Route::post('delete_purchase_invoice_register', 'deletePurchaseInvoiceRegister');
        Route::post('meet_debt_purchase_invoice', 'meetDebt');

    });

    Route::controller(SaleInvoiceController::class)->prefix('sale_invoice')->group(function () {
        Route::post('get_sale_invoice_between_tow_date', 'getSalesInvoiceBetweenTowDate');
        Route::post('get_sale_invoice', 'getSaleInvoice');
        Route::post('get_all_sales_invoices', 'getAllSalesInvoices');
        Route::post('remove_to_archives_sale_invoice', 'removeToArchivesSaleInvoice');
        Route::post('add_sale_invoice', 'addSaleInvoice');
        Route::post('update_sale_invoice', 'updateSaleInvoice');
        Route::post('delete_sale_invoice', 'deleteSaleInvoice');
        Route::post('add_to_archives_sale_invoice', 'addToArchivesSalesInvoice');
        Route::post('get_archives_sales_invoices', 'getArchivesSaleInvoices');
        Route::post('get_archive_sales_invoice', 'getArchiveSaleInvoice');
        Route::post('get_sale_invoice_register', 'getSaleInvoiceRegister');
        Route::post('delete_sale_invoice_register', 'deleteSaleInvoiceRegister');
        Route::post('meet_debt_sale_invoice', 'meetDebt');

    });

    Route::controller(ExpenseController::class)->prefix('expense')->group(function () {
        Route::post('get_all_expenses', 'getAllExpenses');
        Route::post('add_expense', 'addExpense');
        Route::post('update_expense', 'updateExpense');
        Route::post('delete_expense', 'deleteExpense');
        Route::post('get_expense_register', 'getExpenseRegister');
        Route::post('delete_expense_register', 'deleteExpenseRegister');
        Route::post('meet_debt_expense', 'meetDebt');

    });

    Route::controller(MoneyBoxController::class)->prefix('register')->group(function () {
        Route::post('get_total_box', 'getTotalBox');
        Route::post('get_push_or_pull_registers', 'getPushOrPullRegisters');
        Route::post('add_or_remove_cash', 'addOrRemoveCashMoney');
        Route::post('get_register_invoice', 'getRegisterInvoice');
        Route::post('update_register', 'updateRegister');
        Route::post('delete_register', 'deleteRegister');
        Route::post('get_box', 'getProfits');
        Route::post('get_register_registers', 'getRegisterRegisters');
        Route::post('delete_register_register', 'deleteRegisterRegister');

    });

    Route::controller(StocktakingController::class)->prefix('stocktaking')->group(function () {
        Route::post('category', 'stocktakingCategory');
        Route::post('product', 'stocktakingProduct');
        Route::post('client', 'stocktakingClient');
        Route::post('supplier', 'stocktakingSupplier');
        Route::post('all', 'stocktakingAll');
        Route::post('get_categories', 'getCategories');
        Route::post('get_products', 'getProducts');
        Route::post('get_clients', 'getClients');
        Route::post('get_suppliers', 'getSuppliers');
    });

});
