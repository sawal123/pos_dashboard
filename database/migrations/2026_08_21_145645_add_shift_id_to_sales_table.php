<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('shift_id')->nullable()->after('customer_id');

            $table->foreign(['business_id', 'outlet_id', 'shift_id'])
                ->references(['business_id', 'outlet_id', 'id'])
                ->on('shifts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['business_id', 'outlet_id', 'shift_id']);
            $table->dropColumn('shift_id');
        });
    }
};
