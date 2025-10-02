import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Search, Download, Eye, CheckCircle, AlertCircle, Clock, X, Loader2 } from 'lucide-react';
import facebookAds from '@/routes/facebook-ads';

interface AdSearch {
    id: number;
    keywords: string[];
    countries: string[];
    status: string;
    total_results: number;
    winners_count: number;
    potential_count: number;
    data_source: string;
    created_at: string;
    processing_time?: number;
}

interface Props {
    searches: {
        data: AdSearch[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export default function FacebookAdsIndex({ searches }: Props) {
    const [isSearching, setIsSearching] = useState(false);
    const [searchProgress, setSearchProgress] = useState(0);
    const [searchMessage, setSearchMessage] = useState('');
    const [formData, setFormData] = useState<{
        keywords: string[];
        countries: string[];
        predefinedKeywords: string[];
        dataSource: string;
        daysBack: string | number;
        minAds: string | number;
        minDaysRunning: number;
        minAdsForLongRunning: number;
    }>({
        keywords: [''],
        countries: [],
        predefinedKeywords: [],
        dataSource: 'apify',
        daysBack: '',
        minAds: '',
        minDaysRunning: 30,
        minAdsForLongRunning: 5,
    });

    const progressMessages = [
        { progress: 10, message: 'Iniciando búsqueda...' },
        { progress: 25, message: 'Conectando con Apify...' },
        { progress: 40, message: 'Buscando anuncios en Facebook Ads Library...' },
        { progress: 60, message: 'Analizando resultados...' },
        { progress: 75, message: 'Procesando datos de anuncios...' },
        { progress: 85, message: 'Identificando productos ganadores...' },
        { progress: 95, message: 'Finalizando búsqueda...' },
    ];

    useEffect(() => {
        if (!isSearching) {
            setSearchProgress(0);
            setSearchMessage('');
            return;
        }

        let currentStep = 0;
        const interval = setInterval(() => {
            if (currentStep < progressMessages.length) {
                setSearchProgress(progressMessages[currentStep].progress);
                setSearchMessage(progressMessages[currentStep].message);
                currentStep++;
            }
        }, 8000); // Cambiar mensaje cada 8 segundos

        return () => clearInterval(interval);
    }, [isSearching]);

    const countries = [
        { code: 'CL', name: 'Chile' },
        { code: 'PE', name: 'Perú' },
        { code: 'MX', name: 'México' },
        { code: 'AR', name: 'Argentina' },
        { code: 'CO', name: 'Colombia' },
        { code: 'EC', name: 'Ecuador' },
        { code: 'BO', name: 'Bolivia' },
    ];

    const predefinedKeywords = [
        'api.whatsapp.com | pdf',
        'api.whatsapp.com | ebook',
        'api.whatsapp.com | imprimible',
        'api.whatsapp.com | pack',
        'api.whatsapp.com | megapack',
        'api.whatsapp.com | curso',
        'api.whatsapp.com | recetas',
        'api.whatsapp.com | descargables',
        'api.whatsapp.com | cursos',
        'api.whatsapp.com | kit',
        'api.whatsapp.com | educativo',
        'api.whatsapp.com | educacion',
    ];

    const handleAddKeyword = () => {
        setFormData({
            ...formData,
            keywords: [...formData.keywords, ''],
        });
    };

    const handleRemoveKeyword = (index: number) => {
        const newKeywords = formData.keywords.filter((_, i) => i !== index);
        setFormData({ ...formData, keywords: newKeywords });
    };

    const handleKeywordChange = (index: number, value: string) => {
        const newKeywords = [...formData.keywords];
        newKeywords[index] = value;
        setFormData({ ...formData, keywords: newKeywords });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSearching(true);

        // Combinar palabras clave manuales y predefinidas
        const allKeywords = [
            ...formData.keywords.filter(k => k.trim() !== ''),
            ...formData.predefinedKeywords
        ];

        router.post(facebookAds.search.url(), {
            keywords: allKeywords,
            countries: formData.countries,
            dataSource: formData.dataSource,
            daysBack: parseInt(formData.daysBack as string) || 0,
            minAds: parseInt(formData.minAds as string) || 0,
            minDaysRunning: formData.minDaysRunning,
            minAdsForLongRunning: formData.minAdsForLongRunning,
        }, {
            onSuccess: (page) => {
                // La respuesta es JSON, así que necesitamos manejarla diferente
                setIsSearching(false);
            },
            onError: (errors) => {
                alert('Error al realizar la búsqueda');
                console.error(errors);
                setIsSearching(false);
            },
            onFinish: () => {
                setIsSearching(false);
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'completed':
                return <Badge className="bg-green-500"><CheckCircle className="w-3 h-3 mr-1" />Completado</Badge>;
            case 'processing':
                return <Badge className="bg-blue-500"><Clock className="w-3 h-3 mr-1" />Procesando</Badge>;
            case 'failed':
                return <Badge className="bg-red-500"><AlertCircle className="w-3 h-3 mr-1" />Fallido</Badge>;
            default:
                return <Badge className="bg-gray-500">Pendiente</Badge>;
        }
    };

    return (
        <AppLayout>
            <Head title="Facebook Ads Scraper" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold">Facebook Ads Library Scraper</h1>
                    <p className="text-muted-foreground">Encuentra productos ganadores analizando anuncios de Facebook</p>
                </div>

                <Tabs defaultValue="search" className="space-y-6">
                    <TabsList>
                        <TabsTrigger value="search">Nueva Búsqueda</TabsTrigger>
                        <TabsTrigger value="history">Historial</TabsTrigger>
                    </TabsList>

                    <TabsContent value="search">
                        <Card>
                            <CardHeader>
                                <CardTitle>Configurar Búsqueda</CardTitle>
                                <CardDescription>
                                    Define los parámetros para buscar anuncios en Facebook Ads Library
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    {/* Keywords */}
                                    <div className="space-y-2">
                                        <Label>Palabras Clave</Label>
                                        {formData.keywords.map((keyword, index) => (
                                            <div key={index} className="flex gap-2">
                                                <Input
                                                    value={keyword}
                                                    onChange={(e) => handleKeywordChange(index, e.target.value)}
                                                    placeholder="Ej: curso pdf, pack fitness"
                                                    required={index === 0}
                                                />
                                                {formData.keywords.length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() => handleRemoveKeyword(index)}
                                                    >
                                                        Eliminar
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                        <Button type="button" variant="outline" onClick={handleAddKeyword}>
                                            + Agregar Palabra Clave
                                        </Button>

                                    </div>

                                    {/* Palabras Clave Predefinidas y Países */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Palabras Clave Predefinidas */}
                                        <div className="space-y-2">
                                            <Label>Palabras Clave Predefinidas</Label>
                                            <Select onValueChange={(value) => {
                                                if (!formData.predefinedKeywords.includes(value)) {
                                                    setFormData({
                                                        ...formData,
                                                        predefinedKeywords: [...formData.predefinedKeywords, value]
                                                    });
                                                }
                                            }}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Selecciona palabras clave..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {predefinedKeywords.map((keyword) => (
                                                        <SelectItem key={keyword} value={keyword}>
                                                            {keyword.split('|')[1].trim()}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {/* Badges para mostrar seleccionados */}
                                            {formData.predefinedKeywords.length > 0 && (
                                                <div className="flex flex-wrap gap-2 mt-2">
                                                    {formData.predefinedKeywords.map((keyword) => (
                                                        <Badge key={keyword} variant="secondary" className="flex items-center gap-1">
                                                            {keyword.split('|')[1].trim()}
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setFormData({
                                                                        ...formData,
                                                                        predefinedKeywords: formData.predefinedKeywords.filter(k => k !== keyword)
                                                                    });
                                                                }}
                                                                className="ml-1 hover:bg-secondary-foreground/20 rounded-full"
                                                            >
                                                                <X className="w-3 h-3" />
                                                            </button>
                                                        </Badge>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        {/* Countries */}
                                        <div className="space-y-2">
                                            <Label>Países</Label>
                                            <Select onValueChange={(value) => {
                                                if (!formData.countries.includes(value)) {
                                                    setFormData({
                                                        ...formData,
                                                        countries: [...formData.countries, value]
                                                    });
                                                }
                                            }}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Selecciona países..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {countries.map((country) => (
                                                        <SelectItem key={country.code} value={country.code}>
                                                            {country.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {/* Badges para mostrar países seleccionados */}
                                            {formData.countries.length > 0 && (
                                                <div className="flex flex-wrap gap-2 mt-2">
                                                    {formData.countries.map((countryCode) => {
                                                        const country = countries.find(c => c.code === countryCode);
                                                        return (
                                                            <Badge key={countryCode} variant="secondary" className="flex items-center gap-1">
                                                                {country?.name}
                                                                <button
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setFormData({
                                                                            ...formData,
                                                                            countries: formData.countries.filter(c => c !== countryCode)
                                                                        });
                                                                    }}
                                                                    className="ml-1 hover:bg-secondary-foreground/20 rounded-full"
                                                                >
                                                                    <X className="w-3 h-3" />
                                                                </button>
                                                            </Badge>
                                                        );
                                                    })}
                                                </div>
                                            )}
                                        </div>
                                    </div>


                                    {/* Advanced Settings */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label>Días Atrás</Label>
                                            <Input
                                                type="number"
                                                value={formData.daysBack}
                                                onChange={(e) => setFormData({ ...formData, daysBack: e.target.value })}
                                                placeholder="Ej: 30"
                                                min="1"
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Mínimo de Anuncios</Label>
                                            <Input
                                                type="number"
                                                value={formData.minAds}
                                                onChange={(e) => setFormData({ ...formData, minAds: e.target.value })}
                                                placeholder="Ej: 10"
                                                min="1"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div className="flex justify-center">
                                        <Button type="submit" disabled={isSearching}>
                                            <Search className="w-4 h-4 mr-2" />
                                            {isSearching ? 'Buscando...' : 'Iniciar Búsqueda'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="history">
                        <div className="space-y-4">
                            {searches.data.length === 0 ? (
                                <Card>
                                    <CardContent className="py-8 text-center text-muted-foreground">
                                        No hay búsquedas realizadas aún
                                    </CardContent>
                                </Card>
                            ) : (
                                searches.data.map((search) => (
                                    <Card key={search.id}>
                                        <CardHeader>
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <CardTitle className="text-lg">
                                                        Búsqueda #{search.id}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {search.keywords.join(', ')} • {search.countries.join(', ')}
                                                    </CardDescription>
                                                </div>
                                                {getStatusBadge(search.status)}
                                            </div>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="flex items-center justify-between">
                                                <div className="space-y-1 text-sm">
                                                    <p><strong>Resultados:</strong> {search.total_results}</p>
                                                    <p><strong>Ganadores:</strong> {search.winners_count}</p>
                                                    <p><strong>Potenciales:</strong> {search.potential_count}</p>
                                                    <p><strong>Fuente:</strong> {search.data_source}</p>
                                                    <p className="text-muted-foreground">
                                                        {new Date(search.created_at).toLocaleString('es-ES')}
                                                    </p>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Link href={facebookAds.show.url(search.id)}>
                                                        <Button variant="outline" size="sm">
                                                            <Eye className="w-4 h-4 mr-2" />
                                                            Ver
                                                        </Button>
                                                    </Link>
                                                    <Link href={facebookAds.export.url(search.id, { query: { format: 'csv' } })}>
                                                        <Button variant="outline" size="sm">
                                                            <Download className="w-4 h-4 mr-2" />
                                                            CSV
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Overlay de búsqueda */}
            {isSearching && (
                <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center">
                    <Card className="w-full max-w-md mx-4">
                        <CardContent className="pt-6">
                            <div className="space-y-6">
                                {/* Icono animado */}
                                <div className="flex justify-center">
                                    <div className="relative">
                                        <Loader2 className="w-16 h-16 text-primary animate-spin" />
                                        <Search className="w-8 h-8 text-primary absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2" />
                                    </div>
                                </div>

                                {/* Mensaje de progreso */}
                                <div className="text-center space-y-2">
                                    <h3 className="text-lg font-semibold">Buscando anuncios</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {searchMessage || 'Iniciando búsqueda...'}
                                    </p>
                                </div>

                                {/* Barra de progreso */}
                                <div className="space-y-2">
                                    <div className="w-full bg-secondary rounded-full h-2 overflow-hidden">
                                        <div
                                            className="bg-primary h-full transition-all duration-1000 ease-out"
                                            style={{ width: `${searchProgress}%` }}
                                        />
                                    </div>
                                    <p className="text-xs text-center text-muted-foreground">
                                        {searchProgress}% completado
                                    </p>
                                </div>

                                {/* Mensaje informativo */}
                                <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                    <p className="text-xs text-blue-800 dark:text-blue-200">
                                        Este proceso puede tomar varios minutos. Por favor, no cierres esta ventana.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}
