<?php

namespace Tests\Feature;

use App\Models\Colis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_keeps_the_frontend_colis_id_contract(): void
    {
        $sender = User::factory()->create();
        $colis = $this->colis($sender);
        Sanctum::actingAs($sender);

        $this->postJson('/api/tickets', [
            'subject' => 'Question livraison',
            'message' => 'Où en est mon colis ?',
            'colis_id' => $colis->id,
            'priority' => 'high',
        ])->assertCreated()->assertJsonPath('colis_id', $colis->id);
    }

    public function test_user_cannot_attach_an_unrelated_shipment_to_a_ticket(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $colis = $this->colis($owner);
        Sanctum::actingAs($outsider);

        $this->postJson('/api/tickets', [
            'subject' => 'Accès indu',
            'message' => 'Tentative',
            'colis_id' => $colis->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_fields_are_bounded_and_priority_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/tickets', [
            'subject' => str_repeat('a', 256),
            'message' => str_repeat('b', 5001),
            'priority' => 'critical',
        ])->assertUnprocessable()->assertJsonValidationErrors(['subject', 'message', 'priority']);
    }

    private function colis(User $sender): Colis
    {
        return Colis::create([
            'tracking_id' => 'LOGTICKET',
            'expediteur_id' => $sender->id,
            'status' => 'pending',
            'from_city' => 'Casablanca',
            'to_city' => 'Rabat',
            'from_address' => 'Départ',
            'to_address' => 'Arrivée',
            'recipient_name' => 'Destinataire',
            'recipient_phone' => '0611111111',
            'weight' => 1,
            'price' => 50,
            'pin_code' => bcrypt('1234'),
        ]);
    }
}
