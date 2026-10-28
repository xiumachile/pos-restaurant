// src/components/floor-plan/geometry/chairLayout.ts

export interface SeatLayout {
  top: number;
  bottom: number;
  left: number;
  right: number;
}

export interface ChairPosition {
  x: number;
  y: number;
  rotation: number; // 0 = mirando hacia abajo (sur) en coordenadas locales
}

/**
 * Calcula posiciones de sillas con alineación precisa.
 * Las sillas se posicionan JUSTO FUERA del borde de la mesa, mirando SIEMPRE hacia el centro.
 */
export function calculateChairPositions(
  shape: 'round' | 'square' | 'rectangle',
  width: number,
  height: number,
  layout: SeatLayout,
  chairWidth: number = 18,
  chairHeight: number = 22
): ChairPosition[] {
  const positions: ChairPosition[] = [];
  const gap = 3; // Mínima separación entre silla y mesa (3px)

  if (shape === 'round') {
    const radius = Math.min(width, height) / 2;
    const centerX = width / 2;
    const centerY = height / 2;
    const totalChairs = layout.top + layout.bottom + layout.left + layout.right;
    const distanceFromCenter = radius + gap + (chairHeight / 2);
    
    for (let i = 0; i < totalChairs; i++) {
      // En trigonometría: 0° = derecha (3 en punto), 90° = abajo, 180° = izquierda, 270° = arriba
      const angle = (i * 360) / totalChairs;
      const rad = (angle * Math.PI) / 180;
      
      positions.push({
        x: centerX + distanceFromCenter * Math.cos(rad) - (chairWidth / 2),
        y: centerY + distanceFromCenter * Math.sin(rad) - (chairHeight / 2),
        // La silla por defecto mira hacia abajo (0°). 
        // Para que mire al centro desde cualquier ángulo, sumamos 90°.
        // Ej: 0° (derecha) + 90° = 90° (mira a la izquierda, hacia el centro).
        rotation: angle + 90, 
      });
    }
  } else {
    // Mesas cuadradas y rectangulares
    const addChairs = (count: number, side: 'top' | 'bottom' | 'left' | 'right') => {
      if (count <= 0) return;
      
      const availableSpace = side === 'top' || side === 'bottom' ? width : height;
      const spacing = availableSpace / (count + 1);
      
      for (let i = 0; i < count; i++) {
        let x = 0, y = 0, rotation = 0;
        const pos = spacing * (i + 1);

        if (side === 'top') {
          // Arriba de la mesa: debe mirar hacia ABAJO (hacia la mesa)
          x = pos - (chairWidth / 2);
          y = -chairHeight - gap;
          rotation = 0; 
        } else if (side === 'bottom') {
          // Abajo de la mesa: debe mirar hacia ARRIBA (hacia la mesa)
          x = pos - (chairWidth / 2);
          y = height + gap;
          rotation = 180; 
        } else if (side === 'left') {
          // Izquierda de la mesa: debe mirar hacia la DERECHA (hacia la mesa)
          x = -chairWidth - gap;
          y = pos - (chairHeight / 2);
          rotation = 270; 
        } else if (side === 'right') {
          // Derecha de la mesa: debe mirar hacia la IZQUIERDA (hacia la mesa)
          x = width + gap;
          y = pos - (chairHeight / 2);
          rotation = 90; 
        }
        positions.push({ x, y, rotation });
      }
    };

    addChairs(layout.top, 'top');
    addChairs(layout.bottom, 'bottom');
    addChairs(layout.left, 'left');
    addChairs(layout.right, 'right');
  }

  return positions;
}
