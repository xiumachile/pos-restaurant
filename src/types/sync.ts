export type SyncStatus = 'LOCAL_ONLY' | 'PENDING' | 'SYNCING' | 'SYNCED' | 'CONFLICT' | 'ERROR';

export const SYNC_STATUS_CONFIG: Record<SyncStatus, { color: string; icon: string; labelEs: string; labelZh: string }> = {
  LOCAL_ONLY: { 
    color: 'bg-gray-500', 
    icon: '💾', 
    labelEs: 'Guardado en este terminal', 
    labelZh: '仅保存在此终端' 
  },
  PENDING: { 
    color: 'bg-yellow-500', 
    icon: '⏳', 
    labelEs: 'Pendiente de sincronización', 
    labelZh: '等待同步' 
  },
  SYNCING: { 
    color: 'bg-blue-500', 
    icon: '🔄', 
    labelEs: 'Sincronizando...', 
    labelZh: '同步中...' 
  },
  SYNCED: { 
    color: 'bg-green-500', 
    icon: '✅', 
    labelEs: 'Sincronizado (Cocina notificada)', 
    labelZh: '已同步 (厨房已通知)' 
  },
  CONFLICT: { 
    color: 'bg-red-600', 
    icon: '⚠️', 
    labelEs: 'Conflicto: Requiere atención', 
    labelZh: '冲突：需要处理' 
  },
  ERROR: { 
    color: 'bg-red-500', 
    icon: '❌', 
    labelEs: 'Error recuperable', 
    labelZh: '可恢复错误' 
  },
};
