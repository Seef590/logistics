<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicCourierPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_online_couriers_endpoint_exposes_only_allowlisted_profile_fields(): void
    {
        User::create([
            'name' => 'Livreur public',
            'email' => 'private@example.test',
            'phone' => '0612345678',
            'password' => Hash::make('password'),
            'role' => 'livreur',
            'city' => 'Casablanca',
            'cin' => 'PRIVATE-CIN',
            'license_number' => 'PRIVATE-LICENSE',
            'vehicle_type' => 'Moto',
            'vehicle_plate' => 'PRIVATE-PLATE',
            'is_online' => true,
            'is_verified' => true,
            'verification_status' => 'approved',
        ]);

        $response = $this->getJson('/api/misc/livreurs/online')->assertOk();

        $this->assertSame(
            ['id', 'name', 'city', 'rating', 'rating_count', 'vehicle_type'],
            array_keys($response->json('0')),
        );
    }
}
