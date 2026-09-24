/**
 * Helper para verificar el entorno.
 * Se exporta como función para permitir mocking en tests.
 */
export const isDev = (): boolean => import.meta.env.DEV;
export const isProd = (): boolean => import.meta.env.PROD;
