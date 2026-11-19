<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->foreignId('outlet_id')
                ->nullable()
                ->after('role')
                ->constrained('outlets')
                ->nullOnDelete();
            $table->index(['business_id', 'outlet_id']);
        });

        Schema::table('business_invitations', function (Blueprint $table) {
            $table->foreignId('outlet_id')
                ->nullable()
                ->after('role')
                ->constrained('outlets')
                ->nullOnDelete();
            $table->index(['business_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropIndex(['business_id', 'outlet_id']);
            $table->dropColumn('outlet_id');
        });

        Schema::table('business_invitations', function (Blueprint $table) {
            $table->dropForeign(['outlet_id']);
            $table->dropIndex(['business_id', 'outlet_id']);
            $table->dropColumn('outlet_id');
        });
    }
};
