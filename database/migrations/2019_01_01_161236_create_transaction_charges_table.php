<?php

declare(strict_types=1);

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
            $table->string('id', 64)->unique()->comment('付款流水号');
            $table->boolean('paid')->default(false)->nullable();//是否已付款
            $table->boolean('refunded')->default(false)->nullable();//是否存在退款信息
            $table->boolean('reversed')->default(false)->nullable();//订单是否撤销
            $table->string('trade_channel', 64)->nullable()->comment('付款渠道');
            $table->string('trade_type', 16)->nullable()->comment('交易类型');
            $table->morphs('order');//订单关联
            $table->unsignedInteger('total_amount')->comment('订单总金额');
            $table->string('currency', 3)->default('CNY')->comment('货币类型');
            $table->string('subject', 256)->nullable()->comment('订单标题');
            $table->string('description', 127)->nullable()->comment('商品描述');
            $table->ipAddress('client_ip')->nullable()->comment('用户的客户端IP');
            $table->string('transaction_no', 64)->nullable()->comment('支付渠道流水号');
            $table->string('failure_code')->nullable();//订单的错误码
            $table->string('failure_msg')->nullable();//订单的错误消息的描述。
            $table->json('extra')->nullable();//特定渠道发起交易时需要的额外参数，以及部分渠道支付成功返回的额外参数
            $table->json('metadata')->nullable();//元数据
            $table->json('credential')->nullable();//支付凭证，用于客户端发起支付。
            $table->timestamp('expired_at')->nullable()->comment('订单失效时间');
            $table->timestamp('succeed_at')->nullable()->comment('订单支付完成时时间');//银联支付成功时间为接收异步通知的时间
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
        Schema::dropIfExists('transaction_charges');
    }
}
