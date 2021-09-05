<?php

declare(strict_types=1);
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */
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
            $table->string('id', 64)->unique();
            $table->boolean('paid')->default(false)->nullable();//是否已付款
            $table->boolean('refunded')->default(false)->nullable();//是否存在退款信息
            $table->boolean('reversed')->default(false)->nullable();//订单是否撤销
            $table->string('trade_channel', 64)->nullable();//付款渠道
            $table->string('trade_type', 20)->nullable();//交易类型 APP PC等
            $table->morphs('order');//订单关联
            $table->unsignedInteger('amount');//订单总金额（必须大于 0),最小的货币单位
            $table->string('currency', 3)->default('CNY');//3 位 ISO 货币代码，人民币为  CNY 。
            $table->string('subject', 64);//商品标题，该参数最长为 64 个 Unicode 字符
            $table->string('description', 128)->nullable();//订单附加说明，最多 128 个 Unicode 字符。
            $table->ipAddress('client_ip')->nullable();//发起支付请求客户端的 IP 地址
            $table->string('transaction_no', 64)->nullable();//支付渠道返回的交易流水号。
            $table->unsignedInteger('amount_refunded')->nullable()->default(0);//已退款总金额，单位为对应币种的最小货币单位，例如：人民币为分。
            $table->string('failure_code')->nullable();//订单的错误码
            $table->string('failure_msg')->nullable();//订单的错误消息的描述。
            $table->json('extra')->nullable();//特定渠道发起交易时需要的额外参数，以及部分渠道支付成功返回的额外参数
            $table->json('metadata')->nullable();//元数据
            $table->json('credential')->nullable();//支付凭证，用于客户端发起支付。
            $table->timestamp('time_expire')->nullable()->comment('订单失效时间');
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
