<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCarsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type',45);
            $table->string('maker',45);
            $table->string('model',45);
            $table->string('year',45);
            $table->string('picture',45);
            $table->string('status',45);
            $table->timestamps();
        });
        Schema::create('customer_car', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id')->unsigned()->unsigned();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->integer('car_id')->unsigned()->nullable();
            $table->foreign('car_id')->references('id')->on('customers')->onDelete('cascade');
            $table->string('millage',45);
            $table->string('vehicle_no',45);
            $table->string('insurance',45);
            $table->string('status',45);
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
        Schema::dropIfExists('cars');
        Schema::dropIfExists('customer_car');
        $table->dropForeign('customer_car_customer_id_foreign');
        $table->dropForeign('customer_car_car_id_foreign');
    }
}
