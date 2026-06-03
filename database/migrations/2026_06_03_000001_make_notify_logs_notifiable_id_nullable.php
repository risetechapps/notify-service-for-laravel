<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torna notify_logs.notifiable_id nullable.
 *
 * Envios on-demand via Notification::route('mail', $email) usam um
 * AnonymousNotifiable, que não possui chave primária — antes disso o insert
 * violava a constraint NOT NULL da coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            $table->string('notifiable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notify_logs', function (Blueprint $table) {
            $table->string('notifiable_id')->nullable(false)->change();
        });
    }
};
