<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Larva\Transaction\Models\Refund;

class CreateTransactionRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_refunds', function (Blueprint $table) {
            $table->string('id', 64)->unique()->comment('退款流水号');
            $table->string('charge_id', 64)->comment('付款流水号');
            $table->string('transaction_no', 64)->nullable()->comment('网关流水号');
            $table->unsignedInteger('amount');
            $table->string('reason', 127)->nullable()->comment('退款原因');
            $table->string('status')->comment('退款状态');
            $table->json('failure')->nullable()->comment('错误信息');
            $table->timestamp('succeed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('charge_id')->references('id')->on('transaction_charges');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_refunds');
    }
}
