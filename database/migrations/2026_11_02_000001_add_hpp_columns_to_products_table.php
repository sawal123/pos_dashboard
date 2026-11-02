<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('hpp', 15, 2)->nullable()->after('cost');
            $table->string('hpp_method', 20)->default('average')->after('hpp');
            $table->string('cost_source', 10)->default('hpp')->after('hpp_method');
            $table->decimal('hpp_overhead_fixed', 15, 2)->default(0)->after('cost_source');
            $table->decimal('hpp_overhead_percent', 5, 2)->default(0)->after('hpp_overhead_fixed');
            $table->timestamp('hpp_calculated_at')->nullable()->after('hpp_overhead_percent');
        });

        DB::table('products')->update([
            'hpp' => DB::raw('cost'),
            'hpp_calculated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'hpp',
                'hpp_method',
                'cost_source',
                'hpp_overhead_fixed',
                'hpp_overhead_percent',
                'hpp_calculated_at',
            ]);
        });
    }
};
