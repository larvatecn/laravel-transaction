<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Larva\Transaction\Models\Transfer;

class CreateTransactionTransferTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_transfer', function (Blueprint $table) {
            $table->string('id',64)->unique();
            $table->string('trade_channel',64)->comment('支付渠道');
            $table->string('status',15)->default(Transfer::STATUS_SCHEDULED)->nullable()->comment('状态');
            $table->morphs('order');//付款单关联
            $table->unsignedInteger('amount')->comment('金额');
            $table->string('currency',3)->comment('货币代码');
            $table->string('recipient_id')->nullable()->comment('接收者 id');
            $table->string('description')->nullable()->comment('备注信息');
            $table->string('transaction_no',64)->nullable()->comment('网关流水号');
            $table->string('failure_msg')->nullable()->comment('错误信息描述');
            $table->json('metadata')->nullable();//元数据
            $table->json('extra')->nullable();//附加参数
            $table->timestamp('transferred_at')->nullable()->comment('转账时间');
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
        Schema::dropIfExists('transaction_transfer');
    }
}
