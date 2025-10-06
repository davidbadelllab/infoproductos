# Guía de Prompts para Diseño de Anuncios Facebook

## Prompt Principal de Diseño

```
Diseña un anuncio visualmente impactante para Facebook que respete las zonas seguras de la plataforma. El diseño debe ser elegante, moderno y profesional, optimizado para conversiones en redes sociales.

ESPECIFICACIONES TÉCNICAS:
- Dimensión: 1200x628px (ratio 1.91:1 para optimal placement)
- Formato: PNG/JPG de alta calidad
- Zona segura: mantén todos los elementos importantes dentro del 20% central del diseño
- Texto máximo: 20% de la imagen o menos (regla Facebook)
- Logo/marca: no más del 20% de la imagen
- Colores vibrantes pero profesionales
- Tipografía legible en móviles (fuentes sans-serif, mínimo 24px)
- Contraste alto para máxima legibilidad
- Resolución mínima: 1080x1080px para retina displays

ESTILO VISUAL:
- Estilo: moderno, limpio, premium
- Paleta de colores: dinámica y contextual (NO fija). Seleccionar siempre 2–3 colores primarios + 1 neutro según el producto, beneficio y emociones del copy. Evitar repetir siempre la misma paleta.
- Ejemplos de paletas orientativas (no obligatorias):
  - Urgencia/Oferta: rojos/naranjas/amarillos + neutro oscuro
  - Premium/Lujo: negro/blanco con acentos dorados, morado profundo o navy
  - Tecnología: azules/teales con cian y gris suave
  - Bienestar/Espiritual: teales/aquas, lavanda, morados suaves, mucho blanco
  - Bebés/Infantil: pasteles (azul bebé, rosa suave, menta, amarillo claro)
  - Comida: rojos/naranjas/amarillos apetitosos + verde fresco
  - Belleza: rosas/ecru/dorado/morado suave
  - Fitness: lima/naranja/rojo sobre carbón oscuro
  - Finanzas/Negocios: verdes/azules + navy y blanco
- Elementos: gradientes sutiles, iconos minimalistas, botones con shadow
- Composición: regla de los tercios, elementos centrados
- Psicología del color: verde para éxito/dinero, naranja para acción/urgente, azul para confianza/estabilidad

ELEMENTOS OBLIGATORIOS:
- Precio destacado prominentemente con símbolo de moneda
- Call-to-action visible (botón o texto destacado)
- Valor agregado del producto claro
- Testimonial social proof si aplica

DEBE INCLUIR:
- Texto del producto claramente visible
- Precio en formato destacado (ej: "SOLO $47.99")
- Elemento de urgencia/escases (ej: "OFERTA LIMITADA")
- Beneficio principal del producto
- Call-to-action convincente ("COMPRAR AHORA", "OBTENER DESCUENTO")
- Colores que evoquen emociones positivas
- Elementos visuales que respalden el mensaje del producto
- Diseño responsive que se vea bien en móvil

EVITAR:
- Texto excesivo (máximo 20 palabras clave)
- Información de contacto (teléfono, email, URLs largas)
- Descuentos excesivos (máximo 50%)
- Imágenes engañosas o irrelevantes
- Contenido político o controvertido
- Demasiadas personas en la imagen
- Texto que cubra más del 20% de la imagen
- Elementos cerca de los bordes (usar zona segura)

COMPOSICIÓN:
- Enfoque principal en el centro (zona segura de 20%)
- Jerarquía visual clara: producto → precio → beneficio → CTA
- Espaciado suficiente entre elementos
- Equilibrio visual sin sobrecargar
- Elementos que guíen la mirada hacia el CTA

CONTEXTO DE MARKETING:
- Dirigido a compradores online interesados en [PRODUCTO]
Producto: [NOMBRE_PRODUCTO]
Precio: [PRECIO_PRODUCTO]
Texto base del anuncio: [TEXTO_ANUNCIO]
País: [PAIS_DESTINO]
Beneficio principal: [BENEFICIO_CLAVE]
Call-to-action: [CTA_TEXT]
```

## Prompt para Estructura JSON

```json
{
  "facebook_ad_design": {
    "technical_specs": {
      "dimensions": "1200x628px",
      "aspect_ratio": "1.91:1",
      "format": "PNG/JPG",
      "resolution": "minimum 1080x1080px",
      "safe_zone": "central 20%",
      "text_limit": "20% of image maximum"
    },
    "visual_requirements": {
      "style": "modern, clean, premium",
      "color_palette_strategy": {
        "dynamic": true,
        "selection_rule": "choose 2–3 primary hues + 1 neutral based on product category, emotion, CTA, and cultural context",
        "examples": [
          { "theme": "urgency_sale", "hues": ["#E11D48", "#F97316", "#F59E0B", "#111827", "#FFFFFF"] },
          { "theme": "premium_luxury", "hues": ["#0B0B0B", "#FFFFFF", "#C9A227", "#4C1D95", "#0F172A"] },
          { "theme": "tech", "hues": ["#1D4ED8", "#0EA5E9", "#14B8A6", "#334155", "#E5E7EB"] }
        ]
      },
      "typography": {
        "font_family": "sans-serif",
        "min_size": "24px",
        "hierarchy": "product_name > price > benefit > cta"
      }
    },
    "mandatory_elements": [
      "prominent_price_display",
      "clear_call_to_action", 
      "product_value_proposition",
      "urgent_scarcity_element",
      "mobile_optimized_layout"
    ],
    "content_g estrutura": {
      "title": "[PRODUCTO] - Anuncio Facebook",
      "product_name": "[NOMBRE_PRODUCTO]",
      "price": "[PRECIO_FORMATO]",
      "benefit": "[BENEFICIO_PRINCIPAL]", 
      "offer": "[OFERTA_DESCUENTO]",
      "urgency": "[ELEMENTO_URGENCIA]",
      "cta": "[CALL_TO_ACTION]",
      "social_proof": "[TESTIMONIAL_FRAGMENTO]"
    },
    "visual_composition": {
      "layout": "centered_safe_zone",
      "hierarchy": "visual_pyramid_to_cta", 
      "spacing": "generous_breathing_room",
      "contrast": "high_legibility",
      "mobile_first": true
    },
    "facebook_compliance": {
      "text_overlay": "≤20%",
      "logo_size": "≤20%", 
      "contact_info": "minimize",
      "political_content": "avoid",
      "misleading": "forbid"
    },
    "emotional_triggers": {
      "urgency": "limited_time_offer",
      "scarcity": "limited_quantity", 
      "social_proof": "customer_testimonials",
      "value": "premium_quality_affordability",
      "outcome": "desirable_end_state"
    },
    "optimization_targets": {
      "conversion_focus": true,
      "mobile_experience": "priority",
      "scroll_stopping": "primary_goal",
      "brand_recognition": "subtle_integration",
      "action_oriented": "strong_cta"
    }
  }
}
```

## Variables de Sustitución

### Variables Dinámicas:
- `[NOMBRE_PRODUCTO]` - Nombre del producto
- `[PRECIO_PRODUCTO]` - Precio en formato numérico
- `[TEXTO_ANUNCIO]` - Texto base del anuncio original
- `[PAIS_DESTINO]` - Código de país para contexto cultural
- `[BENEFICIO_CLAVE]` - Principal beneficio del producto
- `[CTA_TEXT]` - Texto del call-to-action

### Ejemplo de Uso:
```
Producto: "Curso de Trading Avanzado"
Precio: "97.99"
Texto base: "Aprende a ganar dinero desde casa con trading algorítmico"
País: "CO"
Beneficio: "Ingresos pasivos desde casa"
CTA: "INSCRIBIRME AHORA"
```

### Resultado del Prompt:
```
Diseña un anuncio visualmente impactante para Facebook que respete las zonas seguras de la plataforma. El diseño debe ser elegante, moderno y profesional, optimizado para conversiones en redes sociales.

ESPECIFICACIONES TÉCNICAS:
- Dimensión: 1200x628px (ratio 1.91:1 para optimal placement)
- Formato: PNG/JPG de alta calidad
- Zona segura: mantén todos los elementos importantes dentro del 20% central del diseño
- Texto máximo: 20% de la imagen o menos (regla Facebook)
- Logo/marca: no más del 20% de la imagen
- Colores vibrantes pero profesionales
- Tipografía legible en móviles (fuentes sans-serif, mínimo 24px)
- Contraste alto para máxima legibilidad
- Resolución mínima: 1080x1080px para retina displays

ESTILO VISUAL:
- Estilo: moderno, limpio, premium
- Paleta de colores: colores vibrantes pero profesionales (#FF6B35, #2C3E50, #27AE60, #F39C12)
- Elementos: gradientes sutiles, iconos minimalistas, botones con shadow
- Composición: regla de los tercios, elementos centrados
- Psicología del color: verde para éxito/dinero, naranja para acción/urgente, azul para confianza/estabilidad

ELEMENTOS OBLIGATORIOS:
- Precio destacado prominentemente con símbolo de moneda
- Call-to-action visible (botón o texto destacado)
- Valor agregado del producto claro
- Testimonial social proof si aplica

DEBE INCLUIR:
- Texto del producto claramente visible
- Precio en formato destacado (ej: "SOLO $97.99")
- Elemento de urgencia/escases (ej: "OFERTA LIMITADA")
- Beneficio principal del producto
- Call-to-action convincente ("INSCRIBIRME AHORA")
- Colores que evoquen emociones positivas
- Elementos visuales que respalden el mensaje del producto
- Diseño responsive que se vea bien en móvil

EVITAR:
- Texto excesivo (máximo 20 palabras clave)
- Información de contacto (teléfono, email, URLs largas)
- Descuentos excesivos (máximo 50%)
- Imágenes engañosas o irrelevantes
- Contenido político o controvertido
- Demasiadas personas en la imagen
- Texto que cubra más del 20% de la imagen
- Elementos cerca de los bordes (usar zona segura)

COMPOSICIÓN:
- Enfoque principal en el centro (zona segura de 20%)
- Jerarquía visual clara: producto → precio → beneficio → CTA
- Espaciado suficiente entre elementos
- Equilibrio visual sin sobrecargar
- Elementos que guíen la mirada hacia el CTA

CONTEXTO DE MARKETING:
- Dirigido a compradores online interesados en trading y finanzas
Producto: Curso de Trading Avanzado
Precio: $97.99
Texto base del anuncio: Aprende a ganar dinero desde casa con trading algorítmico
País: CO
Beneficio principal: Ingresos pasivos desde casa
Call-to-action: INSCRIBIRME AHORA
```

## Consejos de Optimización

### Para Mejores Conversiones:
1. **Testea múltiples variaciones** - Producto/personas/pero precio siempre visible
2. **Prioriza la legibilidad móvil** - Muchos usuarios ven desde teléfonos
3. **Usa colores contrastantes** - Verde/azul para generar confianza
4. **Mantén el mensaje simple** - Un beneficio clave por anuncio
5. **Incluye prueba social** - Testimonios, calificaciones, número de usuarios

### Para Cumplimiento Facebook:
1. **Respetar límites de texto** - Máximo 20% de cobertura textual
2. **Evitar contenido político** - Mantener neutral y comercial
3. **Información de contacto mínima** - Solo lo esencial
4. **Imágenes auténticas** - Evitar imágenes fake o manipuladas
5. **CTA claro y honesto** - No promesas irreales

### Adaptaciones Culturales:
- **Países hispanohablantes**: tono más directo y familiar
- **Mercados anglosajones**: más sutil y profesional  
- **Asia**: elementos visuales más prominentes
- **Europa**: enfoque en sostenibilidad y calidad
