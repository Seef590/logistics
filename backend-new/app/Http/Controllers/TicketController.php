<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Colis;
use App\Models\Notification;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::with('responses');
        if ($user->role !== 'admin') $query->where('user_id', $user->id);
        return response()->json($query->orderBy('created_at','desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'colis_id' => 'nullable|integer|exists:colis,id',
            'priority' => 'sometimes|required|in:low,medium,high,urgent',
        ]);
        $user = $request->user();

        if (!empty($validated['colis_id'])) {
            $colis = Colis::findOrFail($validated['colis_id']);
            $canAccess = $user->role === 'admin'
                || $colis->expediteur_id === $user->id
                || $colis->livreur_id === $user->id
                || $colis->voyageur_id === $user->id
                || $colis->recipient_phone === $user->phone;

            if (!$canAccess) {
                return response()->json(['error' => 'Accès refusé à ce colis'], 403);
            }
        }

        $ticket = Ticket::create([
            'user_id'  => $user->id,
            'colis_id' => $validated['colis_id'] ?? null,
            'subject'  => $validated['subject'],
            'message'  => $validated['message'],
            'status'   => 'open',
            'priority' => $validated['priority'] ?? 'medium',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'Ticket créé',
            'message' => "Votre ticket '{$ticket->subject}' a été soumis.",
            'type'    => 'info',
        ]);

        return response()->json($ticket->load('responses'), 201);
    }

    public function respond(Request $request, $id)
    {
        $validated = $request->validate(['message' => 'required|string|max:5000']);
        $user   = $request->user();
        $ticket = Ticket::findOrFail($id);

        if ($user->role !== 'admin' && $ticket->user_id !== $user->id) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $ticket->responses()->create([
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'message'   => $validated['message'],
        ]);

        $ticket->touch();
        return response()->json($ticket->load('responses'));
    }
}
