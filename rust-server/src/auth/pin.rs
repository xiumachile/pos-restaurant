use argon2::{
    Argon2,
    password_hash::{PasswordHasher, PasswordVerifier, phc::PasswordHash},
};

fn validar_formato_pin(pin: &str) -> bool {
    pin.len() == 4 && pin.bytes().all(|b| b.is_ascii_digit())
}

pub fn hash_pin(pin: &str) -> Result<String, Box<dyn std::error::Error + Send + Sync>> {
    if !validar_formato_pin(pin) {
        return Err("El PIN debe contener exactamente 4 dígitos".into());
    }

    let hash = Argon2::default().hash_password(pin.as_bytes())?.to_string();

    Ok(hash)
}

pub fn verify_pin(pin: &str, hash: &str) -> bool {
    if !validar_formato_pin(pin) {
        return false;
    }

    let parsed_hash = match PasswordHash::new(hash) {
        Ok(value) => value,
        Err(_) => return false,
    };

    Argon2::default()
        .verify_password(pin.as_bytes(), &parsed_hash)
        .is_ok()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn pin_correcto() {
        let hash = hash_pin("1234").unwrap();
        assert!(verify_pin("1234", &hash));
    }

    #[test]
    fn pin_incorrecto() {
        let hash = hash_pin("1234").unwrap();
        assert!(!verify_pin("5678", &hash));
    }

    #[test]
    fn pin_con_ceros_iniciales() {
        let hash = hash_pin("0012").unwrap();
        assert!(verify_pin("0012", &hash));
    }

    #[test]
    fn rechazar_pin_invalido() {
        assert!(hash_pin("123").is_err());
        assert!(hash_pin("12345").is_err());
        assert!(hash_pin("12a4").is_err());
        assert!(hash_pin("１２３４").is_err());
    }

    #[test]
    fn hashes_diferentes_para_mismo_pin() {
        let hash1 = hash_pin("1234").unwrap();
        let hash2 = hash_pin("1234").unwrap();

        assert_ne!(hash1, hash2);
        assert!(verify_pin("1234", &hash1));
        assert!(verify_pin("1234", &hash2));
    }

    #[test]
    fn hash_invalido_no_autentica() {
        assert!(!verify_pin("1234", "hash-invalido"));
    }
}
