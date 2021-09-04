<?php
/**
 * This is NOT a freeware, use is subject to license terms.
 *
 * @copyright Copyright (c) 2010-2099 Jinan Larva Information Technology Co., Ltd.
 * @link http://www.larva.com.cn/
 */
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
            $table->unsignedInteger('amount');//退款金额大于 0, 必须小于等于可退款金额，默认为全额退款。
            $table->string('reason', 80)->nullable()->comment('退款原因');//退款详情，最多 191 个 Unicode 字符。
            $table->string('state')->default(Refund::STATE_PENDING)->comment('退款状态');
            $table->timestamp('succeed_at', 0)->nullable();//退款成功的时间，用 Unix 时间戳表示。
            $table->json('failure')->nullable()->comment('错误信息');
            $table->softDeletes();
            $table->timestamps();

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
