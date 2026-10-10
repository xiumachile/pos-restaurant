import { getCurrentDb } from "./tauriSql";

/**
 * Mock de @tauri-apps/api/core para tests.
 * Intercepta invoke() y ejecuta SQL equivalente en better-sqlite3.
 */

export async function invoke(cmd: string, args: any = {}): Promise<any> {
  const db = getCurrentDb();
  
  if (!db) {
    throw new Error("Base de datos no inicializada en test");
  }

  switch (cmd) {
    case "create_order_with_sync": {
      const request = args.request;
      
      // 1. Insertar order
      db.exec(`
        INSERT INTO local_orders (
          local_uuid, company_id, branch_id, terminal_id, table_id,
          order_number, order_type, status, subtotal, discount_total,
          net_amount, tax_total, tip_amount, grand_total, amount_due, guest_count,
          waiter_id, waiter_name, notes, customer_id, customer_name, customer_phone,
          delivery_address, delivery_notes, idempotency_key, sync_status,
          created_at, updated_at
        ) VALUES (
          '${request.local_uuid}', '${request.company_id}', '${request.branch_id}',
          ${request.terminal_id ? `'${request.terminal_id}'` : 'NULL'},
          ${request.table_id ? `'${request.table_id}'` : 'NULL'},
          '${request.order_number}', '${request.order_type}', '${request.status}',
          0, 0, 0, 0, 0, 0, 0, ${request.guest_count},
          ${request.waiter_id ? `'${request.waiter_id}'` : 'NULL'},
          ${request.waiter_name ? `'${request.waiter_name}'` : 'NULL'},
          ${request.notes ? `'${request.notes}'` : 'NULL'},
          ${request.customer_id ? `'${request.customer_id}'` : 'NULL'},
          ${request.customer_name ? `'${request.customer_name}'` : 'NULL'},
          ${request.customer_phone ? `'${request.customer_phone}'` : 'NULL'},
          ${request.delivery_address ? `'${request.delivery_address}'` : 'NULL'},
          ${request.delivery_notes ? `'${request.delivery_notes}'` : 'NULL'},
          '${request.idempotency_key}', 'pending',
          CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
        )
      `);

      // 2. Insertar en sync_queue
      db.exec(`
        INSERT INTO sync_queue (id, company_id, branch_id, entity_type, entity_local_uuid, action, payload, sync_status, created_at)
        VALUES ('${request.local_uuid}', '${request.company_id}', '${request.branch_id}', 'order', '${request.local_uuid}', 'create', '${request.sync_payload}', 'pending', CURRENT_TIMESTAMP)
      `);

      // 3. Actualizar mesa si aplica
      if (request.table_id) {
        db.exec(`
          UPDATE local_tables SET status = 'occupied', current_order_uuid = '${request.local_uuid}' WHERE uuid = '${request.table_id}'
        `);
        db.exec(`
          INSERT OR REPLACE INTO table_local_mutations (table_uuid, pending_status, pending_order_uuid, company_id, branch_id, created_at)
          VALUES ('${request.table_id}', 'occupied', '${request.local_uuid}', '${request.company_id}', '${request.branch_id}', CURRENT_TIMESTAMP)
        `);
      }

      return undefined;
    }

    case "create_local_order":
    case "create_order_item":
    case "enqueue_sync_operation":
    case "update_order_status":
      // Por ahora, hacer noop para estos comandos
      // Se pueden implementar si hay tests que los requieran
      return undefined;

    default:
      throw new Error(`Comando Tauri no mockeado: ${cmd}`);
  }
}
