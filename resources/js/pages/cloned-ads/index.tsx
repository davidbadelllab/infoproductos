import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Eye, Trash2, Calendar, DollarSign, Globe, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

interface ClonedAd {
    id: number;
    uuid: string;
    page_name: string;
    country: string;
    price: string;
    original_copy: string;
    generated_copy: string;
    image_url: string | null;
    video_url: string | null;
    has_image: boolean;
    has_video: boolean;
    created_at: string;
    created_at_human: string;
}

export default function ClonedAdsIndex({ clonedAds }: { clonedAds: ClonedAd[] }) {
    const [deleting, setDeleting] = useState<string | null>(null);
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 6;

    // Calcular paginación
    const totalPages = Math.ceil(clonedAds.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentAds = clonedAds.slice(startIndex, endIndex);

    const handleDelete = (uuid: string) => {
        if (!confirm('¿Estás seguro de eliminar este anuncio clonado?')) return;

        setDeleting(uuid);
        router.delete(`/cloned-ads/${uuid}`, {
            onFinish: () => {
                setDeleting(null);
                // Si eliminamos el último item de la página actual, volver a la anterior
                if (currentAds.length === 1 && currentPage > 1) {
                    setCurrentPage(currentPage - 1);
                }
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Ads Clonados" />

            <div className="container mx-auto p-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold">Ads Clonados</h1>
                    <p className="text-muted-foreground mt-2">
                        Gestiona todos tus anuncios clonados
                    </p>
                </div>

                {clonedAds.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <p className="text-muted-foreground">
                                No tienes anuncios clonados aún. Comienza clonando anuncios desde Facebook Ads.
                            </p>
                            <Button asChild className="mt-4">
                                <Link href="/facebook-ads">Ir a Facebook Ads</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {currentAds.map((ad) => (
                            <Card key={ad.uuid} className="flex flex-col">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{ad.page_name}</CardTitle>
                                            <CardDescription className="mt-1">
                                                <div className="flex items-center gap-2 text-xs mt-1">
                                                    <Badge variant="secondary" className="text-xs">
                                                        <Globe className="w-3 h-3 mr-1" />
                                                        {ad.country}
                                                    </Badge>
                                                    {ad.price && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            <DollarSign className="w-3 h-3 mr-1" />
                                                            {ad.price}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>

                                {ad.has_image && ad.image_url && (
                                    <div className="px-6">
                                        <img
                                            src={ad.image_url}
                                            alt={ad.page_name}
                                            className="w-full h-48 object-cover rounded-md"
                                        />
                                    </div>
                                )}

                                <CardContent className="flex-1 mt-4">
                                    <div className="text-sm text-muted-foreground line-clamp-3">
                                        {ad.generated_copy}
                                    </div>
                                </CardContent>

                                <CardFooter className="flex-col gap-2 pt-4">
                                    <div className="flex items-center gap-2 text-xs text-muted-foreground w-full">
                                        <Calendar className="w-3 h-3" />
                                        <span>{ad.created_at_human}</span>
                                    </div>

                                    <div className="flex gap-2 w-full mt-2">
                                        <Button asChild size="sm" variant="default" className="flex-1">
                                            <Link href={`/cloned-ads/${ad.uuid}`}>
                                                <Eye className="w-4 h-4 mr-1" />
                                                Ver/Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => handleDelete(ad.uuid)}
                                            disabled={deleting === ad.uuid}
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>

                    {/* Paginación */}
                    {totalPages > 1 && (
                        <div className="mt-8 flex items-center justify-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
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
                                        onClick={() => setCurrentPage(page)}
                                        className="w-10"
                                    >
                                        {page}
                                    </Button>
                                ))}
                            </div>

                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                disabled={currentPage === totalPages}
                            >
                                Siguiente
                                <ChevronRight className="w-4 h-4 ml-1" />
                            </Button>
                        </div>
                    )}

                    <div className="mt-4 text-center text-sm text-muted-foreground">
                        Mostrando {startIndex + 1}-{Math.min(endIndex, clonedAds.length)} de {clonedAds.length} anuncios
                    </div>
                </>
                )}
            </div>
        </AppLayout>
    );
}
