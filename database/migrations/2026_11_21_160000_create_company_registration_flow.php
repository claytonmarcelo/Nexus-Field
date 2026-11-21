<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro público da plataforma (Parte A do desenho E6).
     *
     * O pedido nasce antes da empresa existir: por isso `company_registrations`
     * não tem `company_id` e não usa o escopo de tenant. Documentos e aceites
     * guardam o que for preciso para a análise, com o arquivo em disco privado.
     * O endereço IP entra como hash — a finalidade é anti-abuso, não identificar
     * a pessoa, e o dado cru não precisa ficar gravado.
     */
    public function up(): void
    {
        Schema::create('company_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 32)->unique();
            $table->string('state', 24)->default('rascunho');
            $table->json('dados');
            $table->string('cnpj_hash', 64)->unique();
            $table->string('email_hash', 64)->index();
            $table->string('email_token_hash', 64)->nullable();
            $table->timestamp('email_token_expires_at')->nullable();
            $table->string('access_token_hash', 64)->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['state', 'created_at']);
            $table->index('access_token_hash');
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('company_registrations')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('type', 32);
            $table->string('disk', 16)->default('local');
            $table->string('path', 191);
            $table->string('sha256', 64);
            $table->string('mime', 128);
            $table->string('original_name', 191);
            $table->unsignedInteger('size');
            $table->string('review_status', 24)->default('pendente');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['registration_id', 'review_status']);
        });

        Schema::create('company_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('company_registrations')->cascadeOnDelete();
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['registration_id', 'created_at']);
        });

        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('company_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('version', 16);
            $table->timestamp('accepted_at');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->index(['user_id', 'kind']);
            $table->index('registration_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('trade_name')->nullable()->after('legal_name');
            $table->string('cnpj', 14)->nullable()->unique()->after('document');
            $table->string('ie')->nullable()->after('cnpj');
            $table->string('im')->nullable()->after('ie');
            $table->string('cnae_main', 16)->nullable()->after('im');
            $table->json('cnae_secondary')->nullable()->after('cnae_main');
            $table->string('legal_nature', 16)->nullable()->after('cnae_secondary');
            $table->string('size', 24)->nullable()->after('legal_nature');
            $table->date('opened_at')->nullable()->after('size');
            $table->string('tax_regime', 24)->nullable()->after('opened_at');
            $table->string('segment', 64)->nullable()->after('tax_regime');
            $table->unsignedInteger('employee_count')->nullable()->after('segment');
            $table->unsignedInteger('field_technician_count')->nullable()->after('employee_count');
            $table->json('contacts')->nullable()->after('field_technician_count');
            $table->json('legal_responsible')->nullable()->after('contacts');
            $table->string('lifecycle_status', 24)->default('active')->after('legal_responsible');
            $table->foreignId('registration_id')->nullable()->after('lifecycle_status')
                ->constrained('company_registrations')->nullOnDelete();
        });

        // Equivalência com o comportamento atual: toda empresa existente já é
        // ativa, e o documento vira CNPJ apenas quando tem 14 dígitos válidos.
        // A uniqueness de `cnpj` admite NULL repetido no MySQL.
        foreach (DB::table('companies')->select('id', 'name', 'document')->get() as $empresa) {
            $digitos = preg_replace('/\D+/', '', (string) $empresa->document);

            DB::table('companies')->where('id', $empresa->id)->update([
                'legal_name' => $empresa->name,
                'lifecycle_status' => 'active',
                'cnpj' => strlen($digitos) === 14 ? $digitos : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['registration_id']);
            $table->dropColumn([
                'legal_name', 'trade_name', 'cnpj', 'ie', 'im', 'cnae_main',
                'cnae_secondary', 'legal_nature', 'size', 'opened_at', 'tax_regime',
                'segment', 'employee_count', 'field_technician_count', 'contacts',
                'legal_responsible', 'lifecycle_status', 'registration_id',
            ]);
        });

        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('company_review_events');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('company_registrations');
    }
};
