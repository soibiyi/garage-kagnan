<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         if (Schema::hasTable('interaction_clients')) {
        return;
    }
        Schema::create('interaction_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicule_id')->nullable()->constrained('vehicules')->nullOnDelete();
            
            $table->string('type', 50); // appel, whatsapp, sms, email, visite, autre
            $table->string('objet', 255)->nullable();
            $table->text('notes')->nullable();
            $table->text('accord_convenu')->nullable();
            $table->date('date_relance_prevue')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interaction_clients');
    }
};