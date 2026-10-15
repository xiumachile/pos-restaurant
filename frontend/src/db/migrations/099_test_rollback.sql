-- Migración de prueba para validar rollback en apply_migration_internal
-- Contiene un error de sintaxis intencional para forzar el fallo de run_migration_script
SELECT * FROM tabla_que_no_existe_para_forzar_error;
