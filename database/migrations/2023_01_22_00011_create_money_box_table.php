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
        Schema::create('money_box', function (Blueprint $table) {
            $table->id();
            $table->double('total_price');
            $table->enum('type_money',['sales','add_cash','purchases','expenses','withdrawal_cash']);
            $table->date('date');
            $table->boolean('is_finished')->default(1);
            $table->foreignId('repository_id')->constrained('repositories')->cascadeOnDelete()->cascadeOnUpdate();

            $table->softDeletes();
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
        Schema::dropIfExists('money_operations');
    }
};
