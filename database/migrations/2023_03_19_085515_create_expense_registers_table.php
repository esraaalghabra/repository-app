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
        Schema::create('expense_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('user')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date')->default(now());
            $table->enum('type_operation',['add','edit','meet_debt']);

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
        Schema::dropIfExists('expense_registers');
    }
};
