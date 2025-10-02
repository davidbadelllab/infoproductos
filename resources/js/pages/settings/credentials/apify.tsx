import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Save, ExternalLink, Key } from 'lucide-react';
import { edit as editApifyCredentials, update as updateApifyCredentials } from '@/routes/settings/credentials/apify';
import { type BreadcrumbItem } from '@/types';

interface Props {
    apify_api_token?: string;
    apify_actor_id?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API Credentials',
        href: editApifyCredentials().url,
    },
];

export default function ApifyCredentials({ apify_api_token, apify_actor_id }: Props) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        apify_api_token: apify_api_token || '',
        apify_actor_id: apify_actor_id || 'XtaWFhbtfxyzqrFmd',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(updateApifyCredentials().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Credenciales Apify - Settings" />

            <SettingsLayout>

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">Credenciales de Apify</h2>
                    <p className="text-muted-foreground">
                        Configura tus credenciales de Apify para obtener datos reales de Facebook Ads Library
                    </p>
                </div>

                <Alert>
                    <Key className="h-4 w-4" />
                    <AlertDescription>
                        <div className="space-y-2">
                            <p className="font-medium">¿Cómo obtener tus credenciales de Apify?</p>
                            <ol className="list-decimal list-inside space-y-1 text-sm">
                                <li>
                                    Ve a{' '}
                                    <a
                                        href="https://apify.com"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary hover:underline inline-flex items-center gap-1"
                                    >
                                        Apify.com
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                    {' '}y crea una cuenta gratuita
                                </li>
                                <li>Ve a Settings → Integrations → API tokens</li>
                                <li>Copia tu API Token</li>
                                <li>
                                    Busca el actor{' '}
                                    <a
                                        href="https://apify.com/curious_coder/facebook-ad-library-scraper"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary hover:underline inline-flex items-center gap-1"
                                    >
                                        Facebook Ad Library Scraper
                                        <ExternalLink className="h-3 w-3" />
                                    </a>
                                </li>
                                <li>Copia el Actor ID (por defecto ya viene configurado)</li>
                            </ol>
                        </div>
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle>Configuración de API</CardTitle>
                        <CardDescription>
                            Estas credenciales se almacenan de forma segura y solo tú tienes acceso a ellas
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="apify_api_token">
                                    API Token
                                    <span className="text-red-500 ml-1">*</span>
                                </Label>
                                <Input
                                    id="apify_api_token"
                                    type="password"
                                    value={data.apify_api_token}
                                    onChange={(e) => setData('apify_api_token', e.target.value)}
                                    placeholder="apify_api_xxxxxxxxxxxxx"
                                    className={errors.apify_api_token ? 'border-red-500' : ''}
                                />
                                {errors.apify_api_token && (
                                    <p className="text-sm text-red-500">{errors.apify_api_token}</p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    Tu token de API de Apify (comienza con "apify_api_")
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="apify_actor_id">
                                    Actor ID
                                    <span className="text-red-500 ml-1">*</span>
                                </Label>
                                <Input
                                    id="apify_actor_id"
                                    type="text"
                                    value={data.apify_actor_id}
                                    onChange={(e) => setData('apify_actor_id', e.target.value)}
                                    placeholder="XtaWFhbtfxyzqrFmd"
                                    className={errors.apify_actor_id ? 'border-red-500' : ''}
                                />
                                {errors.apify_actor_id && (
                                    <p className="text-sm text-red-500">{errors.apify_actor_id}</p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    ID del actor de Facebook Ad Library Scraper
                                </p>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="w-4 h-4 mr-2" />
                                    {processing ? 'Guardando...' : 'Guardar credenciales'}
                                </Button>

                                {recentlySuccessful && (
                                    <p className="text-sm text-green-600">
                                        ✓ Credenciales guardadas correctamente
                                    </p>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Alert>
                    <AlertDescription className="text-sm">
                        <p className="font-medium mb-2">💡 Nota importante:</p>
                        <ul className="list-disc list-inside space-y-1">
                            <li>Si no configuras tus credenciales, se usarán las credenciales del sistema (limitadas)</li>
                            <li>Con tus propias credenciales tendrás acceso ilimitado a datos reales</li>
                            <li>Apify ofrece un plan gratuito con 5$ de crédito mensual</li>
                            <li>Cada búsqueda consume aproximadamente $0.75 por 1,000 anuncios</li>
                        </ul>
                    </AlertDescription>
                </Alert>
            </div>
            </SettingsLayout>
        </AppLayout>
    );
}
