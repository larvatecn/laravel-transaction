<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_charges', function (Blueprint $table) {
            $table->string('id',64)->unique()->comment('付款流水号');
            $table->string('trade_channel', 64)->nullable()->comment('付款渠道');
            $table->string('trade_type', 16)->nullable()->comment('交易类型');
            $table->string('transaction_no', 64)->nullable()->comment('支付渠道流水号');
            $table->morphs('order');//订单关联
            $table->string('subject', 256)->nullable()->comment('订单标题');
            $table->string('description', 127)->nullable()->comment('商品描述');
            $table->unsignedInteger('total_amount')->comment('订单总金额');
            $table->string('currency', 3)->default('CNY')->comment('货币类型');
            $table->string('state', 32)->nullable()->comment('交易状态');
            $table->string('state_desc', 256)->nullable()->comment('交易状态描述');
            $table->ipAddress('client_ip')->nullable()->comment('用户的客户端IP');
            $table->json('payer')->nullable()->comment('支付者信息');
            $table->json('credential')->nullable()->comment('客户端支付凭证');
            $table->timestamp('expired_at')->nullable()->comment('交易结束时间');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_charges');
    }
}
