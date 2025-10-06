import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import * as fabric from 'fabric';
import {
    ArrowDown,
    ArrowUp,
    ChevronsDown,
    ChevronsUp,
    Download,
    Image as ImageIcon,
    Redo,
    RotateCcw,
    Trash2,
    Type,
    Undo,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface ImageEditorProps {
    imageUrl: string;
    onSave?: (editedImageUrl: string) => void;
}

interface HistoryItem {
    canvas: string;
}

export function ImageEditor({ imageUrl, onSave }: ImageEditorProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const fabricCanvasRef = useRef<fabric.Canvas | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const imageLoadedRef = useRef<boolean>(false);

    const [text, setText] = useState('');
    const [textColor, setTextColor] = useState('#FFFFFF');
    const [backgroundColor, setBackgroundColor] = useState('transparent');
    const [fontSize, setFontSize] = useState(40);
    const [selectedObject, setSelectedObject] = useState<fabric.Object | null>(
        null,
    );
    const [selectedObjectType, setSelectedObjectType] = useState<
        'text' | 'image' | null
    >(null);
    const [fontFamily, setFontFamily] = useState('Arial, sans-serif');
    const [borderColor, setBorderColor] = useState('#FFFFFF');
    const [borderType, setBorderType] = useState<'solid' | 'dashed' | 'dotted'>(
        'solid',
    );
    const [fontWeight, setFontWeight] = useState<'normal' | 'bold'>('normal');
    const [fontStyle, setFontStyle] = useState<'normal' | 'italic'>('normal');
    const [textTransform, setTextTransform] = useState<
        'none' | 'uppercase' | 'lowercase' | 'capitalize'
    >('none');
    const [textDecoration, setTextDecoration] = useState<
        'none' | 'underline' | 'line-through'
    >('none');
    const [textAlign, setTextAlign] = useState<'left' | 'center' | 'right'>('left');
    const [shadowColor, setShadowColor] = useState('#000000');
    const [shadowOffsetX, setShadowOffsetX] = useState(2);
    const [shadowOffsetY, setShadowOffsetY] = useState(2);
    const [history, setHistory] = useState<HistoryItem[]>([]);
    const [historyIndex, setHistoryIndex] = useState(-1);
    const [imageLoaded, setImageLoaded] = useState(false);
    const [hasBaseImage, setHasBaseImage] = useState(false);
    const [contextMenuPosition, setContextMenuPosition] = useState({
        x: 0,
        y: 0,
    });
    const [textMode, setTextMode] = useState(false);
    const [showTextInput, setShowTextInput] = useState(false);
    const [textInputPosition, setTextInputPosition] = useState({ x: 0, y: 0 });

    const backgroundColors = [
        '#FF6B6B',
        '#4ECDC4',
        '#45B7D1',
        '#96CEB4',
        '#FFEAA7',
        '#DDA0DD',
        '#98D8C8',
        '#F7DC6F',
        '#BB8FCE',
        '#85C1E9',
        '#F8BBD9',
        '#82E0AA',
        '#F1C40F',
        '#E74C3C',
        '#9B59B6',
        '#1ABC9C',
        '#F39C12',
        '#E67E22',
        '#95A5A6',
        '#34495E',
    ];

    // Definir saveToHistory ANTES del useEffect para que esté disponible
    const saveToHistory = useCallback(() => {
        if (!fabricCanvasRef.current) return;
        const state = fabricCanvasRef.current.toJSON();
        const newHistory = [
            ...history.slice(0, historyIndex + 1),
            { canvas: JSON.stringify(state) },
        ];
        setHistory(newHistory);
        setHistoryIndex(newHistory.length - 1);
    }, [history, historyIndex]);

    useEffect(() => {
        // Resetear estados cuando cambie la imagen
        setImageLoaded(false);
        setHasBaseImage(false);
        setSelectedObject(null);
        imageLoadedRef.current = false;

        if (!canvasRef.current) return;

        const canvas = new fabric.Canvas(canvasRef.current, {
            width: 800,
            height: 600,
            backgroundColor: 'transparent',
            selection: true,
            preserveObjectStacking: true,
        });

        // Asegurar transparencia visual del elemento <canvas>
        (canvas.getElement() as HTMLCanvasElement).style.backgroundColor = 'transparent';

        fabricCanvasRef.current = canvas;

        setImageLoaded(true);
        setHasBaseImage(!!imageUrl);

        if (!imageUrl || imageUrl.trim() === '') {
            imageLoadedRef.current = true;
            saveToHistory();
                    return;
                }

        // Cargar imagen SIN crossOrigin para URLs del mismo dominio
        const imgElement = document.createElement('img');

        imgElement.onload = () => {
            if (!fabricCanvasRef.current) return;

            // Crear imagen de fabric desde el elemento cargado
            const fabricImg = new fabric.Image(imgElement);

            const canvasAspectRatio = 800 / 600;
            const imgAspectRatio = fabricImg.width! / fabricImg.height!;

            let scale;
            if (imgAspectRatio > canvasAspectRatio) {
                scale = 800 / fabricImg.width!;
            } else {
                scale = 600 / fabricImg.height!;
            }

            fabricImg.scale(scale);
            fabricImg.set({
                left: (800 - fabricImg.width! * scale) / 2,
                top: (600 - fabricImg.height! * scale) / 2,
                selectable: false,
                evented: false,
            });

            fabricCanvasRef.current.backgroundImage = fabricImg;
            fabricCanvasRef.current.renderAll();

            imageLoadedRef.current = true;
            setHasBaseImage(true);
            saveToHistory();
        };

        imgElement.onerror = (e) => {
            console.error('Error loading image:', e);
            setHasBaseImage(false);
            imageLoadedRef.current = true;
        };

        // Asignar src
        imgElement.src = imageUrl;

        // Timeout de seguridad
        const timeoutId = setTimeout(() => {
            if (!imageLoadedRef.current) {
                setHasBaseImage(false);
                imageLoadedRef.current = true;
                setImageLoaded(true);
                saveToHistory();
            }
        }, 5000);

        canvas.on('object:added', saveToHistory);
        canvas.on('object:removed', saveToHistory);
        canvas.on('object:modified', saveToHistory);
        canvas.on('selection:created', (e) => {
            if (e.target) {
                setSelectedObject(e.target);
                setSelectedObjectType(
                    e.target.type === 'i-text' ? 'text' : 'image',
                );
            }
        });
        canvas.on('selection:updated', (e) => {
            if (e.target) {
                setSelectedObject(e.target);
                setSelectedObjectType(
                    e.target.type === 'i-text' ? 'text' : 'image',
                );
            }
        });
        canvas.on('selection:cleared', () => {
            setSelectedObject(null);
            setSelectedObjectType(null);
        });

        const handleDrop = (e: DragEvent) => {
            e.preventDefault();
            const file = e.dataTransfer?.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    const imgUrl = event.target?.result as string;
                    const imgElement = new Image();
                    imgElement.onload = () => {
                        if (!fabricCanvasRef.current) return;
                        const fabricImg = new fabric.Image(imgElement);
                        fabricImg.scaleToWidth(150);
                        fabricImg.set({
                            left: 400,
                            top: 150,
                            cornerStyle: 'circle',
                            cornerColor: '#3b82f6',
                            cornerSize: 16,
                            borderColor: '#3b82f6',
                            borderScaleFactor: 3,
                        });
                        fabricCanvasRef.current.add(fabricImg);
                        fabricCanvasRef.current.renderAll();
                        setSelectedObject(fabricImg);
                        setSelectedObjectType('image');
                    };
                    imgElement.src = imgUrl;
                };
                reader.readAsDataURL(file);
            }
        };

        const handleDragOver = (e: DragEvent) => {
            e.preventDefault();
        };

        const canvasElement = canvasRef.current;
        canvasElement.addEventListener('drop', handleDrop);
        canvasElement.addEventListener('dragover', handleDragOver);

        return () => {
            clearTimeout(timeoutId);
            canvasElement.removeEventListener('drop', handleDrop);
            canvasElement.removeEventListener('dragover', handleDragOver);
            canvas.dispose();
        };
    }, [imageUrl]);

    const undo = () => {
        if (historyIndex > 0) {
            const newIndex = historyIndex - 1;
            setHistoryIndex(newIndex);
            if (fabricCanvasRef.current) {
                fabricCanvasRef.current.loadFromJSON(
                    JSON.parse(history[newIndex].canvas),
                    () => fabricCanvasRef.current?.renderAll(),
                );
            }
        }
    };

    const redo = () => {
        if (historyIndex < history.length - 1) {
            const newIndex = historyIndex + 1;
            setHistoryIndex(newIndex);
            if (fabricCanvasRef.current) {
                fabricCanvasRef.current.loadFromJSON(
                    JSON.parse(history[newIndex].canvas),
                    () => fabricCanvasRef.current?.renderAll(),
                );
            }
        }
    };

    const addText = () => {
        if (!fabricCanvasRef.current || !text.trim()) return;

        const textObj = new fabric.IText(text, {
            left: 100,
            top: 100,
            fontFamily,
            fontSize,
            fill: textColor,
            textAlign,
            backgroundColor:
                backgroundColor === 'transparent' ? undefined : backgroundColor,
            borderColor: borderColor,
            borderWidth: 1,
            borderDashArray:
                borderType === 'dashed'
                    ? [5, 5]
                    : borderType === 'dotted'
                    ? [2, 4]
                    : undefined,
            fontStyle,
            fontWeight,
            textTransform,
            textDecoration,
            shadow:
                shadowColor !== 'transparent'
                    ? new fabric.Shadow({
                          color: shadowColor,
                          blur: 4,
                          offsetX: shadowOffsetX,
                          offsetY: shadowOffsetY,
                      })
                    : undefined,
        });

        fabricCanvasRef.current.add(textObj);
        fabricCanvasRef.current.renderAll();
        setSelectedObject(textObj);
        setSelectedObjectType('text');
        setShowTextInput(false);
        setText('');
    };

    // Apply current shadow settings to the selected text object
    const applyShadowToSelectedText = (
        color: string,
        offsetX: number,
        offsetY: number,
    ) => {
        if (!fabricCanvasRef.current) return;
        if (selectedObject && selectedObjectType === 'text') {
            (selectedObject as any).set({
                shadow: new fabric.Shadow({ color, blur: 4, offsetX, offsetY }),
                textAlign,
                textTransform,
                textDecoration,
            });
            fabricCanvasRef.current.renderAll();
            saveToHistory();
        }
    };

    const downloadImage = () => {
        if (!fabricCanvasRef.current) return;
        const dataURL = (fabricCanvasRef.current as any).toDataURL({
            multiplier: 1,
            format: 'png',
            quality: 1,
        });

        const link = document.createElement('a');
        link.download = 'canvas-image.png';
        link.href = dataURL;
        setTimeout(() => link.click(), 0);

        if (onSave) {
            onSave(dataURL);
        }
    };

    const rotateBackground = () => {
        if (!fabricCanvasRef.current?.backgroundImage) return;
        const currentAngle = fabricCanvasRef.current.backgroundImage.angle || 0;
        fabricCanvasRef.current.backgroundImage.rotate(currentAngle + 90);
        fabricCanvasRef.current.renderAll();
        saveToHistory();
    };

    const addLogo = () => {
        if (!fileInputRef.current) return;
        fileInputRef.current.click();
    };

    // Handle file input change: add selected image as a new movable layer (not background)
    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file || !fabricCanvasRef.current) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            const imgUrl = event.target?.result as string;
            const imgElement = new Image();
            imgElement.onload = () => {
                if (!fabricCanvasRef.current) return;
                const fabricImg = new fabric.Image(imgElement);
                // Reasonable default size and position
                fabricImg.scaleToWidth(300);
                fabricImg.set({
                    left: 100,
                    top: 100,
                    cornerStyle: 'circle',
                    cornerColor: '#3b82f6',
                    cornerSize: 16,
                    borderColor: '#3b82f6',
                    borderScaleFactor: 3,
                    selectable: true,
                    evented: true,
                });
                fabricCanvasRef.current.add(fabricImg);
                fabricCanvasRef.current.setActiveObject(fabricImg);
                fabricCanvasRef.current.renderAll();
                setSelectedObject(fabricImg);
                setSelectedObjectType('image');
                saveToHistory();
            };
            imgElement.src = imgUrl;
        };
        reader.readAsDataURL(file);
        // reset the input so selecting the same file again still triggers onChange
        e.target.value = '';
    };

    const deleteSelected = () => {
        if (!fabricCanvasRef.current || !selectedObject) return;
        fabricCanvasRef.current.remove(selectedObject);
        fabricCanvasRef.current.renderAll();
        setSelectedObject(null);
        setSelectedObjectType(null);
    };

    const rotateSelected = () => {
        if (!fabricCanvasRef.current || !selectedObject) return;
        selectedObject.rotate((selectedObject.angle || 0) + 90);
        fabricCanvasRef.current.renderAll();
    };

    const bringToFront = () => {
        if (!fabricCanvasRef.current || !selectedObject) return;
        (fabricCanvasRef.current as any).bringToFront(selectedObject);
        fabricCanvasRef.current.renderAll();
    };

    const bringForward = () => {
        if (!fabricCanvasRef.current || !selectedObject) return;
        (fabricCanvasRef.current as any).bringForward(selectedObject);
        fabricCanvasRef.current.renderAll();
    };

    const sendBackward = () => {
        if (!fabricCanvasRef.current || !selectedObject) return;
        (fabricCanvasRef.current as any).sendBackward(selectedObject);
        fabricCanvasRef.current.renderAll();
    };

    const sendToBack = () => {
        if (!fabricCanvasRef.current || !selectedObject) return;
        (fabricCanvasRef.current as any).sendToBack(selectedObject);
        fabricCanvasRef.current.renderAll();
    };

    const addBackgroundColor = (color: string) => {
        if (!fabricCanvasRef.current) return;
        const rect = new fabric.Rect({
            left: 0,
            top: 0,
            width: 800,
            height: 600,
            fill: color,
            absolutePositioned: true,
            selectable: false,
            evented: false,
        });
        (fabricCanvasRef.current as any).sendToBack(rect);
        fabricCanvasRef.current.renderAll();
    };

    return (
        <div className="relative">
            {/* Barra de herramientas flotante superior mejorada */}
            {imageLoaded && (
                <div className="absolute top-4 left-1/2 z-10 -translate-x-1/2 transform">
                    <div className="flex gap-3 rounded-xl border border-white/20 bg-black/90 p-3 shadow-2xl backdrop-blur-xl">
                        <Button
                            onClick={undo}
                            disabled={historyIndex <= 0}
                            variant="outline"
                            className="h-10 w-10 rounded-lg border-white/30 bg-white/10 p-0 text-white hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-50"
                            title="Deshacer"
                        >
                            <Undo className="h-4 w-4" />
                        </Button>
                        <Button
                            onClick={redo}
                            disabled={historyIndex >= history.length - 1}
                            variant="outline"
                            className="h-10 w-10 rounded-lg border-white/30 bg-white/10 p-0 text-white hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-50"
                            title="Rehacer"
                        >
                            <Redo className="h-4 w-4" />
                        </Button>
                        <Button
                            onClick={() => setTextMode(true)}
                            className="h-10 w-10 rounded-lg border-blue-400 bg-gradient-to-r from-blue-500 to-purple-600 p-0 text-white shadow-lg hover:from-blue-600 hover:to-purple-700 hover:shadow-blue-500/25"
                            title="Agregar texto"
                        >
                            <Type className="h-4 w-4" />
                        </Button>
                        <Button
                            onClick={() => fileInputRef.current?.click()}
                            variant="outline"
                            className="h-10 w-10 rounded-lg border-white/30 bg-white/10 p-0 text-white hover:bg-white/20"
                            title="Agregar imagen"
                        >
                            <ImageIcon className="h-4 w-4" />
                        </Button>
                        <Button
                            onClick={downloadImage}
                            className="h-10 w-10 rounded-lg border-green-400 bg-gradient-to-r from-green-500 to-emerald-600 p-0 text-white shadow-lg hover:from-green-600 hover:to-emerald-700 hover:shadow-green-500/25"
                            title="Descargar"
                        >
                            <Download className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {/* Controles contextuales para objeto seleccionado - TEXTO */}
            {selectedObject && selectedObjectType === 'text' && imageLoaded && (
                <div className="absolute top-1/2 right-4 z-10 flex -translate-y-1/2 transform flex-col gap-2 rounded-lg bg-white/90 p-2 shadow-lg backdrop-blur-sm dark:bg-gray-800/90">
                    <Button
                        onClick={deleteSelected}
                        variant="destructive"
                        size="sm"
                        title="Eliminar"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                    <Button
                        onClick={rotateSelected}
                        variant="outline"
                        size="sm"
                        title="Rotar"
                    >
                        <RotateCcw className="h-4 w-4" />
                    </Button>
                </div>
            )}

            {/* Controles contextuales para objeto seleccionado - IMAGEN */}
            {selectedObject &&
                selectedObjectType === 'image' &&
                imageLoaded && (
                    <div className="gap-.5 absolute top-1/2 right-4 z-10 flex -translate-y-1/2 transform flex-col rounded-lg bg-white/90 p-2 shadow-lg backdrop-blur-sm dark:bg-gray-800/90">
                        <div className="mb-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                            <p className="mb-2 text-center text-xs text-muted-foreground">
                                Capas
                            </p>
                            <div className="grid grid-cols-2 gap-1">
                                <Button
                                    onClick={bringToFront}
                                    variant="outline"
                                    size="sm"
                                    title="Traer al frente"
                                    className="text-xs"
                                >
                                    <ChevronsUp className="mr-1 h-3 w-3" />
                                    Al frente
                                </Button>
                                <Button
                                    onClick={bringForward}
                                    variant="outline"
                                    size="sm"
                                    title="Traer adelante"
                                    className="text-xs"
                                >
                                    <ArrowUp className="mr-1 h-3 w-3" />
                                    Adelante
                                </Button>
                                <Button
                                    onClick={sendBackward}
                                    variant="outline"
                                    size="sm"
                                    title="Enviar atrás"
                                    className="text-xs"
                                >
                                    <ArrowDown className="mr-1 h-3 w-3" />
                                    Atrás
                                </Button>
                                <Button
                                    onClick={sendToBack}
                                    variant="outline"
                                    size="sm"
                                    title="Enviar al fondo"
                                    className="text-xs"
                                >
                                    <ChevronsDown className="mr-1 h-3 w-3" />
                                    Al fondo
                                </Button>
                            </div>
                        </div>
                        <Button
                            onClick={rotateSelected}
                            variant="outline"
                            size="sm"
                            title="Rotar"
                        >
                            <RotateCcw className="h-4 w-4" />
                        </Button>
                        <Button
                            onClick={deleteSelected}
                            variant="destructive"
                            size="sm"
                            title="Eliminar"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                )}

            {/* Selector de colores de fondo cuando no hay imagen base */}
            {!hasBaseImage && imageLoaded && (
                <div className="absolute top-4 left-4 rounded-lg bg-white/90 p-3 shadow-lg backdrop-blur-sm dark:bg-gray-800/90">
                    <h4 className="mb-2 text-center text-sm font-semibold">
                        Fondo
                    </h4>
                    <div className="grid grid-cols-4 gap-1">
                        {backgroundColors.slice(0, 8).map((color) => (
                            <button
                                key={color}
                                onClick={() => addBackgroundColor(color)}
                                className="h-6 w-6 rounded border-2 border-gray-300 transition-all hover:border-gray-500"
                                style={{ backgroundColor: color }}
                                title={color}
                            />
                        ))}
                    </div>
            </div>
            )}

            {/* Panel flotante de texto minimalista */}
            {showTextInput && imageLoaded && (
                <div
                    className="absolute z-20 min-w-[300px] max-w-[340px] rounded-xl border border-white/20 bg-black/95 p-3 shadow-2xl backdrop-blur-xl"
                    style={{ left: `${textInputPosition.x}px`, top: `${textInputPosition.y}px` }}
                >
                    <div className="space-y-3 text-white">
                        {/* Texto */}
                            <Input
                                value={text}
                                onChange={(e) => setText(e.target.value)}
                            placeholder="Escribe tu texto..."
                            className="h-8 w-full rounded-lg border-white/30 bg-white/10 px-2 text-xs text-white placeholder-gray-400 focus:border-blue-400 focus:bg-white/15"
                            autoFocus
                            onKeyPress={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    addText();
                                    setShowTextInput(false);
                                }
                            }}
                        />

                        {/* Fuente / Tamaño */}
                        <div className="grid grid-cols-2 gap-2">
                            <select
                                value={fontFamily}
                                onChange={(e) => setFontFamily(e.target.value)}
                                className="h-8 w-full rounded-lg border border-white/30 bg-white/10 px-2 text-xs text-white focus:border-blue-400 focus:bg-white/15"
                            >
                                <option value="Arial, sans-serif">Arial</option>
                                <option value="Helvetica, sans-serif">Helvetica</option>
                                <option value="Times New Roman, serif">Times</option>
                                <option value="Georgia, serif">Georgia</option>
                                <option value="Roboto, sans-serif">Roboto</option>
                                <option value="Open Sans, sans-serif">Open Sans</option>
                                <option value="Montserrat, sans-serif">Montserrat</option>
                            </select>
                            <Input
                                type="number"
                                value={fontSize}
                                onChange={(e) => setFontSize(parseInt(e.target.value || '0'))}
                                min="10"
                                max="200"
                                className="h-8 rounded-lg border border-white/30 bg-white/10 px-2 text-xs text-white focus:border-blue-400 focus:bg-white/15"
                                placeholder="40"
                            />
                        </div>

                        {/* Colores */}
                        <div className="grid grid-cols-3 gap-2">
                            <Input type="color" value={textColor} onChange={(e) => setTextColor(e.target.value)} className="h-8 w-full cursor-pointer rounded-lg border-2 border-white/20 bg-white/10" />
                            <Input type="color" value={backgroundColor} onChange={(e) => setBackgroundColor(e.target.value)} className="h-8 w-full cursor-pointer rounded-lg border-2 border-white/20 bg-white/10" />
                            <Input type="color" value={borderColor} onChange={(e) => setBorderColor(e.target.value)} className="h-8 w-full cursor-pointer rounded-lg border-2 border-white/20 bg-white/10" />
                        </div>

                        {/* Sombra */}
                        <div className="grid grid-cols-3 gap-2">
                            <Input type="color" value={shadowColor} onChange={(e) => { setShadowColor(e.target.value); applyShadowToSelectedText(e.target.value, shadowOffsetX, shadowOffsetY); }} className="h-8 w-full cursor-pointer rounded-lg border-2 border-white/20 bg-white/10" />
                            <Input type="number" value={shadowOffsetX} onChange={(e) => { const v = parseInt(e.target.value || '0'); setShadowOffsetX(v); applyShadowToSelectedText(shadowColor, v, shadowOffsetY); }} className="h-8 rounded-lg border border-white/30 bg-white/10 px-2 text-xs text-white" />
                            <Input type="number" value={shadowOffsetY} onChange={(e) => { const v = parseInt(e.target.value || '0'); setShadowOffsetY(v); applyShadowToSelectedText(shadowColor, shadowOffsetX, v); }} className="h-8 rounded-lg border border-white/30 bg-white/10 px-2 text-xs text-white" />
                            </div>

                        {/* Borde */}
                        <div className="grid grid-cols-3 gap-2">
                            <Button onClick={() => setBorderType('solid')} className={`h-8 rounded-md px-2 text-xs ${borderType === 'solid' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>━</Button>
                            <Button onClick={() => setBorderType('dashed')} className={`h-8 rounded-md px-2 text-xs ${borderType === 'dashed' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>┄</Button>
                            <Button onClick={() => setBorderType('dotted')} className={`h-8 rounded-md px-2 text-xs ${borderType === 'dotted' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>┈</Button>
                        </div>

                        {/* Estilos */}
                        <div className="grid grid-cols-3 gap-2">
                            <Button onClick={() => setFontWeight(fontWeight === 'bold' ? 'normal' : 'bold')} className={`h-8 rounded-md px-2 text-xs ${fontWeight === 'bold' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>B</Button>
                            <Button onClick={() => setFontStyle(fontStyle === 'italic' ? 'normal' : 'italic')} className={`h-8 rounded-md px-2 text-xs italic ${fontStyle === 'italic' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>I</Button>
                            <Button onClick={() => setTextDecoration(textDecoration === 'underline' ? 'none' : 'underline')} className={`h-8 rounded-md px-2 text-xs underline ${textDecoration === 'underline' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>U</Button>
                        </div>

                        {/* Transformaciones y alineación */}
                        <div className="grid grid-cols-4 gap-2">
                            <Button onClick={() => setTextTransform('none')} className={`h-8 rounded-md px-2 text-[10px] ${textTransform === 'none' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>Aa</Button>
                            <Button onClick={() => setTextTransform('uppercase')} className={`h-8 rounded-md px-2 text-[10px] ${textTransform === 'uppercase' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>AA</Button>
                            <Button onClick={() => setTextTransform('lowercase')} className={`h-8 rounded-md px-2 text-[10px] ${textTransform === 'lowercase' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>aa</Button>
                            <Button onClick={() => setTextTransform('capitalize')} className={`h-8 rounded-md px-2 text-[10px] ${textTransform === 'capitalize' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>Aa.</Button>
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            <Button onClick={() => setTextAlign('left')} className={`h-8 rounded-md px-2 text-xs ${textAlign === 'left' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>⟸</Button>
                            <Button onClick={() => setTextAlign('center')} className={`h-8 rounded-md px-2 text-xs ${textAlign === 'center' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>⟷</Button>
                            <Button onClick={() => setTextAlign('right')} className={`h-8 rounded-md px-2 text-xs ${textAlign === 'right' ? 'bg-blue-500 text-white' : 'bg-white/10 text-white border-white/30'}`}>⟹</Button>
                            </div>

                        {/* Acciones */}
                        <div className="flex items-center justify-end gap-2 pt-1">
                            <Button
                                onClick={() => { addText(); setShowTextInput(false); }}
                                disabled={!text.trim()}
                                className="h-8 rounded-md bg-green-600 px-3 text-xs text-white disabled:opacity-50"
                            >OK</Button>
                            <Button onClick={() => setShowTextInput(false)} variant="outline" className="h-8 rounded-md border-white/30 bg-white/10 px-3 text-xs text-white">Cerrar</Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Canvas principal */}
            <div className="relative min-h-[600px] flex justify-center">
                <canvas ref={canvasRef} className="block mx-auto" />

                {/* Click para agregar texto */}
                {textMode && imageLoaded && (
                    <div
                        className="absolute inset-0 cursor-crosshair"
                        onClick={(e) => {
                            const canvas = canvasRef.current;
                            if (!canvas) return;
                            const rect = canvas.getBoundingClientRect();
                            const x = e.clientX - rect.left;
                            const y = e.clientY - rect.top;

                            setTextInputPosition({ x, y });
                            setShowTextInput(true);
                            setTextMode(false);
                        }}
                    />
                )}

            {/* Input file hidden */}
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept="image/*"
                        onChange={handleFileChange}
                        className="hidden"
                    />
            </div>
        </div>
    );
}
