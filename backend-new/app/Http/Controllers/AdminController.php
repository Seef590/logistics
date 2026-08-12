<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Colis;
use App\Models\Ticket;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats()
    {
        $total     = Colis::count();
        $delivered = Colis::where('status', 'delivered')->count();
        $pending   = Colis::where('status', 'pending')->count();
        $revenue   = Colis::where('is_paid', true)->sum('price');

        $cityStats = Colis::selectRaw('from_city as city, count(*) as count')
            ->groupBy('from_city')->get();

        $statusStats = [
            ['name' => 'Créé',      'value' => Colis::where('status','created')->count()],
            ['name' => 'En attente','value' => $pending],
            ['name' => 'En transit','value' => Colis::whereIn('status',['picked_up','in_transit','out_for_delivery'])->count()],
            ['name' => 'Livré',     'value' => $delivered],
            ['name' => 'Échoué',    'value' => Colis::where('status','failed')->count()],
        ];

        $monthFn = DB::getDriverName() === 'sqlite' ? "strftime('%m', created_at)" : 'MONTH(created_at)';
        $monthlyData = Colis::selectRaw("{$monthFn} as month, COUNT(*) as colis, SUM(price) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn($r) => [
                'month'   => ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int)$r->month] ?? (string)$r->month,
                'colis'   => (int)$r->colis,
                'revenue' => (float)$r->revenue,
            ]);

        return response()->json([
            'totalColis'           => $total,
            'deliveredColis'       => $delivered,
            'pendingColis'         => $pending,
            'totalRevenue'         => $revenue,
            'totalUsers'           => User::where('role', '!=', 'admin')->count(),
            'totalLivreurs'        => User::where('role', 'livreur')->count(),
            'pendingVerifications' => User::where('verification_status', 'pending')->count(),
            'openTickets'          => Ticket::where('status', 'open')->count(),
            'deliveryRate'         => $total > 0 ? round(($delivered / $total) * 100) : 0,
            'cityStats'            => $cityStats,
            'statusStats'          => $statusStats,
            'monthlyData'          => $monthlyData,
        ]);
    }

    public function users(Request $request)
    {
        $query = User::where('role', '!=', 'admin');
        if ($request->role) $query->where('role', $request->role);
        if ($request->city) $query->where('city', $request->city);
        return response()->json($query->get()->makeHidden('password'));
    }

    public function colis(Request $request)
    {
        $query = Colis::with('statusHistory');
        if ($request->status) $query->where('status', $request->status);
        if ($request->city) $query->where(fn($q) => $q->where('from_city', $request->city)->orWhere('to_city', $request->city));
        return response()->json($query->orderBy('created_at','desc')->get());
    }

    public function tickets()
    {
        return response()->json(Ticket::with('responses')->orderBy('created_at','desc')->get());
    }

    public function updateTicket(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'sometimes|required|in:open,in_progress,resolved,closed',
            'response' => 'nullable|string|max:5000',
        ]);

        $ticket = Ticket::findOrFail($id);
        if (isset($validated['status'])) $ticket->status = $validated['status'];
        $ticket->save();

        if (!empty($validated['response'])) {
            $ticket->responses()->create([
                'user_id'   => auth()->id(),
                'user_name' => 'Support Admin',
                'message'   => $validated['response'],
            ]);
            Notification::create([
                'user_id' => $ticket->user_id,
                'title'   => 'Réponse à votre ticket',
                'message' => "Le support a répondu à votre ticket: {$ticket->subject}",
                'type'    => 'info',
            ]);
        }

        return response()->json($ticket->load('responses'));
    }

    public function pendingCouriers()
    {
        $pending = User::whereIn('role', ['livreur','voyageur'])
            ->where('verification_status', 'pending')
            ->get()->makeHidden('password');
        return response()->json($pending);
    }

    public function verifyCourier(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $user = User::whereIn('role', ['livreur', 'voyageur'])->findOrFail($id);
        $user->update([
            'verification_status' => $request->status,
            'is_verified'         => $request->status === 'approved',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title'   => $request->status === 'approved' ? 'Compte approuvé!' : 'Vérification rejetée',
            'message' => $request->status === 'approved'
                ? 'Votre compte a été approuvé. Vous pouvez commencer à livrer.'
                : 'Votre demande a été rejetée. Raison: ' . ($request->reason ?? 'Documents insuffisants'),
            'type'    => $request->status === 'approved' ? 'success' : 'error',
        ]);

        return response()->json($user->makeHidden('password'));
    }

    public function warnCourier(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $user = User::whereIn('role', ['livreur', 'voyageur'])->findOrFail($id);
        $user->warnings += 1;

        if ($user->warnings === 2) $user->suspended_until = now()->addDays(3);
        elseif ($user->warnings === 3) $user->suspended_until = now()->addDays(14);
        elseif ($user->warnings >= 4) $user->is_banned = true;

        $user->save();

        if ($user->suspended_until?->isFuture() || $user->is_banned) {
            $user->tokens()->delete();
        }

        Notification::create([
            'user_id' => $user->id,
            'title'   => 'Avertissement reçu',
            'message' => "Vous avez reçu un avertissement ({$user->warnings}/4). Raison: " . ($validated['reason'] ?? 'Comportement inapproprié'),
            'type'    => 'warning',
        ]);

        return response()->json($user->makeHidden('password'));
    }

    public function banCourier($id)
    {
        $user = User::whereIn('role', ['livreur', 'voyageur'])->findOrFail($id);
        $user->update(['is_banned' => true]);
        $user->tokens()->delete();
        return response()->json($user->makeHidden('password'));
    }
}
