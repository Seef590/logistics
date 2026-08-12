<?php

namespace App\Http\Controllers;

use App\Models\Colis;
use App\Models\Notification;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ColisController extends Controller
{
    public function track($trackingId)
    {
        $colis = Colis::with([
            'statusHistory:id,colis_id,status,message,city,created_at',
            'livreur:id,name,rating',
            'voyageur:id,name,rating',
        ])->where('tracking_id', strtoupper($trackingId))->first();

        if (!$colis) {
            return response()->json(['error' => 'Colis introuvable'], 404);
        }

        return response()->json($colis->only([
            'id', 'tracking_id', 'status', 'from_city', 'to_city',
            'estimated_delivery', 'created_at', 'updated_at',
            'status_history', 'livreur', 'voyageur',
        ]));
    }

    public function stats(Request $request)
    {
        $user = $request->user();
        $query = Colis::query();

        if ($user->role === 'expediteur') {
            $query->where('expediteur_id', $user->id);
        } elseif ($user->role === 'livreur') {
            $query->where('livreur_id', $user->id);
        } elseif ($user->role === 'voyageur') {
            $query->where('voyageur_id', $user->id);
        } elseif ($user->role === 'destinataire') {
            $query->where('recipient_phone', $user->phone);
        }

        $colis = $query->get();

        return response()->json([
            'total' => $colis->count(),
            'pending' => $colis->where('status', 'pending')->count(),
            'in_transit' => $colis->whereIn('status', ['in_transit', 'picked_up', 'out_for_delivery'])->count(),
            'delivered' => $colis->where('status', 'delivered')->count(),
            'failed' => $colis->where('status', 'failed')->count(),
            'revenue' => $colis->where('is_paid', true)->sum('price'),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Colis::with(['statusHistory', 'livreur:id,name,phone,rating']);
        $isAvailabilitySearch = false;

        if ($user->role === 'expediteur') {
            $query->where('expediteur_id', $user->id);
        } elseif ($user->role === 'livreur') {
            if ($request->status === 'available') {
                $this->ensureVerifiedCarrier($user);
                $isAvailabilitySearch = true;
                $query->where('status', 'pending')
                    ->where(function ($q) use ($user) {
                        $q->where('from_city', $user->city)->orWhere('to_city', $user->city);
                    })
                    ->whereNull('livreur_id')
                    ->whereNull('voyageur_id');
            } else {
                $query->where('livreur_id', $user->id);
            }
        } elseif ($user->role === 'destinataire') {
            $query->where('recipient_phone', $user->phone);
        } elseif ($user->role === 'voyageur') {
            if ($request->from_city && $request->to_city) {
                $this->ensureVerifiedCarrier($user);
                $isAvailabilitySearch = true;
                $query->where('status', 'pending')
                    ->where('from_city', $request->from_city)
                    ->where('to_city', $request->to_city)
                    ->where('is_voyageur_eligible', true)
                    ->whereNull('livreur_id')
                    ->whereNull('voyageur_id');
            } else {
                $query->where('voyageur_id', $user->id);
            }
        }

        if ($isAvailabilitySearch) {
            $query->select([
                'id', 'tracking_id', 'status', 'from_city', 'to_city', 'weight',
                'description', 'price', 'estimated_delivery',
                'is_voyageur_eligible', 'created_at',
            ])->without('livreur');
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['expediteur', 'admin'], true)) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $validated = $request->validate([
            'from_address' => 'required|string|max:500',
            'to_address' => 'required|string|max:500',
            'from_city' => 'required|string|max:100',
            'to_city' => 'required|string|max:100',
            'recipient_name' => 'required|string|max:120',
            'recipient_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'weight' => 'required|numeric|gt:0|max:1000',
            'price' => 'required|numeric|min:0|max:1000000',
            'description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:5000',
            'is_voyageur_eligible' => 'sometimes|boolean',
        ]);

        $sameCity = $validated['from_city'] === $validated['to_city'];
        $pinCode = (string) random_int(1000, 9999);

        do {
            $trackingId = 'LOG'.strtoupper(Str::random(7));
        } while (Colis::where('tracking_id', $trackingId)->exists());

        $colis = Colis::create([
            ...$validated,
            'tracking_id' => $trackingId,
            'expediteur_id' => $user->id,
            'status' => 'pending',
            'description' => $validated['description'] ?? '',
            'notes' => $validated['notes'] ?? '',
            'is_voyageur_eligible' => $validated['is_voyageur_eligible'] ?? false,
            'is_paid' => false,
            'pin_code' => Hash::make($pinCode),
            'qr_code' => 'QR_'.Str::random(8),
            'estimated_delivery' => now()->addDays($sameCity ? 1 : 2),
        ]);

        $colis->statusHistory()->createMany([
            ['status' => 'created', 'message' => "Colis créé par l'expéditeur", 'city' => $validated['from_city'], 'updated_by' => $user->id],
            ['status' => 'pending', 'message' => "En attente d'un livreur", 'city' => $validated['from_city'], 'updated_by' => $user->id],
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Colis créé',
            'message' => "Votre colis {$colis->tracking_id} a été créé avec succès.",
            'type' => 'success',
        ]);

        return response()->json([
            ...$colis->load('statusHistory')->toArray(),
            'pin_code' => $pinCode,
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|in:created,pending,picked_up,in_transit,out_for_delivery,delivered,failed,returned',
            'message' => 'nullable|string|max:500',
        ]);

        $result = DB::transaction(function () use ($id, $validated, $user) {
            $colis = Colis::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($colis->status === $validated['status']) {
                if (in_array($user->role, ['livreur', 'voyageur'], true)
                    && $colis->livreur_id !== $user->id
                    && $colis->voyageur_id !== $user->id) {
                    abort(403, "Vous n'êtes pas assigné à ce colis");
                }
                if (!in_array($user->role, ['livreur', 'voyageur', 'admin'], true)) {
                    abort(403, 'Accès refusé');
                }

                return ['colis' => $colis, 'changed' => false];
            }

            if (in_array($user->role, ['livreur', 'voyageur'], true)) {
                if ($validated['status'] === 'picked_up' && !$colis->livreur_id && !$colis->voyageur_id) {
                    $this->authorizeClaim($user, $colis);
                    $assignmentColumn = $user->role === 'livreur' ? 'livreur_id' : 'voyageur_id';
                    $colis->{$assignmentColumn} = $user->id;
                }

                if ($colis->livreur_id !== $user->id && $colis->voyageur_id !== $user->id) {
                    abort(403, "Vous n'êtes pas assigné à ce colis");
                }

                $transitions = [
                    'pending' => ['picked_up'],
                    'picked_up' => ['in_transit', 'out_for_delivery', 'failed'],
                    'in_transit' => ['out_for_delivery', 'delivered', 'failed'],
                    'out_for_delivery' => ['delivered', 'failed'],
                    'failed' => ['returned'],
                ];
                if (!in_array($validated['status'], $transitions[$colis->status] ?? [], true)) {
                    abort(422, 'Transition de statut invalide');
                }
            } elseif ($user->role !== 'admin') {
                abort(403, 'Accès refusé');
            }

            $colis->status = $validated['status'];
            if ($validated['status'] === 'delivered') {
                $colis->is_paid = true;
            }
            $colis->save();

            $colis->statusHistory()->create([
                'status' => $validated['status'],
                'message' => $validated['message'] ?? "Statut mis à jour: {$validated['status']}",
                'city' => $user->city,
                'updated_by' => $user->id,
            ]);

            if ($validated['status'] === 'delivered') {
                Notification::create([
                    'user_id' => $colis->expediteur_id,
                    'title' => 'Colis livré!',
                    'message' => "Votre colis {$colis->tracking_id} a été livré avec succès.",
                    'type' => 'success',
                ]);
            }

            return ['colis' => $colis, 'changed' => true];
        });

        return response()->json($result['colis']->load('statusHistory'));
    }

    public function validatePin(Request $request, $id)
    {
        $user = $request->user();
        $validated = $request->validate(['pin' => 'required|digits:4']);

        $result = DB::transaction(function () use ($id, $validated, $user) {
            $colis = Colis::whereKey($id)->lockForUpdate()->firstOrFail();

            if ($user->role !== 'admin' && $colis->livreur_id !== $user->id && $colis->voyageur_id !== $user->id) {
                abort(403, 'Accès refusé');
            }
            if (!Hash::check($validated['pin'], $colis->pin_code)) {
                abort(400, 'Code PIN incorrect');
            }
            if ($colis->pin_validated) {
                return ['colis' => $colis, 'newly_delivered' => false];
            }
            if ($user->role !== 'admin' && !in_array($colis->status, ['in_transit', 'out_for_delivery'], true)) {
                abort(422, 'Le colis ne peut pas encore être livré');
            }

            $colis->update(['pin_validated' => true, 'status' => 'delivered', 'is_paid' => true]);
            $colis->statusHistory()->create([
                'status' => 'delivered',
                'message' => 'Livraison confirmée par PIN',
                'city' => $user->city,
                'updated_by' => $user->id,
            ]);

            Notification::create([
                'user_id' => $colis->expediteur_id,
                'title' => 'Colis livré!',
                'message' => "Votre colis {$colis->tracking_id} a été livré avec succès.",
                'type' => 'success',
            ]);

            return ['colis' => $colis, 'newly_delivered' => true];
        });

        return response()->json(['success' => true, 'colis' => $result['colis']->load('statusHistory')]);
    }

    public function rate(Request $request, $id)
    {
        $user = $request->user();
        $colis = Colis::findOrFail($id);
        $validated = $request->validate([
            'to_user_id' => 'required|integer|exists:users,id',
            'score' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($user->phone !== $colis->recipient_phone || $colis->status !== 'delivered') {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $carrierId = $colis->livreur_id ?? $colis->voyageur_id;
        if (!$carrierId || (int) $validated['to_user_id'] !== (int) $carrierId) {
            return response()->json(['error' => 'Transporteur invalide'], 422);
        }
        if (Rating::where('from_user_id', $user->id)->where('colis_id', $id)->exists()) {
            return response()->json(['error' => 'Déjà noté'], 409);
        }

        $rating = Rating::create([
            'from_user_id' => $user->id,
            'to_user_id' => $validated['to_user_id'],
            'colis_id' => $id,
            'score' => $validated['score'],
            'comment' => $validated['comment'] ?? '',
        ]);

        $target = User::find($validated['to_user_id']);
        if ($target) {
            $total = $target->rating * $target->rating_count + $validated['score'];
            $target->rating_count += 1;
            $target->rating = round($total / $target->rating_count, 1);
            $target->save();
        }

        return response()->json($rating, 201);
    }

    private function ensureVerifiedCarrier(User $user): void
    {
        if (!$user->is_verified || $user->verification_status !== 'approved') {
            abort(403, 'Compte transporteur non vérifié');
        }
    }

    private function authorizeClaim(User $user, Colis $colis): void
    {
        $this->ensureVerifiedCarrier($user);

        if ($user->role === 'livreur' && !$user->is_online) {
            abort(403, 'Le livreur doit être en ligne');
        }
        if ($user->role === 'livreur' && !in_array($user->city, [$colis->from_city, $colis->to_city], true)) {
            abort(403, 'Colis indisponible dans votre ville');
        }
        if ($user->role === 'voyageur' && !$colis->is_voyageur_eligible) {
            abort(403, 'Colis non éligible aux voyageurs');
        }
    }
}
