import { useState, useEffect } from "react";
import { localDb } from "@/db/localDb";

export interface ActiveOrder {
  local_uuid: string;
  order_number: string;
  order_type: "dine_in" | "takeout" | "delivery";
  status: string;
  grand_total: number;
  customer_name: string | null;
  customer_phone: string | null;
  created_at: string;
  item_count: number;
}

export function useActiveOrders() {
  const [orders, setOrders] = useState<ActiveOrder[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchOrders = async () => {
      try {
        // Consulta segura: usamos COALESCE o simplemente omitimos columnas si la migración no corrió
        // Intentamos leer las columnas, si fallan, el catch lo manejará, pero lo ideal es que la migración 027 haya corrido.
        // Para ser 100% seguros, usamos una consulta que funcione con o sin las columnas nuevas 
        // (SQLite no soporta IF EXISTS en SELECT, así que dependemos de que la migración 027 se ejecute al iniciar Tauri).
        
        const result = await localDb.select<any>(`
          SELECT 
            o.local_uuid,
            o.order_number,
            o.order_type,
            o.status,
            o.grand_total,
            o.customer_name,
            o.customer_phone,
            o.created_at,
            COUNT(i.local_uuid) as item_count
          FROM local_orders o
          LEFT JOIN local_order_items i ON o.local_uuid = i.order_local_uuid
          WHERE o.status NOT IN ('completed', 'cancelled', 'voided')
          GROUP BY o.local_uuid
          ORDER BY o.created_at DESC
        `);
        
        // Mapear a la interfaz, manejando nulls
        const mappedOrders: ActiveOrder[] = (result || []).map((row: any) => ({
          local_uuid: row.local_uuid,
          order_number: row.order_number,
          order_type: row.order_type,
          status: row.status,
          grand_total: row.grand_total,
          customer_name: row.customer_name || null,
          customer_phone: row.customer_phone || null,
          created_at: row.created_at,
          item_count: row.item_count || 0,
        }));
        
        setOrders(mappedOrders);
      } catch (error: any) {
        console.error("[useActiveOrders] Error fetching orders:", error.message);
        // Si falla por columnas faltantes, intentamos una consulta fallback sin esas columnas
        try {
          const fallbackResult = await localDb.select<any>(`
            SELECT 
              o.local_uuid, o.order_number, o.order_type, o.status, o.grand_total,
              NULL as customer_name, NULL as customer_phone, o.created_at,
              COUNT(i.local_uuid) as item_count
            FROM local_orders o
            LEFT JOIN local_order_items i ON o.local_uuid = i.order_local_uuid
            WHERE o.status NOT IN ('completed', 'cancelled', 'voided')
            GROUP BY o.local_uuid
            ORDER BY o.created_at DESC
          `);
          setOrders((fallbackResult || []).map((row: any) => ({
            ...row,
            customer_name: null,
            customer_phone: null,
            item_count: row.item_count || 0
          })));
        } catch (fallbackError) {
          console.error("[useActiveOrders] Fallback also failed:", fallbackError);
          setOrders([]);
        }
      } finally {
        setLoading(false);
      }
    };

    fetchOrders();
  }, []);

  return { orders, loading };
}
