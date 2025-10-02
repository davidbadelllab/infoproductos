import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { ArrowLeft, ExternalLink, Trophy, TrendingUp, MessageCircle, Calendar, DollarSign, Eye, Info } from 'lucide-react';
import facebookAds from '@/routes/facebook-ads';

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
    ad_spend?: any;
    impressions?: any;
    targeting_info?: any;
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
}

interface Props {
    ad: FacebookAd;
    search: AdSearch;
}

export default function FacebookAdShow({ ad, search }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Facebook Ads',
            href: '/facebook-ads',
        },
        {
            title: `Búsqueda #${search.id}`,
            href: facebookAds.show.url(search.id),
        },
        {
            title: ad.page_name,
            href: facebookAds.showAd.url({ searchId: search.id, adId: ad.id }),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="space-y-6">
                    <Head title={`Anuncio - ${ad.page_name}`} />
                    {/* Header */}
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={facebookAds.show.url(search.id)}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="flex-1">
                            <div className="flex items-center gap-2 mb-2">
                                {ad.is_winner && <Badge className="bg-yellow-500">Ganador</Badge>}
                                {ad.is_potential && <Badge className="bg-blue-500">Potencial</Badge>}
                                {ad.has_whatsapp && <Badge className="bg-green-500"><MessageCircle className="w-3 h-3 mr-1" />WhatsApp</Badge>}
                            </div>
                            <h1 className="text-3xl font-bold tracking-tight bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                                {ad.page_name}
                            </h1>
                            <p className="text-muted-foreground">
                                {ad.country} • Búsqueda #{search.id}
                            </p>
                        </div>
                    </div>

                    {/* Información de la Página */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Trophy className="w-5 h-5" />
                                Información de la Página
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div><span className="font-medium">Nombre:</span> {ad.page_name}</div>
                                <div><span className="font-medium">Page ID:</span> {ad.page_id || 'N/A'}</div>
                                <div><span className="font-medium">Ad ID:</span> {ad.ad_id || 'N/A'}</div>
                                <div><span className="font-medium">Library ID:</span> {ad.library_id || 'N/A'}</div>
                                <div><span className="font-medium">País:</span> {ad.country} ({ad.country_code})</div>
                                <div><span className="font-medium">Fuente:</span> {ad.data_source || 'N/A'}</div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Métricas del Anuncio */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="w-5 h-5" />
                                Métricas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg">
                                    <div className="font-medium text-blue-700 dark:text-blue-300 mb-2">Cantidad de Anuncios</div>
                                    <div className="text-3xl font-bold">{ad.ads_count}</div>
                                </div>
                                <div className="bg-green-50 dark:bg-green-950 p-4 rounded-lg">
                                    <div className="font-medium text-green-700 dark:text-green-300 mb-2">Días Activo</div>
                                    <div className="text-3xl font-bold">{ad.days_running}</div>
                                </div>
                                <div className="bg-purple-50 dark:bg-purple-950 p-4 rounded-lg">
                                    <div className="font-medium text-purple-700 dark:text-purple-300 mb-2">Tiempo Total</div>
                                    <div className="text-3xl font-bold">{ad.total_running_time || 'N/A'}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Fechas */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="w-5 h-5" />
                                Fechas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                                <div><span className="font-medium">Inicio del Anuncio:</span> {ad.ad_start_date ? new Date(ad.ad_start_date).toLocaleDateString('es-ES') : 'N/A'}</div>
                                <div><span className="font-medium">Fin del Anuncio:</span> {ad.ad_end_date ? new Date(ad.ad_end_date).toLocaleDateString('es-ES') : 'N/A'}</div>
                                <div><span className="font-medium">Inicio de Entrega:</span> {ad.ad_delivery_start_time ? new Date(ad.ad_delivery_start_time).toLocaleDateString('es-ES') : 'N/A'}</div>
                                <div><span className="font-medium">Fin de Entrega:</span> {ad.ad_delivery_stop_time ? new Date(ad.ad_delivery_stop_time).toLocaleDateString('es-ES') : 'N/A'}</div>
                                <div><span className="font-medium">Última Vez Visto:</span> {ad.last_seen ? new Date(ad.last_seen).toLocaleDateString('es-ES') : 'N/A'}</div>
                                <div><span className="font-medium">Fecha de Creación:</span> {ad.creation_date ? new Date(ad.creation_date).toLocaleDateString('es-ES') : 'N/A'}</div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Impresiones y Gasto */}
                    {(ad.impressions || ad.ad_spend) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <DollarSign className="w-5 h-5" />
                                    Impresiones y Gasto
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {ad.impressions && (
                                        <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                            <span className="font-medium block mb-2">Impresiones:</span>
                                            <pre className="text-xs overflow-auto">{JSON.stringify(ad.impressions, null, 2)}</pre>
                                        </div>
                                    )}
                                    {ad.ad_spend && (
                                        <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                            <span className="font-medium block mb-2">Gasto en Anuncios:</span>
                                            <pre className="text-xs overflow-auto">{JSON.stringify(ad.ad_spend, null, 2)}</pre>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Tipo y Formato */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Tipo y Formato</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div><span className="font-medium">Tipo de Anuncio:</span> {ad.ad_type || 'N/A'}</div>
                                <div><span className="font-medium">Formato:</span> {ad.ad_format || 'N/A'}</div>
                                <div><span className="font-medium">Estado:</span> {ad.ad_status || 'N/A'}</div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Plataformas */}
                    {ad.platforms && ad.platforms.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Plataformas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {ad.platforms.map((platform) => (
                                        <Badge key={platform} variant="outline" className="text-sm">{platform}</Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* WhatsApp */}
                    {ad.has_whatsapp && ad.whatsapp_number && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageCircle className="w-5 h-5 text-green-500" />
                                    WhatsApp
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <a
                                    href={`https://wa.me/${ad.whatsapp_number.replace(/[^0-9]/g, '')}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-lg text-green-600 hover:underline flex items-center gap-2"
                                >
                                    {ad.whatsapp_number}
                                    <ExternalLink className="w-4 h-4" />
                                </a>
                            </CardContent>
                        </Card>
                    )}

                    {/* Targeting Info */}
                    {ad.targeting_info && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Eye className="w-5 h-5" />
                                    Información de Segmentación
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                    <pre className="text-xs overflow-auto">{JSON.stringify(ad.targeting_info, null, 2)}</pre>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Demographics */}
                    {ad.demographics && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Demografía</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                    <pre className="text-xs overflow-auto">{JSON.stringify(ad.demographics, null, 2)}</pre>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Palabras Clave Coincidentes */}
                    {ad.matched_keywords && ad.matched_keywords.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Palabras Clave Coincidentes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {ad.matched_keywords.map((keyword, idx) => (
                                        <Badge key={idx} variant="secondary" className="text-sm">{keyword}</Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Texto del Anuncio */}
                    {ad.ad_text && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Texto del Anuncio</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg text-sm whitespace-pre-wrap">
                                    {ad.ad_text}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Medios */}
                    {(ad.ad_image_url || ad.ad_video_url) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Medios</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {ad.ad_image_url && (
                                        <div>
                                            <p className="text-sm font-medium mb-2">Imagen:</p>
                                            <img src={ad.ad_image_url} alt="Ad" className="rounded-lg max-w-full" />
                                        </div>
                                    )}
                                    {ad.ad_video_url && (
                                        <div>
                                            <p className="text-sm font-medium mb-2">Video:</p>
                                            <a href={ad.ad_video_url} target="_blank" rel="noopener noreferrer" className="text-blue-500 hover:underline text-sm flex items-center gap-1">
                                                Ver video <ExternalLink className="w-3 h-3" />
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Raw Data */}
                    {ad.raw_data && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Datos Crudos (Raw Data)</CardTitle>
                                <CardDescription>Información completa de Apify</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg">
                                    <pre className="text-xs overflow-auto max-h-96">{JSON.stringify(ad.raw_data, null, 2)}</pre>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Enlaces */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Enlaces Externos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-3">
                                {ad.page_url && (
                                    <a href={ad.page_url} target="_blank" rel="noopener noreferrer">
                                        <Button variant="outline">
                                            <ExternalLink className="w-4 h-4 mr-2" />
                                            Ver Página de Facebook
                                        </Button>
                                    </a>
                                )}
                                {ad.ads_library_url && (
                                    <a href={ad.ads_library_url} target="_blank" rel="noopener noreferrer">
                                        <Button variant="outline">
                                            <ExternalLink className="w-4 h-4 mr-2" />
                                            Ver en Facebook Ads Library
                                        </Button>
                                    </a>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
