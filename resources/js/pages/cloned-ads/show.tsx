import { ImageEditor } from '@/components/ImageEditor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    DollarSign,
    Download,
    Globe,
    Save,
    Trash2,
    Video,
} from 'lucide-react';
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
    image_base64: string | null;
    video_url: string | null;
    has_image: boolean;
    has_video: boolean;
    created_at: string;
    created_at_human: string;
}

export default function ClonedAdShow({ clonedAd }: { clonedAd: ClonedAd }) {
    const [copy, setCopy] = useState(clonedAd.generated_copy);
    const [pageName, setPageName] = useState(clonedAd.page_name);
    const [price, setPrice] = useState(clonedAd.price);
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [generatingVideo, setGeneratingVideo] = useState(false);

    const handleSave = () => {
        setSaving(true);
        router.put(
            `/cloned-ads/${clonedAd.uuid}`,
            {
                generated_copy: copy,
                page_name: pageName,
                price: parseFloat(price) || 0,
            },
            {
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleSaveImage = async (editedImage: string) => {
        try {
            const res = await fetch(
                `/cloned-ads/${clonedAd.uuid}/update-image`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ image: editedImage }),
                },
            );

            const data = await res.json();
            if (res.ok) {
                alert('Imagen actualizada correctamente');
                router.reload();
            } else {
                alert(data.message || 'Error al actualizar la imagen');
            }
        } catch (e) {
            console.error(e);
            alert('Error al guardar la imagen');
        }
    };

    const handleDelete = () => {
        if (!confirm('¿Estás seguro de eliminar este anuncio clonado?')) return;

        setDeleting(true);
        router.delete(`/cloned-ads/${clonedAd.uuid}`);
    };

    const handleDownloadImage = () => {
        if (!clonedAd.image_url) return;

        const link = document.createElement('a');
        link.href = clonedAd.image_url;
        link.download = `${clonedAd.page_name}-image.png`;
        link.click();
    };

    const handleDownloadVideo = () => {
        if (!clonedAd.video_url) return;

        const link = document.createElement('a');
        link.href = clonedAd.video_url;
        link.download = `${clonedAd.page_name}-video.mp4`;
        link.click();
    };

    const handleGenerateVideo = async () => {
        if (!clonedAd.has_image) {
            alert('No hay imagen disponible para generar el video');
            return;
        }

        setGeneratingVideo(true);

        try {
            const res = await fetch(`/cloned-ads/${clonedAd.uuid}/generate-video`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') || '',
                },
                body: JSON.stringify({}),
            });

            const data = await res.json();

            if (res.ok) {
                alert('Video generado correctamente');
                router.reload();
            } else {
                alert(data.message || 'Error al generar el video');
            }
        } catch (e) {
            console.error(e);
            alert('Error al generar el video');
        } finally {
            setGeneratingVideo(false);
        }
    };

    return (
        <AppLayout>
            <Head title={`Editar: ${clonedAd.page_name}`} />

            <div className="container mx-auto p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="ghost" size="icon">
                            <Link href="/cloned-ads">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">
                                {clonedAd.page_name}
                            </h1>
                            <div className="mt-2 flex items-center gap-2">
                                <Badge variant="secondary">
                                    <Globe className="mr-1 h-3 w-3" />
                                    {clonedAd.country}
                                </Badge>
                                <Badge variant="secondary">
                                    <DollarSign className="mr-1 h-3 w-3" />
                                    {clonedAd.price}
                                </Badge>
                                <Badge variant="outline">
                                    <Calendar className="mr-1 h-3 w-3" />
                                    {clonedAd.created_at_human}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button
                            variant="default"
                            onClick={handleGenerateVideo}
                            disabled={generatingVideo || !clonedAd.has_image}
                        >
                            <Video className="mr-2 h-4 w-4" />
                            {generatingVideo ? 'Generando...' : 'Generar Video'}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={deleting}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Eliminar
                        </Button>
                    </div>
                </div>

                {/* Dos columnas: izquierda info+copys, derecha editor */}
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Izquierda */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Anuncio</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label>Nombre de la Página</Label>
                                    <Input
                                        value={pageName}
                                        onChange={(e) => setPageName(e.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label>Precio</Label>
                                    <Input
                                        type="number"
                                        value={price}
                                        onChange={(e) => setPrice(e.target.value)}
                                        step="0.01"
                                    />
                                </div>

                                <Button onClick={handleSave} disabled={saving} className="px-4">
                                    <Save className="mr-2 h-4 w-4" />
                                    {saving ? 'Guardando...' : 'Guardar Cambios'}
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Copy Generado</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <textarea
                                    className="min-h-[200px] w-full resize-none rounded-md border bg-background p-3 text-sm"
                                    value={copy}
                                    onChange={(e) => setCopy(e.target.value)}
                                    placeholder="Edita tu copy..."
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Copy Original</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <textarea
                                    className="min-h-[200px] w-full resize-none rounded-md border bg-muted/50 p-3 text-sm"
                                    value={clonedAd.original_copy}
                                    readOnly
                                />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Derecha */}
                    <div>
                        {clonedAd.has_image && (clonedAd.image_base64 || clonedAd.image_url) && (
                            <Card className="h-full">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>Editor de Imagen</CardTitle>
                                        <Button variant="outline" onClick={handleDownloadImage}>
                                            <Download className="mr-2 h-4 w-4" />
                                            Descargar Original
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <ImageEditor
                                        imageUrl={clonedAd.image_url || clonedAd.image_base64 || ''}
                                        onSave={handleSaveImage}
                                    />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>

                {/* Video */}
                {clonedAd.has_video && clonedAd.video_url && (
                    <Card className="mt-6">
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>Video Generado</CardTitle>
                                <Button
                                    variant="outline"
                                    onClick={handleDownloadVideo}
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Descargar Video
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <video
                                src={clonedAd.video_url}
                                controls
                                className="w-full rounded-lg"
                            />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
