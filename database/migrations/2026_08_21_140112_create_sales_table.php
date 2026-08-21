<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('transaction_number');
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->unique(['business_id', 'transaction_number']);
            $table->unique(['business_id', 'id']);

            $table->foreign(['business_id', 'outlet_id'])
                ->references(['business_id', 'id'])
                ->on('outlets')
                ->restrictOnDelete();

            $table->foreign(['business_id', 'customer_id'])
                ->references(['business_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
