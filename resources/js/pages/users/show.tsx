import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Mail, User } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    updated_at: string;
    roles: Array<{
        id: number;
        name: string;
    }>;
}

interface UsersShowProps {
    user: User;
}

export default function UsersShow({ user }: UsersShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Usuarios',
            href: '/users',
        },
        {
            title: user.name,
            href: `/users/${user.id}`,
        },
    ];

    const getRoleBadgeVariant = (roleName: string) => {
        switch (roleName) {
            case 'super-admin':
                return 'destructive';
            case 'admin':
                return 'default';
            case 'user':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Usuario - ${user.name}`} />
            
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="space-y-6">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/users">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="flex-1">
                            <h1 className="text-3xl font-bold tracking-tight">{user.name}</h1>
                            <p className="text-muted-foreground">
                                Información detallada del usuario
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={`/users/${user.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Editar
                            </Link>
                        </Button>
                    </div>

                    <div className="grid gap-6 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    Información Personal
                                </CardTitle>
                                <CardDescription>
                                    Datos básicos del usuario
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label className="text-sm font-medium text-muted-foreground">
                                        Nombre Completo
                                    </Label>
                                    <p className="text-lg font-medium">{user.name}</p>
                                </div>
                                
                                <div>
                                    <Label className="text-sm font-medium text-muted-foreground flex items-center gap-1">
                                        <Mail className="h-4 w-4" />
                                        Correo Electrónico
                                    </Label>
                                    <p className="text-lg font-medium">{user.email}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Roles y Permisos</CardTitle>
                                <CardDescription>
                                    Roles asignados al usuario
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {user.roles.length > 0 ? (
                                        user.roles.map((role) => (
                                            <Badge
                                                key={role.id}
                                                variant={getRoleBadgeVariant(role.name)}
                                                className="text-sm"
                                            >
                                                {role.name}
                                            </Badge>
                                        ))
                                    ) : (
                                        <p className="text-muted-foreground">Sin roles asignados</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Sistema</CardTitle>
                                <CardDescription>
                                    Datos de creación y modificación
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label className="text-sm font-medium text-muted-foreground">
                                        Fecha de Creación
                                    </Label>
                                    <p className="text-lg font-medium">
                                        {new Date(user.created_at).toLocaleDateString('es-ES', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </p>
                                </div>
                                
                                <div>
                                    <Label className="text-sm font-medium text-muted-foreground">
                                        Última Modificación
                                    </Label>
                                    <p className="text-lg font-medium">
                                        {new Date(user.updated_at).toLocaleDateString('es-ES', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Acciones</CardTitle>
                                <CardDescription>
                                    Operaciones disponibles para este usuario
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Button asChild className="w-full">
                                    <Link href={`/users/${user.id}/edit`}>
                                        <Edit className="mr-2 h-4 w-4" />
                                        Editar Usuario
                                    </Link>
                                </Button>
                                
                                <Button variant="outline" asChild className="w-full">
                                    <Link href="/users">
                                        <ArrowLeft className="mr-2 h-4 w-4" />
                                        Volver a la Lista
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}