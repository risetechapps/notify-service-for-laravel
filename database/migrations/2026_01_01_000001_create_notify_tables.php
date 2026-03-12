<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Log de cada notificação individual ───────────────────────────────
        Schema::create('notify_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Model que disparou a notificação (polimórfico)
            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->index(['notifiable_type', 'notifiable_id']);

            // Canal: sms | mail | push | apns | telegram | slack | discord | teams | websocket | webhook
            $table->string('channel', 30)->index();

            // UUID retornado pelo servidor na resposta 202
            $table->string('server_notification_id')->nullable()->index();

            // Status local: created | sending | sent | error | delivered
            $table->string('status', 30)->default('created')->index();

            // Payload completo enviado ao servidor
            $table->json('payload');

            // Resposta original do servidor (202 Accepted)
            $table->json('server_response')->nullable();

            // Mensagem de erro, se houver
            $table->text('error_message')->nullable();

            // Ciclo de vida
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Vínculo opcional com campanha
            $table->uuid('notify_campaign_id')->nullable()->index();

            $table->timestamps();
            $table->index(['channel', 'status']);
        });

        // ── Campanhas de email ou SMS ─────────────────────────────────────────
        Schema::create('notify_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->index(['notifiable_type', 'notifiable_id']);

            // email | sms
            $table->string('channel', 20)->index();

            $table->string('name');

            // UUID da campanha retornado pelo servidor
            $table->string('server_campaign_id')->nullable()->index();

            // pending | processing | paused | completed | failed
            $table->string('status', 30)->default('pending')->index();

            // Template completo (subject, body, etc.)
            $table->json('template');

            // Driver de envio (null = padrão do servidor)
            $table->string('config_id')->nullable();

            // Webhook que o servidor vai chamar com updates
            $table->string('webhook_url')->nullable();

            $table->unsignedSmallInteger('rate_per_minute')->default(60);
            $table->timestamp('scheduled_at')->nullable();

            // Contadores atualizados via webhook
            $table->unsignedInteger('total_contacts')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ── Contatos de cada campanha ─────────────────────────────────────────
        Schema::create('notify_campaign_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('notify_campaign_id');
            $table->foreign('notify_campaign_id')
                ->references('id')->on('notify_campaigns')
                ->onDelete('cascade');

            // email ou número de telefone
            $table->string('contact');
            $table->string('name')->nullable();
            $table->json('extra_data')->nullable();

            // pending | sent | failed
            $table->string('status', 20)->default('pending')->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
            $table->index(['notify_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notify_campaign_contacts');
        Schema::dropIfExists('notify_campaigns');
        Schema::dropIfExists('notify_logs');
    }
};
