import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { ArrowLeft, Download, ExternalLink, Trophy, TrendingUp, MessageCircle, CopyPlus, RefreshCw, Video, ChevronLeft, ChevronRight } from 'lucide-react';
import facebookAds from '@/routes/facebook-ads';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ImageEditor } from '@/components/ImageEditor';

interface FacebookAd {
    id: number;
    page_name: string;
    page_url?: string;
    ads_library_url?: string;
    ad_text?: string;
    ad_image_url?: string;
    ad_video_url?: string;
    ads_count: number;
    days_running: number;
    country?: string;
    country_code?: string;
    has_whatsapp: boolean;
    whatsapp_number?: string;
    is_winner: boolean;
    is_potential: boolean;
    matched_keywords?: string[];
    platforms?: string[];
    ad_id?: string;
    page_id?: string;
    library_id?: string;
    ad_start_date?: string;
    ad_end_date?: string;
    last_seen?: string;
    creation_date?: string;
    ad_delivery_start_time?: string;
    ad_delivery_stop_time?: string;
    total_running_time?: number;
    ad_spend?: unknown;
    impressions?: unknown;
    targeting_info?: unknown;
    ad_type?: string;
    ad_format?: string;
    ad_status?: string;
    is_real_data?: boolean;
    is_apify_data?: boolean;
    data_source?: string;
    raw_data?: any;
    demographics?: any;
}

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
    facebook_ads: FacebookAd[];
}

interface Props {
    search: AdSearch;
}

export default function FacebookAdsShow({ search }: Props) {
    const [cloneOpen, setCloneOpen] = useState<boolean>(false);
    const [cloneAd, setCloneAd] = useState<FacebookAd | null>(null);
    const [country, setCountry] = useState<string>('CO');
    const [price, setPrice] = useState<string>('');
    const [generated, setGenerated] = useState<string>('');
    const [generatedImage, setGeneratedImage] = useState<string>('');
    const [generatedVideo, setGeneratedVideo] = useState<string>('');
    const [clonedAdUuid, setClonedAdUuid] = useState<string>('');
    const [imageUrl, setImageUrl] = useState<string>('');
    const [generating, setGenerating] = useState<boolean>(false);
    const [regeneratingImage, setRegeneratingImage] = useState<boolean>(false);
    const [generatingVideo, setGeneratingVideo] = useState<boolean>(false);

    // Paginación
    const [winnersPage, setWinnersPage] = useState(1);
    const [potentialsPage, setPotentialsPage] = useState(1);
    const [allPage, setAllPage] = useState(1);
    const itemsPerPage = 6;
    // Filtrar: mostrar solo anuncios con texto válido (excluye placeholders como "Sin texto disponible")
    const hasAdText = (ad: FacebookAd): boolean => {
        const raw = (ad.ad_text ?? '').toString().trim();
        if (!raw) return false;

        const textLower = raw.toLowerCase();
        const placeholders = [
            'sin texto disponible',
            'sin texto',
            'texto no disponible',
            'no text available',
            'text not available',
            'not available',
            'n/a',
        ];
        if (placeholders.some((p) => textLower.includes(p))) {
            return false;
        }

        // Requiere al menos un mínimo de contenido alfanumérico
        const alnum = raw.replace(/[^a-zA-Z0-9ñÑáéíóúÁÉÍÓÚ]+/g, '');
        return alnum.length >= 8;
    };
    // Normaliza la clave de agrupación para evitar duplicados por variaciones (espacios/case)
    const getPageKey = (ad: FacebookAd): string => {
        const raw = (ad.page_id as unknown as string) || ad.page_name || '';
        return raw.toString().trim().toLowerCase();
    };

    // Helper: quedarse con un solo anuncio por clave normalizada (página)
    const uniqueByPage = (ads: FacebookAd[]): FacebookAd[] => {
        const seen = new Set<string>();
        const uniques: FacebookAd[] = [];
        for (const ad of ads) {
            const key = getPageKey(ad);
            if (!seen.has(key)) {
                seen.add(key);
                uniques.push(ad);
            }
        }
        return uniques;
    };

    // Base filtrada: solo anuncios con texto
    const adsWithText = search.facebook_ads.filter(hasAdText);

    // Dedupe por página dentro de cada categoría sobre la base filtrada
    const winners = uniqueByPage(adsWithText.filter(ad => ad.is_winner));
    const potentials = uniqueByPage(adsWithText.filter(ad => ad.is_potential));
    const allUnique = uniqueByPage(adsWithText);

    // Contar cuántas veces aparece cada página (para badge de repetición) usando solo los visibles
    const pageNameCounts = adsWithText.reduce((acc, ad) => {
        const key = getPageKey(ad);
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {} as Record<string, number>);

    const handleAdClick = (ad: FacebookAd) => {
        // Debug: ver qué datos tenemos
        console.log('Ad data:', ad);
        console.log('Search data:', search);
        
        // Validar que tenemos los IDs necesarios
        if (!ad.id || !search.id) {
            console.error('Missing IDs:', { adId: ad.id, searchId: search.id });
            return;
        }
        
        // Debug: ver qué argumentos pasamos a showAd.url
        console.log('Calling showAd.url with:', { searchId: search.id, adId: ad.id });
        
        try {
            // Navegar a la página de detalles del anuncio
            const url = facebookAds.showAd.url({ searchId: search.id, adId: ad.id });
            console.log('Generated URL:', url);
            window.location.href = url;
        } catch (error) {
            console.error('Error generating URL:', error);
        }
    };

    const renderAdCard = (ad: FacebookAd) => {
        const repeatCount = pageNameCounts[getPageKey(ad)] || 1;

        return (
            <Card key={ad.id} className="hover:shadow-lg transition-shadow relative cursor-pointer" onClick={() => handleAdClick(ad)}>
                {/* Badge de repetición en la esquina superior derecha */}
                {repeatCount > 1 && (
                    <div className="absolute top-2 right-2 z-10">
                        <Badge className="bg-orange-500 text-white font-bold">
                            ×{repeatCount}
                        </Badge>
                    </div>
                )}

                <CardHeader>
                    <div className="flex items-start justify-between">
                        <div className="flex-1">
                            <CardTitle className="text-lg flex items-center gap-2">
                                {ad.is_winner && <Trophy className="w-5 h-5 text-yellow-500" />}
                                {ad.is_potential && <TrendingUp className="w-5 h-5 text-blue-500" />}
                                {ad.page_name}
                            </CardTitle>
                            <CardDescription className="mt-1">
                                {ad.country} • {ad.days_running} días activo • {ad.ads_count} anuncios
                            </CardDescription>
                        </div>
                        <div className="flex flex-col gap-1 mt-8">
                            {ad.is_winner && <Badge className="bg-yellow-500">Ganador</Badge>}
                            {ad.is_potential && <Badge className="bg-blue-500">Potencial</Badge>}
                            {ad.has_whatsapp && (
                                <Badge className="bg-green-500">
                                    <MessageCircle className="w-3 h-3 mr-1" />
                                    WhatsApp
                                </Badge>
                            )}
                        </div>
                    </div>
                </CardHeader>
            <CardContent className="space-y-4">
                {/* Imagen del anuncio */}
                {ad.ad_image_url && (
                    <div className="aspect-video w-full overflow-hidden rounded-lg bg-gray-100">
                        <img
                            src={ad.ad_image_url}
                            alt={ad.page_name}
                            className="w-full h-full object-cover"
                            onError={(e) => {
                                (e.target as HTMLImageElement).src = 'https://via.placeholder.com/600x400?text=Sin+Imagen';
                            }}
                        />
                    </div>
                )}

                {/* Texto del anuncio */}
                {ad.ad_text && (
                    <div className="space-y-1">
                        <p className="text-sm font-medium">Texto del Anuncio:</p>
                        <p className="text-sm text-muted-foreground line-clamp-3">
                            {ad.ad_text}
                        </p>
                    </div>
                )}

                {/* WhatsApp */}
                {ad.whatsapp_number && (
                    <div className="space-y-1">
                        <p className="text-sm font-medium">WhatsApp:</p>
                        <a
                            href={`https://wa.me/${ad.whatsapp_number.replace(/[^0-9]/g, '')}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm text-green-600 hover:underline flex items-center gap-1"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {ad.whatsapp_number}
                            <ExternalLink className="w-3 h-3" />
                        </a>
                    </div>
                )}

                {/* Plataformas */}
                {ad.platforms && ad.platforms.length > 0 && (
                    <div className="flex gap-1 flex-wrap">
                        {ad.platforms.map((platform) => (
                            <Badge key={platform} variant="outline" className="text-xs">
                                {platform}
                            </Badge>
                        ))}
                    </div>
                )}

                {/* Enlaces */}
                <div className="flex gap-2" onClick={(e) => e.stopPropagation()}>
                    {ad.page_url && (
                        <a href={ad.page_url} target="_blank" rel="noopener noreferrer">
                            <Button variant="outline" size="sm">
                                <ExternalLink className="w-4 h-4 mr-2" />
                                Página
                            </Button>
                        </a>
                    )}
                    {ad.ads_library_url && (
                        <a href={ad.ads_library_url} target="_blank" rel="noopener noreferrer">
                            <Button variant="outline" size="sm">
                                <ExternalLink className="w-4 h-4 mr-2" />
                                Ads Library
                            </Button>
                        </a>
                    )}
                    <Button variant="default" size="sm" onClick={() => { setCloneAd(ad); setGenerated(''); setGeneratedImage(''); setImageUrl(ad.ad_image_url || ''); setGeneratedVideo(''); setPrice(''); setCountry(ad.country_code || 'CO'); setCloneOpen(true); }}>
                        <CopyPlus className="w-4 h-4 mr-2" />
                        Clonar
                    </Button>
                </div>
            </CardContent>
        </Card>
        );
    };

    // Helper para renderizar una lista con paginación
    const renderPaginatedList = (ads: FacebookAd[], currentPage: number, setPage: (page: number) => void) => {
        const totalPages = Math.ceil(ads.length / itemsPerPage);
        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const currentAds = ads.slice(startIndex, endIndex);

        return (
            <>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {currentAds.map(renderAdCard)}
                </div>

                {totalPages > 1 && (
                    <>
                        <div className="mt-8 flex items-center justify-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPage(Math.max(1, currentPage - 1))}
                                disabled={currentPage === 1}
                            >
                                <ChevronLeft className="w-4 h-4 mr-1" />
                                Anterior
                            </Button>

                            <div className="flex items-center gap-1">
                                {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                                    <Button
                                        key={page}
                                        variant={currentPage === page ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => setPage(page)}
                                        className="w-10"
                                    >
                                        {page}
                                    </Button>
                                ))}
                            </div>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setPage(Math.min(totalPages, currentPage + 1))}
                                disabled={currentPage === totalPages}
                            >
                                Siguiente
                                <ChevronRight className="w-4 h-4 ml-1" />
                            </Button>
                        </div>

                        <div className="mt-4 text-center text-sm text-muted-foreground">
                            Mostrando {startIndex + 1}-{Math.min(endIndex, ads.length)} de {ads.length} anuncios
                        </div>
                    </>
                )}
            </>
        );
    };

    return (
        <AppLayout>
            <Head title={`Búsqueda #${search.id} - Facebook Ads`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {/* Header */}
                <div className="mb-6 space-y-4">
                    <Link href={facebookAds.index.url()}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            Volver
                        </Button>
                    </Link>

                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold">Búsqueda #{search.id}</h1>
                            <p className="text-muted-foreground">
                                {search.keywords.join(', ')} • {search.countries.join(', ')}
                            </p>
                        </div>
                        <Link href={facebookAds.export.url(search.id, { query: { format: 'csv' } })}>
                            <Button>
                                <Download className="w-4 h-4 mr-2" />
                                Exportar CSV
                            </Button>
                        </Link>
                    </div>

                    {/* Stats */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Total Resultados</CardDescription>
                                <CardTitle className="text-3xl">{search.total_results}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Ganadores</CardDescription>
                                <CardTitle className="text-3xl text-yellow-500 flex items-center gap-2">
                                    <Trophy className="w-6 h-6" />
                                    {search.winners_count}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Potenciales</CardDescription>
                                <CardTitle className="text-3xl text-blue-500 flex items-center gap-2">
                                    <TrendingUp className="w-6 h-6" />
                                    {search.potential_count}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Con WhatsApp</CardDescription>
                                <CardTitle className="text-3xl text-green-500 flex items-center gap-2">
                                    <MessageCircle className="w-6 h-6" />
                                    {search.facebook_ads.filter(ad => ad.has_whatsapp).length}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </div>
                </div>

                {/* Results */}
                <Tabs defaultValue="winners" className="space-y-6">
                    <TabsList>
                        <TabsTrigger value="winners">
                            Ganadores ({winners.length})
                        </TabsTrigger>
                        <TabsTrigger value="potentials">
                            Potenciales ({potentials.length})
                        </TabsTrigger>
                        <TabsTrigger value="all">
                            Todos ({allUnique.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="winners" className="space-y-4">
                        {winners.length === 0 ? (
                            <Card>
                                <CardContent className="py-8 text-center text-muted-foreground">
                                    No se encontraron productos ganadores
                                </CardContent>
                            </Card>
                        ) : (
                            renderPaginatedList(winners, winnersPage, setWinnersPage)
                        )}
                    </TabsContent>

                    <TabsContent value="potentials" className="space-y-4">
                        {potentials.length === 0 ? (
                            <Card>
                                <CardContent className="py-8 text-center text-muted-foreground">
                                    No se encontraron productos potenciales
                                </CardContent>
                            </Card>
                        ) : (
                            renderPaginatedList(potentials, potentialsPage, setPotentialsPage)
                        )}
                    </TabsContent>

                    <TabsContent value="all" className="space-y-4">
                        {renderPaginatedList(allUnique, allPage, setAllPage)}
                    </TabsContent>
                </Tabs>
            </div>

            {/* Clone modal */}
            <Dialog open={cloneOpen} onOpenChange={setCloneOpen}>
                <DialogContent className="sm:max-w-4xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Clonar anuncio</DialogTitle>
                        <DialogDescription>
                            Genera un copy optimizado para vender usando Gemini AI
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Nombre de la página</Label>
                            <Input value={cloneAd?.page_name || ''} readOnly />
                        </div>
                        <div>
                            <Label>Texto original</Label>
                            <textarea className="w-full rounded-md border bg-background p-3 text-sm" rows={6} readOnly value={cloneAd?.ad_text || ''} />
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label>País</Label>
                                <Input value={country} onChange={(e) => setCountry(e.target.value.toUpperCase())} placeholder="CO" />
                            </div>
                            <div>
                                <Label>Precio</Label>
                                <Input type="number" min="0" step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} placeholder="19.99" />
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" onClick={() => setCloneOpen(false)}>Cancelar</Button>
                            <Button onClick={async () => {
                                if (!cloneAd) return; setGenerating(true);
                                try {
                                    // Obtener token CSRF fresco cada vez
                                    const csrfMeta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
                                    const csrfToken = csrfMeta?.content;

                                    if (!csrfToken) {
                                        alert('Sesión expirada. Por favor recarga la página (F5).');
                                        setGenerating(false);
                                        return;
                                    }

                                    const res = await fetch(facebookAds.generate.url(), {
                                        method: 'POST',
                                        credentials: 'same-origin',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'X-CSRF-TOKEN': csrfToken,
                                            'Accept': 'application/json',
                                        },
                                        body: JSON.stringify({
                                            page_name: cloneAd.page_name,
                                            ad_text: cloneAd.ad_text,
                                            country,
                                            price: parseFloat(price || '0'),
                                        }),
                                    });

                                    // Si es error 419, mostrar mensaje específico
                                    if (res.status === 419) {
                                        alert('Tu sesión ha expirado. Por favor recarga la página (presiona F5) e intenta de nuevo.');
                                        setGenerating(false);
                                        return;
                                    }

                                    const data = await res.json();
                                    if (!res.ok) {
                                        const errorMessage = data?.message || 'Error generando copy';
                                        alert(errorMessage);
                                        return;
                                    }
                                    setGenerated(data.generated || '');
                                    setGeneratedImage(data.image || '');
                                    setClonedAdUuid(data.cloned_ad_uuid || '');
                                    setImageUrl(data.image_url || '');
                                    if (data.warning) {
                                        alert(data.warning);
                                    }
                                } catch (e) {
                                    console.error('Error inesperado:', e);
                                    alert('Error inesperado al generar el copy. Por favor intenta de nuevo.');
                                } finally {
                                    setGenerating(false);
                                }
                            }} disabled={generating || !price}>
                                {generating ? 'Generando…' : 'Generar con Gemini'}
                            </Button>
                        </div>
                        {generated && (
                            <div className="space-y-4">
                                <div>
                                    <Label>Copy generado</Label>
                                    <textarea
                                        className="w-full rounded-md border bg-background p-3 text-sm"
                                        rows={8}
                                        value={generated}
                                        onChange={(e) => setGenerated(e.target.value)}
                                        placeholder="Edita tu copy aquí antes de regenerar imagen o video"
                                    />
                                </div>
                                {(generatedImage || imageUrl) && (
                                    <div>
                                        <Label>{generatedImage ? 'Imagen generada' : 'Imagen original'}</Label>
                                        <div className="mt-2 border rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                                            <ImageEditor
                                                imageUrl={generatedImage || imageUrl}
                                                onSave={(editedImage) => {
                                                    setGeneratedImage(editedImage);
                                                }}
                                            />
                                            <div className="mt-3 flex justify-center gap-2 flex-wrap">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={async () => {
                                                        if (!cloneAd) return;
                                                        setRegeneratingImage(true);
                                                        try {
                                                            const res = await fetch('/facebook-ads/regenerate-image', {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                                                },
                                                                body: JSON.stringify({
                                                                    page_name: cloneAd.page_name,
                                                                    ad_text: generated || cloneAd.ad_text,
                                                                    country,
                                                                    price: parseFloat(price || '0'),
                                                                    cloned_ad_uuid: clonedAdUuid,
                                                                }),
                                                            });
                                                            const data = await res.json();
                                                            if (!res.ok) throw new Error(data?.message || 'Error regenerando imagen');
                                                            setGeneratedImage(data.image || '');
                                                            if (data.image_url) setImageUrl(data.image_url);
                                                        } catch (e) {
                                                            console.error(e);
                                                            alert('No se pudo regenerar la imagen');
                                                        } finally {
                                                            setRegeneratingImage(false);
                                                        }
                                                    }}
                                                    disabled={regeneratingImage}
                                                >
                                                    <RefreshCw className={`w-4 h-4 mr-2 ${regeneratingImage ? 'animate-spin' : ''}`} />
                                                    {regeneratingImage ? 'Regenerando...' : 'Regenerar Imagen'}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        const link = document.createElement('a');
                                                        link.href = generatedImage;
                                                        link.download = `ad-${cloneAd?.page_name || 'image'}.png`;
                                                        link.click();
                                                    }}
                                                >
                                                    <Download className="w-4 h-4 mr-2" />
                                                    Descargar
                                                </Button>
                                            <Button
                                                size="sm"
                                                variant="default"
                                                onClick={async () => {
                                                    if (!clonedAdUuid) {
                                                        alert('Debes generar el copy primero.');
                                                        return;
                                                    }
                                                    try {
                                                        // Guardar copy
                                                        await fetch(`/cloned-ads/${clonedAdUuid}`, {
                                                            method: 'PUT',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                                            },
                                                            body: JSON.stringify({
                                                                generated_copy: generated,
                                                                page_name: cloneAd?.page_name,
                                                                price: parseFloat(price || '0'),
                                                            }),
                                                        });

                                                        // Guardar imagen si existe
                                                        if (generatedImage) {
                                                            await fetch(`/cloned-ads/${clonedAdUuid}/update-image`, {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                                                },
                                                                body: JSON.stringify({ image: generatedImage }),
                                                            });
                                                        }

                                                        // Guardar video si existe
                                                        if (generatedVideo) {
                                                            await fetch(`/cloned-ads/${clonedAdUuid}/update-video`, {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                                                },
                                                                body: JSON.stringify({ video: generatedVideo }),
                                                            });
                                                        }

                                                        alert('Anuncio guardado correctamente');
                                                    } catch (e) {
                                                        console.error(e);
                                                        alert('No se pudo guardar el anuncio');
                                                    }
                                                }}
                                            >
                                                Guardar
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                onClick={async () => {
                                                    if (!clonedAdUuid) {
                                                        alert('Primero guarda el anuncio.');
                                                        return;
                                                    }
                                                    // Placeholder: integración con Facebook Marketing API
                                                    alert('Programación pendiente: conectaremos con Facebook Ads API para publicar a fecha/hora.');
                                                }}
                                            >
                                                Programar anuncio
                                            </Button>
                                                <Button
                                                    size="sm"
                                                    variant="default"
                                                    onClick={async () => {
                                                        if (!cloneAd || !generatedImage) return;
                                                        setGeneratingVideo(true);
                                                        try {
                                                            const res = await fetch(facebookAds.generateVideo.url(), {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                                                },
                                                                body: JSON.stringify({
                                                                    image: generatedImage,
                                                                    page_name: cloneAd.page_name || 'Tu Negocio',
                                                                    text1: cloneAd.page_name || 'Tu Negocio Aquí',
                                                                    text2: `Descubre Más
[size 150%]¡Aprovecha![/size]`,
                                                                }),
                                                            });
                                                            const data = await res.json();
                                                            if (!res.ok) {
                                                                alert(data?.message || 'Error generando video');
                                                                throw new Error(data?.message || 'Error generando video');
                                                            }
                                                            // Veo puede retornar video_url o video_data (base64)
                                                            setGeneratedVideo(data.video_data || data.video_url || '');
                                                        } catch (e) {
                                                            console.error(e);
                                                        } finally {
                                                            setGeneratingVideo(false);
                                                        }
                                                    }}
                                                    disabled={generatingVideo || !generatedImage}
                                                >
                                                    <Video className={`w-4 h-4 mr-2 ${generatingVideo ? 'animate-pulse' : ''}`} />
                                                    {generatingVideo ? 'Generando video...' : 'Generar Video'}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                                {generatedVideo && (
                                    <div>
                                        <Label>Video generado</Label>
                                        <div className="mt-2 border rounded-lg p-4 bg-gray-50 dark:bg-gray-900">
                                            <video
                                                src={generatedVideo}
                                                controls
                                                className="w-full max-w-md mx-auto rounded-lg shadow-lg"
                                            />
                                            <div className="mt-3 flex justify-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        const link = document.createElement('a');
                                                        link.href = generatedVideo;
                                                        link.download = `video-${cloneAd?.page_name || 'ad'}.mp4`;
                                                        link.click();
                                                    }}
                                                >
                                                    <Download className="w-4 h-4 mr-2" />
                                                    Descargar Video
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
