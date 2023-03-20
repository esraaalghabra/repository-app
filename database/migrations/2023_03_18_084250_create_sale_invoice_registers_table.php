<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sale_invoice_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_invoice_id')->constrained('sales_invoices')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date')->default(now());
            $table->enum('type_operation',['add','edit','add_to_archive','remove_to_archive','meet_debt']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sale_invoice_registers');
    }
};
