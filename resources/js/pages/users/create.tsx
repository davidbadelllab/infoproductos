import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle } from 'lucide-react';

interface Role {
    id: number;
    name: string;
}

interface UsersCreateProps {
    roles: Role[];
}

export default function UsersCreate({ roles }: UsersCreateProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: [] as string[],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Usuarios',
            href: '/users',
        },
        {
            title: 'Crear Usuario',
            href: '/users/create',
        },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/users', {
            onSuccess: () => reset(),
        });
    };

    const handleRoleChange = (roleName: string, checked: boolean) => {
        if (checked) {
            setData('roles', [...data.roles, roleName]);
        } else {
            setData('roles', data.roles.filter(role => role !== roleName));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="space-y-6">
                    <Head title="Crear Usuario" />

            <div className="flex items-center gap-4">
                <Button variant="outline" size="sm" asChild>
                    <Link href="/users">
                        <ArrowLeft className="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Crear Usuario</h1>
                    <p className="text-muted-foreground">
                        Agregar un nuevo usuario al sistema
                    </p>
                </div>
            </div>

            <Card className="max-w-2xl">
                <CardHeader>
                    <CardTitle>Información del Usuario</CardTitle>
                    <CardDescription>
                        Completa los datos del nuevo usuario
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Nombre completo"
                                style={{ 
                                    color: '#111827', 
                                    backgroundColor: '#ffffff',
                                    borderColor: '#d1d5db'
                                }}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="email">Correo Electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="correo@ejemplo.com"
                                style={{ 
                                    color: '#111827', 
                                    backgroundColor: '#ffffff',
                                    borderColor: '#d1d5db'
                                }}
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">Contraseña</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="Mínimo 8 caracteres"
                                style={{ 
                                    color: '#111827', 
                                    backgroundColor: '#ffffff',
                                    borderColor: '#d1d5db'
                                }}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password_confirmation">Confirmar Contraseña</Label>
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="Repite la contraseña"
                                style={{ 
                                    color: '#111827', 
                                    backgroundColor: '#ffffff',
                                    borderColor: '#d1d5db'
                                }}
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>

                        <div className="space-y-4">
                            <Label>Roles</Label>
                            <div className="space-y-3">
                                {roles.map((role) => (
                                    <div key={role.id} className="flex items-center space-x-2">
                                        <Checkbox
                                            id={`role-${role.id}`}
                                            checked={data.roles.includes(role.name)}
                                            onCheckedChange={(checked) => 
                                                handleRoleChange(role.name, checked as boolean)
                                            }
                                        />
                                        <Label 
                                            htmlFor={`role-${role.id}`}
                                            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                        >
                                            {role.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            <InputError message={errors.roles} />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                Crear Usuario
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href="/users">Cancelar</Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
                </div>
            </div>
        </AppLayout>
    );
}
