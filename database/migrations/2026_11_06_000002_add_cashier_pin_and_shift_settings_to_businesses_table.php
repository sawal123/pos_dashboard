<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('cashier_pin', 6)->default('123456')->after('tax_rate');
            $table->unsignedBigInteger('default_opening_cash')->default(0)->after('cashier_pin');
            $table->boolean('auto_open_shift')->default(true)->after('default_opening_cash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['cashier_pin', 'default_opening_cash', 'auto_open_shift']);
        });
    }
};

