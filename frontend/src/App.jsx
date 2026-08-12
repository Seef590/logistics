import { Switch, Route, Router as WouterRouter, useLocation } from "wouter";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { Toaster } from "@/components/ui/toaster";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AuthProvider } from "./contexts/AuthContext";
import { useAuth } from "./contexts/AuthContext";
import Home from "./pages/Home";
import Auth from "./pages/Auth";
import ExpediteurDashboard from "./pages/expediteur/Dashboard";
import LivreurDashboard from "./pages/livreur/Dashboard";
import DestinataireDashboard from "./pages/destinataire/Dashboard";
import VoyageurDashboard from "./pages/voyageur/Dashboard";
import AdminDashboard from "./pages/admin/Dashboard";

const queryClient = new QueryClient();

function NotFound() {
  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-4xl font-bold text-gray-800 mb-4">404</h1>
        <p className="text-gray-600">Page introuvable</p>
        <a href="/" className="mt-4 inline-block text-blue-600 hover:underline">Retour à l'accueil</a>
      </div>
    </div>
  );
}

function PrivateRoute({ component: Component, role }) {
  const { user, loading } = useAuth();
  const [, navigate] = useLocation();

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }
  if (!user) {
    navigate("/auth");
    return null;
  }
  if (user.role !== role) {
    const paths = {
      admin: "/admin",
      expediteur: "/expediteur",
      livreur: "/livreur",
      destinataire: "/destinataire",
      voyageur: "/voyageur",
    };
    navigate(paths[user.role] || "/");
    return null;
  }
  return <Component />;
}

function Router() {
  return (
    <Switch>
      <Route path="/" component={Home} />
      <Route path="/auth" component={Auth} />
      <Route path="/expediteur" component={() => <PrivateRoute component={ExpediteurDashboard} role="expediteur" />} />
      <Route path="/livreur" component={() => <PrivateRoute component={LivreurDashboard} role="livreur" />} />
      <Route path="/destinataire" component={() => <PrivateRoute component={DestinataireDashboard} role="destinataire" />} />
      <Route path="/voyageur" component={() => <PrivateRoute component={VoyageurDashboard} role="voyageur" />} />
      <Route path="/admin" component={() => <PrivateRoute component={AdminDashboard} role="admin" />} />
      <Route component={NotFound} />
    </Switch>
  );
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <AuthProvider>
          <WouterRouter>
            <Router />
          </WouterRouter>
        </AuthProvider>
        <Toaster />
      </TooltipProvider>
    </QueryClientProvider>
  );
}
