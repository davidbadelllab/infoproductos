import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Save, Key, Eye, EyeOff } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { edit as editGeminiCredentials, update as updateGeminiCredentials } from '@/routes/settings/credentials/gemini';

interface Props {
    gemini_api_key?: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API Credentials',
        href: editGeminiCredentials().url,
    },
];

export default function GeminiCredentials({ gemini_api_key }: Props) {
    const { data, setData, post, put, processing, errors, recentlySuccessful } = useForm({
        gemini_api_key: gemini_api_key || '',
    });

    const [showApiKey, setShowApiKey] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(updateGeminiCredentials().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Credenciales Gemini - Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Credenciales de Gemini</h2>
                        <p className="text-muted-foreground">Guarda tu API Key de Google Gemini. Se almacena por usuario.</p>
                    </div>

                    <Alert>
                        <AlertDescription>
                            Añade tu API key de Google AI Studio (Gemini). Solo tú podrás usarla para generar copys.
                        </AlertDescription>
                    </Alert>

                    <Card>
                        <CardHeader>
                            <CardTitle>Configuración de API</CardTitle>
                            <CardDescription>Tu clave se guarda cifrada en la base de datos asociada a tu usuario.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-6" autoComplete="off">
                                {/* Campo oculto para confundir al autocompletado */}
                                <input type="password" autoComplete="new-password" style={{display: 'none'}} tabIndex={-1} />

                                <div className="space-y-2">
                                    <Label htmlFor="gemini_api_key">Gemini API Key<span className="text-red-500 ml-1">*</span></Label>
                                    <div className="relative">
                                        <Input
                                            id="gemini_api_key"
                                            name="gemini_api_key_field"
                                            type={showApiKey ? "text" : "password"}
                                            value={data.gemini_api_key}
                                            onChange={(e) => setData('gemini_api_key', e.target.value)}
                                            placeholder="AIzaSy..."
                                            className={errors.gemini_api_key ? 'border-red-500 pr-10' : 'pr-10'}
                                            autoComplete="new-password"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                                            onClick={() => setShowApiKey(!showApiKey)}
                                        >
                                            {showApiKey ? (
                                                <EyeOff className="h-4 w-4 text-muted-foreground" />
                                            ) : (
                                                <Eye className="h-4 w-4 text-muted-foreground" />
                                            )}
                                        </Button>
                                    </div>
                                    {errors.gemini_api_key && <p className="text-sm text-red-500">{errors.gemini_api_key}</p>}
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button type="submit" disabled={processing}>
                                        <Save className="w-4 h-4 mr-2" />
                                        {processing ? 'Guardando...' : 'Guardar credenciales'}
                                    </Button>
                                    {recentlySuccessful && (
                                        <p className="text-sm text-green-600">✓ Credenciales guardadas correctamente</p>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}


