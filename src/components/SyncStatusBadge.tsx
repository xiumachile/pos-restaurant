import React from 'react';

// Mapeo de los valores del Enum del backend (minúsculas) a la configuración de UI
const SYNC_STATUS_CONFIG: Record<string, { color: string; icon: string; labelEs: string; labelZh: string }> = {
  pending: { 
    color: 'bg-yellow-500', 
    icon: '⏳', 
    labelEs: 'Pendiente de sincronización', 
    labelZh: '等待同步' 
  },
  syncing: { 
    color: 'bg-blue-500', 
    icon: '🔄', 
    labelEs: 'Sincronizando...', 
    labelZh: '同步中...' 
  },
  synced: { 
    color: 'bg-green-500', 
    icon: '✅', 
    labelEs: 'Sincronizado (Cocina notificada)', 
    labelZh: '已同步 (厨房已通知)' 
  },
  conflict: { 
    color: 'bg-red-600', 
    icon: '⚠️', 
    labelEs: 'Conflicto: Requiere atención', 
    labelZh: '冲突：需要处理' 
  },
  failed: { 
    color: 'bg-red-500', 
    icon: '❌', 
    labelEs: 'Error recuperable', 
    labelZh: '可恢复错误' 
  },
  // Fallback para cuando no hay registro de sync (ej. creado online directamente)
  local_only: {
    color: 'bg-gray-500',
    icon: '💾',
    labelEs: 'Guardado en este terminal',
    labelZh: '仅保存在此终端'
  }
};

interface SyncStatusBadgeProps {
  status: string; // Valor del enum del backend ('pending', 'synced', 'conflict', 'failed')
  className?: string;
  lang?: 'es' | 'zh';
}

export const SyncStatusBadge: React.FC<SyncStatusBadgeProps> = ({ 
  status, 
  className = '', 
  lang = 'es' 
}) => {
  // Normalizar el status a minúsculas para el mapeo
  const normalizedStatus = (status || 'local_only').toLowerCase();
  const config = SYNC_STATUS_CONFIG[normalizedStatus] || SYNC_STATUS_CONFIG.local_only;
  const label = lang === 'zh' ? config.labelZh : config.labelEs;

  return (
    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white ${config.color} ${className}`}>
      <span aria-hidden="true">{config.icon}</span>
      <span>{label}</span>
    </span>
  );
};
