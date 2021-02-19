<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->string('id',64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('charge_id',64);//支付  charge 对象的  id
            $table->unsignedInteger('amount');//退款金额大于 0, 必须小于等于可退款金额，默认为全额退款。
            $table->string('status')->default('pending');//退款状态（目前支持三种状态: pending: 处理中; succeeded: 成功; failed: 失败）。
            $table->string('description',191)->nullable();//退款详情，最多 191 个 Unicode 字符。
            $table->string('failure_code')->nullable();//订单的错误码
            $table->string('failure_msg')->nullable();//订单的错误消息的描述。
            $table->string('charge_order_id',64)->nullable();//商户订单号，这边返回的是  charge 对象中的  order_no 参数。
            $table->string('transaction_no',64)->nullable();//交易流水号，由第三方渠道提供。
            $table->string('funding_source',20)->nullable();//微信及 QQ 类退款资金来源。取值范围： unsettled_funds ：使用未结算资金退款； recharge_funds ：微信-使用可用余额退款，QQ-使用可用现金账户资金退款。
            //注：默认值  unsettled_funds ，该参数对于微信渠道的退款来说仅适用于微信老资金流商户使用，包括  wx 、 wx_pub 、 wx_pub_qr 、 wx_lite 、 wx_wap 、 wx_pub_scan 六个渠道；
            //新资金流退款资金默认从基本账户中扣除。该参数仅在请求退款，传入该字段时返回。
            $table->json('metadata')->nullable();//元数据
            $table->json('extra')->nullable();//附加参数
            $table->timestamp('time_succeed', 0)->nullable();//退款成功的时间，用 Unix 时间戳表示。
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');

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
