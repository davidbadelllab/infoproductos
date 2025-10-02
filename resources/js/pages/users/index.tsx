import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Edit, Eye, MoreHorizontal, Plus, Search, Trash2, User, Users, Calendar, Mail, Shield } from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    roles: Array<{
        id: number;
        name: string;
    }>;
}

interface UsersIndexProps {
    users: {
        data: User[];
        links: unknown[];
        meta: unknown;
    };
}

export default function UsersIndex({ users }: UsersIndexProps) {
    const [deleting, setDeleting] = useState<number | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Usuarios',
            href: '/users',
        },
    ];

    const handleDelete = (userId: number) => {
        if (confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
            setDeleting(userId);
            router.delete(`/users/${userId}`, {
                onFinish: () => setDeleting(null),
            });
        }
    };

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

    const getRoleIcon = (roleName: string) => {
        switch (roleName) {
            case 'super-admin':
                return <Shield className="h-3 w-3" />;
            case 'admin':
                return <User className="h-3 w-3" />;
            case 'user':
                return <Users className="h-3 w-3" />;
            default:
                return <User className="h-3 w-3" />;
        }
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map(word => word.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const filteredUsers = users.data.filter(user => {
        const matchesSearch = user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                            user.email.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesRole = roleFilter === 'all' || user.roles.some(role => role.name === roleFilter);
        return matchesSearch && matchesRole;
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="space-y-6">
                    <Head title="Usuarios" />

                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                            Gestión de Usuarios
                        </h1>
                        <p className="text-muted-foreground">
                            Administra y supervisa todos los usuarios del sistema
                        </p>
                    </div>
                    <Button asChild className="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700">
                        <Link href="/users/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Nuevo Usuario
                        </Link>
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-3 md:grid-cols-3">
                    <Card className="border-l-4 border-l-blue-500">
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-blue-100 rounded-lg">
                                    <Users className="h-4 w-4 text-blue-600" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Total Usuarios</p>
                                    <p className="text-xl font-bold">{users.data.length}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-green-500">
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-green-100 rounded-lg">
                                    <Shield className="h-4 w-4 text-green-600" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Administradores</p>
                                    <p className="text-xl font-bold">
                                        {users.data.filter(user => user.roles.some(role => role.name === 'admin' || role.name === 'super-admin')).length}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-l-4 border-l-purple-500">
                        <CardContent className="p-4">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 bg-purple-100 rounded-lg">
                                    <User className="h-4 w-4 text-purple-600" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">Usuarios Regulares</p>
                                    <p className="text-xl font-bold">
                                        {users.data.filter(user => user.roles.some(role => role.name === 'user')).length}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters Section */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Buscar por nombre o email..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                            <Select value={roleFilter} onValueChange={setRoleFilter}>
                                <SelectTrigger className="w-full md:w-[200px]">
                                    <SelectValue placeholder="Filtrar por rol" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Todos los roles</SelectItem>
                                    <SelectItem value="super-admin">Super Admin</SelectItem>
                                    <SelectItem value="admin">Admin</SelectItem>
                                    <SelectItem value="user">Usuario</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardContent>
                </Card>

                {/* Users Grid */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {filteredUsers.map((user) => (
                        <Card key={user.id} className="group hover:shadow-lg transition-all duration-200 border-0 shadow-md">
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center space-x-3">
                                        <Avatar className="h-12 w-12 ring-2 ring-blue-100">
                                            <AvatarImage src="" alt={user.name} />
                                            <AvatarFallback className="bg-gradient-to-br from-blue-500 to-purple-600 text-white font-semibold">
                                                {getInitials(user.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="space-y-1">
                                            <CardTitle className="text-lg group-hover:text-blue-600 transition-colors">
                                                {user.name}
                                            </CardTitle>
                                            <div className="flex items-center space-x-1 text-sm text-muted-foreground">
                                                <Mail className="h-3 w-3" />
                                                <span className="truncate max-w-[150px]">{user.email}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link href={`/users/${user.id}`} className="flex items-center">
                                                    <Eye className="mr-2 h-4 w-4" />
                                                    Ver detalles
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={`/users/${user.id}/edit`} className="flex items-center">
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Editar
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                onClick={() => handleDelete(user.id)}
                                                disabled={deleting === user.id}
                                                className="text-red-600 focus:text-red-600"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                {deleting === user.id ? 'Eliminando...' : 'Eliminar'}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-0">
                                <div className="space-y-3">
                                    <div className="flex flex-wrap gap-1">
                                        {user.roles.map((role) => (
                                            <Badge
                                                key={role.id}
                                                variant={getRoleBadgeVariant(role.name)}
                                                className="flex items-center space-x-1"
                                            >
                                                {getRoleIcon(role.name)}
                                                <span className="capitalize">{role.name.replace('-', ' ')}</span>
                                            </Badge>
                                        ))}
                                    </div>
                                    <div className="flex items-center space-x-1 text-xs text-muted-foreground">
                                        <Calendar className="h-3 w-3" />
                                        <span>Miembro desde {new Date(user.created_at).toLocaleDateString('es-ES', {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        })}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Empty State */}
                {filteredUsers.length === 0 && users.data.length > 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="text-center space-y-4">
                                <div className="mx-auto h-12 w-12 rounded-full bg-muted flex items-center justify-center">
                                    <Search className="h-6 w-6 text-muted-foreground" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold">No se encontraron usuarios</h3>
                                    <p className="text-muted-foreground">
                                        Intenta ajustar los filtros de búsqueda
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {users.data.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16">
                            <div className="text-center space-y-4">
                                <div className="mx-auto h-12 w-12 rounded-full bg-muted flex items-center justify-center">
                                    <Users className="h-6 w-6 text-muted-foreground" />
                                </div>
                                <div>
                                    <h3 className="text-lg font-semibold">No hay usuarios registrados</h3>
                                    <p className="text-muted-foreground mb-6">
                                        Comienza creando el primer usuario del sistema
                                    </p>
                                </div>
                                <Button asChild className="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700">
                                    <Link href="/users/create">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Crear Primer Usuario
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
                </div>
            </div>
        </AppLayout>
    );
}
