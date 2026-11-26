<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\StockSiege;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminStockIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_stock_at_or_below_the_reorder_threshold(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lowStock = Stock::forceCreate(['designation_piece' => 'Filtre à huile', 'quantite' => 20]);
        $healthyStock = Stock::forceCreate(['designation_piece' => 'Filtre à air', 'quantite' => 20]);
        $secondLowStock = Stock::forceCreate(['designation_piece' => 'Plaquettes de frein', 'quantite' => 8]);

        StockSiege::create([
            'stock_id' => $lowStock->id,
            'siege' => 'Terminus 27',
            'quantite_attribuee' => 20,
            'sortie' => 15,
        ]);
        StockSiege::create([
            'stock_id' => $healthyStock->id,
            'siege' => 'Terminus 27',
            'quantite_attribuee' => 8,
            'sortie' => 3,
        ]);
        StockSiege::create([
            'stock_id' => $secondLowStock->id,
            'siege' => 'Zone Industrielle',
            'quantite_attribuee' => 6,
            'sortie' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.pieces.index', ['filter' => 'stock_bas']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.filter', 'stock_bas')
                ->where('seuilStockBas', 5)
                ->where('stocks.total', 2)
                ->where('stocks.data.0.id', $lowStock->id)
                ->where('resumeStock.total', 3)
                ->where('resumeStock.stock_bas', 2)
                ->where('resumeStock.stock_normal', 1)
                ->where('resumeStock.non_attribues', 14)
            );

        $this->actingAs($admin)
            ->get(route('admin.pieces.index', ['filter' => 'stock_bas', 'search' => 'Plaquettes']))
            ->assertInertia(fn ($page) => $page
                ->where('stocks.total', 1)
                ->where('stocks.data.0.id', $secondLowStock->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.pieces.index', ['filter' => 'stock_normal']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.filter', 'stock_normal')
                ->where('stocks.total', 1)
                ->where('stocks.data.0.id', $healthyStock->id)
            );

        $this->actingAs($admin)
            ->get(route('admin.pieces.index', ['filter' => 'stock_bas', 'siege' => 'Terminus 27']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.siege', 'Terminus 27')
                ->where('stocks.total', 2)
                ->where('stocks.data.0.id', $healthyStock->id)
            );
    }

    public function test_import_preview_reports_create_and_update_without_changing_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = Stock::forceCreate([
            'reference' => 'F-001',
            'designation_piece' => 'Filtre huile',
            'quantite' => 3,
        ]);
        $file = UploadedFile::fake()->createWithContent(
            'stocks.csv',
            "Reference;Designation Piece;Quantite Initiale\nF-001;Filtre huile;8\nF-002;Plaquettes;5\n"
        );

        $this->actingAs($admin)
            ->postJson(route('administration.stocks.import.preview'), ['fichier' => $file])
            ->assertOk()
            ->assertJsonPath('can_import', true)
            ->assertJsonPath('crees', 1)
            ->assertJsonPath('mis_a_jour', 1)
            ->assertJsonPath('rows.0.action', 'Mise à jour')
            ->assertJsonPath('rows.1.action', 'Création');

        $this->assertSame(3, $existing->fresh()->quantite);
        $this->assertDatabaseMissing('stocks', ['reference' => 'F-002']);
    }

    public function test_import_preview_blocks_duplicate_rows_and_import_rejects_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contenu = "Reference;Designation Piece;Quantite Initiale\nF-003;Pompe;4\nF-003;Pompe;6\n";

        $this->actingAs($admin)
            ->postJson(route('administration.stocks.import.preview'), [
                'fichier' => UploadedFile::fake()->createWithContent('doublons.csv', $contenu),
            ])
            ->assertOk()
            ->assertJsonPath('can_import', false)
            ->assertJsonPath('issue_count', 1);

        $this->actingAs($admin)
            ->post(route('administration.stocks.import'), [
                'fichier' => UploadedFile::fake()->createWithContent('doublons.csv', $contenu),
            ])
            ->assertSessionHasErrors('fichier');

        $this->assertDatabaseMissing('stocks', ['reference' => 'F-003']);
    }
}
