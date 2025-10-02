import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Search, Trophy, TrendingUp } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface DashboardProps extends PageProps {
    stats: {
        total_searches: number;
        total_winners: number;
        total_potential: number;
    };
}

function getGreeting() {
    const hour = new Date().getHours();

    if (hour >= 6 && hour < 12) {
        return 'Buenos días';
    } else if (hour >= 12 && hour < 20) {
        return 'Buenas tardes';
    } else {
        return 'Buenas noches';
    }
}

export default function Dashboard() {
    const { auth, stats } = usePage<DashboardProps>().props;
    const greeting = getGreeting();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                {/* Saludo */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">{greeting}, {auth.user.name}</h1>
                    <p className="text-muted-foreground mt-2">Aquí tienes un resumen de tu actividad</p>
                </div>

                {/* Cards de estadísticas */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total de Búsquedas</CardTitle>
                            <Search className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_searches}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Búsquedas realizadas
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Anuncios Ganadores</CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_winners}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Anuncios con mejor rendimiento
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Potenciales Ganadores</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_potential}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Anuncios con potencial
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
