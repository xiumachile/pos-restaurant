use chrono::{DateTime, Utc};

pub const MAX_INTENTOS_PIN: i32 = 5;
pub const MINUTOS_BLOQUEO_PIN: i64 = 15;

pub fn usuario_esta_bloqueado(
    bloqueado_hasta: Option<DateTime<Utc>>,
    ahora: DateTime<Utc>,
) -> bool {
    match bloqueado_hasta {
        Some(fecha) => fecha > ahora,
        None => false,
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use chrono::Duration;

    #[test]
    fn usuario_sin_bloqueo() {
        let ahora = Utc::now();

        assert!(!usuario_esta_bloqueado(None, ahora));
    }

    #[test]
    fn bloqueo_vigente() {
        let ahora = Utc::now();
        let hasta = ahora + Duration::minutes(10);

        assert!(usuario_esta_bloqueado(Some(hasta), ahora));
    }

    #[test]
    fn bloqueo_vencido() {
        let ahora = Utc::now();
        let hasta = ahora - Duration::minutes(1);

        assert!(!usuario_esta_bloqueado(Some(hasta), ahora));
    }

    #[test]
    fn bloqueo_vence_exactamente_ahora() {
        let ahora = Utc::now();

        assert!(!usuario_esta_bloqueado(Some(ahora), ahora));
    }

    #[test]
    fn politica_de_intentos_y_duracion() {
        assert_eq!(MAX_INTENTOS_PIN, 5);
        assert_eq!(MINUTOS_BLOQUEO_PIN, 15);
    }
}
