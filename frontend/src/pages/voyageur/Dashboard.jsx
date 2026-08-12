
import { useState, useEffect } from "react";
import { useLocation } from "wouter";
import { useAuth } from "../../contexts/AuthContext";
import { api } from "../../lib/api";
import Navbar from "../../components/Navbar";
import { Package, MapPin, ArrowRight, RefreshCw, DollarSign, AlertTriangle } from "lucide-react";

const CITIES = [
  "Casablanca", "Rabat", "Marrakech", "Fès", "Tanger", "Agadir",
  "Meknès", "Oujda", "Kénitra", "Tétouan", "Safi", "Mohammedia",
  "Khouribga", "Béni Mellal", "El Jadida", "Nador", "Settat", "Laâyoune"
];

export default function VoyageurDashboard() {
  const { user } = useAuth();
  const [, navigate] = useLocation();
  const [fromCity, setFromCity] = useState(user?.city || "Casablanca");
  const [toCity, setToCity] = useState("Rabat");
  const [available, setAvailable] = useState([]);
  const [myDeliveries, setMyDeliveries] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searched, setSearched] = useState(false);
  const [updating, setUpdating] = useState(false);

  useEffect(() => {
    if (!user) { navigate("/auth"); return; }
    api.colis.list().then(setMyDeliveries).catch((e) => { console.error(e); });
  }, [user]);

  const search = async () => {
    setLoading(true);
    try {
      const res = await api.colis.list({ fromCity, toCity });
      setAvailable(res);
      setSearched(true);
    } catch (e) { console.error(e); }
    setLoading(false);
  };

  const acceptColis = async (colis) => {
    setUpdating(true);
    try {
      await api.colis.updateStatus(colis.id, "picked_up", "Colis pris en charge par un voyageur");
      search();
      const mine = await api.colis.list();
      setMyDeliveries(mine);
    } catch (e) { alert(e.message); }
    setUpdating(false);
  };

  const updateStatus = async (id, status, msg) => {
    setUpdating(true);
    try {
      await api.colis.updateStatus(id, status, msg);
      const mine = await api.colis.list();
      setMyDeliveries(mine);
    } catch (e) { alert(e.message); }
    setUpdating(false);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <div className="max-w-5xl mx-auto px-4 py-6">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-800">Bonjour, {user?.name?.split(" ")[0]} 🚗</h1>
          <p className="text-gray-500 text-sm">Tableau de bord Voyageur — Livrez des colis en voyageant</p>
        </div>

        <div className="bg-teal-50 border border-teal-200 rounded-2xl p-5 mb-6">
          <h3 className="font-bold text-teal-800 mb-1">Comment ça marche?</h3>
          <p className="text-sm text-teal-700 mb-4">
            Vous voyagez de Casablanca à Marrakech? Gagnez de l'argent en prenant des colis sur votre route. Aucune inscription livreur requise!
          </p>
          <div className="grid md:grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-semibold text-teal-700 mb-1.5">Ville de départ</label>
              <select value={fromCity} onChange={(e) => setFromCity(e.target.value)} className="w-full border-2 border-teal-200 rounded-xl px-4 py-2.5 text-sm focus:border-teal-500 focus:outline-none bg-white">
                {CITIES.map((c) => <option key={c}>{c}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-semibold text-teal-700 mb-1.5">Ville d'arrivée</label>
              <select value={toCity} onChange={(e) => setToCity(e.target.value)} className="w-full border-2 border-teal-200 rounded-xl px-4 py-2.5 text-sm focus:border-teal-500 focus:outline-none bg-white">
                {CITIES.filter((c) => c !== fromCity).map((c) => <option key={c}>{c}</option>)}
              </select>
            </div>
          </div>
          <button
            onClick={search}
            disabled={loading || fromCity === toCity}
            className="mt-4 w-full py-3 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 transition-colors"
          >
            <MapPin size={18} />
            {loading ? "Recherche..." : `Chercher colis: ${fromCity} → ${toCity}`}
          </button>
        </div>

        {searched && (
          <div className="mb-6">
            <h3 className="font-semibold text-gray-800 mb-3">
              Colis disponibles: {fromCity} → {toCity} ({available.length})
            </h3>
            {available.length === 0 ? (
              <div className="bg-white rounded-2xl border p-10 text-center text-gray-400">
                <Package size={40} className="mx-auto mb-3 opacity-30" />
                <div>Aucun colis disponible sur ce trajet pour l'instant</div>
              </div>
            ) : (
              <div className="grid md:grid-cols-2 gap-4">
                {available.map((c) => (
                  <div key={c.id} className="bg-white rounded-2xl border shadow-sm overflow-hidden">
                    <div className="bg-gradient-to-r from-teal-500 to-cyan-600 px-4 py-3 flex justify-between items-center">
                      <span className="text-white font-mono font-bold">{c.trackingId}</span>
                      <span className="text-white font-bold">{c.price} MAD</span>
                    </div>
                    <div className="p-4">
                      <div className="flex items-center gap-4 text-sm mb-3">
                        <div>
                          <div className="text-xs text-gray-500">De</div>
                          <div className="font-semibold">{c.fromCity}</div>
                        </div>
                        <ArrowRight size={18} className="text-gray-400" />
                        <div>
                          <div className="text-xs text-gray-500">Vers</div>
                          <div className="font-semibold">{c.toCity}</div>
                        </div>
                      </div>
                      <div className="text-sm text-gray-500 mb-3">
                        {c.weight} kg • {c.description || "Colis"}
                      </div>
                      <button
                        onClick={() => acceptColis(c)}
                        disabled={updating}
                        className="w-full py-2.5 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white rounded-xl text-sm font-semibold transition-colors"
                      >
                        Prendre ce Colis
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {myDeliveries.length > 0 && (
          <div>
            <h3 className="font-semibold text-gray-800 mb-3">Mes Livraisons ({myDeliveries.length})</h3>
            <div className="space-y-3">
              {myDeliveries.map((c) => (
                <div key={c.id} className="bg-white rounded-2xl border shadow-sm px-5 py-4">
                  <div className="flex justify-between items-center mb-2">
                    <span className="font-mono font-bold text-blue-700">{c.trackingId}</span>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${c.status === "delivered" ? "bg-green-100 text-green-700" : "bg-orange-100 text-orange-700"}`}>
                      {c.status}
                    </span>
                  </div>
                  <div className="flex justify-between text-sm">
                    <span>{c.fromCity} → {c.toCity} • {c.weight}kg</span>
                    <span className="font-bold text-teal-600">{c.price} MAD</span>
                  </div>
                  {c.status !== "delivered" && (
                    <div className="flex gap-2 mt-3">
                      <button onClick={() => updateStatus(c.id, "in_transit", "En route avec le voyageur")} className="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg text-xs font-medium">En Transit</button>
                      <button onClick={() => updateStatus(c.id, "delivered", "Livré par le voyageur")} className="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg text-xs font-medium">Livré</button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
